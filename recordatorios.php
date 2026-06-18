<?php
require_once 'sesion.php';
$pdo = conectarDB();
$msg = ''; $msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $titulo    = trim($_POST['titulo']   ?? '');
        $mensaje   = trim($_POST['mensaje']  ?? '');
        $fechaHora = trim($_POST['fecha_hora'] ?? '');
        if ($titulo && $fechaHora) {
            // Validar formato de fecha
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $fechaHora);
            if ($dt && $dt > new DateTime()) {
                $stmt = $pdo->prepare("INSERT INTO recordatorio (id_usuario, titulo, mensaje, fecha_hora) VALUES (?, ?, ?, ?)");
                $stmt->execute([$uid, $titulo, $mensaje, $dt->format('Y-m-d H:i:s')]);
                $msg = '¡Recordatorio creado!'; $msgTipo = 'success';
            } else {
                $msg = 'La fecha debe ser futura y tener formato válido.'; $msgTipo = 'error';
            }
        } else {
            $msg = 'El título y la fecha son requeridos.'; $msgTipo = 'error';
        }

    } elseif ($accion === 'eliminar') {
        $idRec = (int)$_POST['id_recordatorio'];
        $pdo->prepare("DELETE FROM recordatorio WHERE id_recordatorio = ? AND id_usuario = ?")->execute([$idRec, $uid]);
        $msg = 'Recordatorio eliminado.'; $msgTipo = 'success';
    }
}

$stmtRec = $pdo->prepare("SELECT * FROM recordatorio WHERE id_usuario = ? ORDER BY fecha_hora ASC");
$stmtRec->execute([$uid]);
$recordatorios = $stmtRec->fetchAll();
$proximos = array_values(array_filter($recordatorios, fn($r) => strtotime($r['fecha_hora']) >= time()));
$pasados  = array_values(array_filter($recordatorios, fn($r) => strtotime($r['fecha_hora']) < time()));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Recordatorios | MindCare+</title>
<link rel="stylesheet" href="dashboard.css"/>
</head>
<body>
<div class="app-shell">
<?php renderSidebar('recordatorios'); ?>
<div class="main-content">
<?php renderTopbar('Recordatorios 🔔', 'Gestiona tus alertas y notificaciones'); ?>
<main class="page-body">

<?php if ($msg): ?>
<div class="alert alert-<?= $msgTipo === 'success' ? 'success' : 'error' ?> show"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="grid-2 mb-24">
  <!-- Crear recordatorio -->
  <div class="card">
    <div class="card-header"><div class="card-title">+ Nuevo recordatorio</div></div>
    <form method="POST">
      <input type="hidden" name="accion" value="crear"/>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
      <div class="form-group">
        <label class="form-label">Título</label>
        <input type="text" name="titulo" class="form-input" placeholder="Ej: Meditar antes de dormir" required maxlength="150"/>
      </div>
      <div class="form-group">
        <label class="form-label">Mensaje (opcional)</label>
        <textarea name="mensaje" class="form-textarea" style="min-height:70px;" placeholder="Detalle del recordatorio..." maxlength="500"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Fecha y hora</label>
        <input type="datetime-local" name="fecha_hora" class="form-input" required min="<?= date('Y-m-d\TH:i') ?>"/>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">🔔 Crear recordatorio</button>
    </form>
  </div>

  <!-- Próximos -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">⏰ Próximos recordatorios</div>
      <span class="badge badge-accent"><?= count($proximos) ?></span>
    </div>
    <?php if ($proximos): ?>
      <?php foreach ($proximos as $r): ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid var(--line);">
          <div style="width:40px;height:40px;border-radius:10px;background:var(--accent-light);display:grid;place-items:center;font-size:1.2rem;flex-shrink:0;">🔔</div>
          <div style="flex:1;">
            <strong style="font-size:.9rem;"><?= htmlspecialchars($r['titulo']) ?></strong>
            <div style="font-size:.78rem;color:var(--muted);">📅 <?= date('d/m/Y H:i', strtotime($r['fecha_hora'])) ?></div>
            <?php if ($r['mensaje']): ?>
              <div style="font-size:.82rem;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($r['mensaje']) ?></div>
            <?php endif; ?>
          </div>
          <form method="POST">
            <input type="hidden" name="accion" value="eliminar"/>
            <input type="hidden" name="id_recordatorio" value="<?= (int)$r['id_recordatorio'] ?>"/>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
            <button class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este recordatorio?')">🗑</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><div class="empty-icon">🔔</div><p>No tienes recordatorios próximos.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- Historial pasados -->
<?php if ($pasados): ?>
<div class="card">
  <div class="card-header"><div class="card-title">📋 Recordatorios pasados</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Título</th><th>Mensaje</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach (array_reverse($pasados) as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['titulo']) ?></strong></td>
          <td style="color:var(--muted);font-size:.85rem;"><?= htmlspecialchars($r['mensaje'] ?? '—') ?></td>
          <td><?= date('d/m/Y H:i', strtotime($r['fecha_hora'])) ?></td>
          <td><span class="badge badge-blue">Pasado</span></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="accion" value="eliminar"/>
              <input type="hidden" name="id_recordatorio" value="<?= (int)$r['id_recordatorio'] ?>"/>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
              <button class="btn btn-sm btn-danger">🗑</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</main>
</div>
</div>
</body>
</html>
