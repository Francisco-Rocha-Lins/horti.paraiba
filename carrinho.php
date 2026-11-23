<?php
require_once __DIR__ . '/includes/bootstrap.php';

/* ---------- Ações (sempre por POST + token CSRF) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $acao = $_POST['acao'] ?? '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $quantidade = filter_var($_POST['quantidade'] ?? 1, FILTER_VALIDATE_INT);
    $retorno = retorno_seguro($_POST['retorno'] ?? null, 'carrinho.php');

    $produto = null;
    if ($id) {
        $produto = consultar_um(
            $conexao,
            "SELECT id, nome, estoque FROM produtos WHERE id = ? AND ativo = 1",
            'i',
            [$id]
        );
    }

    switch ($acao) {
        case 'adicionar':
            if (!$produto) {
                flash('danger', 'Produto não encontrado.');
                break;
            }
            $estoque = (int)$produto['estoque'];
            if ($estoque < 1) {
                flash('danger', $produto['nome'] . ' está esgotado.');
                break;
            }
            $quantidade = max(1, (int)$quantidade);
            $novo = (int)($_SESSION['carrinho'][$id] ?? 0) + $quantidade;

            if ($novo > $estoque) {
                $_SESSION['carrinho'][$id] = $estoque;
                flash('warning', 'Só temos ' . $estoque . ' unidade(s) de ' . $produto['nome'] . '. Ajustamos o carrinho.');
            } else {
                $_SESSION['carrinho'][$id] = $novo;
                flash('success', $produto['nome'] . ' foi adicionado ao carrinho.');
            }
            break;

        case 'mais':
        case 'menos':
        case 'atualizar':
            if (!$produto || !isset($_SESSION['carrinho'][$id])) {
                flash('danger', 'Produto não encontrado no carrinho.');
                break;
            }
            $atual = (int)$_SESSION['carrinho'][$id];
            if ($acao === 'mais') {
                $novo = $atual + 1;
            } elseif ($acao === 'menos') {
                $novo = $atual - 1;
            } else {
                $novo = $quantidade === false ? $atual : (int)$quantidade;
            }

            if ($novo < 1) {
                unset($_SESSION['carrinho'][$id]);
            } elseif ($novo > (int)$produto['estoque']) {
                $_SESSION['carrinho'][$id] = (int)$produto['estoque'];
                flash('warning', 'Estoque máximo de ' . $produto['nome'] . ': ' . (int)$produto['estoque'] . ' unidade(s).');
            } else {
                $_SESSION['carrinho'][$id] = $novo;
            }
            break;

        case 'remover':
            if ($id) {
                unset($_SESSION['carrinho'][$id]);
                flash('info', 'Produto removido do carrinho.');
            }
            break;

        case 'limpar':
            $_SESSION['carrinho'] = [];
            flash('info', 'Carrinho esvaziado.');
            break;
    }

    redirecionar($retorno);
}

/* ---------- Exibição ---------- */
[$itens, $total, $avisos] = carrinho_carregar($conexao);
foreach ($avisos as $aviso) {
    flash('warning', $aviso);
}

$tituloPagina = 'Meu carrinho';
require __DIR__ . '/includes/header.php';
?>

<a href="index.php" class="text-decoration-none fw-bold">← Continuar comprando</a>
<h1 class="mt-3 mb-4">🛒 Meu carrinho</h1>

<?php if (empty($itens)): ?>

    <div class="caixa text-center">
        <h3>Seu carrinho está vazio!</h3>
        <p>Que tal escolher alguns produtos?</p>
        <a href="index.php" class="btn btn-horti">Explorar produtos</a>
    </div>

<?php else: ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <?php foreach ($itens as $p): ?>
                <div class="item-carrinho">
                    <div class="d-flex gap-3 align-items-center flex-wrap">

                        <?php if (imagem_existe($p['imagem'])): ?>
                            <img src="<?= e($p['imagem']) ?>" class="item-img" alt="<?= e($p['nome']) ?>">
                        <?php else: ?>
                            <div class="item-img"><?= icone_categoria($p['categoria']) ?></div>
                        <?php endif; ?>

                        <div class="flex-grow-1" style="min-width: 160px;">
                            <h5 class="mb-1"><a href="produto.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none"><?= e($p['nome']) ?></a></h5>
                            <div><?= moeda($p['preco']) ?> cada · <small><?= (int)$p['estoque'] ?> em estoque</small></div>

                            <div class="qtd-grupo mt-2">
                                <form action="carrinho.php" method="POST">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button class="btn-qtd" name="acao" value="menos" type="submit" aria-label="Diminuir">−</button>
                                </form>

                                <form action="carrinho.php" method="POST">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="acao" value="atualizar">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="number" name="quantidade" class="form-control form-control-sm"
                                           value="<?= (int)$p['quantidade'] ?>" min="1" max="<?= (int)$p['estoque'] ?>"
                                           data-auto-submit aria-label="Quantidade">
                                </form>

                                <form action="carrinho.php" method="POST">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button class="btn-qtd" name="acao" value="mais" type="submit" aria-label="Aumentar"
                                        <?= $p['quantidade'] >= $p['estoque'] ? 'disabled' : '' ?>>+</button>
                                </form>
                            </div>
                        </div>

                        <div class="text-end ms-auto">
                            <div class="fw-bold fs-5"><?= moeda($p['subtotal']) ?></div>
                            <form action="carrinho.php" method="POST" class="mt-2">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="remover">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-sm btn-horti-outline" type="submit">Remover</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <form action="carrinho.php" method="POST" data-confirmar="Esvaziar o carrinho?">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="limpar">
                <button class="btn btn-link text-decoration-none p-0" type="submit">Esvaziar carrinho</button>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="resumo">
                <h4>Resumo</h4>
                <div class="d-flex justify-content-between mt-3">
                    <span>Itens</span><strong><?= carrinho_contar() ?></strong>
                </div>
                <hr style="border-color: var(--verde); opacity: 1;">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fs-5 fw-bold">Total</span>
                    <span class="fs-3 fw-bold"><?= moeda($total) ?></span>
                </div>
                <a href="finalizar_compra.php" class="btn btn-horti w-100 mt-3">🚚 Finalizar compra</a>
                <a href="index.php" class="btn btn-horti-outline w-100 mt-2">Continuar comprando</a>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
