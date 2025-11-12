<?php
// notify_admin.php - interface para editar notify_config.json
// (VERSÃO COM TOKEN OCULTO)

$configFile = __DIR__ . '/../includes/notify_config.json';
$errors = [];
$success = false;

// Configuração padrão (sem token)
$cfg = [
    "service" => "telegram",
    // "telegram_token" => "", // (Removido)
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

// Inclui a função de envio (que agora inclui o config.php)
require_once __DIR__ . '/../includes/notify_function.php';

// --- Handler AJAX: teste de envio (Simplificado) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'test')) {
    header('Content-Type: application/json; charset=utf-8');

    $template = $_POST['template'] ?? $cfg['template'];
    $message = str_replace(
        ['{temp}','{hum}','{datahora}','{id}'],
        [25.5, 55.2, date('Y-m-d H:i:s'), 'TEST'],
        $template
    );
    
    // Apenas lógica do Telegram (sem $token)
    $chat_id = trim($_POST['telegram_chat_id'] ?? '');
    if (empty($chat_id)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensagem' => 'Chat ID é obrigatório para testar.']);
        exit;
    }
    // Chama a função que já sabe o token
    $result = send_telegram_bot($chat_id, $message); 

    echo json_encode([ 'ok' => $result['ok'], 'http_code' => $result['http_code'], 'body' => $result['body'], 'error' => $result['error'], 'sent_message' => $message ]);
    exit;
}

// --- Handler: Salvar (Simplificado) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    
    $cfg['service'] = 'telegram'; 
    $cfg['enabled'] = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    
    // Telegram (só o Chat ID)
    $cfg['telegram_chat_id'] = trim($_POST['telegram_chat_id'] ?? '');
    
    // Comum
    $cfg['template'] = trim($_POST['template'] ?? $cfg['template']);
    $nt = trim($_POST['notify_temp_above'] ?? '');
    $nh = trim($_POST['notify_hum_above'] ?? '');
    $cfg['notify_temp_above'] = $nt === '' ? null : floatval($nt);
    $cfg['notify_hum_above']  = $nh === '' ? null : floatval($nh);

    // Validação (só Chat ID)
    if ($cfg['enabled'] && (empty($cfg['telegram_chat_id']))) {
        $errors[] = "Quando ativado, Chat ID é obrigatório.";
    }

    if (empty($errors)) {
        // Remove campos desnecessários do JSON
        unset($cfg['phone']);
        unset($cfg['apikey']);
        unset($cfg['telegram_token']); // Garante que o token nunca seja salvo aqui

        $saved = @file_put_contents($configFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($saved === false) { $errors[] = "Erro ao gravar arquivo de configuração."; } else { $success = true; }
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
    <h3>Configurar Notificações (Telegram)</h3>

    <?php if ($success): ?> <div class="alert alert-success">Configuração salva com sucesso.</div> <?php endif; ?>
    <?php if (!empty($errors)): ?> <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul></div> <?php endif; ?>

    <form id="cfgForm" method="post" class="mb-4">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="enabled">Ativar notificações</label>
      </div>

      <div id="telegram_fields" class="p-3 border rounded mb-3" style="background-color: #f8f9fa;">
        <h5><i class="bi bi-telegram"></i> Configuração do Telegram</h5>
        
        <div class="mb-3">
          <label class="form-label">Chat ID (O "endereço" para onde enviar)</label>
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