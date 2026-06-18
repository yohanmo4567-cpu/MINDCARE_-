const navToggle = document.querySelector(".nav-toggle");
const navMenu = document.querySelector("#nav-menu");

if (navToggle && navMenu) {
  navToggle.addEventListener("click", () => {
    const isOpen = navToggle.getAttribute("aria-expanded") === "true";
    navToggle.setAttribute("aria-expanded", String(!isOpen));
    navMenu.classList.toggle("is-open", !isOpen);
    document.body.classList.toggle("nav-open", !isOpen);
  });

  navMenu.addEventListener("click", (event) => {
    if (event.target instanceof HTMLAnchorElement) {
      navToggle.setAttribute("aria-expanded", "false");
      navMenu.classList.remove("is-open");
      document.body.classList.remove("nav-open");
    }
  });
}

const year = document.querySelector("#year");
if (year) {
  year.textContent = String(new Date().getFullYear());
}

document.querySelectorAll(".auth-form").forEach((form) => {
  const message = form.querySelector(".form-message");

  form.addEventListener("submit", (event) => {
    event.preventDefault();

    const fields = Array.from(form.querySelectorAll("input"));
    let firstInvalid = null;

    fields.forEach((field) => {
      const isValid = field.checkValidity();
      field.setAttribute("aria-invalid", String(!isValid));
      if (!isValid && !firstInvalid) {
        firstInvalid = field;
      }
    });

    if (firstInvalid) {
      message.textContent = "Revisa los campos marcados antes de continuar.";
      message.classList.add("is-error");
      firstInvalid.focus();
      return;
    }

    message.textContent = "Formulario validado correctamente. Listo para conectar con PHP y MySQL.";
    message.classList.remove("is-error");
    form.reset();
    fields.forEach((field) => field.removeAttribute("aria-invalid"));
  });
});
