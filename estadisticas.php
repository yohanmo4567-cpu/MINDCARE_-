<?php
require_once 'sesion.php';
$pdo = conectarDB();

// Emociones por día (última semana)
$stmtEmoSem = $pdo->prepare("SELECT DATE(fecha_registro) as dia, COUNT(*) as total FROM emocion WHERE id_usuario = ? AND fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY dia ORDER BY dia");
$stmtEmoSem->execute([$uid]);
$emocionSemana = $stmtEmoSem->fetchAll();

// Hábitos completados por día (última semana)
$stmtHabSem = $pdo->prepare("SELECT sh.fecha, COUNT(*) as completados FROM seguimiento_habito sh JOIN habito h ON sh.id_habito = h.id_habito WHERE h.id_usuario = ? AND sh.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY sh.fecha ORDER BY sh.fecha");
$stmtHabSem->execute([$uid]);
$habitoSemana = $stmtHabSem->fetchAll();

// Estados más frecuentes
$stmtEstados = $pdo->prepare("SELECT ee.nombre_estado, COUNT(*) as total FROM emocion_estado es2 JOIN emocion e ON es2.id_emocion = e.id_emocion JOIN estado_emocional ee ON es2.id_estado = ee.id_estado WHERE e.id_usuario = ? GROUP BY ee.nombre_estado ORDER BY total DESC LIMIT 6");
$stmtEstados->execute([$uid]);
$estadosFrecuentes = $stmtEstados->fetchAll();

// Totales generales
$stmtTotales = $pdo->prepare("SELECT (SELECT COUNT(*) FROM emocion WHERE id_usuario = ?) as emociones, (SELECT COUNT(*) FROM habito WHERE id_usuario = ?) as habitos, (SELECT COUNT(*) FROM participacion_actividad WHERE id_usuario = ?) as actividades, (SELECT COUNT(*) FROM recordatorio WHERE id_usuario = ?) as recordatorios");
$stmtTotales->execute([$uid, $uid, $uid, $uid]);
$totales = $stmtTotales->fetch();

// Racha de hábitos
$stmtRacha = $pdo->prepare("SELECT COUNT(DISTINCT sh.fecha) as racha FROM seguimiento_habito sh JOIN habito h ON sh.id_habito = h.id_habito WHERE h.id_usuario = ? AND sh.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmtRacha->execute([$uid]);
$racha = $stmtRacha->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Estadísticas | MindCare+</title>
<link rel="stylesheet" href="dashboard.css"/>
</head>
<body>
<div class="app-shell">
<?php renderSidebar('estadisticas'); ?>
<div class="main-content">
<?php renderTopbar('Estadísticas 📊', 'Tu progreso y bienestar en números'); ?>
<main class="page-body">

<!-- Stats generales -->
<div class="stats-grid mb-24">
  <div class="stat-card"><div class="stat-icon green">💚</div><div class="stat-info"><div class="stat-value"><?= $totales['emociones'] ?></div><div class="stat-label">Emociones registradas</div></div></div>
  <div class="stat-card"><div class="stat-icon blue">🔁</div><div class="stat-info"><div class="stat-value"><?= $totales['habitos'] ?></div><div class="stat-label">Hábitos activos</div></div></div>
  <div class="stat-card"><div class="stat-icon accent">🧘</div><div class="stat-info"><div class="stat-value"><?= $totales['actividades'] ?></div><div class="stat-label">Actividades realizadas</div></div></div>
  <div class="stat-card"><div class="stat-icon primary">🔥</div><div class="stat-info"><div class="stat-value"><?= $racha ?></div><div class="stat-label">Días activo este mes</div></div></div>
</div>

<div class="grid-2 mb-24">
  <!-- Emociones últimos 7 días -->
  <div class="card">
    <div class="card-header"><div class="card-title">💚 Emociones por día (7 días)</div></div>
    <?php if ($emocionSemana): ?>
      <?php foreach ($emocionSemana as $dia): ?>
        <div style="margin-bottom:10px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:.82rem;font-weight:600;"><?= date('d/m', strtotime($dia['dia'])) ?></span>
            <span style="font-size:.78rem;color:var(--muted);"><?= $dia['total'] ?> registro<?= $dia['total'] > 1 ? 's' : '' ?></span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= min($dia['total'] * 20, 100) ?>%"></div></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><div class="empty-icon">📊</div><p>Sin datos esta semana.</p></div>
    <?php endif; ?>
  </div>

  <!-- Hábitos últimos 7 días -->
  <div class="card">
    <div class="card-header"><div class="card-title">🔁 Hábitos completados (7 días)</div></div>
    <?php if ($habitoSemana): ?>
      <?php foreach ($habitoSemana as $dia): ?>
        <div style="margin-bottom:10px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:.82rem;font-weight:600;"><?= date('d/m', strtotime($dia['fecha'])) ?></span>
            <span style="font-size:.78rem;color:var(--muted);"><?= $dia['completados'] ?> hábito<?= $dia['completados'] > 1 ? 's' : '' ?></span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= min($dia['completados'] * 25, 100) ?>%"></div></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><div class="empty-icon">🔁</div><p>Sin hábitos completados esta semana.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- Estados más frecuentes -->
<div class="card">
  <div class="card-header"><div class="card-title">🎭 Mis estados emocionales más frecuentes</div></div>
  <?php if ($estadosFrecuentes): ?>
    <?php $maxTotal = max(array_column($estadosFrecuentes, 'total')); ?>
    <div class="grid-2">
      <?php $colores = ['badge-green','badge-blue','badge-accent','badge-primary','badge-red','badge-green']; ?>
      <?php foreach ($estadosFrecuentes as $i => $e): $pct = round($e['total'] / $maxTotal * 100); ?>
        <div style="padding:16px;border:1px solid var(--line);border-radius:var(--radius-sm);background:var(--surface-soft);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span class="badge <?= $colores[$i % count($colores)] ?>"><?= htmlspecialchars($e['nombre_estado']) ?></span>
            <strong style="font-size:1.1rem;"><?= $e['total'] ?>×</strong>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">🎭</div><p>Registra emociones para ver tus estados más frecuentes.</p></div>
  <?php endif; ?>
</div>

</main>
</div>
</div>
</body>
</html>
