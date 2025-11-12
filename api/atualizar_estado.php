<?php
// atualizar_estado.php
// (Versão que lê o Token do config.php)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/notify_function.php'; // (Agora já inclui o config.php)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// ... (resto dos headers) ...
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
$notifyConfigFile = __DIR__ . '/../includes/notify_config.json';

// ... (Validação de $temp e $hum não muda) ...
$temp_raw = $_GET['temp'] ?? $_POST['temp'] ?? null;
$hum_raw  = $_GET['hum']  ?? $_POST['hum']  ?? null;
if ($temp_raw === null || $hum_raw === null) { http_response_code(400); echo json_encode(["status" => "erro", "mensagem" => "Parâmetros 'temp' e 'hum' obrigatórios"]); exit; }
if (!is_numeric($temp_raw) || !is_numeric($hum_raw)) { http_response_code(400); echo json_encode(["status" => "erro", "mensagem" => "Parâmetros inválidos"]); exit; }
$temp = floatval($temp_raw);
$hum  = floatval($hum_raw);
$datahora = date('Y-m-d H:i:s');
// --- Fim da Validação ---

// ... (Conexão DB não muda) ...
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try { $conn = new mysqli($host, $user, $pass, $db); $conn->set_charset('utf8mb4'); } catch (Exception $e) { error_log("atualizar_estado.php: Erro DB: " . $e->getMessage()); http_response_code(500); echo json_encode(["status" => "erro", "mensagem" => "Falha na conexão DB"]); exit; }

$notified = false;
$notify_response = null;

try {
    // AÇÃO 1: Sobrescrever a tabela de estado atual (não muda)
    $stmt = $conn->prepare("INSERT INTO dht_estado_atual (id, temperatura, umidade, datahora) VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE temperatura = VALUES(temperatura), umidade = VALUES(umidade), datahora = VALUES(datahora)");
    $stmt->bind_param("dds", $temp, $hum, $datahora);
    $stmt->execute();
    $stmt->close();

    // AÇÃO 2: Verificar e enviar notificações (Threshold 2)
    if (file_exists($notifyConfigFile)) {
        $cfgRaw = @file_get_contents($notifyConfigFile);
        $cfg = json_decode($cfgRaw, true);
        
        if (json_last_error() === JSON_ERROR_NONE && !empty($cfg['enabled'])) {
            
            // ... (Lógica de $shouldNotify não muda) ...
            $notify_temp_above = isset($cfg['notify_temp_above']) && is_numeric($cfg['notify_temp_above']) ? floatval($cfg['notify_temp_above']) : null;
            $notify_hum_above  = isset($cfg['notify_hum_above'])  && is_numeric($cfg['notify_hum_above']) ? floatval($cfg['notify_hum_above'])  : null;
            $shouldNotify = false;
            $reason = ""; 
            if ($notify_temp_above !== null && $temp >= $notify_temp_above) { $shouldNotify = true; $reason = "Temperatura ({$temp}°C >= {$notify_temp_above}°C)"; }
            if ($notify_hum_above !== null  && $hum >= $notify_hum_above) { if ($shouldNotify) $reason .= " E Umidade"; else $reason = "Umidade"; $reason .= " ({$hum}% >= {$notify_hum_above}%)"; $shouldNotify = true; }
            if (($notify_temp_above === null && $notify_hum_above === null)) { $shouldNotify = false; error_log("atualizar_estado.php: Notificação não enviada - nenhum threshold definido."); }

            if ($shouldNotify) {
                error_log("atualizar_estado.php: Condição de notificação atingida. Motivo: " . $reason);
                
                $template = $cfg['template'] ?? 'ALERTA: Temp {temp} °C, Hum {hum}% em {datahora}';
                $message = str_replace(['{temp}','{hum}','{datahora}','{id}'], [number_format($temp,2, '.', ''), number_format($hum,2,'.',''), $datahora, "LIVE"], $template);
                
                // --- LÓGICA DE ENVIO ATUALIZADA ---
                // Verifica apenas pelo CHAT ID (o token vem do config.php)
                if (!empty($cfg['telegram_chat_id'])) {
                    // Chama a nova função (só 2 parâmetros)
                    $sendResult = send_telegram_bot($cfg['telegram_chat_id'], $message); 
                    $notified = $sendResult['ok'];
                    $notify_response = $sendResult;
                    error_log("atualizar_estado.php: Tentativa de envio Telegram -> ok=" . ($notified? '1':'0'));
                } else {
                    error_log("atualizar_estado.php: Notificação falhou - Chat ID do Telegram ausente no notify_config.json.");
                }
            }
        }
    } 

    echo json_encode(["status" => "ok", "mensagem" => "Estado atualizado", "notified" => $notified, "notify_response" => $notify_response]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("atualizar_estado.php: Erro geral: " . $e->getMessage());
    echo json_encode(["status" => "erro", "mensagem" => $e->getMessage()]);
}

$conn->close();
?>