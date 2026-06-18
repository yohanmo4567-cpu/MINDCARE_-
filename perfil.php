<?php
require_once 'sesion.php';
$pdo = conectarDB();
$msg = ''; $msgTipo = '';

$stmtUser = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
$stmtUser->execute([$uid]);
$usuario = $stmtUser->fetch();

if (!$usuario) {
    session_destroy();
    header('Location: login.html');
    exit;
}

// Carpeta donde se guardan las fotos
define('UPLOAD_DIR', 'uploads/fotos/');
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = $_POST['accion'] ?? '';

    // ── SUBIR FOTO ───────────────────────────────────────────────
    if ($accion === 'subir_foto') {
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $file     = $_FILES['foto'];
            $maxSize  = 3 * 1024 * 1024; // 3 MB
            $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($file['size'] > $maxSize) {
                $msg = 'La imagen no puede superar 3 MB.'; $msgTipo = 'error';
            } elseif (!in_array($mimeType, $allowed)) {
                $msg = 'Solo se permiten imágenes JPG, PNG, WEBP o GIF.'; $msgTipo = 'error';
            } else {
                // Eliminar foto anterior si existe
                if (!empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil'])) {
                    unlink($usuario['foto_perfil']);
                }
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'user_' . $uid . '_' . time() . '.' . strtolower($ext);
                $destino  = UPLOAD_DIR . $fileName;

                if (move_uploaded_file($file['tmp_name'], $destino)) {
                    $pdo->prepare("UPDATE usuario SET foto_perfil = ? WHERE id_usuario = ?")
                        ->execute([$destino, $uid]);
                    $_SESSION['foto_perfil'] = $destino;
                    $usuario['foto_perfil']  = $destino;
                    $msg = '¡Foto actualizada!'; $msgTipo = 'success';
                } else {
                    $msg = 'Error al guardar la imagen.'; $msgTipo = 'error';
                }
            }
        } else {
            $msg = 'Selecciona una imagen válida.'; $msgTipo = 'error';
        }

    // ── ELIMINAR FOTO ────────────────────────────────────────────
    } elseif ($accion === 'eliminar_foto') {
        if (!empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil'])) {
            unlink($usuario['foto_perfil']);
        }
        $pdo->prepare("UPDATE usuario SET foto_perfil = NULL WHERE id_usuario = ?")
            ->execute([$uid]);
        $usuario['foto_perfil'] = null;
        $_SESSION['foto_perfil'] = null;
        $msg = 'Foto eliminada.'; $msgTipo = 'success';

    // ── ACTUALIZAR DATOS ─────────────────────────────────────────
    } elseif ($accion === 'actualizar') {
        $nombre_u   = trim($_POST['nombre']   ?? '');
        $apellido_u = trim($_POST['apellido'] ?? '');
        $edad_u     = (int)($_POST['edad']    ?? 0);

        if ($nombre_u && $apellido_u && mb_strlen($nombre_u) <= 100 && mb_strlen($apellido_u) <= 100) {
            $stmt = $pdo->prepare("UPDATE usuario SET nombre = ?, apellido = ?, edad = ? WHERE id_usuario = ?");
            $stmt->execute([$nombre_u, $apellido_u, $edad_u > 0 ? $edad_u : null, $uid]);
            $_SESSION['nombre']   = $nombre_u;
            $_SESSION['apellido'] = $apellido_u;
            $msg = '¡Perfil actualizado!'; $msgTipo = 'success';
            $usuario['nombre']   = $nombre_u;
            $usuario['apellido'] = $apellido_u;
            $usuario['edad']     = $edad_u > 0 ? $edad_u : null;
        } else {
            $msg = 'Nombre y apellido son requeridos (máx. 100 caracteres).'; $msgTipo = 'error';
        }

    // ── CAMBIAR CONTRASEÑA ───────────────────────────────────────
    } elseif ($accion === 'cambiar_password') {
        $actual    = $_POST['password_actual']    ?? '';
        $nueva     = $_POST['password_nueva']     ?? '';
        $confirmar = $_POST['password_confirmar'] ?? '';

        if (!password_verify($actual, $usuario['contraseña'])) {
            $msg = 'La contraseña actual es incorrecta.'; $msgTipo = 'error';
        } elseif (mb_strlen($nueva) < 8) {
            $msg = 'La nueva contraseña debe tener al menos 8 caracteres.'; $msgTipo = 'error';
        } elseif ($nueva !== $confirmar) {
            $msg = 'Las contraseñas nuevas no coinciden.'; $msgTipo = 'error';
        } else {
            $hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE usuario SET contraseña = ? WHERE id_usuario = ?")->execute([$hash, $uid]);
            $msg = '¡Contraseña actualizada!'; $msgTipo = 'success';
        }
    }
}

$tieneFoto = !empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Mi Perfil | MindCare+</title>
<link rel="stylesheet" href="dashboard.css"/>
<style>
.avatar-wrap {
  position: relative;
  width: 100px; height: 100px;
  margin: 0 auto 16px;
}
.avatar-img {
  width: 100px; height: 100px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--primary-light);
  box-shadow: var(--shadow);
}
.avatar-initials {
  width: 100px; height: 100px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  display: grid; place-items: center;
  font-size: 2.2rem; font-weight: 800; color: #fff;
  box-shadow: var(--shadow);
}
.avatar-edit-btn {
  position: absolute; bottom: 2px; right: 2px;
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--primary); color: #fff;
  border: 2px solid #fff;
  display: grid; place-items: center;
  cursor: pointer; font-size: .85rem;
  box-shadow: var(--shadow-sm);
  transition: background .2s;
}
.avatar-edit-btn:hover { background: var(--primary-dark); }

/* Modal foto */
.foto-modal {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.55); z-index: 200;
  align-items: center; justify-content: center;
}
.foto-modal.open { display: flex; }
.foto-modal-box {
  background: #fff; border-radius: var(--radius);
  padding: 32px; width: 90%; max-width: 420px;
  position: relative;
}
.foto-modal-close {
  position: absolute; top: 14px; right: 16px;
  background: none; border: none; font-size: 1.3rem;
  color: var(--muted); cursor: pointer;
}
.foto-modal-close:hover { color: var(--ink); }

/* Preview */
.foto-preview-wrap { text-align: center; margin: 16px 0; }
.foto-preview {
  width: 120px; height: 120px; border-radius: 50%;
  object-fit: cover; border: 3px solid var(--primary-light);
  margin: 0 auto; display: none;
}
.foto-preview.visible { display: block; }

/* Drop zone */
.drop-zone {
  border: 2px dashed var(--line); border-radius: var(--radius-sm);
  padding: 28px 16px; text-align: center;
  cursor: pointer; transition: all .2s;
  background: var(--surface-soft);
}
.drop-zone:hover, .drop-zone.dragover {
  border-color: var(--primary); background: var(--primary-light);
}
.drop-zone input[type="file"] { display: none; }
.drop-zone-icon { font-size: 2rem; margin-bottom: 8px; }
.drop-zone p { font-size: .85rem; color: var(--muted); margin: 0; }
.drop-zone strong { color: var(--primary); }
</style>
</head>
<body>
<div class="app-shell">
<?php renderSidebar('perfil'); ?>
<div class="main-content">
<?php renderTopbar('Mi Perfil 👤', 'Gestiona tu información personal'); ?>
<main class="page-body">

<?php if ($msg): ?>
<div class="alert alert-<?= $msgTipo === 'success' ? 'success' : 'error' ?> show"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="grid-2">

  <!-- ── COLUMNA IZQUIERDA: foto + datos ── -->
  <div class="card">

    <!-- Avatar -->
    <div style="text-align:center; padding: 20px 0 24px;">
      <div class="avatar-wrap">
        <?php if ($tieneFoto): ?>
          <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>" alt="Foto de perfil" class="avatar-img"/>
        <?php else: ?>
          <div class="avatar-initials"><?= htmlspecialchars($iniciales) ?></div>
        <?php endif; ?>
        <button type="button" class="avatar-edit-btn" onclick="abrirModalFoto()" title="Cambiar foto">✏️</button>
      </div>

      <div style="font-size:1.2rem;font-weight:700;"><?= htmlspecialchars($usuario['nombre']) ?> <?= htmlspecialchars($usuario['apellido']) ?></div>
      <div style="color:var(--muted);font-size:.85rem;margin-top:2px;"><?= htmlspecialchars($usuario['correo']) ?></div>
      <div style="margin-top:8px;">
        <span class="badge <?= $esAdmin ? 'badge-accent' : 'badge-primary' ?>">
          <?= $esAdmin ? '⚙️ Administrador' : '👤 Usuario' ?>
        </span>
      </div>

      <?php if ($tieneFoto): ?>
        <form method="POST" style="margin-top:10px;" onsubmit="return confirm('¿Eliminar tu foto de perfil?')">
          <input type="hidden" name="accion" value="eliminar_foto"/>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
          <button type="submit" class="btn btn-sm btn-danger">🗑 Eliminar foto</button>
        </form>
      <?php endif; ?>
    </div>

    <hr style="border:none;border-top:1px solid var(--line);margin-bottom:20px;"/>

    <!-- Formulario datos -->
    <form method="POST">
      <input type="hidden" name="accion" value="actualizar"/>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-input" value="<?= htmlspecialchars($usuario['nombre']) ?>" required maxlength="100"/>
        </div>
        <div class="form-group">
          <label class="form-label">Apellido</label>
          <input type="text" name="apellido" class="form-input" value="<?= htmlspecialchars($usuario['apellido']) ?>" required maxlength="100"/>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" class="form-input" value="<?= htmlspecialchars($usuario['correo']) ?>" disabled style="opacity:.6;cursor:not-allowed;"/>
        <small style="color:var(--muted);font-size:.75rem;">El correo no se puede cambiar.</small>
      </div>
      <div class="form-group">
        <label class="form-label">Edad</label>
        <input type="number" name="edad" class="form-input" value="<?= (int)($usuario['edad'] ?? 0) ?: '' ?>" min="13" max="120"/>
      </div>
      <div class="form-group">
        <label class="form-label">Miembro desde</label>
        <input type="text" class="form-input" value="<?= $usuario['fecha_registro'] ? date('d/m/Y', strtotime($usuario['fecha_registro'])) : 'N/A' ?>" disabled style="opacity:.6;cursor:not-allowed;"/>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">💾 Guardar cambios</button>
    </form>
  </div>

  <!-- ── COLUMNA DERECHA: contraseña + logout ── -->
  <div>
    <div class="card mb-20">
      <div class="card-header"><div class="card-title">🔒 Cambiar contraseña</div></div>
      <form method="POST">
        <input type="hidden" name="accion" value="cambiar_password"/>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>
        <div class="form-group">
          <label class="form-label">Contraseña actual</label>
          <input type="password" name="password_actual" class="form-input" required autocomplete="current-password"/>
        </div>
        <div class="form-group">
          <label class="form-label">Nueva contraseña</label>
          <input type="password" name="password_nueva" class="form-input" required minlength="8" autocomplete="new-password"/>
          <small style="color:var(--muted);font-size:.75rem;">Mínimo 8 caracteres.</small>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar nueva contraseña</label>
          <input type="password" name="password_confirmar" class="form-input" required autocomplete="new-password"/>
        </div>
        <button type="submit" class="btn btn-ghost" style="width:100%">🔒 Cambiar contraseña</button>
      </form>
    </div>

    <div class="card" style="border-color:var(--red-light);">
      <div class="card-header"><div class="card-title" style="color:var(--red);">⚠️ Zona de riesgo</div></div>
      <p style="font-size:.85rem;color:var(--muted);margin-bottom:16px;">Al cerrar sesión perderás acceso hasta volver a iniciar.</p>
      <a href="logout.php" class="btn btn-danger" style="width:100%;display:flex;justify-content:center;">🚪 Cerrar sesión</a>
    </div>
  </div>
</div>

<!-- ── MODAL FOTO ── -->
<div class="foto-modal" id="fotoModal">
  <div class="foto-modal-box">
    <button class="foto-modal-close" onclick="cerrarModalFoto()">✕</button>
    <h3 style="margin-bottom:16px;font-size:1rem;">📷 Cambiar foto de perfil</h3>

    <form method="POST" enctype="multipart/form-data" id="fotoForm">
      <input type="hidden" name="accion" value="subir_foto"/>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"/>

      <!-- Drop zone -->
      <div class="drop-zone" id="dropZone" onclick="document.getElementById('fotoInput').click()">
        <input type="file" name="foto" id="fotoInput" accept="image/jpeg,image/png,image/webp,image/gif"/>
        <div class="drop-zone-icon">🖼️</div>
        <p><strong>Haz clic</strong> o arrastra tu foto aquí</p>
        <p style="margin-top:4px;font-size:.75rem;">JPG, PNG, WEBP o GIF · Máx. 3 MB</p>
      </div>

      <!-- Preview -->
      <div class="foto-preview-wrap">
        <img id="fotoPreview" class="foto-preview" src="" alt="Preview"/>
        <p id="fotoNombre" style="font-size:.78rem;color:var(--muted);margin-top:6px;"></p>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;" id="btnSubirFoto" disabled>
        📤 Subir foto
      </button>
    </form>
  </div>
</div>

<script src="dashboard.js"></script>
<script>
// ── Modal foto ────────────────────────────────────────────────
function abrirModalFoto() {
  document.getElementById('fotoModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function cerrarModalFoto() {
  document.getElementById('fotoModal').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('fotoModal').addEventListener('click', function(e) {
  if (e.target === this) cerrarModalFoto();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModalFoto(); });

// ── Preview de imagen ─────────────────────────────────────────
const fotoInput   = document.getElementById('fotoInput');
const fotoPreview = document.getElementById('fotoPreview');
const fotoNombre  = document.getElementById('fotoNombre');
const btnSubir    = document.getElementById('btnSubirFoto');
const dropZone    = document.getElementById('dropZone');

function mostrarPreview(file) {
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    fotoPreview.src = e.target.result;
    fotoPreview.classList.add('visible');
    fotoNombre.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
    btnSubir.disabled = false;
  };
  reader.readAsDataURL(file);
}

fotoInput.addEventListener('change', () => mostrarPreview(fotoInput.files[0]));

// Drag & drop
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    fotoInput.files = dt.files;
    mostrarPreview(file);
  }
});
</script>
</body>
</html>
