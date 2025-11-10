<?php
// get_ultimo.php - versão robusta para depuração e CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/config.php';

$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_errno) {
    http_response_code(500);
    error_log("get_ultimo.php - DB connect error: ".$conn->connect_error);
    echo json_encode(['status'=>'erro','mensagem'=>'Falha conexao DB']);
    exit;
}

if (!$res = $conn->query("SELECT temperatura, umidade, datahora FROM dht ORDER BY id DESC LIMIT 1")) {
    http_response_code(500);
    error_log("get_ultimo.php - Query error: ".$conn->error);
    echo json_encode(['status'=>'erro','mensagem'=>'Erro na query']);
    $conn->close();
    exit;
}

$row = $res->fetch_assoc();
if (!$row) {
    echo json_encode(['status'=>'ok','mensagem'=>'sem dados']);
} else {
    // padroniza nomes
    echo json_encode([
        'status' => 'ok',
        'temperatura' => floatval($row['temperatura']),
        'umidade' => floatval($row['umidade']),
        'datahora' => $row['datahora']
    ]);
}
$conn->close();
?>