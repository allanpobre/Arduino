document.addEventListener('DOMContentLoaded', function(){
    // ---------- CONFIG (valores iniciais) ----------
    const DEFAULT_ENDPOINT = 'http://192.168.137.78/status'; // IP do ESP
    const DEFAULT_INTERVAL = 4000;
    const DEFAULT_MAX_POINTS = 12;
    const DEFAULT_MAX_FAILS = 3;
    const DEFAULT_TEMP_THRESHOLD = 2.0;
    const DEFAULT_HUM_THRESHOLD = 10.0;
    // NOVO: Endpoint para buscar o último dado do banco
    const DB_LAST_ENDPOINT = 'api/get_ultimo.php'; // Caminho relativo
    const DB_LAST_INTERVAL = 15000; // Buscar a cada 15 segundos

    // Estado
    let running = true;
    let failCount = 0;
    let offline = false;
    let readings = []; // {t: Date, temp: Number, hum: Number}
    let espTimer = null;
    let dbTimer = null; // Timer para buscar do DB

    // Elementos
    const endpointLabel = document.getElementById('endpointLabel');
    const displayTemp = document.getElementById('displayTemp');
    const displayHum = document.getElementById('displayHum');
    const displayTime = document.getElementById('displayTime');
    const displayLastDb = document.getElementById('displayLastDb'); // NOVO
    const connBadge = document.getElementById('connBadge');
    const logEl = document.getElementById('log');

    const btnToggle = document.getElementById('btnToggle');
    const btnToggleText = document.getElementById('btnToggleText');
    const btnRetry = document.getElementById('btnRetry');
    const btnClear = document.getElementById('btnClear');
    const btnCopy = document.getElementById('btnCopy');
    const btnEdit = document.getElementById('btnEdit');
    const btnSettings = document.getElementById('btnSettings');

    const intervalInput = document.getElementById('intervalInput');
    const maxPointsInput = document.getElementById('maxPointsInput');
    const fallbackSelect = document.getElementById('fallbackSelect');
    const failsInput = document.getElementById('failsInput');

    const summaryCount = document.getElementById('summaryCount');
    const summaryLast = document.getElementById('summaryLast');
    const summaryMin = document.getElementById('summaryMin');
    const summaryMax = document.getElementById('summaryMax');
    const summaryAvg = document.getElementById('summaryAvg');

    const pointsCount = document.getElementById('pointsCount');
    const minVal = document.getElementById('minVal');
    const maxVal = document.getElementById('maxVal');
    const avgVal = document.getElementById('avgVal');

    // Modal elements
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    const modalEndpoint = document.getElementById('modalEndpoint');
    const modalInterval = document.getElementById('modalInterval');
    const modalFallback = document.getElementById('modalFallback'); // Relevante para fallback do ESP
    const modalTempThreshold = document.getElementById('modalTempThreshold');
    const modalHumThreshold = document.getElementById('modalHumThreshold');
    const modalSave = document.getElementById('modalSave');

    // load settings from localStorage
    function loadSettings(){
      const s = JSON.parse(localStorage.getItem('esp_settings')||'{}');
      return {
        endpoint: s.endpoint || DEFAULT_ENDPOINT,
        interval: s.interval || DEFAULT_INTERVAL,
        maxPoints: s.maxPoints || DEFAULT_MAX_POINTS,
        fallback: s.fallback || 'off', // Fallback do ESP
        maxFails: s.maxFails || DEFAULT_MAX_FAILS, // Falhas do ESP
        tempThreshold: (s.tempThreshold !== undefined) ? s.tempThreshold : DEFAULT_TEMP_THRESHOLD,
        humThreshold:  (s.humThreshold  !== undefined) ? s.humThreshold  : DEFAULT_HUM_THRESHOLD
      };
    }
    function saveSettings(settings){ localStorage.setItem('esp_settings', JSON.stringify(settings)); }

    let settings = loadSettings();

    // UI populate
    function populateUI() {
        endpointLabel.innerText = settings.endpoint;
        intervalInput.value = settings.interval;
        maxPointsInput.value = settings.maxPoints;
        fallbackSelect.value = settings.fallback;
        failsInput.value = settings.maxFails;
        // Preenche o modal também
        modalEndpoint.value = settings.endpoint;
        modalInterval.value = settings.interval;
        modalTempThreshold.value = settings.tempThreshold;
        modalHumThreshold.value = settings.humThreshold;
    }
    populateUI();

    // ---------- Chart.js setup ----------
    const mainCtx = document.getElementById('mainChart').getContext('2d');
    function createGradient(ctx){ /* ... (sem mudanças) ... */ 
      const g = ctx.createLinearGradient(0,0,0,400);
      g.addColorStop(0, 'rgba(255,99,132,0.18)');
      g.addColorStop(1, 'rgba(255,99,132,0.02)');
      return g;
    }
    const mainConfig = { /* ... (sem mudanças) ... */ 
      type: 'line',
      data: { labels: [], datasets:[
        { label:'Temperatura (°C)', data:[], fill:true, tension:0.3, pointRadius:4, borderWidth:2, yAxisID: 'y_temp', backgroundColor: null, borderColor: 'rgba(255,99,132,1)' },
        { label:'Umidade (%)', data:[], fill:false, tension:0.3, pointRadius:2, borderWidth:2, yAxisID: 'y_hum', borderColor: 'rgba(54,162,235,1)' }
      ] },
      options: {
        responsive:true, maintainAspectRatio:false,
        interaction:{mode:'index', intersect:false},
        plugins:{ legend:{display:true}, tooltip:{callbacks:{label:ctx=> `${ctx.dataset.label}: ${ctx.formattedValue} ${ctx.dataset.label.includes('Umidade')?'%':'°C'}`}}},
        scales:{ x:{ display:true, grid:{display:false}}, y_temp:{ position:'left', beginAtZero:true, suggestedMin:0, suggestedMax:50, title:{display:true, text:'°C'} }, y_hum:{ position:'right', beginAtZero:true, suggestedMin:0, suggestedMax:100, grid:{display:false}, title:{display:true, text:'%'} } }
      }
    };
    const mainChart = new Chart(mainCtx, mainConfig);
    const sparkCtx = document.getElementById('sparkChart').getContext('2d');
    const sparkConfig = { /* ... (sem mudanças) ... */ 
      type:'line', data:{ labels:[], datasets:[{ data:[], fill:false, tension:0.5, pointRadius:0, borderWidth:1 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{display:false}, y:{display:false}} } };
    const sparkChart = new Chart(sparkCtx, sparkConfig);
    function refreshGradients(){ /* ... (sem mudanças) ... */ 
      mainChart.data.datasets[0].backgroundColor = createGradient(mainCtx); 
      mainChart.update('none'); 
    }
    refreshGradients();

    // ---------- helpers ----------
    function log(msg){ const now = new Date().toLocaleTimeString(); logEl.textContent = `[${now}] ${msg}\n` + logEl.textContent; }
    function setBadge(text, variant='secondary'){ connBadge.className = 'badge bg-' + variant; connBadge.innerText = text; }
    function statsFromData(arr, key){ /* ... (sem mudanças) ... */ 
      if(!arr.length) return {min:NaN,max:NaN,avg:NaN,last:NaN};
      const vals = arr.map(r=> r[key]);
      const min = Math.min(...vals); const max = Math.max(...vals);
      const avg = vals.reduce((a,b)=>a+b,0)/vals.length;
      return {min,max,avg,last:vals[vals.length-1]};
    }

    function pushReading(temp, hum){
      const now = new Date();
      readings.push({t:now, temp: Number(temp), hum: Number(hum)});
      if(readings.length > Number(settings.maxPoints)) readings.shift();

      // update charts
      mainChart.data.labels = readings.map(r=> new Date(r.t).toLocaleTimeString());
      mainChart.data.datasets[0].data = readings.map(r=> r.temp);
      mainChart.data.datasets[1].data = readings.map(r=> r.hum);
      mainChart.update('active');

      sparkChart.data.labels = readings.map((r,i)=>i);
      sparkChart.data.datasets[0].data = readings.map(r=> r.temp);
      sparkChart.update('none');

      // update UI summary (temperatura)
      const s = statsFromData(readings, 'temp');
      displayTemp.innerText = isNaN(s.last) ? '— °C' : s.last.toFixed(1) + ' °C';
      displayHum.innerText = isNaN(readings[readings.length-1]?.hum) ? '— %' : readings[readings.length-1].hum.toFixed(1) + ' %';
      displayTime.innerText = now.toLocaleString(); // Hora da leitura do ESP
      
      // Atualiza contadores e estatísticas
      pointsCount.innerText = readings.length;
      minVal.innerText = isNaN(s.min)?'—': s.min.toFixed(1)+' °C';
      maxVal.innerText = isNaN(s.max)?'—': s.max.toFixed(1)+' °C';
      avgVal.innerText = isNaN(s.avg)?'—': s.avg.toFixed(1)+' °C';
      summaryCount.innerText = readings.length;
      summaryLast.innerText = isNaN(s.last)?'—': s.last.toFixed(1)+' °C';
      summaryMin.innerText = isNaN(s.min)?'—': s.min.toFixed(1)+' °C';
      summaryMax.innerText = isNaN(s.max)?'—': s.max.toFixed(1)+' °C';
      summaryAvg.innerText = isNaN(s.avg)?'—': s.avg.toFixed(1)+' °C';

      log(`Leitura ESP -> T: ${Number(temp).toFixed(1)}°C, H: ${Number(hum).toFixed(1)}%`);
    }

    // ---------- network (ESP) ----------
    async function fetchFromESP(){
      const url = settings.endpoint + (settings.endpoint.includes('?') ? '&_ts=' + Date.now() : '?_ts=' + Date.now());
      const controller = new AbortController();
      const timeout = setTimeout(()=> controller.abort(), 7000); // 7s

      try {
        const resp = await fetch(url, { cache: 'no-store', mode: 'cors', signal: controller.signal });
        clearTimeout(timeout);
        if (!resp.ok) { const txt = await resp.text().catch(()=>''); throw new Error('HTTP ' + resp.status + (txt ? ': '+txt : '')); }

        let json;
        try { json = await resp.json(); } catch (e) { throw new Error('Resposta ESP não-JSON'); }

        // Tenta ler 'temperatura' ou outros nomes comuns
        const t = parseFloat(json.temperatura ?? json.temp ?? json.t ?? json.valor);
        const h = parseFloat(json.umidade ?? json.hum ?? json.h ?? json.umidade);

        if (isNaN(t) || isNaN(h)) throw new Error('JSON do ESP sem valores numéricos (t/h)');

        failCount = 0; offline = false;
        setBadge('Online','success');
        pushReading(t, h);
      } catch (err) {
        clearTimeout(timeout);
        log('Erro ESP: ' + (err.message || err));
        failCount++;
        setBadge('Erro ('+failCount+')','warning');

        if (settings.fallback === 'random') {
          const fallbackTemp = (Math.random()*20)+10;
          const fallbackHum = (Math.random()*60)+20;
          log('Inserindo fallback ESP: ' + fallbackTemp.toFixed(1) + '°C, ' + fallbackHum.toFixed(1) + '%');
          pushReading(fallbackTemp, fallbackHum);
        } else {
          if (failCount >= Number(settings.maxFails)) {
            offline = true;
            setBadge('Offline','danger');
            log('ESP offline após ' + failCount + ' falhas.');
          }
        }
      }
    }

    // ---------- NOVO: network (DB) ----------
    async function fetchLastSaved() {
      const url = `${DB_LAST_ENDPOINT}?_ts=${Date.now()}`;
      try {
        const resp = await fetch(url, { cache: 'no-store', mode: 'cors' });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);

        const json = await resp.json();
        if (json.status === 'ok' && json.temperatura !== undefined) {
           const temp = parseFloat(json.temperatura);
           const hum = parseFloat(json.umidade);
           const dt = json.datahora ? new Date(json.datahora.replace(' ', 'T') + 'Z') : null; // Ajusta para ISO e UTC
           
           displayLastDb.innerHTML = `<i class="bi bi-database-check"></i> Último BD: <strong>${temp.toFixed(1)}°C</strong> / <strong>${hum.toFixed(1)}%</strong> <small class="text-muted">(${dt ? dt.toLocaleString() : 'N/A'})</small>`;
           log(`Último BD -> T: ${temp.toFixed(1)}°C, H: ${hum.toFixed(1)}% em ${json.datahora || 'N/A'}`);
        } else if (json.mensagem === 'sem dados') {
             displayLastDb.innerHTML = `<i class="bi bi-database-x"></i> Banco de dados vazio.`;
             log('Banco de dados vazio.');
        } else {
            throw new Error(json.mensagem || 'Resposta inválida');
        }
      } catch (err) {
          log('Erro ao buscar do BD: ' + (err.message || err));
          displayLastDb.innerHTML = `<i class="bi bi-database-exclamation text-danger"></i> Falha ao buscar do BD.`;
      }
    }

    function startLoops(){
      if(espTimer) clearInterval(espTimer);
      if(dbTimer) clearInterval(dbTimer);
      
      // Loop para o ESP
      espTimer = setInterval(()=>{ if(running && !offline) fetchFromESP(); }, Number(settings.interval));
      
      // Loop para o BD (com intervalo fixo ou configurável)
      dbTimer = setInterval(()=>{ if(running) fetchLastSaved(); }, DB_LAST_INTERVAL);
      
      // Busca inicial
      if (running) {
          fetchFromESP();
          fetchLastSaved();
      }
    }

    // ---------- UI actions ----------
    btnToggle.addEventListener('click', ()=>{
      running = !running; btnToggleText.innerText = running ? 'Pausar' : 'Continuar';
      btnToggle.querySelector('i').className = running ? 'bi bi-pause-fill me-1' : 'bi bi-play-fill me-1';
      if(running) {
          setBadge('Tentando','info');
          startLoops(); // Reinicia os loops ao continuar
      } else {
          clearInterval(espTimer); espTimer = null;
          clearInterval(dbTimer); dbTimer = null;
          setBadge('Pausado','secondary');
      }
    });

    btnRetry.addEventListener('click', ()=>{ 
        offline=false; 
        failCount=0; 
        setBadge('Tentando','info'); 
        fetchFromESP(); // Força leitura do ESP
        fetchLastSaved(); // Força leitura do BD
    });
    
    btnClear.addEventListener('click', ()=>{ readings=[]; /* ... (limpa gráficos e UI) ... */ displayTemp.innerText='— °C'; displayHum.innerText='— %'; displayTime.innerText='—'; log('Limpo histórico'); });
    btnCopy.addEventListener('click', async ()=>{ /* ... (sem mudanças) ... */ });
    
    btnEdit.addEventListener('click', ()=>{ 
        modalEndpoint.value = settings.endpoint; 
        modalInterval.value = settings.interval; 
        // modalFallback.value = settings.fallback; // Removido do modal
        modalTempThreshold.value = settings.tempThreshold; 
        modalHumThreshold.value = settings.humThreshold; 
        editModal.show(); 
    });
    btnSettings.addEventListener('click', ()=>{ 
        modalEndpoint.value = settings.endpoint; 
        modalInterval.value = settings.interval; 
        // modalFallback.value = settings.fallback; // Removido do modal
        modalTempThreshold.value = settings.tempThreshold; 
        modalHumThreshold.value = settings.humThreshold; 
        editModal.show(); 
    });

    modalSave.addEventListener('click', async ()=>{
      // Salva configurações do Modal
      settings.endpoint = modalEndpoint.value || settings.endpoint;
      settings.interval = Number(modalInterval.value) || settings.interval;
      // settings.fallback = modalFallback.value || settings.fallback; // Removido do modal
      settings.tempThreshold = parseFloat(modalTempThreshold.value) || settings.tempThreshold;
      settings.humThreshold  = parseFloat(modalHumThreshold.value)  || settings.humThreshold;
      
      // Atualiza também os inputs da aba Controles (se houver)
      settings.fallback = fallbackSelect.value;
      settings.maxPoints = Number(maxPointsInput.value);
      settings.maxFails = Number(failsInput.value);

      saveSettings(settings);
      log('Configurações salvas (local)');
      populateUI(); // Atualiza a UI principal com os novos valores

      // tenta enviar thresholds para o ESP (/config)
      try {
        const urlObj = new URL(settings.endpoint);
        const base = urlObj.origin; 
        const configUrl = `${base}/config?temp=${encodeURIComponent(settings.tempThreshold)}&hum=${encodeURIComponent(settings.humThreshold)}`;

        log('Enviando thresholds para ESP: ' + configUrl);
        const controller = new AbortController();
        const timeout = setTimeout(()=> controller.abort(), 5000);
        const resp = await fetch(configUrl, { method: 'GET', mode: 'cors', signal: controller.signal });
        clearTimeout(timeout);
        if (!resp.ok) { const t = await resp.text().catch(()=>''); throw new Error('HTTP '+resp.status+(t?': '+t:'')); }
        const j = await resp.json().catch(()=>null);
        log('ESP respondeu config: ' + (j ? JSON.stringify(j) : 'ok'));
      } catch (e) {
        log('Não foi possível enviar thresholds para ESP: ' + e);
      }

      startLoops(); // Reinicia os loops com novos intervalos/configs
    });

    // Listeners para os inputs da aba Controles (salvam diretamente)
    intervalInput.addEventListener('change', ()=>{ settings.interval = Number(intervalInput.value) || settings.interval; saveSettings(settings); startLoops(); });
    maxPointsInput.addEventListener('change', ()=>{ settings.maxPoints = Number(maxPointsInput.value) || settings.maxPoints; saveSettings(settings); });
    fallbackSelect.addEventListener('change', ()=>{ settings.fallback = fallbackSelect.value; saveSettings(settings); });
    failsInput.addEventListener('change', ()=>{ settings.maxFails = Number(failsInput.value) || settings.maxFails; saveSettings(settings); });

    // iniciar
    setBadge('Iniciando','secondary');
    log('UI inicializada — endpoint ESP: ' + settings.endpoint);
    startLoops(); // Inicia os loops do ESP e do BD
    window.addEventListener('resize', ()=>{ refreshGradients(); });
  });