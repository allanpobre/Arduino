<?php
// notify_admin.php - interface para editar notify_config.json
// VERSÃO SIMPLIFICADA (APENAS TELEGRAM)

$configFile = __DIR__ . '/../includes/notify_config.json';
$errors = [];
$success = false;

// Configuração padrão (sem campos do WhatsApp)
$cfg = [
    "service" => "telegram", // Fixo
    "telegram_token" => "",
    "telegram_chat_id" => "",
    "enabled" => false,
    "notify_temp_above" => null,
    "notify_hum_above" => null,
    "template" => "ALERTA: Temp {temp} °C, Hum {hum}% em {datahora}"
];

// carregar config existente
if (file_exists($configFile)) {
    $raw = @file_get_contents($configFile);
    $parsed = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
        $cfg = array_merge($cfg, $parsed);
    }
}

// Inclui a função de envio
require_once __DIR__ . '/../includes/notify_function.php';

// --- Handler AJAX: teste de envio (Simplificado) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'test')) {
    header('Content-Type: application/json; charset=utf-8');

    $template = $_POST['template'] ?? $cfg['template'];
    
    $test_temp = 25.5;
    $test_hum  = 55.2;
    $datahora  = date('Y-m-d H:i:s');
    $test_id   = 'TEST-' . time();
    
    $message = str_replace(
        ['{temp}','{hum}','{datahora}','{id}'],
        [number_format($test_temp,2,'.',''), number_format($test_hum,2,'.',''), $datahora, $test_id],
        $template
    );
    
    $result = ['ok' => false];
    
    // Apenas lógica do Telegram
    $token = trim($_POST['telegram_token'] ?? '');
    $chat_id = trim($_POST['telegram_chat_id'] ?? '');
    if (empty($token) || empty($chat_id)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensagem' => 'Telegram Token e Chat ID são obrigatórios para testar.']);
        exit;
    }
    $result = send_telegram_bot($token, $chat_id, $message);

    echo json_encode([
        'ok' => $result['ok'],
        'http_code' => $result['http_code'],
        'body' => $result['body'],
        'error' => $result['error'],
        'sent_message' => $message
    ]);
    exit;
}

// --- Handler: Salvar (Simplificado) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    
    $cfg['service'] = 'telegram'; // Fixo
    $cfg['enabled'] = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    
    // Remove campos do WhatsApp
    $cfg['phone'] = ""; 
    $cfg['apikey'] = "";
    
    // Telegram
    $cfg['telegram_token'] = trim($_POST['telegram_token'] ?? '');
    $cfg['telegram_chat_id'] = trim($_POST['telegram_chat_id'] ?? '');
    
    // Comum
    $cfg['template'] = trim($_POST['template'] ?? $cfg['template']);
    $nt = trim($_POST['notify_temp_above'] ?? '');
    $nh = trim($_POST['notify_hum_above'] ?? '');
    $cfg['notify_temp_above'] = $nt === '' ? null : floatval($nt);
    $cfg['notify_hum_above']  = $nh === '' ? null : floatval($nh);

    // Validação simplificada
    if ($cfg['enabled'] && (empty($cfg['telegram_token']) || empty($cfg['telegram_chat_id']))) {
        $errors[] = "Quando ativado, Bot Token e Chat ID são obrigatórios.";
    }

    if (empty($errors)) {
        // Remove campos antigos antes de salvar
        unset($cfg['phone']);
        unset($cfg['apikey']);

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
    <h3>Configurar Notificações (Telegram)</h3> <?php if ($success): ?>
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

      <div id="telegram_fields" class="p-3 border rounded mb-3" style="background-color: #f8f9fa;">
        <h5><i class="bi bi-telegram"></i> Configuração do Bot Telegram</h5>
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

      <h5>Configurações de Alerta</h5>
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