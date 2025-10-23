<?php
// notify_admin.php (Refatorado como API pura)
ini_set('display_errors', 0); // API não deve vazar erros
error_reporting(E_ALL);

// Inclui a função de envio centralizada
require_once __DIR__ . '/notify_function.php';

// Define cabeçalhos para API (JSON) e CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Permite ser chamado de qualquer lugar
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$configFile = __DIR__ . '/notify_config.json';
$action = $_REQUEST['action'] ?? null;

// Configuração Padrão
$cfg_defaults = [
    "enabled" => false,
    "phone" => "",
    "apikey" => "",
    "notify_temp_above" => null,
    "notify_hum_above" => null,
    "template" => "ALERTA: Temp {temp} °C, Hum {hum}% em {datahora}"
];

/**
 * Carrega a configuração, mesclando com os padrões.
 */
function loadConfig($configFile, $cfg_defaults) {
    $cfg = $cfg_defaults;
    if (file_exists($configFile)) {
        $raw = @file_get_contents($configFile);
        $parsed = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            $cfg = array_merge($cfg, $parsed);
        }
    }
    return $cfg;
}

// --- Roteamento da API ---

try {
    // Ação: Carregar Config
    if ($action === 'load' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $cfg = loadConfig($configFile, $cfg_defaults);
        echo json_encode(['status' => 'ok', 'config' => $cfg]);
        exit;
    }

    // Ação: Salvar Config
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cfg = loadConfig($configFile, $cfg_defaults);

        // Obter dados do POST (o JS envia como FormData)
        $cfg['enabled'] = isset($_POST['enabled']) && $_POST['enabled'] === '1';
        $cfg['phone'] = trim($_POST['phone'] ?? '');
        $cfg['apikey'] = trim($_POST['apikey'] ?? '');
        $cfg['template'] = trim($_POST['template'] ?? $cfg_defaults['template']);

        $nt = trim($_POST['notify_temp_above'] ?? '');
        $nh = trim($_POST['notify_hum_above'] ?? '');
        $cfg['notify_temp_above'] = $nt === '' ? null : floatval($nt);
        $cfg['notify_hum_above']  = $nh === '' ? null : floatval($nh);

        if ($cfg['enabled'] && (empty($cfg['phone']) || empty($cfg['apikey']))) {
            throw new Exception("Quando ativado, telefone e apikey são obrigatórios.", 400);
        }

        $saved = @file_put_contents($configFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($saved === false) {
            throw new Exception("Erro ao gravar arquivo de configuração (verifique permissões).", 500);
        }
        echo json_encode(['status' => 'ok', 'mensagem' => 'Configuração de notificação salva.']);
        exit;
    }

    // Ação: Testar Envio
    if ($action === 'test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $phone = trim($_POST['phone'] ?? '');
        $apikey = trim($_POST['apikey'] ?? '');
        $template = $_POST['template'] ?? $cfg_defaults['template'];

        if (empty($phone) || empty($apikey)) {
            throw new Exception("Telefone e apikey são obrigatórios para testar.", 400);
        }

        $test_temp = 25.5;
        $test_hum = 55.2;
        $datahora  = date('Y-m-d H:i:s');
        $test_id   = 'TEST-' . time();

        $message = str_replace(
            ['{temp}','{hum}','{datahora}','{id}'],
            [number_format($test_temp,2,'.',''), number_format($test_hum,2,'.',''), $datahora, $test_id],
            $template
        );

        $result = send_whatsapp_callmebot($phone, $message, $apikey);
        echo json_encode(array_merge($result, ['sent_message' => $message]));
        exit;
    }

    throw new Exception("Ação inválida ou método HTTP incorreto.", 400);

} catch (Exception $e) {
    http_response_code($e->getCode() > 0 ? $e->getCode() : 400);
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
    exit;
}