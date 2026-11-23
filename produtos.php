<?php
require_once __DIR__ . '/../includes/bootstrap.php';
exigir_admin();

/* ---------- Ações ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $acao = $_POST['acao'] ?? '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $retorno = retorno_seguro($_POST['retorno'] ?? null, 'produtos.php');

    $produto = $id ? consultar_um($conexao, "SELECT id, nome, imagem FROM produtos WHERE id = ?", 'i', [$id]) : null;

    if (!$produto) {
        flash('danger', 'Produto não encontrado.');
        redirecionar($retorno);
    }

    if ($acao === 'rapido') {
        $preco = parse_preco($_POST['preco'] ?? '');
        $estoque = filter_var($_POST['estoque'] ?? null, FILTER_VALIDATE_INT);

        if ($preco === null || $preco <= 0 || $estoque === false || $estoque < 0) {
            flash('danger', 'Preço ou estoque inválido para ' . $produto['nome'] . '.');
        } else {
            executar($conexao, "UPDATE produtos SET preco = ?, estoque = ? WHERE id = ?", 'dii', [$preco, $estoque, $id]);
            flash('success', $produto['nome'] . ' atualizado.');
        }
    } elseif ($acao === 'excluir') {
        $usado = (int)(consultar_um($conexao, "SELECT COUNT(*) AS n FROM itens_pedido WHERE produto_id = ?", 'i', [$id])['n'] ?? 0);

        if ($usado > 0) {
            // Produto com histórico de pedidos não pode sumir: é apenas desativado
            executar($conexao, "UPDATE produtos SET ativo = 0 WHERE id = ?", 'i', [$id]);
            flash('info', $produto['nome'] . ' já aparece em pedidos, então foi desativado (sai da loja, mas o histórico fica).');
        } else {
            executar($conexao, "DELETE FROM produtos WHERE id = ?", 'i', [$id]);
            remover_imagem($produto['imagem']);
            flash('success', $produto['nome'] . ' foi excluído.');
        }
    } elseif ($acao === 'reativar') {
        executar($conexao, "UPDATE produtos SET ativo = 1 WHERE id = ?", 'i', [$id]);
        flash('success', $produto['nome'] . ' voltou para a loja.');
    }

    redirecionar($retorno);
}

/* ---------- Listagem ---------- */
$q = trim((string)($_GET['q'] ?? ''));
$categoria = $_GET['categoria'] ?? '';
$situacao = $_GET['situacao'] ?? 'ativos';

$sql = "SELECT id, nome, categoria, preco, estoque, imagem, ativo FROM produtos WHERE 1=1";
$tipos = '';
$params = [];

if ($q !== '') {
    $sql .= " AND (nome LIKE ? OR descricao LIKE ?)";
    $tipos .= 'ss';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if (isset(CATEGORIAS[$categoria])) {
    $sql .= " AND categoria = ?";
    $tipos .= 's';
    $params[] = $categoria;
}
switch ($situacao) {
    case 'inativos': $sql .= " AND ativo = 0"; break;
    case 'baixo':    $sql .= " AND ativo = 1 AND estoque BETWEEN 1 AND " . (int)ESTOQUE_BAIXO; break;
    case 'sem':      $sql .= " AND ativo = 1 AND estoque <= 0"; break;
    case 'todos':    break;
    default:         $situacao = 'ativos'; $sql .= " AND ativo = 1";
}
$sql .= " ORDER BY nome";

$produtos = consultar($conexao, $sql, $tipos, $params);
$retorno = 'produtos.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

$tituloAdmin = 'Produtos';
$menuAtivo = 'produtos';
require __DIR__ . '/_topo.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h2 mb-0">Produtos</h1>
    <a href="produto_form.php" class="btn btn-horti">➕ Novo produto</a>
</div>

<form class="row g-2 mb-3" method="GET">
    <div class="col-md-5"><input type="search" name="q" class="form-control" placeholder="Pesquisar por nome ou descrição" value="<?= e($q) ?>"></div>
    <div class="col-6 col-md-3">
        <select name="categoria" class="form-select">
            <option value="">Todas as categorias</option>
            <?php foreach (CATEGORIAS as $nome => $_): ?>
                <option <?= $categoria === $nome ? 'selected' : '' ?>><?= e($nome) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select name="situacao" class="form-select">
            <option value="ativos" <?= $situacao === 'ativos' ? 'selected' : '' ?>>Ativos</option>
            <option value="baixo" <?= $situacao === 'baixo' ? 'selected' : '' ?>>Estoque baixo</option>
            <option value="sem" <?= $situacao === 'sem' ? 'selected' : '' ?>>Sem estoque</option>
            <option value="inativos" <?= $situacao === 'inativos' ? 'selected' : '' ?>>Desativados</option>
            <option value="todos" <?= $situacao === 'todos' ? 'selected' : '' ?>>Todos</option>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-horti w-100" type="submit">Filtrar</button></div>
</form>

<?php if (empty($produtos)): ?>
    <div class="vazio">Nenhum produto encontrado.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table tabela-horti align-middle">
            <thead>
                <tr><th></th><th>Produto</th><th style="width:130px">Preço (R$)</th><th style="width:110px">Estoque</th><th class="text-end">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ($produtos as $p): $f = 'f' . (int)$p['id']; ?>
                <tr class="<?= $p['ativo'] ? '' : 'opacity-50' ?>">
                    <td>
                        <?php if (imagem_existe($p['imagem'])): ?>
                            <img class="miniatura" src="../<?= e($p['imagem']) ?>" alt="">
                        <?php else: ?>
                            <div class="miniatura"><?= icone_categoria($p['categoria']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= e($p['nome']) ?></strong><br>
                        <small><?= e($p['categoria']) ?></small>
                        <?php if (!$p['ativo']): ?><span class="badge text-bg-secondary">Desativado</span><?php endif; ?>
                        <?php if ($p['ativo'] && (int)$p['estoque'] <= 0): ?><span class="badge text-bg-danger">Sem estoque</span>
                        <?php elseif ($p['ativo'] && (int)$p['estoque'] <= ESTOQUE_BAIXO): ?><span class="badge" style="background:var(--rosa);color:var(--verde)">Estoque baixo</span><?php endif; ?>
                    </td>
                    <td><input form="<?= $f ?>" type="text" inputmode="decimal" name="preco" class="form-control form-control-sm"
                               value="<?= e(number_format((float)$p['preco'], 2, ',', '')) ?>"></td>
                    <td><input form="<?= $f ?>" type="number" name="estoque" min="0" class="form-control form-control-sm" value="<?= (int)$p['estoque'] ?>"></td>
                    <td class="text-end text-nowrap">
                        <form id="<?= $f ?>" method="POST" class="d-inline">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="rapido">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
                            <button class="btn btn-horti btn-sm" type="submit" title="Salvar preço e estoque">Salvar</button>
                        </form>
                        <a class="btn btn-horti-outline btn-sm" href="produto_form.php?id=<?= (int)$p['id'] ?>">Editar</a>

                        <?php if ($p['ativo']): ?>
                            <form method="POST" class="d-inline"
                                  data-confirmar="Excluir <?= e($p['nome']) ?>? Se já houver pedidos com ele, será apenas desativado.">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
                                <button class="btn btn-horti-rosa btn-sm" type="submit">Excluir</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="reativar">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
                                <button class="btn btn-horti-rosa btn-sm" type="submit">Reativar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_rodape.php'; ?>
