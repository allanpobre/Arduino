<?php
// notify_admin.php - interface para editar notify_config.json + botão "Testar envio"
// Atenção: este script NÃO tem autenticação — proteja com .htaccess ou mova para rede interna.

$configFile = __DIR__ . '/notify_config.json';
$errors = [];
$success = false;
$cfg = [
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

// Função de envio (usada tanto em salvar_dht.php quanto aqui)
function send_whatsapp_callmebot(string $phone, string $text, string $apikey): array {
    $url = "https://api.callmebot.com/whatsapp.php?phone=" . urlencode($phone)
         . "&text=" . urlencode($text)
         . "&apikey=" . urlencode($apikey);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8); // timeout curto
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // Desabilita a verificação SSL
    $body = curl_exec($ch);
    $err = null;
    if ($body === false) $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = ($http_code >= 200 && $http_code < 300 && $err === null);
    return ['ok' => $ok, 'http_code' => $http_code, 'body' => $body, 'error' => $err];
}
    

// --- Handler AJAX: teste de envio ---
// Se receber POST com action=test, processa e retorna JSON (usado pelo botão "Testar envio")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'test')) {
    header('Content-Type: application/json; charset=utf-8');

    $phone = trim($_POST['phone'] ?? '');
    $apikey = trim($_POST['apikey'] ?? '');
    $template = $_POST['template'] ?? $cfg['template'];

    // valores de teste (padrões caso não enviados)
    $test_temp = isset($_POST['test_temp']) && is_numeric($_POST['test_temp']) ? floatval($_POST['test_temp']) : 25.5;
    $test_hum  = isset($_POST['test_hum'])  && is_numeric($_POST['test_hum'])  ? floatval($_POST['test_hum'])  : 55.2;
    $datahora  = date('Y-m-d H:i:s');
    $test_id   = 'TEST-' . time();

    if (empty($phone) || empty($apikey)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensagem' => 'Telefone e apikey são obrigatórios para testar.', 'phone' => $phone]);
        exit;
    }

    // montar mensagem substituindo placeholders
    $message = str_replace(
        ['{temp}','{hum}','{datahora}','{id}'],
        [number_format($test_temp,2,'.',''), number_format($test_hum,2,'.',''), $datahora, $test_id],
        $template
    );

    // enviar
    $result = send_whatsapp_callmebot($phone, $message, $apikey);

    // responder JSON com o resultado do cURL
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
    // gravação do form (salvar config)
    $cfg['enabled'] = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    $cfg['phone'] = trim($_POST['phone'] ?? '');
    $cfg['apikey'] = trim($_POST['apikey'] ?? '');
    $cfg['template'] = trim($_POST['template'] ?? $cfg['template']);

    $nt = trim($_POST['notify_temp_above'] ?? '');
    $nh = trim($_POST['notify_hum_above'] ?? '');
    $cfg['notify_temp_above'] = $nt === '' ? null : floatval($nt);
    $cfg['notify_hum_above']  = $nh === '' ? null : floatval($nh);

    if ($cfg['enabled'] && (empty($cfg['phone']) || empty($cfg['apikey']))) {
        $errors[] = "Quando ativado, telefone e apikey são obrigatórios.";
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
  <title>Configurar Notificações WhatsApp - CallMeBot</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container" style="max-width:900px">
    <h3>Configurar Notificações WhatsApp (CallMeBot)</h3>

    <?php if ($success): ?>
      <div class="alert alert-success">Configuração salva com sucesso.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul></div>
    <?php endif; ?>

    <form id="cfgForm" method="post" class="mb-4">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="enabled">Ativar notificações WhatsApp (CallMeBot)</label>
      </div>

      <div class="mb-3">
        <label class="form-label">Telefone (com +PAÍS) — ex: +5511999999999</label>
        <input id="phone" class="form-control" name="phone" value="<?= htmlspecialchars($cfg['phone']) ?>" placeholder="+5511..." />
      </div>

      <div class="mb-3">
        <label class="form-label">API Key (da CallMeBot)</label>
        <input id="apikey" class="form-control" name="apikey" value="<?= htmlspecialchars($cfg['apikey']) ?>" />
      </div>

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
        <a href="notify_config.json" class="btn btn-outline-secondary" target="_blank">Ver arquivo JSON</a>
      </div>
    </form>

    <div id="testResult" style="display:none;" class="mt-3"></div>

    <hr>
    <h5>Como obter o APIKEY do CallMeBot</h5>
    <ol>
      <li>Abra o WhatsApp do telefone que receberá as mensagens.</li>
      <li>Adicione/responda ao número do CallMeBot conforme as instruções do site e obtenha o APIKEY.</li>
      <li>Preencha <b>telefone</b> e <b>apikey</b> acima e ative as notificações.</li>
    </ol>
    <p class="small text-muted">A API gratuita do CallMeBot é destinada a uso pessoal. Para uso comercial/alto volume considere alternativas oficiais.</p>

    <hr>
    <h6>Exemplo de template</h6>
    <pre>ALERTA: Temperatura {temp} °C, Umidade {hum}% — {datahora}</pre>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function(){
    const btnTest = document.getElementById('btnTest');
    const testResult = document.getElementById('testResult');

    btnTest.addEventListener('click', async function(){
      testResult.style.display = 'block';
      testResult.innerHTML = '<div class="alert alert-info">Enviando teste...</div>';

      const formData = new FormData();
      formData.append('action', 'test');
      formData.append('phone', document.getElementById('phone').value);
      formData.append('apikey', document.getElementById('apikey').value);
      formData.append('template', document.getElementById('template').value);

      // valores de teste (poderia expor campos gui se quiser)
      formData.append('test_temp', 28.75);
      formData.append('test_hum', 63.4);

      try {
        const resp = await fetch(window.location.href, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });

        const json = await resp.json().catch(()=>null);
        if (!resp.ok) {
          testResult.innerHTML = '<div class="alert alert-danger">Erro HTTP: ' + resp.status + (json && json.mensagem ? (' — ' + json.mensagem) : '') + '</div>';
          return;
        }

        if (json) {
          if (json.ok) {
            testResult.innerHTML = '<div class="alert alert-success"><strong>Enviado com sucesso</strong><br>HTTP: ' + json.http_code + '<br>Resposta: ' + escapeHtml(String(json.body || '')) + '</div>'
              + '<pre class="mt-2">Mensagem enviada:\n' + escapeHtml(String(json.sent_message || '')) + '</pre>';
          } else {
            testResult.innerHTML = '<div class="alert alert-warning"><strong>Falha</strong><br>HTTP: ' + json.http_code + '<br>Erro cURL: ' + escapeHtml(String(json.error || '')) + '<br>Body: ' + escapeHtml(String(json.body || '')) + '</div>';
          }
        } else {
          testResult.innerHTML = '<div class="alert alert-warning">Resposta inesperada (não JSON)</div>';
        }
      } catch (e) {
        testResult.innerHTML = '<div class="alert alert-danger">Erro de rede/JS: ' + escapeHtml(String(e)) + '</div>';
      }
    });

    // helper para evitar XSS em exibicao simples
    function escapeHtml(s){
      return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
  });

  
  </script>
</body>
</html>
