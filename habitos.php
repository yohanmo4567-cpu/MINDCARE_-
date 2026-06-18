<?php
require_once 'sesion.php';
$pdo = conectarDB();
$msg = ''; $msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre_h = trim($_POST['nombre_habito'] ?? '');
        $desc     = trim($_POST['descripcion']   ?? '');
        $freq     = trim($_POST['frecuencia']    ?? 'Diario');
        $freqsValidas = ['Diario', 'Semanal', 'Lunes a Viernes', 'Fines de semana'];
        if (!in_array($freq, $freqsValidas, true)) $freq = 'Diario';
        if ($nombre_h) {
            $stmt = $pdo->prepare("INSERT INTO habito (id_usuario, nombre_habito, descripcion, frecuencia) VALUES (?, ?, ?, ?)");
            $stmt->execute([$uid, $nombre_h, $desc, $freq]);
            $msg = '¡Hábito creado!'; $msgTipo = 'success';
        } else {
            $msg = 'El nombre del hábito es requerido.'; $msgTipo = 'error';
        }

    } elseif ($accion === 'completar') {
        $idHabito = (int)($_POST['id_habito'] ?? 0);
        // Verificar que el hábito pertenece al usuario
        $own = $pdo->prepare("SELECT id_habito FROM habito WHERE id_habito = ? AND id_usuario = ?");
        $own->execute([$idHabito, $uid]);
        if ($own->fetch()) {
            $check = $pdo->prepare("SELECT id_seguimiento FROM seguimiento_habito WHERE id_habito = ? AND fecha = CURDATE()");
            $check->execute([$idHabito]);
            if (!$check->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO seguimiento_habito (id_habito, fecha, progreso) VALUES (?, CURDATE(), 100)");
                $stmt->execute([$idHabito]);
                $msg = '¡Hábito completado hoy!'; $msgTipo = 'success';
            } else {
                $msg = 'Ya completaste este hábito hoy.'; $msgTipo = 'error';
            }
        }

    } elseif ($accion === 'eliminar') {
        $idHabito = (int)($_POST['id_habito'] ?? 0);
        // Verificar propiedad antes de eliminar
        $own = $pdo->prepare("SELECT id_habito FROM habito WHERE id_habito = ? AND id_usuario = ?");
        $own->execute([$idHabito, $uid]);
        if ($own->fetch()) {
            $pdo->prepare("DELETE FROM seguimiento_habito WHERE id_habito = ?")->execute([$idHabito]);
            $pdo->prepare("DELETE FROM habito WHERE id_habito = ? AND id_usuario = ?")->execute([$idHabito, $uid]);
            $msg = 'Hábito eliminado.'; $msgTipo = 'success';
        }
    }
}

$stmtHab = $pdo->prepare("
    SELECT h.*,
           (SELECT COUNT(*) FROM seguimiento_habito sh WHERE sh.id_habito = h.id_habito AND sh.fecha = CURDATE()) as completado_hoy,
           (SELECT COUNT(*) FROM seguimiento_habito sh WHERE sh.id_habito = h.id_habito) as total_dias
    FROM habito h
    WHERE h.id_usuario = ?
    ORDER BY h.id_habito DESC
");
$stmtHab->execute([$uid]);
$habitos = $stmtHab->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Hábitos | MindCare+</title>
<link rel="stylesheet" href="dashboard.css"/>
</head>
<body>
<div class="app-shell">
<?php renderSidebar('habitos'); ?>
<div class="main-content">
<?php renderTopbar('Hábitos y Rutinas 🔁', 'Construye hábitos saludables día a día'); ?>
<main class="page-body">

<?php if ($msg): ?>
<div class="alert alert-<?= $msgTipo === 'success' ? 'success' : 'error' ?> show"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="grid-2 mb-24">
  <!-- Crear hábito -->
  <div class="card">
    <div class="card-header"><div class="card-title">+ Nuevo hábito</div></div>
    <form method="POST">
      <input type="hidden" name="accion" value="crear"/>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
      <div class="form-group">
        <label class="form-label">Nombre del hábito</label>
        <input type="text" name="nombre_habito" class="form-input" placeholder="Ej: Meditar 10 minutos" required maxlength="150"/>
      </div>
      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-textarea" placeholder="¿En qué consiste este hábito?" style="min-height:70px;" maxlength="500"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Frecuencia</label>
        <select name="frecuencia" class="form-select">
          <option value="Diario">Diario</option>
          <option value="Semanal">Semanal</option>
          <option value="Lunes a Viernes">Lunes a Viernes</option>
          <option value="Fines de semana">Fines de semana</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">🔁 Crear hábito</button>
    </form>
  </div>

  <!-- Progreso hoy -->
  <div class="card">
    <div class="card-header"><div class="card-title">📊 Progreso de hoy</div></div>
    <?php
    $completadosHoy = count(array_filter($habitos, fn($h) => $h['completado_hoy'] > 0));
    $totalHabs = count($habitos);
    $pct = $totalHabs > 0 ? round($completadosHoy / $totalHabs * 100) : 0;
    ?>
    <div style="text-align:center;padding:20px 0;">
      <div style="font-size:3rem;font-weight:800;color:var(--primary);"><?= $pct ?>%</div>
      <div style="color:var(--muted);margin-bottom:16px;"><?= $completadosHoy ?> de <?= $totalHabs ?> hábitos completados</div>
      <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
    </div>
    <div style="margin-top:20px;">
      <?php foreach ($habitos as $h): ?>
        <div class="habit-item">
          <div class="habit-check <?= $h['completado_hoy'] ? 'done' : '' ?>"></div>
          <div class="habit-info">
            <strong><?= htmlspecialchars($h['nombre_habito']) ?></strong><br>
            <span><?= (int)$h['total_dias'] ?> días completados</span>
          </div>
          <span class="habit-freq"><?= htmlspecialchars($h['frecuencia']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (empty($habitos)): ?>
        <div class="empty-state"><div class="empty-icon">🔁</div><p>Crea tu primer hábito.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Lista completa -->
<div class="card">
  <div class="card-header"><div class="card-title">📋 Mis hábitos</div></div>
  <?php if ($habitos): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Hábito</th><th>Descripción</th><th>Frecuencia</th><th>Días completados</th><th>Hoy</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($habitos as $h): ?>
        <tr>
          <td><strong><?= htmlspecialchars($h['nombre_habito']) ?></strong></td>
          <td style="color:var(--muted);font-size:.85rem;"><?= htmlspecialchars($h['descripcion'] ?? '—') ?></td>
          <td><span class="badge badge-blue"><?= htmlspecialchars($h['frecuencia']) ?></span></td>
          <td><span class="badge badge-primary"><?= (int)$h['total_dias'] ?> días</span></td>
          <td>
            <?php if ($h['completado_hoy']): ?>
              <span class="badge badge-green">✅ Completado</span>
            <?php else: ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="accion" value="completar"/>
                <input type="hidden" name="id_habito" value="<?= (int)$h['id_habito'] ?>"/>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
                <button class="btn btn-sm btn-primary">Completar</button>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este hábito y todo su historial?')">
              <input type="hidden" name="accion" value="eliminar"/>
              <input type="hidden" name="id_habito" value="<?= (int)$h['id_habito'] ?>"/>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
              <button class="btn btn-sm btn-danger">🗑</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">🔁</div><p>Aún no tienes hábitos. ¡Crea tu primero!</p></div>
  <?php endif; ?>
</div>
</main>
</div>
</div>
</body>
</html>
