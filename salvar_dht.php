<?php
// salvar_dht.php - versão com opcional notificação WhatsApp (CallMeBot)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- (CORREÇÃO) ---
// Inclui a função de envio centralizada
require_once __DIR__ . '/notify_function.php';
// --------------------

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

// configuração do arquivo de notificações
$notifyConfigFile = __DIR__ . '/notify_config.json';

// conexão com tratamento
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    error_log("Erro de conexão com o DB: " . $e->getMessage());  // Log de erro de conexão
    http_response_code(500);
    echo json_encode(["status" => "erro", "mensagem" => "Falha na conexão DB: " . $e->getMessage()]);
    exit;
}

// obter parametros (aceita GET ou POST)
$temp_raw = $_GET['temp'] ?? $_POST['temp'] ?? null;
$hum_raw  = $_GET['hum']  ?? $_POST['hum']  ?? null;

if ($temp_raw === null || $hum_raw === null) {
    error_log("Erro: 'temp' ou 'hum' não fornecidos");  // Log de erro de parâmetros
    http_response_code(400);
    echo json_encode(["status" => "erro", "mensagem" => "Parâmetros 'temp' e 'hum' obrigatórios"]);
    $conn->close();
    exit;
}

if (!is_numeric($temp_raw) || !is_numeric($hum_raw)) {
    error_log("Erro: Parâmetros inválidos: temp={$temp_raw}, hum={$hum_raw}");  // Log de erro se parâmetros não forem numéricos
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
    if ($stmt === false) {
        error_log("Erro no prepare da query: " . $conn->error);  // Log se falhar no prepare
        throw new Exception($conn->error);
    }
    $stmt->bind_param("dds", $temp, $hum, $datahora); // d,d,s
    if (!$stmt->execute()) {
        error_log("Erro na execução da query: " . $stmt->error);  // Log se falhar na execução
        throw new Exception($stmt->error);
    }

    $insertedId = $stmt->insert_id;
    $stmt->close();

    // --- NOTIFICAÇÃO WHATSAPP (CALLMEBOT) ---
    $notified = false;
    $notify_response = null;
    if (file_exists($notifyConfigFile)) {
        $cfgRaw = @file_get_contents($notifyConfigFile);
        $cfg = json_decode($cfgRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($cfg['enabled'])) {
            // parâmetros do cfg (esperado: phone, apikey, enabled, optional thresholds)
            $phone = trim($cfg['phone'] ?? '');
            $apikey = trim($cfg['apikey'] ?? '');
            $template = $cfg['template'] ?? 'ALERTA: Temp {temp} °C, Hum {hum}% em {datahora}';
            // thresholds opcionais (se definidos, só enviar quando ultrapassar)
            $notify_temp_above = isset($cfg['notify_temp_above']) ? floatval($cfg['notify_temp_above']) : null;
            $notify_hum_above  = isset($cfg['notify_hum_above'])  ? floatval($cfg['notify_hum_above'])  : null;

            $shouldNotify = true;
            if ($notify_temp_above !== null && $temp < $notify_temp_above) $shouldNotify = false;
            if ($notify_hum_above !== null  && $hum  < $notify_hum_above)  $shouldNotify = false;

            if ($shouldNotify && !empty($phone) && !empty($apikey)) {
                // monta a mensagem substituindo chaves simples
                $message = str_replace(
                    ['{temp}','{hum}','{datahora}','{id}'],
                    [number_format($temp,2, '.', ''), number_format($hum,2,'.',''), $datahora, $insertedId],
                    $template
                );

                // envia via CallMeBot (AGORA A FUNÇÃO EXISTE)
                $sendResult = send_whatsapp_callmebot($phone, $message, $apikey);
                $notified = $sendResult['ok'];
                $notify_response = $sendResult;
                // registrar log server-side
                error_log("salvar_dht.php: notify -> phone={$phone} ok=" . ($notified? '1':'0') . " resp=" . ($sendResult['body'] ?? 'no-body'));
            }
        } else {
            error_log("salvar_dht.php: notify_config.json inválido ou JSON parse error");
        }
    } // else: notificação não configurada — tudo OK

    echo json_encode(["status" => "ok", "temperatura" => $temp, "umidade" => $hum, "datahora" => $datahora, "id" => $insertedId, "notified" => $notified, "notify_response" => $notify_response]);
    $conn->close();
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "erro", "mensagem" => $e->getMessage()]);
    if (isset($stmt) && $stmt) $stmt->close();
    $conn->close();
    exit;
}