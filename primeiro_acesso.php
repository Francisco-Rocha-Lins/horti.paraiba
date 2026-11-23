<?php
// Cria o PRIMEIRO administrador. Depois que existir um, esta página se bloqueia sozinha.
require_once __DIR__ . '/../includes/bootstrap.php';

$existe = (int)(consultar_um($conexao, "SELECT COUNT(*) AS n FROM administradores")['n'] ?? 0) > 0;
if ($existe) {
    flash('info', 'O primeiro acesso já foi configurado. Entre com seu e-mail e senha.');
    redirecionar('login.php');
}

$erros = [];
$nome = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    $confirma = (string)($_POST['confirma'] ?? '');

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erros[] = 'Informe o nome (até 100 caracteres).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erros[] = 'Informe um e-mail válido.';
    }
    if (strlen($senha) < 8) {
        $erros[] = 'A senha deve ter pelo menos 8 caracteres.';
    }
    if ($senha !== $confirma) {
        $erros[] = 'A confirmação da senha não confere.';
    }

    if (empty($erros)) {
        executar(
            $conexao,
            "INSERT INTO administradores (nome, email, senha_hash) VALUES (?, ?, ?)",
            'sss',
            [$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]
        );
        flash('success', 'Administrador criado! Agora é só entrar.');
        redirecionar('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Primeiro acesso | Painel Horti Paraíba</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/horti.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <div class="login-box caixa" style="max-width: 480px;">
        <h1 class="h3 text-center mb-1">🍃 Primeiro acesso</h1>
        <p class="text-center mb-4">Crie o administrador da loja</p>

        <?php if ($erros): ?>
            <div class="alert alert-danger"><?php foreach ($erros as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrf_campo() ?>
            <label class="form-label" for="nome">Nome</label>
            <input type="text" id="nome" name="nome" class="form-control mb-3" maxlength="100" value="<?= e($nome) ?>" required>

            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-control mb-3" maxlength="150" value="<?= e($email) ?>" required>

            <label class="form-label" for="senha">Senha (mín. 8 caracteres)</label>
            <input type="password" id="senha" name="senha" class="form-control mb-3" minlength="8" required>

            <label class="form-label" for="confirma">Confirmar senha</label>
            <input type="password" id="confirma" name="confirma" class="form-control mb-4" minlength="8" required>

            <button class="btn btn-horti w-100" type="submit">Criar administrador</button>
        </form>
    </div>
</div>
</body>
</html>
