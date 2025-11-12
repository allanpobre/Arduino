<?php
// notify_admin.php - interface para editar notify_config.json
// AGORA COM SUPORTE A WHATSAPP (CALLMEBOT) E TELEGRAM (BOT API)

// --- (CAMINHO CORRIGIDO) ---
$configFile = __DIR__ . '/../includes/notify_config.json';
// --------------------

$errors = [];
$success = false;
$cfg = [
    // --- Novos campos ---
    "service" => "whatsapp", // 'whatsapp' ou 'telegram'
    "telegram_token" => "",
    "telegram_chat_id" => "",
    // --- Campos antigos ---
    "enabled" => false,
    "phone" => "",
    "apikey" => "",
    "notify_temp_above" => null,
    "notify_hum_above" => null,
    "template" => "ALERTA: Temp {temp} °C, Hum {hum}% em {datahora}"
];

// carregar config existente (se houver)
if (file_exists($configFile)) {
    $raw = @file_get_contents($configFile);
    $parsed = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
        $cfg = array_merge($cfg, $parsed);
    }
}

// --- (CAMINHO CORRIGIDO) ---
// Inclui a função de envio centralizada
require_once __DIR__ . '/../includes/notify_function.php';
// --------------------


// --- Handler AJAX: teste de envio ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'test')) {
    header('Content-Type: application/json; charset=utf-8');

    $service = $_POST['service'] ?? 'whatsapp';
    $template = $_POST['template'] ?? $cfg['template'];
    
    // Dados de teste
    $test_temp = isset($_POST['test_temp']) && is_numeric($_POST['test_temp']) ? floatval($_POST['test_temp']) : 25.5;
    $test_hum  = isset($_POST['test_hum'])  && is_numeric($_POST['test_hum'])  ? floatval($_POST['test_hum'])  : 55.2;
    $datahora  = date('Y-m-d H:i:s');
    $test_id   = 'TEST-' . time();
    
    // Monta a mensagem
    $message = str_replace(
        ['{temp}','{hum}','{datahora}','{id}'],
        [number_format($test_temp,2,'.',''), number_format($test_hum,2,'.',''), $datahora, $test_id],
        $template
    );
    
    $result = ['ok' => false];

    if ($service === 'whatsapp') {
        $phone = trim($_POST['phone'] ?? '');
        $apikey = trim($_POST['apikey'] ?? '');
        if (empty($phone) || empty($apikey)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensagem' => 'Telefone (WhatsApp) e apikey são obrigatórios para testar.']);
            exit;
        }
        $result = send_whatsapp_callmebot($phone, $message, $apikey);

    } elseif ($service === 'telegram') {
        $token = trim($_POST['telegram_token'] ?? '');
        $chat_id = trim($_POST['telegram_chat_id'] ?? '');
        if (empty($token) || empty($chat_id)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensagem' => 'Telegram Token e Chat ID são obrigatórios para testar.']);
            exit;
        }
        $result = send_telegram_bot($token, $chat_id, $message);
    }

    echo json_encode([
        'ok' => $result['ok'],
        'http_code' => $result['http_code'],
        'body' => $result['body'],
        'error' => $result['error'],
        'sent_message' => $message
    ]);
    exit;
}

// --- Se chegou aqui, é a renderização normal da página (GET) ou gravação via POST (Salvar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    
    $cfg['service'] = $_POST['service'] ?? 'whatsapp';
    $cfg['enabled'] = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    
    // WhatsApp
    $cfg['phone'] = trim($_POST['phone'] ?? '');
    $cfg['apikey'] = trim($_POST['apikey'] ?? '');
    
    // Telegram
    $cfg['telegram_token'] = trim($_POST['telegram_token'] ?? '');
    $cfg['telegram_chat_id'] = trim($_POST['telegram_chat_id'] ?? '');
    
    // Comum
    $cfg['template'] = trim($_POST['template'] ?? $cfg['template']);
    $nt = trim($_POST['notify_temp_above'] ?? '');
    $nh = trim($_POST['notify_hum_above'] ?? '');
    $cfg['notify_temp_above'] = $nt === '' ? null : floatval($nt);
    $cfg['notify_hum_above']  = $nh === '' ? null : floatval($nh);

    if ($cfg['enabled']) {
        if ($cfg['service'] === 'whatsapp' && (empty($cfg['phone']) || empty($cfg['apikey']))) {
            $errors[] = "Quando WhatsApp está ativado, telefone e apikey são obrigatórios.";
        }
        if ($cfg['service'] === 'telegram' && (empty($cfg['telegram_token']) || empty($cfg['telegram_chat_id']))) {
            $errors[] = "Quando Telegram está ativado, Bot Token e Chat ID são obrigatórios.";
        }
    }

    if (empty($errors)) {
        $saved = @file_put_contents($configFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($saved === false) {
            $errors[] = "Erro ao gravar arquivo de configuração (verifique permissões).";
        } else {
            $success = true;
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Configurar Notificações</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
  <div class="container" style="max-width:900px">
    <h3>Configurar Notificações</h3>

    <?php if ($success): ?>
      <div class="alert alert-success">Configuração salva com sucesso.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul></div>
    <?php endif; ?>

    <form id="cfgForm" method="post" class="mb-4">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="enabled">Ativar notificações</label>
      </div>

      <div class="mb-3">
        <label class="form-label">Serviço de Notificação</label>
        <div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="service" id="service_whatsapp" value="whatsapp" <?= $cfg['service'] === 'whatsapp' ? 'checked' : '' ?>>
              <label class="form-check-label" for="service_whatsapp">WhatsApp (CallMeBot)</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="service" id="service_telegram" value="telegram" <?= $cfg['service'] === 'telegram' ? 'checked' : '' ?>>
              <label class="form-check-label" for="service_telegram">Telegram (Bot API)</label>
            </div>
        </div>
      </div>

      <div id="whatsapp_fields" class="p-3 border rounded mb-3" style="background-color: #f8f9fa;">
        <h5><i class="bi bi-whatsapp"></i> Configuração WhatsApp (CallMeBot)</h5>
        <div class="mb-3">
          <label class="form-label">Telefone (com +PAÍS) — ex: +5511999999999</label>
          <input id="phone" class="form-control" name="phone" value="<?= htmlspecialchars($cfg['phone']) ?>" placeholder="+5511..." />
        </div>
        <div class="mb-3">
          <label class="form-label">API Key (da CallMeBot)</label>
          <input id="apikey" class="form-control" name="apikey" value="<?= htmlspecialchars($cfg['apikey']) ?>" />
        </div>
      </div>

      <div id="telegram_fields" class="p-3 border rounded mb-3" style="background-color: #f8f9fa;">
        <h5><i class="bi bi-telegram"></i> Configuração Telegram (Bot API)</h5>
        <div class="mb-3">
          <label class="form-label">Bot Token (fornecido pelo @BotFather)</label>
          <input id="telegram_token" class="form-control" name="telegram_token" value="<?= htmlspecialchars($cfg['telegram_token']) ?>" placeholder="123456:ABC-DEF123..." />
        </div>
        <div class="mb-3">
          <label class="form-label">Chat ID (seu ID de usuário, ex: 7664820098)</label>
          <input id="telegram_chat_id" class="form-control" name="telegram_chat_id" value="<?= htmlspecialchars($cfg['telegram_chat_id']) ?>" placeholder="123456789" />
        </div>
      </div>

      <hr>

      <h5>Configurações Comuns</h5>
      <div class="mb-3">
        <label class="form-label">Template da mensagem</label>
        <textarea id="template" class="form-control" name="template" rows="3"><?= htmlspecialchars($cfg['template']) ?></textarea>
        <div class="form-text">Placeholders: <code>{temp}</code>, <code>{hum}</code>, <code>{datahora}</code>, <code>{id}</code></div>
      </div>

      <div class="mb-3 row">
        <div class="col">
          <label class="form-label">Notificar quando temperatura ≥ (opcional)</label>
          <input id="notify_temp_above" class="form-control" name="notify_temp_above" value="<?= $cfg['notify_temp_above'] === null ? '' : htmlspecialchars($cfg['notify_temp_above']) ?>" />
        </div>
        <div class="col">
          <label class="form-label">Notificar quando umidade ≥ (opcional)</label>
          <input id="notify_hum_above" class="form-control" name="notify_hum_above" value="<?= $cfg['notify_hum_above'] === null ? '' : htmlspecialchars($cfg['notify_hum_above']) ?>" />
        </div>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Salvar</button>
        <button id="btnTest" class="btn btn-outline-success" type="button">Testar envio</button>
        <a href="../includes/notify_config.json" class="btn btn-outline-secondary" target="_blank">Ver JSON</a>
      </div>
    </form>

    <div id="testResult" style="display:none;" class="mt-3"></div>

  </div>

  <script src="../assets/js/admin.js"></script>
</body>
</html>