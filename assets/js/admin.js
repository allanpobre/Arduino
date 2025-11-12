document.addEventListener('DOMContentLoaded', function(){
    const btnTest = document.getElementById('btnTest');
    const testResult = document.getElementById('testResult');
    
    // --- LÓGICA DE MOSTRAR/OCULTAR REMOVIDA ---
    // (Não é mais necessário)

    // --- LÓGICA DE TESTE ATUALIZADA (SÓ TELEGRAM) ---
    btnTest.addEventListener('click', async function(){
      testResult.style.display = 'block';
      testResult.innerHTML = '<div class="alert alert-info">Enviando teste...</div>';

      const formData = new FormData();
      formData.append('action', 'test');
      
      // Não precisa mais enviar 'service'
      
      // Envia apenas os campos do Telegram
      formData.append('telegram_token', document.getElementById('telegram_token').value);
      formData.append('telegram_chat_id', document.getElementById('telegram_chat_id').value);
      formData.append('template', document.getElementById('template').value);

      // valores de teste
      formData.append('test_temp', 28.75);
      formData.append('test_hum', 63.4);

      try {
        // Envia o POST para ele mesmo
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
            testResult.innerHTML = '<div class="alert alert-success"><strong>Enviado com sucesso (Telegram)</strong><br>HTTP: ' + json.http_code + '<br>Resposta: ' + escapeHtml(String(json.body || '')) + '</div>'
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

    // helper para evitar XSS
    function escapeHtml(s){
      return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
  });