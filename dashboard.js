/**
 * MindCare+ · dashboard.js
 * JavaScript global para todas las páginas del área de usuario.
 */

// ── SIDEBAR TOGGLE (móvil) ───────────────────────────────────────
(function () {
  const btn     = document.getElementById('sidebarToggleBtn');
  const sidebar = document.getElementById('sidebar');
  if (!btn || !sidebar) return;

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebar.classList.toggle('open');
    btn.setAttribute('aria-expanded', sidebar.classList.contains('open'));
  });

  // Cerrar al hacer clic fuera
  document.addEventListener('click', (e) => {
    if (sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) &&
        e.target !== btn) {
      sidebar.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
    }
  });

  // Cerrar con Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      sidebar.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
    }
  });
})();

// ── HABIT CHECK (visual, solo en dashboard) ──────────────────────
document.querySelectorAll('.habit-check').forEach((btn) => {
  btn.addEventListener('click', () => btn.classList.toggle('done'));
});

// ── ALERTAS: auto-ocultar después de 5 s ────────────────────────
document.querySelectorAll('.alert.show').forEach((el) => {
  setTimeout(() => {
    el.style.transition = 'opacity .4s';
    el.style.opacity    = '0';
    setTimeout(() => el.remove(), 400);
  }, 5000);
});

// ── MOOD SELECTOR (emociones.php) ───────────────────────────────
function toggleMood(btn) {
  btn.classList.toggle('selected');
  const hidden = document.getElementById('hiddenEstados');
  if (!hidden) return;
  hidden.innerHTML = '';
  document.querySelectorAll('.mood-btn.selected').forEach((b) => {
    const inp   = document.createElement('input');
    inp.type    = 'hidden';
    inp.name    = 'estados[]';
    inp.value   = b.dataset.id;
    hidden.appendChild(inp);
  });
}

// ── PROGRESO ANIMADO ────────────────────────────────────────────
document.querySelectorAll('.progress-fill').forEach((bar) => {
  const target = bar.style.width;
  bar.style.width = '0%';
  requestAnimationFrame(() => {
    setTimeout(() => { bar.style.width = target; }, 80);
  });
});

// ── CONFIRM ELIMINAR (formularios con data-confirm) ─────────────
document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (e) => {
    if (!confirm(form.dataset.confirm)) e.preventDefault();
  });
});

// ── MODAL (recursos.php) ─────────────────────────────────────────
window.cerrarModal = function () {
  const overlay = document.getElementById('modalRecurso');
  if (overlay) {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }
};

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') window.cerrarModal?.();
});

const modalOverlay = document.getElementById('modalRecurso');
if (modalOverlay) {
  modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) window.cerrarModal();
  });
}

// ── TABLA: resaltar fila activa al hover ─────────────────────────
document.querySelectorAll('table tbody tr').forEach((row) => {
  row.style.cursor = 'default';
});

// ── TOPBAR: marcar página actual en nav ─────────────────────────
(function () {
  const current = window.location.pathname.split('/').pop();
  document.querySelectorAll('.nav-item').forEach((link) => {
    const href = link.getAttribute('href');
    if (href === current) link.classList.add('active');
  });
})();
