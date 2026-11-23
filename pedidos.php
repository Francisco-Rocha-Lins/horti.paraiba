<?php
require_once __DIR__ . '/../includes/bootstrap.php';
exigir_admin();

$status = $_GET['status'] ?? '';
if (!isset(STATUS_PEDIDO[$status])) {
    $status = '';
}
$q = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT id, nome_cliente, telefone, total, status, data_pedido FROM pedidos WHERE 1=1";
$tipos = '';
$params = [];

if ($status !== '') {
    $sql .= " AND status = ?";
    $tipos .= 's';
    $params[] = $status;
}
if ($q !== '') {
    $sql .= " AND (nome_cliente LIKE ? OR telefone LIKE ? OR id = ?)";
    $tipos .= 'ssi';
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = (int)ltrim($q, '#');
}
$sql .= " ORDER BY id DESC LIMIT 300";

$pedidos = consultar($conexao, $sql, $tipos, $params);

$contagem = [];
foreach (consultar($conexao, "SELECT status, COUNT(*) AS n FROM pedidos GROUP BY status") as $linha) {
    $contagem[$linha['status']] = (int)$linha['n'];
}

$tituloAdmin = 'Pedidos';
$menuAtivo = 'pedidos';
require __DIR__ . '/_topo.php';
?>

<h1 class="h2 mb-3">Pedidos</h1>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="pedidos.php" class="btn btn-sm <?= $status === '' ? 'btn-horti' : 'btn-horti-outline' ?>">Todos</a>
    <?php foreach (STATUS_PEDIDO as $chave => $rotulo): ?>
        <a href="pedidos.php?status=<?= e($chave) ?>" class="btn btn-sm <?= $status === $chave ? 'btn-horti' : 'btn-horti-outline' ?>">
            <?= e($rotulo) ?> (<?= $contagem[$chave] ?? 0 ?>)
        </a>
    <?php endforeach; ?>
</div>

<form class="row g-2 mb-3" method="GET">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="col-md-6"><input type="search" name="q" class="form-control" placeholder="Número do pedido, nome ou telefone" value="<?= e($q) ?>"></div>
    <div class="col-md-2"><button class="btn btn-horti w-100" type="submit">Buscar</button></div>
</form>

<?php if (empty($pedidos)): ?>
    <div class="vazio">Nenhum pedido encontrado.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table tabela-horti align-middle">
            <thead><tr><th>Nº</th><th>Cliente</th><th>Telefone</th><th>Total</th><th>Status</th><th>Data</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pedidos as $o): ?>
                <tr>
                    <td><strong>#<?= (int)$o['id'] ?></strong></td>
                    <td><?= e($o['nome_cliente']) ?></td>
                    <td><?= e($o['telefone']) ?></td>
                    <td><?= moeda($o['total']) ?></td>
                    <td><?= badge_status($o['status']) ?></td>
                    <td><?= e(data_br($o['data_pedido'])) ?></td>
                    <td><a class="btn btn-horti btn-sm" href="pedido.php?id=<?= (int)$o['id'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_rodape.php'; ?>
