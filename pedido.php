<?php
require_once __DIR__ . '/../includes/bootstrap.php';
exigir_admin();

$id = filter_var($_REQUEST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    redirecionar('pedidos.php');
}

/* ---------- Mudança de status ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $novo = $_POST['status'] ?? '';

    try {
        $conexao->begin_transaction();

        // Trava o pedido enquanto o status é alterado (evita dois cliques / duas abas)
        $atual = consultar_um(
            $conexao,
            "SELECT status, estoque_devolvido FROM pedidos WHERE id = ? FOR UPDATE",
            'i',
            [$id]
        );

        if (!$atual) {
            throw new RuntimeException('Pedido não encontrado.');
        }
        if (!isset(STATUS_PEDIDO[$novo])) {
            throw new RuntimeException('Status inválido.');
        }
        if (!in_array($novo, TRANSICOES_STATUS[$atual['status']] ?? [], true)) {
            throw new RuntimeException('Esta mudança de status não é permitida.');
        }

        if ($novo === 'cancelado' && (int)$atual['estoque_devolvido'] === 0) {
            // Devolve o estoque UMA única vez (a marca estoque_devolvido impede repetição)
            $itens = consultar($conexao, "SELECT produto_id, quantidade FROM itens_pedido WHERE pedido_id = ?", 'i', [$id]);
            foreach ($itens as $item) {
                executar($conexao, "UPDATE produtos SET estoque = estoque + ? WHERE id = ?", 'ii',
                    [(int)$item['quantidade'], (int)$item['produto_id']]);
            }
            executar($conexao, "UPDATE pedidos SET status = ?, estoque_devolvido = 1 WHERE id = ?", 'si', [$novo, $id]);
            $msg = 'Pedido cancelado e estoque devolvido.';
        } else {
            executar($conexao, "UPDATE pedidos SET status = ? WHERE id = ?", 'si', [$novo, $id]);
            $msg = 'Status atualizado para “' . STATUS_PEDIDO[$novo] . '”.';
        }

        $conexao->commit();
        flash('success', $msg);

    } catch (RuntimeException $ex) {
        $conexao->rollback();
        flash('danger', $ex->getMessage());
    } catch (Throwable $ex) {
        $conexao->rollback();
        error_log('[admin/pedido] ' . $ex->getMessage());
        flash('danger', 'Não foi possível atualizar o pedido.');
    }

    redirecionar('pedido.php?id=' . $id);
}

/* ---------- Exibição ---------- */
$pedido = consultar_um($conexao, "SELECT * FROM pedidos WHERE id = ?", 'i', [$id]);
if (!$pedido) {
    flash('danger', 'Pedido não encontrado.');
    redirecionar('pedidos.php');
}
$itens = consultar(
    $conexao,
    "SELECT nome_produto, quantidade, preco_unitario, subtotal FROM itens_pedido WHERE pedido_id = ?",
    'i',
    [$id]
);
$proximos = TRANSICOES_STATUS[$pedido['status']] ?? [];
$telefoneNum = preg_replace('/\D/', '', (string)$pedido['telefone']);

$tituloAdmin = 'Pedido #' . $id;
$menuAtivo = 'pedidos';
require __DIR__ . '/_topo.php';
?>

<a href="pedidos.php" class="text-decoration-none fw-bold">← Voltar aos pedidos</a>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 mb-4">
    <h1 class="h2 mb-0">Pedido #<?= (int)$pedido['id'] ?></h1>
    <?= badge_status($pedido['status']) ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="caixa mb-4">
            <h2 class="h5">Produtos</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Produto</th><th class="text-center">Qtd.</th><th class="text-end">Unitário</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($itens as $it): ?>
                        <tr>
                            <td><?= e($it['nome_produto']) ?></td>
                            <td class="text-center"><?= (int)$it['quantidade'] ?></td>
                            <td class="text-end"><?= moeda($it['preco_unitario']) ?></td>
                            <td class="text-end"><?= moeda($it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3" class="text-end">Total</th><th class="text-end fs-5"><?= moeda($pedido['total']) ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="caixa mb-4">
            <h2 class="h5">Cliente e entrega</h2>
            <p class="mb-1"><strong><?= e($pedido['nome_cliente']) ?></strong></p>
            <p class="mb-1">📞 <?= e($pedido['telefone']) ?>
                <?php if (strlen($telefoneNum) >= 10): ?>
                    · <a href="https://wa.me/55<?= e($telefoneNum) ?>" target="_blank" rel="noopener">WhatsApp</a>
                <?php endif; ?>
            </p>
            <p class="mb-1">📍 <?= e($pedido['endereco']) ?>, <?= e($pedido['numero']) ?>
                <?= !empty($pedido['complemento']) ? ' — ' . e($pedido['complemento']) : '' ?></p>
            <p class="mb-1">CEP <?= e($pedido['cep']) ?></p>
            <p class="mb-0 small">Pedido feito em <?= e(data_br($pedido['data_pedido'])) ?></p>
        </div>

        <div class="caixa-rosa">
            <h2 class="h5">Alterar status</h2>

            <?php if (empty($proximos)): ?>
                <p class="mb-0">Este pedido está <strong><?= e(STATUS_PEDIDO[$pedido['status']] ?? $pedido['status']) ?></strong> e não pode mais ser alterado.
                    <?= $pedido['status'] === 'cancelado' ? 'O estoque já foi devolvido.' : '' ?></p>
            <?php else: ?>
                <form method="POST">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= (int)$pedido['id'] ?>">
                    <select name="status" class="form-select mb-3" id="novoStatus">
                        <?php foreach ($proximos as $s): ?>
                            <option value="<?= e($s) ?>"><?= e(STATUS_PEDIDO[$s]) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-horti w-100" type="submit"
                            onclick="var s=document.getElementById('novoStatus').value; if(s==='cancelado'){return confirm('Cancelar o pedido? O estoque será devolvido e isso não pode ser desfeito.');}">
                        Atualizar status
                    </button>
                </form>
                <p class="small mt-2 mb-0">Ao cancelar, os produtos voltam ao estoque automaticamente (uma única vez).</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_rodape.php'; ?>
