<?php
header('Content-Type: application/json');

// Configuração do banco
$host = "localhost";
$db   = "esp_monitor";
$user = "root";
$pass = "";

// Conexão
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status" => "erro", "mensagem" => $conn->connect_error]);
    exit;
}

// Recebe valor do ESP
$valor = isset($_GET['valor']) ? floatval($_GET['valor']) : 0;
$datahora = date('Y-m-d H:i:s');

// Insere no banco
$stmt = $conn->prepare("INSERT INTO tensao (valor, datahora) VALUES (?, ?)");
$stmt->bind_param("ds", $valor, $datahora);
$stmt->execute();

// Retorna JSON de sucesso
echo json_encode(["status" => "ok", "valor" => $valor]);

$stmt->close();
$conn->close();
?>
