<?php
require_once 'sesion.php';
if (!$esAdmin) { header('Location: dashboard.php'); exit; }
$pdo = conectarDB();
$msg = ''; $msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_recurso') {
        $titulo    = trim($_POST['titulo']    ?? '');
        $contenido = trim($_POST['contenido'] ?? '');
        $idAdmin   = (int)$_SESSION['admin_id'];
        if ($titulo && $contenido) {
            $stmt = $pdo->prepare("INSERT INTO recurso_educativo (id_admin, titulo, contenido, fecha_publicacion) VALUES (?, ?, ?, CURDATE())");
            $stmt->execute([$idAdmin, $titulo, $contenido]);
            $msg = '¡Recurso creado!'; $msgTipo = 'success';
        } else {
            $msg = 'El título y el contenido son requeridos.'; $msgTipo = 'error';
        }

    } elseif ($accion === 'crear_actividad') {
        $nombreAct = trim($_POST['nombre_actividad'] ?? '');
        $desc      = trim($_POST['descripcion']      ?? '');
        $dur       = (int)($_POST['duracion']        ?? 0);
        if ($nombreAct) {
            $stmt = $pdo->prepare("INSERT INTO actividad (nombre_actividad, descripcion, duracion) VALUES (?, ?, ?)");
            $stmt->execute([$nombreAct, $desc, $dur > 0 ? $dur : null]);
            $msg = '¡Actividad creada!'; $msgTipo = 'success';
        } else {
            $msg = 'El nombre de la actividad es requerido.'; $msgTipo = 'error';
        }

    } elseif ($accion === 'eliminar_usuario') {
        $idUsuario = (int)($_POST['id_usuario'] ?? 0);
        // No permitir que el admin se elimine a sí mismo
        if ($idUsuario && $idUsuario !== $uid) {
            $pdo->prepare("DELETE FROM usuario WHERE id_usuario = ?")->execute([$idUsuario]);
            $msg = 'Usuario eliminado.'; $msgTipo = 'success';
        } else {
            $msg = 'No puedes eliminar tu propia cuenta desde aquí.'; $msgTipo = 'error';
        }
    }
}

// Totales globales
$totUsuarios    = (int)$pdo->query("SELECT COUNT(*) FROM usuario")->fetchColumn();
$totEmociones   = (int)$pdo->query("SELECT COUNT(*) FROM emocion")->fetchColumn();
$totHabitos     = (int)$pdo->query("SELECT COUNT(*) FROM habito")->fetchColumn();
$totActividades = (int)$pdo->query("SELECT COUNT(*) FROM actividad")->fetchColumn();

// Usuarios recientes
$usuarios = $pdo->query("SELECT id_usuario, nombre, apellido, correo, fecha_registro, edad FROM usuario ORDER BY fecha_registro DESC LIMIT 10")->fetchAll();

// Recursos
$recursos = $pdo->query("SELECT * FROM recurso_educativo ORDER BY fecha_publicacion DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Panel Admin | MindCare+</title>
<link rel="stylesheet" href="dashboard.css"/>
</head>
<body>
<div class="app-shell">
<?php renderSidebar('panel_admin'); ?>
<div class="main-content">
<?php renderTopbar('Panel de Administración ⚙️', 'Gestión y supervisión de la plataforma'); ?>
<main class="page-body">

<?php if ($msg): ?>
<div class="alert alert-<?= $msgTipo === 'success' ? 'success' : 'error' ?> show"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Stats globales -->
<div class="stats-grid mb-24">
  <div class="stat-card"><div class="stat-icon green">👥</div><div class="stat-info"><div class="stat-value"><?= $totUsuarios ?></div><div class="stat-label">Usuarios registrados</div></div></div>
  <div class="stat-card"><div class="stat-icon blue">💚</div><div class="stat-info"><div class="stat-value"><?= $totEmociones ?></div><div class="stat-label">Emociones registradas</div></div></div>
  <div class="stat-card"><div class="stat-icon accent">🔁</div><div class="stat-info"><div class="stat-value"><?= $totHabitos ?></div><div class="stat-label">Hábitos creados</div></div></div>
  <div class="stat-card"><div class="stat-icon primary">🧘</div><div class="stat-info"><div class="stat-value"><?= $totActividades ?></div><div class="stat-label">Actividades disponibles</div></div></div>
</div>

<div class="grid-2 mb-24">
  <!-- Crear recurso educativo -->
  <div class="card">
    <div class="card-header"><div class="card-title">📚 Crear recurso educativo</div></div>
    <form method="POST">
      <input type="hidden" name="accion" value="crear_recurso"/>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
      <div class="form-group">
        <label class="form-label">Título</label>
        <input type="text" name="titulo" class="form-input" placeholder="Título del recurso" required maxlength="200"/>
      </div>
      <div class="form-group">
        <label class="form-label">Contenido</label>
        <textarea name="contenido" class="form-textarea" placeholder="Escribe el contenido del recurso..." required></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">📚 Publicar recurso</button>
    </form>
  </div>

  <!-- Crear actividad -->
  <div class="card">
    <div class="card-header"><div class="card-title">🧘 Crear actividad de bienestar</div></div>
    <form method="POST">
      <input type="hidden" name="accion" value="crear_actividad"/>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
      <div class="form-group">
        <label class="form-label">Nombre de la actividad</label>
        <input type="text" name="nombre_actividad" class="form-input" placeholder="Ej: Respiración profunda" required maxlength="150"/>
      </div>
      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-textarea" style="min-height:70px;" placeholder="¿En qué consiste?" maxlength="500"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Duración (minutos)</label>
        <input type="number" name="duracion" class="form-input" placeholder="Ej: 15" min="1" max="480"/>
      </div>
      <button type="submit" class="btn btn-accent" style="width:100%">🧘 Crear actividad</button>
    </form>
  </div>
</div>

<!-- Usuarios registrados -->
<div class="card mb-24">
  <div class="card-header">
    <div class="card-title">👥 Usuarios registrados</div>
    <span class="badge badge-primary"><?= $totUsuarios ?> usuarios</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Nombre</th><th>Correo</th><th>Edad</th><th>Registro</th></tr></thead>
      <tbody>
      <?php foreach ($usuarios as $u): ?>
        <tr>
          <td style="color:var(--muted);"><?= (int)$u['id_usuario'] ?></td>
          <td><strong><?= htmlspecialchars($u['nombre']) ?> <?= htmlspecialchars($u['apellido']) ?></strong></td>
          <td style="color:var(--muted);"><?= htmlspecialchars($u['correo']) ?></td>
          <td><?= $u['edad'] ? (int)$u['edad'] : '—' ?></td>
          <td><?= $u['fecha_registro'] ? date('d/m/Y', strtotime($u['fecha_registro'])) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recursos publicados -->
<div class="card">
  <div class="card-header"><div class="card-title">📚 Recursos publicados</div></div>
  <?php if ($recursos): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Título</th><th>Publicado</th></tr></thead>
      <tbody>
      <?php foreach ($recursos as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['titulo']) ?></strong></td>
          <td><?= date('d/m/Y', strtotime($r['fecha_publicacion'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">📚</div><p>Aún no hay recursos publicados.</p></div>
  <?php endif; ?>
</div>

</main>
</div>
</div>
<script src="dashboard.js"></script>
</body>
</html>
