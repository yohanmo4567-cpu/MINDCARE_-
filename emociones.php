<?php
require_once 'sesion.php';
$pdo = conectarDB();

$msg = ''; $msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = $_POST['accion'] ?? '';

    // ── REGISTRAR EMOCIÓN ────────────────────────────────────────
    if ($accion === 'registrar') {
        $observacion = trim($_POST['observacion'] ?? '');
        $estadosPost = $_POST['estados'] ?? [];
        if (!empty($estadosPost)) {
            $stmtIns = $pdo->prepare("INSERT INTO emocion (id_usuario, fecha_registro, observacion) VALUES (?, NOW(), ?)");
            $stmtIns->execute([$uid, $observacion]);
            $idEmocion = $pdo->lastInsertId();
            foreach ($estadosPost as $idEstado) {
                $stmtEE = $pdo->prepare("INSERT INTO emocion_estado (id_emocion, id_estado) VALUES (?, ?)");
                $stmtEE->execute([$idEmocion, (int)$idEstado]);
            }
            $msg = '¡Emoción registrada exitosamente!'; $msgTipo = 'success';
        } else {
            $msg = 'Selecciona al menos un estado emocional.'; $msgTipo = 'error';
        }

    // ── ELIMINAR EMOCIÓN ─────────────────────────────────────────
    } elseif ($accion === 'eliminar_emocion') {
        $idEmocion = (int)($_POST['id_emocion'] ?? 0);
        if ($idEmocion > 0) {
            // Verificar que la emoción pertenece al usuario
            $check = $pdo->prepare("SELECT id_emocion FROM emocion WHERE id_emocion = ? AND id_usuario = ?");
            $check->execute([$idEmocion, $uid]);
            if ($check->fetch()) {
                $pdo->prepare("DELETE FROM emocion_estado WHERE id_emocion = ?")->execute([$idEmocion]);
                $pdo->prepare("DELETE FROM emocion WHERE id_emocion = ? AND id_usuario = ?")->execute([$idEmocion, $uid]);
                $msg = 'Emoción eliminada.'; $msgTipo = 'success';
            } else {
                $msg = 'No tienes permiso para eliminar esta emoción.'; $msgTipo = 'error';
            }
        }
    }
}

// Estados disponibles
$estadosDB = $pdo->query("SELECT * FROM estado_emocional ORDER BY nombre_estado")->fetchAll();

// Historial
$stmtHist = $pdo->prepare("
    SELECT e.id_emocion, e.fecha_registro, e.observacion,
           GROUP_CONCAT(ee.nombre_estado SEPARATOR ', ') AS estados
    FROM emocion e
    LEFT JOIN emocion_estado es2 ON e.id_emocion = es2.id_emocion
    LEFT JOIN estado_emocional ee ON es2.id_estado = ee.id_estado
    WHERE e.id_usuario = ?
    GROUP BY e.id_emocion
    ORDER BY e.fecha_registro DESC
    LIMIT 20
");
$stmtHist->execute([$uid]);
$historial = $stmtHist->fetchAll();

// Emojis por estado
$emojiMap = [
    'feliz'        => '😊', 'alegre'      => '😄', 'contento'    => '🙂',
    'ansioso'      => '😰', 'ansiosa'     => '😰', 'nervioso'    => '😬',
    'triste'       => '😔', 'deprimido'   => '😞', 'melancólico' => '😢',
    'estresado'    => '😤', 'agobiado'    => '😩', 'cansado'     => '😴',
    'tranquilo'    => '😌', 'calmado'     => '🧘', 'relajado'    => '😮‍💨',
    'motivado'     => '💪', 'entusiasmado'=> '🤩', 'emocionado'  => '🥳',
    'enojado'      => '😠', 'frustrado'   => '😤', 'irritado'    => '😒',
    'asustado'     => '😨', 'preocupado'  => '😟', 'inseguro'    => '😕',
    'agradecido'   => '🙏', 'satisfecho'  => '😎', 'orgulloso'   => '😤',
];
$defaultEmojis = ['😊','😔','😰','😤','😌','💪','😢','🤩','😠','😨'];

function getEmoji(string $nombre, array $map, array $defaults, int $idx): string {
    $key = mb_strtolower(trim($nombre));
    foreach ($map as $k => $e) {
        if (str_contains($key, $k)) return $e;
    }
    return $defaults[$idx % count($defaults)];
}

// Resumen emocional
$conteo = [];
foreach ($historial as $h) {
    foreach (explode(', ', $h['estados'] ?? '') as $est) {
        $est = trim($est);
        if ($est) $conteo[$est] = ($conteo[$est] ?? 0) + 1;
    }
}
arsort($conteo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Control Emocional | MindCare+</title>
<link rel="stylesheet" href="dashboard.css"/>
</head>
<body>
<div class="app-shell">
<?php renderSidebar('emociones'); ?>
<div class="main-content">
<?php renderTopbar('Control Emocional 💚', 'Registra y monitorea tu estado emocional'); ?>
<main class="page-body">

<?php if ($msg): ?>
<div class="alert alert-<?= $msgTipo === 'success' ? 'success' : 'error' ?> show"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="grid-2 mb-24">

  <!-- Formulario registro -->
  <div class="card">
    <div class="card-header"><div class="card-title">+ Registrar emoción</div></div>
    <form method="POST">
      <input type="hidden" name="accion" value="registrar"/>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
      <div class="form-group">
        <label class="form-label">¿Cómo te sientes hoy?</label>
        <div class="mood-grid" id="moodGrid">
          <?php if (!empty($estadosDB)): ?>
            <?php foreach ($estadosDB as $i => $e): ?>
              <?php $emoji = getEmoji($e['nombre_estado'], $emojiMap, $defaultEmojis, $i); ?>
              <button type="button" class="mood-btn" data-id="<?= (int)$e['id_estado'] ?>" onclick="toggleMood(this)">
                <span><?= $emoji ?></span>
                <span><?= htmlspecialchars($e['nombre_estado']) ?></span>
              </button>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state"><div class="empty-icon">😶</div><p>No hay estados emocionales. El admin debe agregarlos.</p></div>
          <?php endif; ?>
        </div>
        <div id="hiddenEstados"></div>
        <p style="font-size:.78rem;color:var(--muted);margin-top:6px;">Puedes seleccionar varios estados.</p>
      </div>
      <div class="form-group">
        <label class="form-label">Observaciones (opcional)</label>
        <textarea name="observacion" class="form-textarea" placeholder="¿Qué pasó hoy? ¿Cómo fue tu día?"></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">💚 Guardar registro</button>
    </form>
  </div>

  <!-- Resumen -->
  <div class="card">
    <div class="card-header"><div class="card-title">📊 Resumen emocional</div></div>
    <?php if ($conteo): ?>
      <?php $total = array_sum($conteo); ?>
      <?php foreach (array_slice($conteo, 0, 5) as $nombre_est => $cantidad): ?>
        <?php $pct = round($cantidad / $total * 100); ?>
        <div style="margin-bottom:14px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($nombre_est) ?></span>
            <span style="font-size:.78rem;color:var(--muted);"><?= $cantidad ?> veces (<?= $pct ?>%)</span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><div class="empty-icon">📊</div><p>Registra emociones para ver tu resumen.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- Historial -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📋 Historial de emociones</div>
    <span class="badge badge-primary"><?= count($historial) ?> registros</span>
  </div>
  <?php if ($historial): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Estado(s)</th>
          <th>Observación</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($historial as $h): ?>
        <tr>
          <td style="white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($h['fecha_registro'])) ?></td>
          <td>
            <?php foreach (explode(', ', $h['estados'] ?? 'Sin estado') as $est): ?>
              <span class="badge badge-primary" style="margin:2px;"><?= htmlspecialchars(trim($est)) ?></span>
            <?php endforeach; ?>
          </td>
          <td style="max-width:240px;color:var(--muted);font-size:.85rem;">
            <?= htmlspecialchars($h['observacion'] ?? '—') ?>
          </td>
          <td>
            <form method="POST" onsubmit="return confirm('¿Eliminar este registro emocional?')">
              <input type="hidden" name="accion" value="eliminar_emocion"/>
              <input type="hidden" name="id_emocion" value="<?= (int)$h['id_emocion'] ?>"/>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
              <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">🗑</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon">💚</div><p>Aún no tienes emociones registradas.</p></div>
  <?php endif; ?>
</div>

</main>
</div>
</div>
<script src="dashboard.js"></script>
</body>
</html>
