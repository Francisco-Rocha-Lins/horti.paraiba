<?php
require_once __DIR__ . '/../includes/bootstrap.php';
exigir_admin();

$n = static function (string $sql, string $tipos = '', array $p = []) use ($conexao) {
    $linha = consultar_um($conexao, $sql, $tipos, $p);
    return $linha ? array_values($linha)[0] : 0;
};

$totalProdutos = (int)$n("SELECT COUNT(*) FROM produtos WHERE ativo = 1");
$totalPedidos = (int)$n("SELECT COUNT(*) FROM pedidos");
$pendentes = (int)$n("SELECT COUNT(*) FROM pedidos WHERE status = 'pendente'");
$valorTotal = (float)$n("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE status <> 'cancelado'");
$semEstoque = consultar($conexao, "SELECT id, nome, categoria FROM produtos WHERE ativo = 1 AND estoque <= 0 ORDER BY nome");
$estoqueBaixo = consultar(
    $conexao,
    "SELECT id, nome, estoque FROM produtos WHERE ativo = 1 AND estoque BETWEEN 1 AND ? ORDER BY estoque, nome",
    'i',
    [ESTOQUE_BAIXO]
);
$ultimos = consultar($conexao, "SELECT id, nome_cliente, total, status, data_pedido FROM pedidos ORDER BY id DESC LIMIT 6");

$tituloAdmin = 'Dashboard';
$menuAtivo = 'dashboard';
require __DIR__ . '/_topo.php';
?>

<h1 class="h2 mb-4">Dashboard</h1>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="stat"><div>Produtos ativos</div><div class="num"><?= $totalProdutos ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><div>Pedidos</div><div class="num"><?= $totalPedidos ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat <?= $pendentes > 0 ? 'alerta' : '' ?>"><div>Pedidos pendentes</div><div class="num"><?= $pendentes ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat destaque"><div>Valor dos pedidos</div><div class="num fs-3"><?= moeda($valorTotal) ?></div><small>sem os cancelados</small></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="caixa h-100">
            <h2 class="h5">⚠️ Estoque baixo (até <?= ESTOQUE_BAIXO ?> un.)</h2>
            <?php if (empty($estoqueBaixo)): ?>
                <p class="mb-0">Tudo certo por aqui. ✔</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($estoqueBaixo as $p): ?>
                        <li class="list-group-item d-flex justify-content-between bg-transparent">
                            <a href="produto_form.php?id=<?= (int)$p['id'] ?>"><?= e($p['nome']) ?></a>
                            <strong><?= (int)$p['estoque'] ?> un.</strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="caixa h-100">
            <h2 class="h5">🚫 Sem estoque</h2>
            <?php if (empty($semEstoque)): ?>
                <p class="mb-0">Nenhum produto esgotado. ✔</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($semEstoque as $p): ?>
                        <li class="list-group-item d-flex justify-content-between bg-transparent">
                            <a href="produto_form.php?id=<?= (int)$p['id'] ?>"><?= e($p['nome']) ?></a>
                            <span><?= e($p['categoria']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12">
        <div class="caixa">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">🧾 Últimos pedidos</h2>
                <a href="pedidos.php" class="btn btn-horti-outline btn-sm">Ver todos</a>
            </div>
            <?php if (empty($ultimos)): ?>
                <p class="mb-0">Nenhum pedido ainda.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Nº</th><th>Cliente</th><th>Total</th><th>Status</th><th>Data</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($ultimos as $o): ?>
                            <tr>
                                <td>#<?= (int)$o['id'] ?></td>
                                <td><?= e($o['nome_cliente']) ?></td>
                                <td><?= moeda($o['total']) ?></td>
                                <td><?= badge_status($o['status']) ?></td>
                                <td><?= e(data_br($o['data_pedido'])) ?></td>
                                <td><a class="btn btn-horti-outline btn-sm" href="pedido.php?id=<?= (int)$o['id'] ?>">Abrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_rodape.php'; ?>
