<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Erros "de negócio" (estoque, dados inválidos) podem ser mostrados ao cliente.
// Qualquer outro erro vai para o log do PHP e o cliente vê uma mensagem genérica.
class ErroDeCompra extends Exception {}

/* ---------- Página de confirmação ---------- */
$pedido = null;
$itensPedido = [];

if (isset($_GET['pedido'])) {
    $pid = filter_var($_GET['pedido'], FILTER_VALIDATE_INT);

    // Só mostra o pedido para quem acabou de fazê-lo (guardado na sessão)
    if ($pid && $pid === (int)($_SESSION['ultimo_pedido_id'] ?? 0)) {
        $pedido = consultar_um(
            $conexao,
            "SELECT id, nome_cliente, total, data_pedido FROM pedidos WHERE id = ?",
            'i',
            [$pid]
        );
        if ($pedido) {
            $itensPedido = consultar(
                $conexao,
                "SELECT nome_produto, quantidade, subtotal FROM itens_pedido WHERE pedido_id = ?",
                'i',
                [$pid]
            );
        }
    }
}

/* ---------- Processamento da compra ---------- */
$erros = [];
$dados = ['nome' => '', 'telefone' => '', 'cep' => '', 'endereco' => '', 'numero' => '', 'complemento' => ''];

if (!$pedido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    foreach ($dados as $campo => $_) {
        $dados[$campo] = trim((string)($_POST[$campo] ?? ''));
    }

    if ($dados['nome'] === '' || $dados['telefone'] === '' || $dados['cep'] === ''
        || $dados['endereco'] === '' || $dados['numero'] === '') {
        $erros[] = 'Preencha todos os campos obrigatórios.';
    }
    if (mb_strlen($dados['nome']) > 150 || mb_strlen($dados['endereco']) > 255
        || mb_strlen($dados['numero']) > 20 || mb_strlen($dados['complemento']) > 100) {
        $erros[] = 'Algum campo ultrapassa o tamanho permitido.';
    }

    $telefoneNum = preg_replace('/\D/', '', $dados['telefone']);
    if ($dados['telefone'] !== '' && (strlen($telefoneNum) < 10 || strlen($telefoneNum) > 11)) {
        $erros[] = 'Telefone inválido. Informe DDD + número.';
    }

    $cepNum = preg_replace('/\D/', '', $dados['cep']);
    if ($dados['cep'] !== '' && strlen($cepNum) !== 8) {
        $erros[] = 'CEP inválido. Informe 8 números.';
    }

    if (empty($_SESSION['carrinho'])) {
        $erros[] = 'Seu carrinho está vazio.';
    }

    if (empty($erros)) {
        try {
            $conexao->begin_transaction();

            $ids = array_map('intval', array_keys($_SESSION['carrinho']));
            sort($ids); // ordem fixa evita travamentos entre compras simultâneas
            $marcadores = implode(',', array_fill(0, count($ids), '?'));

            // Bloqueia as linhas dos produtos até o fim da transação
            $produtos = [];
            $linhas = consultar(
                $conexao,
                "SELECT id, nome, preco, estoque FROM produtos
                 WHERE ativo = 1 AND id IN ($marcadores) ORDER BY id FOR UPDATE",
                str_repeat('i', count($ids)),
                $ids
            );
            foreach ($linhas as $p) {
                $produtos[(int)$p['id']] = $p;
            }

            $itensCompra = [];
            $total = 0.0;

            foreach ($_SESSION['carrinho'] as $id => $quantidade) {
                $id = (int)$id;
                $quantidade = (int)$quantidade;

                if (!isset($produtos[$id])) {
                    throw new ErroDeCompra('Um produto do carrinho não está mais disponível. Revise o carrinho.');
                }

                $p = $produtos[$id];

                if ($quantidade < 1 || $quantidade > (int)$p['estoque']) {
                    throw new ErroDeCompra('Estoque insuficiente para ' . $p['nome'] . '. Disponível: ' . (int)$p['estoque'] . '.');
                }

                // O preço vem sempre do banco, nunca do navegador
                $subtotal = round((float)$p['preco'] * $quantidade, 2);
                $total += $subtotal;

                $itensCompra[] = [
                    'id' => $id,
                    'nome' => $p['nome'],
                    'preco' => (float)$p['preco'],
                    'quantidade' => $quantidade,
                    'subtotal' => $subtotal,
                ];
            }

            $cepFormatado = substr($cepNum, 0, 5) . '-' . substr($cepNum, 5);

            executar(
                $conexao,
                "INSERT INTO pedidos (nome_cliente, telefone, cep, endereco, numero, complemento, total, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')",
                'ssssssd',
                [$dados['nome'], $dados['telefone'], $cepFormatado, $dados['endereco'],
                 $dados['numero'], $dados['complemento'], $total]
            );
            $pedidoId = (int)$conexao->insert_id;

            foreach ($itensCompra as $item) {
                executar(
                    $conexao,
                    "INSERT INTO itens_pedido (pedido_id, produto_id, nome_produto, quantidade, preco_unitario, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    'iisidd',
                    [$pedidoId, $item['id'], $item['nome'], $item['quantidade'], $item['preco'], $item['subtotal']]
                );

                $linhasAtualizadas = executar(
                    $conexao,
                    "UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND estoque >= ?",
                    'iii',
                    [$item['quantidade'], $item['id'], $item['quantidade']]
                );

                if ($linhasAtualizadas !== 1) {
                    throw new ErroDeCompra('Não foi possível reservar o estoque de ' . $item['nome'] . '. Tente novamente.');
                }
            }

            $conexao->commit();

            $_SESSION['carrinho'] = [];
            $_SESSION['ultimo_pedido_id'] = $pedidoId;
            redirecionar('finalizar_compra.php?pedido=' . $pedidoId);

        } catch (Throwable $ex) {
            $conexao->rollback();

            if ($ex instanceof ErroDeCompra) {
                $erros[] = $ex->getMessage();
            } else {
                error_log('[finalizar_compra] ' . $ex->getMessage());
                $erros[] = 'Não foi possível registrar o pedido. Tente novamente em instantes.';
            }
        }
    }
}

/* ---------- Resumo do carrinho ---------- */
$itens = [];
$total = 0.0;

if (!$pedido) {
    [$itens, $total, $avisos] = carrinho_carregar($conexao);
    foreach ($avisos as $aviso) {
        flash('warning', $aviso);
    }

    if (empty($itens) && empty($erros)) {
        flash('info', 'Seu carrinho está vazio. Adicione produtos para finalizar a compra.');
        redirecionar('carrinho.php');
    }
}

$tituloPagina = $pedido ? 'Pedido confirmado' : 'Finalizar compra';
require __DIR__ . '/includes/header.php';
?>

<?php if ($pedido): ?>

    <div class="caixa text-center mx-auto" style="max-width: 640px;">
        <div style="font-size: 3.5rem;">🌷</div>
        <h1 class="h2">Pedido confirmado!</h1>
        <p class="mt-3 mb-1">Obrigado, <strong><?= e($pedido['nome_cliente']) ?></strong>!</p>
        <p class="fs-5">Número do pedido: <strong>#<?= (int)$pedido['id'] ?></strong></p>

        <div class="text-start mt-4">
            <?php foreach ($itensPedido as $item): ?>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span><?= e($item['nome_produto']) ?> (<?= (int)$item['quantidade'] ?>x)</span>
                    <strong><?= moeda($item['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="mt-4">Total: <?= moeda($pedido['total']) ?></h3>

        <p class="mt-3 small">
            Seu pedido foi registrado no sistema do Horti Paraíba. O pagamento e a entrega
            serão combinados posteriormente com a loja. Nenhum pagamento foi realizado agora.
        </p>

        <a href="index.php" class="btn btn-horti mt-2">Voltar para a loja</a>
    </div>

<?php else: ?>

    <a href="carrinho.php" class="text-decoration-none fw-bold">← Voltar ao carrinho</a>
    <h1 class="mt-3 mb-4">🚚 Finalizar compra</h1>

    <?php if (!empty($erros)): ?>
        <div class="alert alert-danger">
            <?php foreach ($erros as $erro): ?>
                <div><?= e($erro) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="caixa">
                <h3 class="mb-4">Seus dados</h3>

                <form method="POST" action="finalizar_compra.php" novalidate>
                    <?= csrf_campo() ?>

                    <label class="form-label" for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" class="form-control mb-3" maxlength="150"
                           value="<?= e($dados['nome']) ?>" required autocomplete="name">

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label" for="telefone">Telefone (com DDD)</label>
                            <input type="tel" id="telefone" name="telefone" class="form-control mb-3"
                                   data-mascara="telefone" placeholder="(11) 91234-5678"
                                   value="<?= e($dados['telefone']) ?>" required autocomplete="tel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cep">CEP</label>
                            <input type="text" id="cep" name="cep" class="form-control mb-3"
                                   data-mascara="cep" placeholder="00000-000" inputmode="numeric"
                                   value="<?= e($dados['cep']) ?>" required autocomplete="postal-code">
                        </div>
                    </div>

                    <label class="form-label" for="endereco">Endereço</label>
                    <input type="text" id="endereco" name="endereco" class="form-control mb-3" maxlength="255"
                           value="<?= e($dados['endereco']) ?>" required autocomplete="street-address">

                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label" for="numero">Número</label>
                            <input type="text" id="numero" name="numero" class="form-control mb-3" maxlength="20"
                                   value="<?= e($dados['numero']) ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="complemento">Complemento (opcional)</label>
                            <input type="text" id="complemento" name="complemento" class="form-control mb-3" maxlength="100"
                                   value="<?= e($dados['complemento']) ?>">
                        </div>
                    </div>

                    <p class="small">O pagamento e a entrega serão combinados depois com a loja.</p>

                    <button type="submit" class="btn btn-horti w-100 py-3">✔ Confirmar pedido</button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="resumo">
                <h3>Resumo do pedido</h3>

                <?php foreach ($itens as $p): ?>
                    <div class="d-flex justify-content-between mt-3">
                        <span><?= e($p['nome']) ?> (<?= (int)$p['quantidade'] ?>x)</span>
                        <strong><?= moeda($p['subtotal']) ?></strong>
                    </div>
                <?php endforeach; ?>

                <hr style="border-color: var(--verde); opacity: 1;">

                <div class="d-flex justify-content-between align-items-center">
                    <span class="fs-5 fw-bold">Total</span>
                    <span class="fs-3 fw-bold"><?= moeda($total) ?></span>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
