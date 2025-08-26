<?php
// salvar_tensao.php - versão com validação e debug (apenas para DESENVOLVIMENTO)
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
// habilita CORS (se acessar a partir do navegador / fetch)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Config DB
$host = "localhost";
$db   = "esp_monitor";
$user = "root";
$pass = "";

// conexão com tratamento de exceção
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "erro", "mensagem" => "Falha na conexão DB: " . $e->getMessage()]);
    exit;
}

// obter e validar parametro
if (!isset($_GET['valor'])) {
    http_response_code(400);
    echo json_encode(["status" => "erro", "mensagem" => "Parâmetro 'valor' ausente"]);
    $conn->close();
    exit;
}

$valor_raw = $_GET['valor'];
if (!is_numeric($valor_raw)) {
    http_response_code(400);
    echo json_encode(["status" => "erro", "mensagem" => "Parâmetro 'valor' inválido: $valor_raw"]);
    $conn->close();
    exit;
}

$valor = floatval($valor_raw);
$datahora = date('Y-m-d H:i:s');

// preparar e executar
try {
    $stmt = $conn->prepare("INSERT INTO tensao (valor, datahora) VALUES (?, ?)");
    if ($stmt === false) throw new Exception($conn->error);
    $stmt->bind_param("ds", $valor, $datahora); // d = double, s = string
    $stmt->execute();

    echo json_encode(["status" => "ok", "valor" => $valor, "datahora" => $datahora]);
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "erro", "mensagem" => $e->getMessage()]);
    if (isset($stmt) && $stmt) $stmt->close();
    $conn->close();
}
