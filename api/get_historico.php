<?php
// get_historico.php
// Retorna todos os dados das últimas X horas (padrão 24h)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'esp_monitor';

// Pegar o período (em horas) da URL, com um padrão de 24h
$horas = isset($_GET['horas']) && is_numeric($_GET['horas']) ? (int)$_GET['horas'] : 24;

// Garantir que as horas sejam um valor razoável (entre 1 e 720 [30 dias])
if ($horas < 1) $horas = 1;
if ($horas > 720) $horas = 720;


$conn = @new mysqli($host, $user, $pass, $db);
if ($conn->connect_errno) {
    http_response_code(500);
    error_log("get_historico.php - DB connect error: ".$conn->connect_error);
    echo json_encode(['status'=>'erro','mensagem'=>'Falha conexao DB']);
    exit;
}

// Query para buscar dados das últimas X horas, ordenados por data
$sql = "SELECT temperatura, umidade, datahora 
        FROM dht 
        WHERE datahora >= NOW() - INTERVAL ? HOUR 
        ORDER BY id ASC";

try {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    
    $stmt->bind_param("i", $horas); // "i" para integer
    
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();
    $data = [];
    
    while ($row = $res->fetch_assoc()) {
        // Formata os dados para o Chart.js (t, temp, hum)
        $data[] = [
            't' => $row['datahora'],
            'temp' => floatval($row['temperatura']),
            'hum' => floatval($row['umidade'])
        ];
    }
    
    $stmt->close();
    $conn->close();

    echo json_encode([
        'status' => 'ok',
        'count' => count($data),
        'dados' => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("get_historico.php - Query error: ".$e->getMessage());
    echo json_encode(['status'=>'erro','mensagem'=>'Erro na query: ' . $e->getMessage()]);
    if (isset($stmt) && $stmt) $stmt->close();
    $conn->close();
    exit;
}
?>