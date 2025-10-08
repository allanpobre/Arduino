<?php
// salvar_dht.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$host = "localhost";
$db   = "esp_monitor";
$user = "root";
$pass = "";

// conexão com tratamento
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "erro", "mensagem" => "Falha na conexão DB: " . $e->getMessage()]);
    exit;
}

// obter parametros (aceita GET ou POST)
$temp_raw = $_GET['temp'] ?? $_POST['temp'] ?? null;
$hum_raw  = $_GET['hum']  ?? $_POST['hum']  ?? null;

if ($temp_raw === null || $hum_raw === null) {
    http_response_code(400);
    echo json_encode(["status" => "erro", "mensagem" => "Parâmetros 'temp' e 'hum' obrigatórios"]);
    $conn->close();
    exit;
}

if (!is_numeric($temp_raw) || !is_numeric($hum_raw)) {
    http_response_code(400);
    echo json_encode(["status" => "erro", "mensagem" => "Parâmetros inválidos: temp={$temp_raw}, hum={$hum_raw}"]);
    $conn->close();
    exit;
}

$temp = floatval($temp_raw);
$hum  = floatval($hum_raw);
$datahora = date('Y-m-d H:i:s');

try {
    $stmt = $conn->prepare("INSERT INTO dht (temperatura, umidade, datahora) VALUES (?, ?, ?)");
    if ($stmt === false) throw new Exception($conn->error);
    $stmt->bind_param("dds", $temp, $hum, $datahora); // d,d,s
    $stmt->execute();

    echo json_encode(["status" => "ok", "temperatura" => $temp, "umidade" => $hum, "datahora" => $datahora]);
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "erro", "mensagem" => $e->getMessage()]);
    if (isset($stmt) && $stmt) $stmt->close();
    $conn->close();
}
