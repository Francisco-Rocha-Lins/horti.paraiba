<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (admin_logado()) {
    redirecionar('index.php');
}

$temAdmin = (int)(consultar_um($conexao, "SELECT COUNT(*) AS n FROM administradores")['n'] ?? 0) > 0;
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (time() < (int)($_SESSION['login_bloqueio'] ?? 0)) {
        $erro = 'Muitas tentativas. Aguarde um minuto e tente de novo.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');

        $admin = consultar_um(
            $conexao,
            "SELECT id, nome, senha_hash FROM administradores WHERE email = ?",
            's',
            [$email]
        );

        // Sempre confere uma senha, mesmo sem usuário, para não revelar se o e-mail existe
        $hash = $admin['senha_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuYq3Yb5Zr0wX1Zq1h9n0Z2m6mJf1sH6e';

        if (password_verify($senha, $hash) && $admin) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_nome'] = $admin['nome'];
            $_SESSION['admin_ultimo'] = time();
            unset($_SESSION['login_tentativas'], $_SESSION['login_bloqueio']);

            if (password_needs_rehash($admin['senha_hash'], PASSWORD_DEFAULT)) {
                executar($conexao, "UPDATE administradores SET senha_hash = ? WHERE id = ?", 'si',
                    [password_hash($senha, PASSWORD_DEFAULT), $admin['id']]);
            }
            redirecionar('index.php');
        }

        $_SESSION['login_tentativas'] = (int)($_SESSION['login_tentativas'] ?? 0) + 1;
        if ($_SESSION['login_tentativas'] >= 5) {
            $_SESSION['login_bloqueio'] = time() + 60;
            $_SESSION['login_tentativas'] = 0;
        }
        $erro = 'E-mail ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Entrar | Painel Horti Paraíba</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/horti.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <div class="login-box caixa">
        <h1 class="h3 text-center mb-1">🍃 Horti Paraíba</h1>
        <p class="text-center mb-4">Painel da loja</p>

        <?= flash_exibir() ?>
        <?php if ($erro): ?><div class="alert alert-danger"><?= e($erro) ?></div><?php endif; ?>

        <?php if (!$temAdmin): ?>
            <div class="alert alert-info">
                Nenhum administrador cadastrado ainda.
                <a href="primeiro_acesso.php">Criar o primeiro acesso</a>.
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrf_campo() ?>
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-control mb-3" required autofocus>

            <label class="form-label" for="senha">Senha</label>
            <input type="password" id="senha" name="senha" class="form-control mb-4" required>

            <button class="btn btn-horti w-100" type="submit">Entrar</button>
        </form>

        <a href="../index.php" class="d-block text-center mt-3">← Voltar para a loja</a>
    </div>
</div>
</body>
</html>
