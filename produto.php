<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$produto = $id ? consultar_um($conexao, "SELECT * FROM produtos WHERE id = ? AND ativo = 1", 'i', [$id]) : null;

if (!$produto) {
    http_response_code(404);
    $tituloPagina = 'Produto não encontrado';
    require __DIR__ . '/includes/header.php';
    echo '<div class="vazio"><h3>😕 Produto não encontrado</h3><p>Ele pode ter saído da loja.</p>'
       . '<a href="index.php" class="btn btn-horti">Voltar para a loja</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$estoque = (int)$produto['estoque'];
[$estTexto, $estClasse] = estoque_info($estoque);
$noCarrinho = (int)($_SESSION['carrinho'][$produto['id']] ?? 0);

$relacionados = consultar(
    $conexao,
    "SELECT id, nome, preco, imagem, categoria FROM produtos
     WHERE ativo = 1 AND estoque > 0 AND categoria = ? AND id <> ?
     ORDER BY data_cadastro DESC LIMIT 4",
    'si',
    [$produto['categoria'], $produto['id']]
);

$tituloPagina = $produto['nome'];
require __DIR__ . '/includes/header.php';
?>

<a href="index.php" class="text-decoration-none fw-bold">← Voltar para a loja</a>

<div class="row g-4 mt-1">
    <div class="col-md-6">
        <div class="detalhe-foto">
            <?php if (imagem_existe($produto['imagem'])): ?>
                <img src="<?= e($produto['imagem']) ?>" alt="<?= e($produto['nome']) ?>">
            <?php else: ?>
                <div class="detalhe-emoji"><?= icone_categoria($produto['categoria']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-6">
        <span class="selo-categoria"><?= e($produto['categoria']) ?></span>
        <h1 class="mt-3"><?= e($produto['nome']) ?></h1>
        <p><?= nl2br(e($produto['descricao'] ?? '')) ?></p>

        <div class="preco-grande"><?= moeda($produto['preco']) ?></div>
        <p class="estoque <?= e($estClasse) ?> mt-2"><?= e($estTexto) ?></p>

        <?php if ($estoque > 0): ?>
            <form action="carrinho.php" method="POST" class="d-flex flex-wrap gap-2 align-items-center mt-3">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="adicionar">
                <input type="hidden" name="id" value="<?= (int)$produto['id'] ?>">
                <input type="hidden" name="retorno" value="produto.php?id=<?= (int)$produto['id'] ?>">
                <label for="quantidade" class="form-label mb-0">Quantidade</label>
                <input type="number" id="quantidade" name="quantidade" class="form-control" style="width: 90px;"
                       value="1" min="1" max="<?= $estoque ?>" required>
                <button class="btn btn-horti" type="submit">🛒 Adicionar ao carrinho</button>
            </form>
            <?php if ($noCarrinho > 0): ?>
                <p class="mt-3 mb-0">Você já tem <strong><?= $noCarrinho ?></strong> no carrinho.
                    <a href="carrinho.php">Ver carrinho</a></p>
            <?php endif; ?>
        <?php else: ?>
            <button class="btn btn-horti mt-3" disabled>Produto indisponível</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($relacionados)): ?>
    <h2 class="titulo-secao mt-5">Você também pode gostar</h2>
    <div class="row g-3">
        <?php foreach ($relacionados as $r): ?>
            <div class="col-6 col-md-3">
                <a href="produto.php?id=<?= (int)$r['id'] ?>" class="text-decoration-none">
                    <div class="produto-card">
                        <div class="produto-foto" style="height:130px">
                            <?php if (imagem_existe($r['imagem'])): ?>
                                <img src="<?= e($r['imagem']) ?>" alt="<?= e($r['nome']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="emoji"><?= icone_categoria($r['categoria']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="produto-corpo">
                            <strong><?= e($r['nome']) ?></strong>
                            <span class="fw-bold"><?= moeda($r['preco']) ?></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
