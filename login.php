<?php
// ── CONFIGURACIÓN ────────────────────────────────────────────────
define('DB_HOST',    '127.0.0.1');
define('DB_PORT',    3306);
define('DB_NAME',    'mindcare');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

define('REDIRECT_LOGIN',           'login.html');
define('REDIRECT_DASHBOARD_USER',  'dashboard.php');
define('REDIRECT_DASHBOARD_ADMIN', 'panel_admin.php');

// ── SESIÓN ───────────────────────────────────────────────────────
session_start();

// Si ya hay sesión activa, redirigir directo
if (isset($_SESSION['usuario_id'])) {
    $destino = (!empty($_SESSION['es_admin'])) ? REDIRECT_DASHBOARD_ADMIN : REDIRECT_DASHBOARD_USER;
    header('Location: ' . $destino);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . REDIRECT_LOGIN);
    exit;
}

// ── FUNCIONES ────────────────────────────────────────────────────
function conectarDB() {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

function redirigirConError($codigo) {
    header('Location: ' . REDIRECT_LOGIN . '?error=' . urlencode($codigo));
    exit;
}

// ── INPUTS ───────────────────────────────────────────────────────
$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    redirigirConError('campos');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirigirConError('campos');
}

// ── AUTENTICACIÓN ────────────────────────────────────────────────
try {
    $pdo = conectarDB();

    // Buscar usuario por correo
    $stmt = $pdo->prepare('SELECT id_usuario, nombre, apellido, correo, contraseña FROM usuario WHERE correo = ? LIMIT 1');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    // Verificar que existe y la contraseña es correcta
    if (!$usuario || !password_verify($password, $usuario['contraseña'])) {
        redirigirConError('credenciales');
    }

    // Verificar si es administrador
    $stmtAdmin = $pdo->prepare('SELECT id_admin, nivel_acceso, rol FROM administrador WHERE id_usuario = ? LIMIT 1');
    $stmtAdmin->execute([$usuario['id_usuario']]);
    $admin   = $stmtAdmin->fetch();
    $esAdmin = ($admin !== false);

    // Iniciar sesión
    session_regenerate_id(true);
    $_SESSION['usuario_id']     = $usuario['id_usuario'];
    $_SESSION['nombre']         = $usuario['nombre'];
    $_SESSION['apellido']       = $usuario['apellido'];
    $_SESSION['correo']         = $usuario['correo'];
    $_SESSION['es_admin']       = $esAdmin;
    $_SESSION['autenticado_en'] = time();
    $_SESSION['csrf_token']     = bin2hex(random_bytes(32));

    if ($esAdmin && $admin) {
        $_SESSION['admin_id']     = $admin['id_admin'];
        $_SESSION['nivel_acceso'] = $admin['nivel_acceso'];
        $_SESSION['rol']          = $admin['rol'];

        // Registrar auditoría
        try {
            $pdo->prepare('INSERT INTO auditoria (id_admin, accion, fecha_accion) VALUES (?, ?, NOW())')
                ->execute([$admin['id_admin'], 'LOGIN: Inicio de sesión de administrador']);
        } catch (Exception $e) {
            // No interrumpir el login si falla la auditoría
        }
    }

    $destino = $esAdmin ? REDIRECT_DASHBOARD_ADMIN : REDIRECT_DASHBOARD_USER;
    header('Location: ' . $destino);
    exit;

} catch (PDOException $e) {
    // Para depurar, descomenta la siguiente línea:
    // die('Error BD: ' . $e->getMessage());
    error_log('[MindCare+] Error BD login: ' . $e->getMessage());
    redirigirConError('servidor');
}
