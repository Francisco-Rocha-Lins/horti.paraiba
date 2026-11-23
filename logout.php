<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    unset($_SESSION['admin_id'], $_SESSION['admin_nome'], $_SESSION['admin_ultimo']);
    session_regenerate_id(true);
    flash('success', 'Você saiu do painel.');
}
redirecionar('login.php');
