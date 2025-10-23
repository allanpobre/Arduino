<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ESP Monitor - Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      min-height: 100vh;
      background-color: #f8f9fa; /* Fundo geral */
    }
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 250px; /* Largura do menu */
      padding: 20px;
      background-color: #fff;
      border-right: 1px solid #dee2e6;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      z-index: 100;
    }
    .sidebar .nav-link {
      font-size: 1.05rem;
      color: #343a40;
      border-radius: 0.375rem;
      margin-bottom: 5px;
    }
    .sidebar .nav-link i {
      width: 30px;
      text-align: center;
    }
    .sidebar .nav-link.active {
      background-color: #0d6efd;
      color: #fff;
    }
    .sidebar .nav-link:not(.active):hover {
      background-color: #e9ecef;
      color: #000;
    }
    .sidebar .logo {
      font-weight: 700;
      font-size: 1.5rem;
      color: #0d6efd;
    }
    /* Onde o conteúdo será carregado */
    .content-frame {
      position: fixed;
      top: 0;
      left: 250px; /* Deve ser igual à largura do sidebar */
      right: 0;
      bottom: 0;
      border: none;
      width: calc(100% - 250px);
      height: 100vh;
    }
    
    /* Ajuste para telas pequenas (mobile) */
    @media (max-width: 767.98px) {
      .sidebar {
        width: 100%;
        height: auto;
        position: relative; /* Deixa de ser fixo */
        border-right: 0;
        border-bottom: 1px solid #dee2e6;
      }
      .content-frame {
        position: relative;
        left: 0;
        width: 100%;
        height: calc(100vh - 160px); /* Ajuste a altura conforme necessidade */
      }
    }
  </style>
</head>
<body>

  <div class="sidebar d-flex flex-column">
    <div class="logo d-flex align-items-center mb-3">
      <i class="bi bi-speedometer2 me-2"></i>
      <span>ESP Monitor</span>
    </div>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item">
        <a href="pagina_dashboard.html" class="nav-link active" target="contentFrame">
          <i class="bi bi-graph-up"></i>
          Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a href="notify_admin.php" class="nav-link" target="contentFrame">
          <i class="bi bi-whatsapp"></i>
          Notificações
        </a>
      </li>
    </ul>
    <hr>
    <div class="small text-muted">Projeto ESP8266</div>
  </div>

  <iframe name="contentFrame" src="pagina_dashboard.html" class="content-frame">
  </iframe>

  <script>
    // Script para marcar o link ativo no menu
    document.addEventListener('DOMContentLoaded', function() {
      const links = document.querySelectorAll('.sidebar .nav-link');
      
      links.forEach(link => {
        link.addEventListener('click', function() {
          // Remove a classe 'active' de todos
          links.forEach(l => l.classList.remove('active'));
          // Adiciona 'active' apenas no clicado
          this.classList.add('active');
        });
      });
    });
  </script>
</body>
</html>