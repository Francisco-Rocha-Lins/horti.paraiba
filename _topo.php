<?php
// Topo padrão do painel. A página define $tituloAdmin e $menuAtivo antes de incluir.
$tituloAdmin = $tituloAdmin ?? 'Painel';
$menuAtivo = $menuAtivo ?? '';
$itensMenu = [
    'dashboard' => ['index.php', '📊 Dashboard'],
    'produtos'  => ['produtos.php', '📦 Produtos'],
    'novo'      => ['produto_form.php', '➕ Novo produto'],
    'pedidos'   => ['pedidos.php', '🧾 Pedidos'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($tituloAdmin) ?> | Painel Horti Paraíba</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/horti.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrap">
    <aside class="admin-menu">
        <a class="marca" href="index.php">🍃 Painel Horti</a>

        <?php foreach ($itensMenu as $chave => [$url, $rotulo]): ?>
            <a class="item <?= $menuAtivo === $chave ? 'ativo' : '' ?>" href="<?= e($url) ?>"><?= $rotulo ?></a>
        <?php endforeach; ?>

        <a class="item" href="../index.php" target="_blank" rel="noopener">🛍️ Ver loja</a>

        <form action="logout.php" method="POST" class="mt-3">
            <?= csrf_campo() ?>
            <div class="small px-2 mb-1 opacity-75">Olá, <?= e($_SESSION['admin_nome'] ?? '') ?></div>
            <button class="btn btn-horti-rosa btn-sm w-100" type="submit">Sair</button>
        </form>
    </aside>

    <section class="admin-conteudo">
        <?= flash_exibir() ?>
