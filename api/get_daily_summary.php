<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT DATE(datahora) as dia, temperatura, datahora FROM dht ORDER BY datahora ASC";

$result = $conn->query($sql);

$daily_data = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $daily_data[$row['dia']][] = $row;
    }
}

$events = array();
foreach ($daily_data as $day => $data) {
    $max_temp = -999;
    $min_temp = 999;
    $max_temp_time = '';
    $min_temp_time = '';

    foreach ($data as $row) {
        if ($row['temperatura'] > $max_temp) {
            $max_temp = $row['temperatura'];
            $max_temp_time = $row['datahora'];
        }
        if ($row['temperatura'] < $min_temp) {
            $min_temp = $row['temperatura'];
            $min_temp_time = $row['datahora'];
        }
    }

            $events[] = array(
                'title' => 'Max: ' . round($max_temp, 1) . '°C ( ' . date('H:i', strtotime($max_temp_time)) . ' )',
                'start' => $day,
                'backgroundColor' => '#dc3545',
                'textColor' => '#ffffff'
            );
            $events[] = array(
                'title' => 'Min: ' . round($min_temp, 1) . '°C ( ' . date('H:i', strtotime($min_temp_time)) . ' )',
                'start' => $day,
                'backgroundColor' => '#0d6efd',
                'textColor' => '#ffffff'
            );}

$conn->close();

echo json_encode($events);
?>