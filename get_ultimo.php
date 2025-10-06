<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$conn = new mysqli("localhost", "root", "", "esp_monitor");
$res = $conn->query("SELECT valor, datahora FROM tensao ORDER BY id DESC LIMIT 1");
$row = $res->fetch_assoc();
echo json_encode(["tensao" => floatval($row['valor']), "datahora" => $row['datahora']]);
?>
