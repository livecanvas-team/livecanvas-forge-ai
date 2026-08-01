document.addEventListener("DOMContentLoaded", function () {
  var header = document.querySelector("[data-houseflow-header]");
  var updateHeader = function () {
    if (header) header.classList.toggle("is-scrolled", window.scrollY > 24);
  };

  updateHeader();
  window.addEventListener("scroll", updateHeader, { passive: true });

  var targets = document.querySelectorAll("#houseflow-page .houseflow-card, #houseflow-page .houseflow-task, #houseflow-page .houseflow-quote");
  if (!("IntersectionObserver" in window) || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

  targets.forEach(function (target) { target.classList.add("houseflow-reveal"); });
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      entry.target.classList.add("is-visible");
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.12 });
  targets.forEach(function (target) { observer.observe(target); });
});
