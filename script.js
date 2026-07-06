// script.js — comportamiento mínimo: menú móvil y manejo simple del formulario
document.addEventListener('DOMContentLoaded', function () {
  // año en footer
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // menú móvil
  const nav = document.getElementById('nav');
  const toggle = document.getElementById('navToggle');
  toggle && toggle.addEventListener('click', () => {
    if (!nav) return;
    const shown = nav.style.display === 'flex' || nav.style.display === 'block';
    nav.style.display = shown ? 'none' : 'flex';
    toggle.setAttribute('aria-expanded', String(!shown));
  });

  // manejo simple del formulario (demo)
  const form = document.getElementById('contactForm');
  const msg = document.getElementById('formMessage');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const data = new FormData(form);
      const name = data.get('name') || 'Alumno';
      // Aquí podrías integrar Formspree, Netlify Forms o tu backend
      msg.textContent = `Gracias ${name}, tu mensaje ha sido recibido (demo).`;
      form.reset();
    });
  }
});
