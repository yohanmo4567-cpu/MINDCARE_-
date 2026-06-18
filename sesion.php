<?php
// ── MINDCARE+ · sesion.php ──────────────────────────────────────
// Incluir en TODOS los archivos PHP del dashboard.
// ───────────────────────────────────────────────────────────────
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.html');
    exit;
}

if (!defined('DB_HOST')) {
    define('DB_HOST',    '127.0.0.1');
    define('DB_PORT',    3306);
    define('DB_NAME',    'mindcare');
    define('DB_USER',    'root');
    define('DB_PASS',    '');
    define('DB_CHARSET', 'utf8mb4');
}

function conectarDB(): PDO {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

$uid      = (int) $_SESSION['usuario_id'];
$nombre   = htmlspecialchars($_SESSION['nombre']   ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$apellido = htmlspecialchars($_SESSION['apellido'] ?? '',         ENT_QUOTES, 'UTF-8');
$esAdmin  = (bool)($_SESSION['es_admin'] ?? false);
$iniciales = strtoupper(mb_substr($nombre, 0, 1) . mb_substr($apellido, 0, 1));

// ── ICONOS SVG ───────────────────────────────────────────────────
function svgIcon(string $name): string {
    $icons = [
        'dashboard'     => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'perfil'        => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'emociones'     => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
        'habitos'       => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
        'actividades'   => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>',
        'recursos'      => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        'estadisticas'  => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'recordatorios' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'admin'         => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>',
        'logout'        => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'bell'          => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'user'          => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'menu'          => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
    ];
    return $icons[$name] ?? '';
}

// ── SIDEBAR ──────────────────────────────────────────────────────
function renderSidebar(string $paginaActiva = ''): void {
    global $nombre, $apellido, $iniciales, $esAdmin;

    $pages = [
        'dashboard'     => ['icon' => 'dashboard',     'label' => 'Dashboard',       'href' => 'dashboard.php'],
        'perfil'        => ['icon' => 'perfil',        'label' => 'Mi Perfil',        'href' => 'perfil.php'],
        'emociones'     => ['icon' => 'emociones',     'label' => 'Control Emocional','href' => 'emociones.php'],
        'habitos'       => ['icon' => 'habitos',       'label' => 'Hábitos',          'href' => 'habitos.php'],
        'actividades'   => ['icon' => 'actividades',   'label' => 'Actividades',      'href' => 'actividades.php'],
        'recursos'      => ['icon' => 'recursos',      'label' => 'Recursos',         'href' => 'recursos.php'],
        'estadisticas'  => ['icon' => 'estadisticas',  'label' => 'Estadísticas',     'href' => 'estadisticas.php'],
        'recordatorios' => ['icon' => 'recordatorios', 'label' => 'Recordatorios',    'href' => 'recordatorios.php'],
    ];
    $adminPages = [
        'panel_admin' => ['icon' => 'admin', 'label' => 'Panel Admin', 'href' => 'panel_admin.php'],
    ];

    echo '<aside class="sidebar" id="sidebar">';

    // Brand
    echo '<div class="sidebar-brand">';
    echo '<div class="brand-mark">M+</div>';
    echo '<span>Mind<em>Care</em>+</span>';
    echo '</div>';

    // Nav
    echo '<nav class="sidebar-nav">';
    echo '<span class="nav-label">Principal</span>';
    foreach ($pages as $key => $page) {
        $active = ($paginaActiva === $key) ? ' active' : '';
        $icon   = svgIcon($page['icon']);
        echo "<a href='" . htmlspecialchars($page['href'], ENT_QUOTES) . "' class='nav-item{$active}'>{$icon} {$page['label']}</a>";
    }
    if ($esAdmin) {
        echo '<span class="nav-label">Administración</span>';
        foreach ($adminPages as $key => $page) {
            $active = ($paginaActiva === $key) ? ' active' : '';
            $icon   = svgIcon($page['icon']);
            echo "<a href='" . htmlspecialchars($page['href'], ENT_QUOTES) . "' class='nav-item{$active}'>{$icon} {$page['label']}</a>";
        }
    }
    echo '</nav>';

    // Footer
    echo '<div class="sidebar-footer"><div class="user-pill">';
    echo "<div class='user-avatar'>{$iniciales}</div>";
    echo "<div class='user-info'><strong>{$nombre} {$apellido}</strong><span>" . ($esAdmin ? 'Administrador' : 'Usuario') . "</span></div>";
    echo "<a href='logout.php' class='btn-logout' title='Cerrar sesión'>" . svgIcon('logout') . "</a>";
    echo '</div></div>';

    echo '</aside>';
}

// ── TOPBAR ───────────────────────────────────────────────────────
function renderTopbar(string $titulo, string $subtitulo = ''): void {
    $tituloSafe    = htmlspecialchars($titulo,    ENT_QUOTES, 'UTF-8');
    $subtituloSafe = htmlspecialchars($subtitulo, ENT_QUOTES, 'UTF-8');

    echo '<header class="topbar">';
    echo '<div class="flex gap-10">';
    echo '<button class="sidebar-toggle" id="sidebarToggleBtn" aria-label="Menú">' . svgIcon('menu') . '</button>';
    echo "<div class='topbar-title'><h1>{$tituloSafe}</h1>" . ($subtituloSafe ? "<p>{$subtituloSafe}</p>" : '') . '</div>';
    echo '</div>';
    echo '<div class="topbar-actions">';
    echo '<a href="recordatorios.php" class="btn btn-ghost btn-sm" title="Recordatorios">' . svgIcon('bell') . '</a>';
    echo '<a href="perfil.php" class="btn btn-ghost btn-sm">' . svgIcon('user') . ' Mi perfil</a>';
    echo '</div>';
    echo '</header>';
}

// ── CSRF ─────────────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Solicitud inválida.');
    }
}
