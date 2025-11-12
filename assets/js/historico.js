document.addEventListener('DOMContentLoaded', function() {
    
    // ---------- CONFIG ----------
    const API_ENDPOINT = 'api/get_historico.php';

    // ---------- Elementos UI ----------
    const statusInfo = document.getElementById('statusInfo');
    const pointsCount = document.getElementById('pointsCount');
    const minVal = document.getElementById('minVal');
    const maxVal = document.getElementById('maxVal');
    const avgVal = document.getElementById('avgVal');
    const periodoBtns = document.getElementById('periodoBtns');
    const tabelaContainer = document.getElementById('tabela-container'); // Container da nova tabela

    // ---------- Chart.js setup ----------
    const mainCtx = document.getElementById('mainChart').getContext('2d');
    
    function createGradient(ctx){ 
      const g = ctx.createLinearGradient(0, 0, 0, 450); // Altura do gráfico
      g.addColorStop(0, 'rgba(255,99,132,0.18)');
      g.addColorStop(1, 'rgba(255,99,132,0.02)');
      return g;
    }

    const mainConfig = { 
      type: 'line',
      data: { labels: [], datasets:[
        { 
          label:'Temperatura (°C)', 
          data:[], 
          fill:true, 
          tension:0.3, 
          pointRadius:2, // Pontos menores
          borderWidth:2, 
          yAxisID: 'y_temp', 
          backgroundColor: createGradient(mainCtx), 
          borderColor: 'rgba(255,99,132,1)' 
        },
        { 
          label:'Umidade (%)', 
          data:[], 
          fill:false, 
          tension:0.3, 
          pointRadius:2, // Pontos menores
          borderWidth:2, 
          yAxisID: 'y_hum', 
          borderColor: 'rgba(54,162,235,1)' 
        }
      ] },
      options: {
        responsive:true, 
        maintainAspectRatio:false,
        interaction:{mode:'index', intersect:false},
        plugins:{ 
            legend:{display:true}, 
            tooltip:{
                callbacks:{label:ctx=> `${ctx.dataset.label}: ${ctx.formattedValue} ${ctx.dataset.label.includes('Umidade')?'%':'°C'}`}
            }
        },
        scales:{ 
            x:{ 
                display:true, 
                grid:{display:false},
                title: { display: true, text: 'Data e Hora' },
                ticks: {
                    autoSkip: true,
                    maxTicksLimit: 20 // Limita para não poluir o eixo
                }
            },
            // --- EIXOS FIXOS ---
            y_temp:{ 
                position:'left', 
                title:{display:true, text:'°C'},
                min: 0,  // Eixo fixo
                max: 50  // Eixo fixo
            }, 
            y_hum:{ 
                position:'right', 
                grid:{display:false}, 
                title:{display:true, text:'%'},
                min: 0,   // Eixo fixo
                max: 100  // Eixo fixo
            } 
            // --- FIM DAS ALTERAÇÕES ---
        } 
      }
    };
    const mainChart = new Chart(mainCtx, mainConfig);

    // ---------- Funções de Dados ----------

    // Helper para calcular estatísticas
    function statsFromData(arr, key){ 
      if(!arr.length) return {min:NaN,max:NaN,avg:NaN};
      const vals = arr.map(r=> r[key]);
      const min = Math.min(...vals); const max = Math.max(...vals);
      const avg = vals.reduce((a,b)=>a+b,0)/vals.length;
      return {min,max,avg};
    }

    // Função principal para buscar dados e atualizar tudo
    async function fetchHistory(horas = 24) {
        statusInfo.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Carregando ${horas}h...`;
        tabelaContainer.innerHTML = '<p class="text-center text-muted p-5">Carregando dados...</p>'; // Mostra loading na tabela
        
        const url = `${API_ENDPOINT}?horas=${horas}&_ts=${Date.now()}`;

        try {
            const resp = await fetch(url);
            if (!resp.ok) throw new Error('Falha na rede: ' + resp.status);

            const json = await resp.json();
            if (json.status !== 'ok') throw new Error('Erro na API: ' + (json.mensagem || 'Resposta inválida'));

            const dados = json.dados;
            
            // 1. Atualiza os dados do gráfico
            mainChart.data.labels = dados.map(d => {
                // Converte '2025-11-12 14:30:00' para '12/11 14:30'
                const dt = new Date(d.t.replace(' ', 'T') + 'Z'); // Trata como UTC
                return dt.toLocaleString('pt-BR', { 
                    day: 'numeric', 
                    month: 'numeric', 
                    hour: 'numeric', 
                    minute: 'numeric',
                    timeZone: 'America/Sao_Paulo' // Ajuste para seu fuso!
                });
            });
            mainChart.data.datasets[0].data = dados.map(d => d.temp);
            mainChart.data.datasets[1].data = dados.map(d => d.hum);
            mainChart.update();

            // 2. Atualiza estatísticas (Temperatura)
            const s = statsFromData(dados, 'temp');
            pointsCount.innerText = dados.length;
            minVal.innerText = isNaN(s.min)?'—': s.min.toFixed(1)+' °C';
            maxVal.innerText = isNaN(s.max)?'—': s.max.toFixed(1)+' °C';
            avgVal.innerText = isNaN(s.avg)?'—': s.avg.toFixed(1)+' °C';

            statusInfo.innerText = `Exibindo ${dados.length} registros.`;

            // 3. Preenche a tabela de dados
            populateTable(dados);

        } catch (err) {
            console.error(err);
            statusInfo.innerText = 'Erro ao carregar dados.';
            statusInfo.style.color = 'red';
            tabelaContainer.innerHTML = `<p class="text-center text-danger p-5">Erro ao carregar dados: ${err.message}</p>`;
        }
    }

    /**
     * Preenche a aba de Tabela com os dados brutos.
     */
    function populateTable(dados) {
        if (!dados || dados.length === 0) {
            tabelaContainer.innerHTML = '<p class="text-center text-muted p-5">Nenhum dado encontrado para este período.</p>';
            return;
        }

        let tableHtml = '<table class="table table-sm table-striped table-hover">';
        tableHtml += `
            <thead class="table-light" style="position: sticky; top: 0;">
                <tr>
                    <th>Data/Hora</th>
                    <th>Temperatura (°C)</th>
                    <th>Umidade (%)</th>
                </tr>
            </thead>
            <tbody>
        `;

        // Itera ao contrário (mostra os mais recentes primeiro)
        for (let i = dados.length - 1; i >= 0; i--) {
            const d = dados[i];
            const dt = new Date(d.t.replace(' ', 'T') + 'Z'); // Trata como UTC
            // Formato: 12/11/25, 15:01:02
            const dataFormatada = dt.toLocaleString('pt-BR', {
                timeZone: 'America/Sao_Paulo', // Ajuste para seu fuso!
                year: '2-digit', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });

            tableHtml += `
                <tr>
                    <td>${dataFormatada}</td>
                    <td>${d.temp.toFixed(1)}</td>
                    <td>${d.hum.toFixed(1)}</td>
                </tr>
            `;
        }

        tableHtml += '</tbody></table>';
        tabelaContainer.innerHTML = tableHtml;
    }


    // ---------- Event Listeners ----------

    // Adiciona listener para os botões de período
    periodoBtns.addEventListener('click', function(e) {
        if (e.target.tagName === 'BUTTON') {
            // Remove 'active' de todos
            periodoBtns.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
            // Adiciona 'active' ao clicado
            e.target.classList.add('active');
            
            const horas = e.target.getAttribute('data-horas');
            fetchHistory(horas);
        }
    });

    // --- Carrega os dados iniciais (24h por padrão) ---
    fetchHistory(24);
});