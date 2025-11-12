document.addEventListener('DOMContentLoaded', function(){
    const btnTest = document.getElementById('btnTest');
    const testResult = document.getElementById('testResult');

    // --- LÓGICA DE TESTE ATUALIZADA (SÓ CHAT ID) ---
    btnTest.addEventListener('click', async function(){
      testResult.style.display = 'block';
      testResult.innerHTML = '<div class="alert alert-info">Enviando teste...</div>';

      const formData = new FormData();
      formData.append('action', 'test');
      
      // Envia apenas os campos relevantes
      // (O TOKEN É LIDO PELO PHP NO SERVIDOR)
      formData.append('telegram_chat_id', document.getElementById('telegram_chat_id').value);
      formData.append('template', document.getElementById('template').value);

      // valores de teste
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
          let errorMsg = json && json.mensagem ? json.mensagem : '';
          testResult.innerHTML = `<div class="alert alert-danger">Erro HTTP: ${resp.status} — ${escapeHtml(errorMsg)}</div>`;
          return;
        }

        if (json) {
          if (json.ok) {
            testResult.innerHTML = '<div class="alert alert-success"><strong>Enviado com sucesso (Telegram)</strong><br>HTTP: ' + json.http_code + '<br>Resposta: ' + escapeHtml(String(json.body || '')) + '</div>'
              + '<pre class="mt-2">Mensagem enviada:\n' + escapeHtml(String(json.sent_message || '')) + '</pre>';
          } else {
            testResult.innerHTML = '<div class="alert alert-warning"><strong>Falha</strong><br>HTTP: ' + json.http_code + '<br>Erro: ' + escapeHtml(String(json.error || '')) + '<br>Body: ' + escapeHtml(String(json.body || '')) + '</div>';
          }
        } else {
          testResult.innerHTML = '<div class="alert alert-warning">Resposta inesperada (não JSON)</div>';
        }
      } catch (e) {
        testResult.innerHTML = '<div class="alert alert-danger">Erro de rede/JS: ' + escapeHtml(String(e)) + '</div>';
      }
    });

    function escapeHtml(s){
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
  });