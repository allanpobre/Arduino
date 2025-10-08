<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli("localhost", "root", "", "esp_monitor");
if ($conn->connect_errno) {
    http_response_code(500);
    echo json_encode(["status"=>"erro","mensagem"=>"Falha DB: ".$conn->connect_error]);
    exit;
}
$res = $conn->query("SELECT temperatura, umidade, datahora FROM dht ORDER BY id DESC LIMIT 1");
if (!$res) {
    http_response_code(500);
    echo json_encode(["status"=>"erro","mensagem"=>$conn->error]);
    $conn->close();
    exit;
}
$row = $res->fetch_assoc();
if (!$row) {
    echo json_encode(["status"=>"ok","mensagem"=>"sem dados"]);
} else {
    echo json_encode([
      "temperatura" => floatval($row['temperatura']),
      "umidade" => floatval($row['umidade']),
      "datahora" => $row['datahora']
    ]);
}
$conn->close();
