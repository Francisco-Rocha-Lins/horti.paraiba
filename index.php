<?php
require_once __DIR__ . '/includes/bootstrap.php';

$categoriaSelecionada = $_GET['categoria'] ?? '';
if (!isset(CATEGORIAS[$categoriaSelecionada])) {
    $categoriaSelecionada = '';
}
$busca = trim((string)($_GET['busca'] ?? ''));

$ordens = [
    'recentes' => 'data_cadastro DESC',
    'menor'    => 'preco ASC',
    'maior'    => 'preco DESC',
    'nome'     => 'nome ASC',
];
$ordem = $_GET['ordem'] ?? 'recentes';
if (!isset($ordens[$ordem])) {
    $ordem = 'recentes';
}

$sql = "SELECT id, nome, categoria, descricao, preco, imagem, estoque FROM produtos WHERE ativo = 1";
$tipos = '';
$params = [];

if ($categoriaSelecionada !== '') {
    $sql .= " AND categoria = ?";
    $tipos .= 's';
    $params[] = $categoriaSelecionada;
}
if ($busca !== '') {
    $sql .= " AND (nome LIKE ? OR descricao LIKE ?)";
    $tipos .= 'ss';
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}
// Produtos esgotados vão para o fim da lista
$sql .= " ORDER BY (estoque <= 0) ASC, " . $ordens[$ordem];

$produtos = consultar($conexao, $sql, $tipos, $params);

$retorno = 'index.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

$tituloPagina = $categoriaSelecionada ?: 'Mercado online';
$buscaAtual = $busca;
$buscaAuto = true;
require __DIR__ . '/includes/header.php';
?>

<?php if ($categoriaSelecionada === '' && $busca === ''): ?>
<section class="hero">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="sobretitulo">FRESQUINHO • NATURAL • PERTO DE VOCÊ</div>
            <h1 class="mt-2">Da nossa loja para sua casa.</h1>
            <p class="mt-3 mb-4">Tudo o que você precisa, com qualidade, praticidade e carinho.</p>
            <a href="#produtos" class="btn btn-horti-rosa">Conheça nossos produtos →</a>
        </div>
        <div class="col-md-4 hero-emoji d-none d-md-block">🍊🥬</div>
    </div>
</section>
<?php endif; ?>

<section class="mt-5" id="categorias">
    <h2 class="titulo-secao">Explore nossas categorias</h2>
    <div class="row g-3">
        <?php $i = 0; foreach (CATEGORIAS as $nome => $cat): $i++; ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a class="categoria-tile <?= $i % 2 === 0 ? 'alt' : '' ?> <?= $nome === $categoriaSelecionada ? 'ativa' : '' ?>"
                   href="index.php?categoria=<?= urlencode($nome) ?>#produtos">
                    <div class="ico"><?= $cat['icone'] ?></div>
                    <strong><?= e($nome) ?></strong>
                    <small><?= e($cat['descricao']) ?></small>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="mt-5" id="produtos">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
        <div>
            <h2 class="titulo-secao mb-1"><?= $categoriaSelecionada ? e($categoriaSelecionada) : 'Nossos produtos' ?></h2>
            <p class="mb-0">
                <?php if ($busca !== ''): ?>
                    Resultados para “<strong><?= e($busca) ?></strong>” · <?= count($produtos) ?> produto(s)
                <?php else: ?>
                    <?= count($produtos) ?> produto(s) · escolha seus favoritos!
                <?php endif; ?>
            </p>
        </div>

        <form method="GET" action="index.php#produtos" class="d-flex gap-2 align-items-center">
            <?php if ($categoriaSelecionada): ?><input type="hidden" name="categoria" value="<?= e($categoriaSelecionada) ?>"><?php endif; ?>
            <?php if ($busca !== ''): ?><input type="hidden" name="busca" value="<?= e($busca) ?>"><?php endif; ?>
            <select name="ordem" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Ordenar">
                <option value="recentes" <?= $ordem === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
                <option value="menor" <?= $ordem === 'menor' ? 'selected' : '' ?>>Menor preço</option>
                <option value="maior" <?= $ordem === 'maior' ? 'selected' : '' ?>>Maior preço</option>
                <option value="nome" <?= $ordem === 'nome' ? 'selected' : '' ?>>Nome (A-Z)</option>
            </select>
            <?php if ($categoriaSelecionada || $busca !== ''): ?>
                <a href="index.php" class="btn btn-horti-outline btn-sm text-nowrap">Ver todos</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="row g-4">
        <?php if (empty($produtos)): ?>
            <div class="col-12">
                <div class="vazio">
                    <h3>🛒 Nenhum produto encontrado</h3>
                    <p>Experimente outra categoria ou outra pesquisa.</p>
                    <a href="index.php" class="btn btn-horti">Voltar para a loja</a>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($produtos as $p):
            $estoque = (int)$p['estoque'];
            [$estTexto, $estClasse] = estoque_info($estoque);
        ?>
            <div class="col-6 col-md-4 col-lg-3">
                <article class="produto-card <?= $estoque <= 0 ? 'esgotado' : '' ?>">
                    <a class="produto-foto" href="produto.php?id=<?= (int)$p['id'] ?>">
                        <?php if (imagem_existe($p['imagem'])): ?>
                            <img src="<?= e($p['imagem']) ?>" alt="<?= e($p['nome']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="emoji"><?= icone_categoria($p['categoria']) ?></div>
                        <?php endif; ?>
                    </a>

                    <div class="produto-corpo">
                        <span class="selo-categoria"><?= e($p['categoria']) ?></span>
                        <h3 class="produto-nome"><a href="produto.php?id=<?= (int)$p['id'] ?>"><?= e($p['nome']) ?></a></h3>
                        <p class="produto-desc"><?= e(mb_strimwidth((string)$p['descricao'], 0, 70, '…', 'UTF-8')) ?></p>
                        <div class="produto-preco"><?= moeda($p['preco']) ?></div>
                        <p class="estoque <?= e($estClasse) ?>"><?= e($estTexto) ?></p>

                        <div class="produto-acoes">
                            <a href="produto.php?id=<?= (int)$p['id'] ?>" class="btn btn-horti-outline btn-sm flex-fill">Ver</a>
                            <?php if ($estoque > 0): ?>
                                <form action="carrinho.php" method="POST" class="flex-fill d-flex">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="acao" value="adicionar">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="quantidade" value="1">
                                    <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
                                    <button class="btn btn-horti btn-sm w-100" type="submit">🛒 Adicionar</button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-horti btn-sm flex-fill" disabled>Esgotado</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
