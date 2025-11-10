<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ESP Monitor - Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/menu.css" rel="stylesheet">
</head>
<body>

  <div class="sidebar d-flex flex-column">
    <div class="logo d-flex align-items-center mb-3">
      <i class="bi bi-thermometer-half me-2"></i>
      <span>M.A.S</span>
    </div>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item">
        <a href="pagina_dashboard.php" class="nav-link active" target="contentFrame">
          <i class="bi bi-graph-up"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="pagina_agenda.html" class="nav-link" target="contentFrame">
          <i class="bi bi-calendar-event"></i>
          <span>Agenda</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="api/notify_admin.php" class="nav-link" target="contentFrame">
          <i class="bi bi-whatsapp"></i>
          <span>Notificações</span>
        </a>
      </li>
    </ul>
    <hr>
  </div>

  <div class="toggle-button-container">
      <button class="btn btn-primary btn-sm" id="toggle-menu"><i class="bi bi-arrows-angle-contract"></i></button>
  </div>

  <iframe name="contentFrame" src="pagina_dashboard.php" class="content-frame">
  </iframe>

  <script>
    // Script para marcar o link ativo no menu
    document.addEventListener('DOMContentLoaded', function() {
      const links = document.querySelectorAll('.sidebar .nav-link');
      
      links.forEach(link => {
        link.addEventListener('click', function() {
          links.forEach(l => l.classList.remove('active'));
          this.classList.add('active');
        });
      });

      const toggleMenu = document.getElementById('toggle-menu');
      toggleMenu.addEventListener('click', function() {
        document.body.classList.toggle('sidebar-collapsed');
        const icon = toggleMenu.querySelector('i');
        if (document.body.classList.contains('sidebar-collapsed')) {
          icon.classList.remove('bi-arrows-angle-contract');
          icon.classList.add('bi-arrows-angle-expand');
        } else {
          icon.classList.remove('bi-arrows-angle-expand');
          icon.classList.add('bi-arrows-angle-contract');
        }
      });
    });
  </script>
</body>
</html>