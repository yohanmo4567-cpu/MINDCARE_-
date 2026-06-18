<?php
require_once 'sesion.php';
$pdo = conectarDB();

$categorias = $pdo->query("SELECT * FROM categoria ORDER BY nombre_categoria")->fetchAll();
$filtroCategoria = (int)($_GET['categoria'] ?? 0);

if ($filtroCategoria) {
    $stmt = $pdo->prepare("
        SELECT r.*, GROUP_CONCAT(c.nombre_categoria SEPARATOR ', ') as categorias
        FROM recurso_educativo r
        LEFT JOIN recurso_categoria rc ON r.id_recurso = rc.id_recurso
        LEFT JOIN categoria c ON rc.id_categoria = c.id_categoria
        WHERE rc.id_categoria = ?
        GROUP BY r.id_recurso
        ORDER BY r.fecha_publicacion DESC
    ");
    $stmt->execute([$filtroCategoria]);
} else {
    $stmt = $pdo->prepare("
        SELECT r.*, GROUP_CONCAT(c.nombre_categoria SEPARATOR ', ') as categorias
        FROM recurso_educativo r
        LEFT JOIN recurso_categoria rc ON r.id_recurso = rc.id_recurso
        LEFT JOIN categoria c ON rc.id_categoria = c.id_categoria
        GROUP BY r.id_recurso
        ORDER BY r.fecha_publicacion DESC
    ");
    $stmt->execute();
}
$recursos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Recursos | MindCare+</title>
<link rel="stylesheet" href="dashboard.css"/>
<style>
/* Modal */
.modal-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 100;
  align-items: center;
  justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: #fff;
  border-radius: var(--radius);
  padding: 32px;
  max-width: 640px; width: 90%;
  max-height: 80vh; overflow-y: auto;
  position: relative;
}
.modal-close {
  position: absolute; top: 16px; right: 16px;
  background: none; border: none;
  font-size: 1.4rem; color: var(--muted); cursor: pointer;
  line-height: 1;
}
.modal-close:hover { color: var(--ink); }
</style>
</head>
<body>
<div class="app-shell">
<?php renderSidebar('recursos'); ?>
<div class="main-content">
<?php renderTopbar('Recursos Educativos 📚', 'Aprende y practica el autocuidado'); ?>
<main class="page-body">

<!-- Filtros por categoría -->
<div class="card mb-24">
  <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
    <span style="font-weight:600;font-size:.85rem;">Filtrar por:</span>
    <a href="recursos.php" class="btn btn-sm <?= !$filtroCategoria ? 'btn-primary' : 'btn-ghost' ?>">Todos</a>
    <?php foreach ($categorias as $cat): ?>
      <a href="?categoria=<?= (int)$cat['id_categoria'] ?>" class="btn btn-sm <?= $filtroCategoria === (int)$cat['id_categoria'] ? 'btn-primary' : 'btn-ghost' ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Recursos -->
<?php if ($recursos): ?>
<div class="grid-3">
  <?php $emojis = ['📖','🧠','💡','🌱','🎯','🌬️','💆','🧘','❤️','📝']; ?>
  <?php foreach ($recursos as $i => $r): ?>
    <div class="card" style="display:flex;flex-direction:column;">
      <div style="font-size:2.5rem;margin-bottom:12px;"><?= $emojis[$i % count($emojis)] ?></div>
      <div style="font-weight:700;font-size:1rem;margin-bottom:8px;"><?= htmlspecialchars($r['titulo']) ?></div>
      <?php if ($r['categorias']): ?>
        <div style="margin-bottom:10px;">
          <?php foreach (explode(', ', $r['categorias']) as $cat): ?>
            <span class="badge badge-primary" style="margin:2px;"><?= htmlspecialchars(trim($cat)) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div style="color:var(--muted);font-size:.85rem;flex:1;margin-bottom:12px;line-height:1.6;">
        <?= htmlspecialchars(mb_substr($r['contenido'] ?? '', 0, 180)) ?>...
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;">
        <span style="font-size:.75rem;color:var(--muted);">📅 <?= date('d/m/Y', strtotime($r['fecha_publicacion'])) ?></span>
        <button onclick="abrirRecurso(<?= (int)$r['id_recurso'] ?>)" class="btn btn-primary btn-sm">Leer más →</button>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <div class="card"><div class="empty-state"><div class="empty-icon">📚</div><p>No hay recursos disponibles aún. El administrador los agregará próximamente.</p></div></div>
<?php endif; ?>

<!-- Modal recurso completo — corregido: un solo atributo display -->
<div id="modalRecurso" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
  <div class="modal-box">
    <button class="modal-close" onclick="cerrarModal()" aria-label="Cerrar">✕</button>
    <div id="modalContenido"></div>
  </div>
</div>

</main>
</div>
</div>

<script>
// Pasar datos de forma segura a JS usando json_encode con escape de HTML
const recursos = <?= json_encode($recursos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function abrirRecurso(id) {
  const r = recursos.find(x => parseInt(x.id_recurso) === id);
  if (!r) return;

  // Escapar contenido usando textContent para evitar XSS
  const titulo   = document.createElement('h2');
  titulo.id      = 'modalTitulo';
  titulo.style.marginBottom = '12px';
  titulo.textContent = r.titulo;

  const fecha = document.createElement('p');
  fecha.style.cssText = 'color:var(--muted);font-size:.85rem;margin-bottom:20px;';
  fecha.textContent = '📅 ' + r.fecha_publicacion;

  const contenido = document.createElement('div');
  contenido.style.cssText = 'line-height:1.8;color:var(--ink);white-space:pre-wrap;';
  contenido.textContent = r.contenido || '';

  const box = document.getElementById('modalContenido');
  box.innerHTML = '';
  box.appendChild(titulo);
  box.appendChild(fecha);
  box.appendChild(contenido);

  document.getElementById('modalRecurso').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function cerrarModal() {
  document.getElementById('modalRecurso').classList.remove('open');
  document.body.style.overflow = '';
}

// Cerrar con Escape o click fuera
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });
document.getElementById('modalRecurso').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});
</script>
</body>
</html>
