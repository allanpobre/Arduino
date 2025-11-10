<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Leitura DHT11 — ESP8266</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-gauge@0.3.0/dist/chartjs-gauge.min.js"></script>
  
  <link href="assets/css/dashboard.css" rel="stylesheet">
<script>
    const DEFAULT_ENDPOINT = '<?php require_once __DIR__ . '/config/config.php'; echo DEFAULT_ENDPOINT; ?>';
</script>
</head>
<body>
  <div class="app-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="d-flex align-items-center gap-3">
        <div class="logo">D</div>
        <div>
          <div style="font-weight:700;">Leitura DHT (ESP8266)</div>
          <div style="font-size:0.85rem;color:#6b7280">Temperatura + Umidade — UI atualizada</div>
        </div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <div class="text-end me-2">
          <div style="font-size:0.8rem;color:#6b7280">Status ESP</div>
          <div id="connBadge" class="badge bg-secondary">Iniciando</div>
        </div>
        <div>
          <button id="btnSettings" class="btn btn-outline-primary btn-sm" title="Configurações"><i class="bi bi-gear"></i></button>
        </div>
      </div>
    </div>

    <div class="card card-modern">
      <div class="card-body">
        <div class="grid">
          <div class="chart-area">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div style="font-size:0.85rem;color:#6b7280">Leitura Atual (Live ESP)</div>
                <div class="d-flex align-items-baseline gap-2">
                  <div id="displayTemp" style="font-weight:800;font-size:1.5rem">— °C</div>
                  <div id="displayHum" style="font-size:1rem;color:#6b7280">— %</div>
                </div>
                <div id="displayLastDb" class="mt-1" style="font-size:0.9rem; color:#198754; font-weight: 500;">
                  <span class="placeholder col-8 placeholder-sm"></span>
                </div>
              </div>
              <div class="text-end">
                <div style="font-size:0.85rem;color:#6b7280">Atualizado (ESP)</div>
                <div id="displayTime" style="color:#6b7280">—</div>
              </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <canvas id="tempGauge"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="humGauge"></canvas>
                </div>
            </div>

            <div class="chart-box mini-card">
              <canvas id="mainChart" aria-label="Gráfico de temperatura e umidade" role="img"></canvas>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3" style="font-size:0.9rem;color:#6b7280">
              <div>Histórico (local): <span id="pointsCount">0</span> pontos</div>
              <div>Mín/Máx/Média (Temp): <strong id="minVal">—</strong> / <strong id="maxVal">—</strong> / <strong id="avgVal">—</strong></div>
            </div>
          </div>

          <div class="right-col">
            <div class="mini-card mb-3">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="d-flex align-items-baseline gap-2">
                    <div style="font-size:0.85rem;color:#6b7280">Endpoint ESP</div>
                    <small class="text-muted ms-2" id="endpointHint">(Deve apontar para /status do ESP)</small>
                  </div>
                  <div style="font-weight:600;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" id="endpointLabel">...</div>
                </div>
                <div class="d-flex flex-column align-items-end">
                  <div class="d-flex gap-1">
                    <button id="btnCopy" class="btn btn-sm btn-outline-secondary" title="Copiar endpoint"><i class="bi bi-clipboard"></i></button>
                    <button id="btnEdit" class="btn btn-sm btn-outline-secondary" title="Editar endpoint"><i class="bi bi-pencil"></i></button>
                  </div>
                </div>
              </div>
            </div>

            <ul class="nav nav-tabs" id="infoTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-controles-btn" data-bs-toggle="tab" data-bs-target="#tab-controles" type="button" role="tab">Controles</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-resumo-btn" data-bs-toggle="tab" data-bs-target="#tab-resumo" type="button" role="tab">Resumo</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-logs-btn" data-bs-toggle="tab" data-bs-target="#tab-logs" type="button" role="tab">Logs</button>
              </li>
            </ul>

            <div class="tab-content mini-card" id="infoTabsContent" style="border-top-left-radius: 0;">
              
              <div class="tab-pane fade show active" id="tab-controles" role="tabpanel">
                <div class="d-grid gap-2">
                   <div class="d-flex gap-2">
                     <button id="btnToggle" class="btn btn-primary btn-sm" style="flex:1"><i class="bi bi-pause-fill me-1"></i><span id="btnToggleText">Pausar</span></button>
                     <button id="btnRetry" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Forçar</button>
                     <button id="btnClear" class="btn btn-outline-secondary btn-sm"><i class="bi bi-trash me-1"></i>Limpar</button>
                   </div>
                   <hr class="my-2">
                   <small class="text-muted text-center">Configurações avançadas no botão <i class="bi bi-gear"></i>.</small>
                 </div>
              </div>

              <div class="tab-pane fade" id="tab-resumo" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="text-muted">Resumo (local)</div>
                  <div class="text-muted">Últimos <span id="summaryCount">0</span></div>
                </div>
                <div class="d-flex flex-column gap-2">
                  <div class="d-flex justify-content-between"><div class="text-muted">Último (Temp)</div><div id="summaryLast">— °C</div></div>
                  <div class="d-flex justify-content-between"><div class="text-muted">Min</div><div id="summaryMin">—</div></div>
                  <div class="d-flex justify-content-between"><div class="text-muted">Max</div><div id="summaryMax">—</div></div>
                  <div class="d-flex justify-content-between"><div class="text-muted">Média</div><div id="summaryAvg">—</div></div>
                </div>
                <hr>
                <div class="text-muted">Sparkline (Temp)</div>
                <div style="height:72px"><canvas id="sparkChart"></canvas></div>
              </div>

              <div class="tab-pane fade" id="tab-logs" role="tabpanel">
                <pre id="log">Iniciando...</pre>
              </div>

            </div>
            
          </div>
        </div>

        <footer style="margin-top:16px;text-align:center;color:#475569;font-size:0.9rem">Feito com ❤️ — clique no <i class="bi bi-gear"></i> para configurar.</footer>
      </div>
    </div>
  </div>

  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Configurações</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">

          <ul class="nav nav-tabs" id="modalTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="modal-tab-conexao-btn" data-bs-toggle="tab" data-bs-target="#modal-tab-conexao" type="button" role="tab">Conexão ESP</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="modal-tab-thresholds-btn" data-bs-toggle="tab" data-bs-target="#modal-tab-thresholds" type="button" role="tab">Thresholds ESP</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="modal-tab-dashboard-btn" data-bs-toggle="tab" data-bs-target="#modal-tab-dashboard" type="button" role="tab">Dashboard</button>
            </li>
          </ul>

          <div class="tab-content pt-3" id="modalTabsContent">

            <div class="tab-pane fade show active" id="modal-tab-conexao" role="tabpanel">
              <div class="mb-3">
                <label class="form-label">Endpoint do ESP (normalmente /status)</label>
                <input id="modalEndpoint" class="form-control" placeholder="http://192.168.x.x/status" />
              </div>
              <div class="mb-3">
                <label class="form-label">Intervalo de Leitura do ESP (ms)</label>
                <input id="modalInterval" type="number" class="form-control" min="500" step="100"/>
              </div>
            </div>

            <div class="tab-pane fade" id="modal-tab-thresholds" role="tabpanel">
              <p class="form-text">Estes valores são enviados ao ESP para definir quando ele deve salvar dados no banco.</p>
              <div class="mb-3">
                <label class="form-label">Threshold temperatura (°C)</label>
                <input id="modalTempThreshold" type="number" step="0.1" class="form-control" />
              </div>
              <div class="mb-3">
                <label class="form-label">Threshold umidade (%)</label>
                <input id="modalHumThreshold" type="number" step="0.1" class="form-control" />
              </div>
            </div>

             <div class="tab-pane fade" id="modal-tab-dashboard" role="tabpanel">
               <p class="form-text">Configurações de exibição e comportamento do dashboard.</p>
               <div class="mb-3">
                  <label class="form-label">Máx pontos no gráfico</label>
                  <input id="modalMaxPoints" type="number" class="form-control" min="4" max="240" />
                </div>

                <div class="mb-3">
                  <label class="form-label">Usar fallback em erro ESP</label>
                  <select id="modalFallback" class="form-select">
                    <option value="off">Desligado</option>
                    <option value="random">Inserir valor random</option>
                  </select>
                  <div class="form-text">Se o ESP falhar, o gráfico pode mostrar um valor aleatório ou parar.</div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Tentativas ESP até offline</label>
                  <input id="modalMaxFails" type="number" class="form-control" min="1" max="10" />
                  <div class="form-text">Após quantas falhas seguidas do ESP o status muda para "Offline".</div>
                </div>
            </div>

          </div> </div> <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" id="modalSave" class="btn btn-primary" data-bs-dismiss="modal">Salvar e Aplicar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/dashboard.js"></script>
</body>
</html>