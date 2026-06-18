<?php
require_once 'sesion.php';
$pdo = conectarDB();
$msg = ''; $msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_actividad'])) {
    verifyCsrf();
    $idAct   = (int)$_POST['id_actividad'];
    $progreso = max(0, min(100, (int)($_POST['progreso'] ?? 100)));

    // Verificar que la actividad existe
    $checkAct = $pdo->prepare("SELECT id_actividad FROM actividad WHERE id_actividad = ?");
    $checkAct->execute([$idAct]);
    if ($checkAct->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO participacion_actividad (id_usuario, id_actividad, fecha_realizacion, progreso) VALUES (?, ?, CURDATE(), ?)");
        $stmt->execute([$uid, $idAct, $progreso]);
        $msg = '¡Actividad registrada!'; $msgTipo = 'success';
    } else {
        $msg = 'Actividad no válida.'; $msgTipo = 'error';
    }
}

$actividades = $pdo->query("SELECT * FROM actividad ORDER BY nombre_actividad")->fetchAll();
$stmtPart = $pdo->prepare("
    SELECT pa.*, a.nombre_actividad
    FROM participacion_actividad pa
    JOIN actividad a ON pa.id_actividad = a.id_actividad
    WHERE pa.id_usuario = ?
    ORDER BY pa.fecha_realizacion DESC
    LIMIT 10
");
$stmtPart->execute([$uid]);
$participaciones = $stmtPart->fetchAll();

$iconos = ['🧘','🌬️','💪','🚶','🎨','📖','🧗','🏃','🌿','💆'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Actividades | MindCare+</title>
<link rel="stylesheet" href="dashboard.css"/>
</head>
<body>
<div class="app-shell">
<?php renderSidebar('actividades'); ?>
<div class="main-content">
<?php renderTopbar('Actividades de Bienestar 🧘', 'Ejercicios para reducir el estrés y mejorar tu bienestar'); ?>
<main class="page-body">

<?php if ($msg): ?>
<div class="alert alert-<?= $msgTipo === 'success' ? 'success' : 'error' ?> show"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Catálogo de actividades -->
<div class="card mb-24">
  <div class="card-header">
    <div class="card-title">🌟 Actividades disponibles</div>
    <div class="card-subtitle">Selecciona una para registrar tu participación</div>
  </div>
  <?php if ($actividades): ?>
  <div class="grid-3">
    <?php foreach ($actividades as $i => $a): $icono = $iconos[$i % count($iconos)]; ?>
      <div style="border:1px solid var(--line);border-radius:var(--radius);padding:20px;background:var(--surface-soft);">
        <div style="font-size:2rem;margin-bottom:10px;"><?= $icono ?></div>
        <div style="font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($a['nombre_actividad']) ?></div>
        <div style="font-size:.82rem;color:var(--muted);margin-bottom:8px;"><?= htmlspecialchars($a['descripcion'] ?? '') ?></div>
        <?php if (!empty($a['duracion'])): ?>
          <span class="badge badge-blue" style="margin-bottom:12px;">⏱ <?= (int)$a['duracion'] ?> min</span>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="id_actividad" value="<?= (int)$a['id_actividad'] ?>"/>
          <input type="hidden" name="progreso" value="100"/>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
          <button class="btn btn-primary btn-sm" style="width:100%">✅ Realicé esta actividad</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">🧘</div><p>No hay actividades disponibles aún. El administrador debe agregarlas.</p></div>
  <?php endif; ?>
</div>

<!-- Historial de participaciones -->
<div class="card">
  <div class="card-header"><div class="card-title">📋 Mi historial de actividades</div></div>
  <?php if ($participaciones): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Actividad</th><th>Fecha</th><th>Progreso</th></tr></thead>
      <tbody>
      <?php foreach ($participaciones as $p): ?>
        <tr>
          <td><strong><?= htmlspecialchars($p['nombre_actividad']) ?></strong></td>
          <td><?= date('d/m/Y', strtotime($p['fecha_realizacion'])) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="progress-bar" style="width:100px;"><div class="progress-fill" style="width:<?= (int)$p['progreso'] ?>%"></div></div>
              <span style="font-size:.8rem;color:var(--muted);"><?= (int)$p['progreso'] ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">🌿</div><p>Aún no has realizado ninguna actividad. ¡Empieza hoy!</p></div>
  <?php endif; ?>
</div>

</main>
</div>
</div>
</body>
</html>
