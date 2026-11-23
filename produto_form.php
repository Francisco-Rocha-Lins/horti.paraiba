<?php
require_once __DIR__ . '/../includes/bootstrap.php';
exigir_admin();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$produto = null;

if ($id) {
    $produto = consultar_um($conexao, "SELECT * FROM produtos WHERE id = ?", 'i', [$id]);
    if (!$produto) {
        flash('danger', 'Produto não encontrado.');
        redirecionar('produtos.php');
    }
}

$form = [
    'nome'      => $produto['nome'] ?? '',
    'categoria' => $produto['categoria'] ?? '',
    'preco'     => isset($produto['preco']) ? number_format((float)$produto['preco'], 2, ',', '') : '',
    'descricao' => $produto['descricao'] ?? '',
    'estoque'   => $produto['estoque'] ?? '0',
];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    foreach ($form as $campo => $_) {
        $form[$campo] = trim((string)($_POST[$campo] ?? ''));
    }

    $preco = parse_preco($form['preco']);
    $estoque = filter_var($form['estoque'], FILTER_VALIDATE_INT);

    if ($form['nome'] === '' || mb_strlen($form['nome']) > 150) {
        $erros[] = 'Informe o nome do produto (até 150 caracteres).';
    }
    if (!isset(CATEGORIAS[$form['categoria']])) {
        $erros[] = 'Escolha uma categoria válida.';
    }
    if ($preco === null || $preco <= 0 || $preco > 99999999) {
        $erros[] = 'Informe um preço válido, maior que zero.';
    }
    if ($estoque === false || $estoque < 0) {
        $erros[] = 'Informe uma quantidade de estoque válida (0 ou mais).';
    }

    $novaImagem = null;
    if (empty($erros)) {
        $novaImagem = salvar_imagem($_FILES['imagem'] ?? [], $erroImagem);
        if ($erroImagem) {
            $erros[] = $erroImagem;
        }
    }

    if (empty($erros)) {
        try {
            if ($produto) {
                $imagemFinal = $produto['imagem'];
                if ($novaImagem) {
                    $imagemFinal = $novaImagem;
                } elseif (!empty($_POST['remover_imagem'])) {
                    $imagemFinal = null;
                }

                executar(
                    $conexao,
                    "UPDATE produtos SET nome = ?, categoria = ?, preco = ?, descricao = ?, imagem = ?, estoque = ? WHERE id = ?",
                    'ssdssii',
                    [$form['nome'], $form['categoria'], $preco, $form['descricao'], $imagemFinal, $estoque, $id]
                );

                if ($imagemFinal !== $produto['imagem']) {
                    remover_imagem($produto['imagem']);
                }
                flash('success', 'Produto atualizado com sucesso!');
            } else {
                executar(
                    $conexao,
                    "INSERT INTO produtos (nome, categoria, preco, descricao, imagem, estoque) VALUES (?, ?, ?, ?, ?, ?)",
                    'ssdssi',
                    [$form['nome'], $form['categoria'], $preco, $form['descricao'], $novaImagem ?? '', $estoque]
                );
                flash('success', 'Produto cadastrado com sucesso! Ele já aparece na vitrine.');
            }
            redirecionar('produtos.php');

        } catch (Throwable $ex) {
            error_log('[produto_form] ' . $ex->getMessage());
            if ($novaImagem) {
                remover_imagem($novaImagem); // não deixa arquivo órfão
            }
            $erros[] = 'Erro ao salvar o produto. Tente novamente.';
        }
    }
}

$tituloAdmin = $produto ? 'Editar produto' : 'Novo produto';
$menuAtivo = $produto ? 'produtos' : 'novo';
require __DIR__ . '/_topo.php';
?>

<h1 class="h2 mb-4"><?= $produto ? 'Editar produto' : 'Novo produto' ?></h1>

<?php if ($erros): ?>
    <div class="alert alert-danger"><?php foreach ($erros as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="caixa" style="max-width: 720px;">
    <form method="POST" enctype="multipart/form-data">
        <?= csrf_campo() ?>

        <label class="form-label" for="nome">Nome do produto</label>
        <input type="text" id="nome" name="nome" class="form-control mb-3" maxlength="150" value="<?= e($form['nome']) ?>" required>

        <div class="row">
            <div class="col-md-6">
                <label class="form-label" for="categoria">Categoria</label>
                <select id="categoria" name="categoria" class="form-select mb-3" required>
                    <option value="">Escolha uma categoria</option>
                    <?php foreach (CATEGORIAS as $nome => $_): ?>
                        <option <?= $form['categoria'] === $nome ? 'selected' : '' ?>><?= e($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="preco">Preço (R$)</label>
                <input type="text" id="preco" name="preco" inputmode="decimal" class="form-control mb-3" placeholder="0,00" value="<?= e($form['preco']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="estoque">Estoque</label>
                <input type="number" id="estoque" name="estoque" min="0" class="form-control mb-3" value="<?= e($form['estoque']) ?>" required>
            </div>
        </div>

        <label class="form-label" for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" rows="3" class="form-control mb-3"><?= e($form['descricao']) ?></textarea>

        <label class="form-label" for="imagem">Foto do produto</label>
        <?php if ($produto && imagem_existe($produto['imagem'])): ?>
            <div class="d-flex align-items-center gap-3 mb-2">
                <img class="miniatura" style="width:90px;height:90px" src="../<?= e($produto['imagem']) ?>" alt="Foto atual">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remover_imagem" value="1" id="remover">
                    <label class="form-check-label" for="remover">Remover foto atual</label>
                </div>
            </div>
        <?php endif; ?>
        <input type="file" id="imagem" name="imagem" class="form-control" accept="image/jpeg,image/png,image/webp">
        <div class="form-text mb-4">JPG, PNG ou WEBP, até 3 MB. <?= $produto ? 'Envie uma nova só se quiser trocar.' : '' ?></div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-horti"><?= $produto ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
            <a href="produtos.php" class="btn btn-horti-outline">Cancelar</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/_rodape.php'; ?>
