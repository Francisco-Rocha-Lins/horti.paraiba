<?php
// Conexão com o banco (MySQL do XAMPP)
$host = "localhost";
$usuario = "root";
$senha = "";
$banco = "horti_paraiba";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexao = new mysqli($host, $usuario, $senha, $banco);
    $conexao->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log("[Horti Paraíba] Erro de conexão: " . $e->getMessage());
    http_response_code(500);
    die("Não foi possível conectar ao banco de dados. Verifique se o MySQL está ligado no XAMPP.");
}
