<?php
/**
 * MindCare+ · registro.php
 * ─────────────────────────────────────────────────────────────
 * Registra un nuevo usuario en la base de datos MySQL (XAMPP).
 */

declare(strict_types=1);

// ── 1. CONFIGURACIÓN ────────────────────────────────────────────
if (!defined('DB_HOST')) {
    define('DB_HOST',    '127.0.0.1');
    define('DB_PORT',    3306);
    define('DB_NAME',    'mindcare');
    define('DB_USER',    'root');
    define('DB_PASS',    '');
    define('DB_CHARSET', 'utf8mb4');
}

define('REDIRECT_REGISTRO',    'registro.html');
define('REDIRECT_LOGIN',       'login.html');
define('MIN_PASSWORD_LENGTH',  8);
define('MIN_EDAD',             13);
define('MAX_EDAD',             120);

// ── 2. CABECERAS Y SESIÓN ────────────────────────────────────────
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

// ── 3. SOLO POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . REDIRECT_REGISTRO);
    exit;
}

// ── 4. FUNCIONES ─────────────────────────────────────────────────
function conectarDB(): PDO {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

function redirigirConError(string $codigo): never {
    header('Location: ' . REDIRECT_REGISTRO . '?error=' . urlencode($codigo));
    exit;
}

function limpiarTexto(string $valor): string {
    return htmlspecialchars(strip_tags(trim($valor)), ENT_QUOTES, 'UTF-8');
}

// ── 5. INPUTS ─────────────────────────────────────────────────────
$nombre   = limpiarTexto($_POST['nombre']   ?? '');
$apellido = limpiarTexto($_POST['apellido'] ?? '');
$email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
$edadRaw  = trim($_POST['edad'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm']  ?? '';

// ── 6. VALIDACIONES ───────────────────────────────────────────────
if (empty($nombre) || empty($apellido) || empty($email) || empty($password) || empty($confirm)) {
    redirigirConError('campos');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirigirConError('campos');
}

if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
    redirigirConError('campos');
}

if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
    redirigirConError('campos');
}

// CORRECCIÓN: comparación directa de contraseñas (no hash_equals para texto plano)
if ($password !== $confirm) {
    redirigirConError('contrasenas');
}

$edad = null;
if ($edadRaw !== '') {
    $edadInt = filter_var($edadRaw, FILTER_VALIDATE_INT);
    if ($edadInt === false || $edadInt < MIN_EDAD || $edadInt > MAX_EDAD) {
        redirigirConError('edad_invalida');
    }
    $edad = $edadInt;
}

// ── 7. BASE DE DATOS ──────────────────────────────────────────────
try {
    $pdo = conectarDB();

    $stmtCheck = $pdo->prepare('SELECT id_usuario FROM usuario WHERE correo = :correo LIMIT 1');
    $stmtCheck->execute([':correo' => $email]);

    if ($stmtCheck->fetch() !== false) {
        redirigirConError('correo_existe');
    }

    $hashPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    if ($hashPassword === false) {
        error_log('[MindCare+ Registro] Error al generar hash de contraseña.');
        redirigirConError('servidor');
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO usuario (nombre, apellido, correo, contraseña, fecha_registro, edad)
         VALUES (:nombre, :apellido, :correo, :contrasena, CURDATE(), :edad)'
    );
    $stmtInsert->execute([
        ':nombre'     => $nombre,
        ':apellido'   => $apellido,
        ':correo'     => $email,
        ':contrasena' => $hashPassword,
        ':edad'       => $edad,
    ]);

    $nuevoId = (int) $pdo->lastInsertId();
    error_log(sprintf('[MindCare+ Registro] Nuevo usuario ID: %d, Correo: %s', $nuevoId, $email));

    header('Location: ' . REDIRECT_LOGIN . '?registro=ok');
    exit;

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        redirigirConError('correo_existe');
    }
    error_log('[MindCare+ Registro] Error BD: ' . $e->getMessage());
    redirigirConError('servidor');
}
