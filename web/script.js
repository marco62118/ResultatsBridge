/* ═══════════════════════════════════════════════
   script.js  –  ResultatsBridge
   Script commun à toutes les pages
   ═══════════════════════════════════════════════ */

/* ──────────────────────────────────────────────
   1. NAVIGATION PAR ONGLETS
   Utilisé sur index.html (page d'accueil)
   ────────────────────────────────────────────── */
function initOnglets() {
    const links    = document.querySelectorAll('.tab-nav a[data-tab]');
    const sections = document.querySelectorAll('.page-section');
    if (!links.length) return;   // page sans onglets → on sort

    links.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const tab = link.dataset.tab;

            links.forEach(l => l.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));

            link.classList.add('active');
            const cible = document.getElementById(tab);
            if (cible) {
                cible.classList.add('active');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                triggerAnims(cible);
            }
        });
    });

    // Activer les animations de la section visible au chargement
    const sectionActive = document.querySelector('.page-section.active');
    if (sectionActive) triggerAnims(sectionActive);
}

/* ──────────────────────────────────────────────
   2. ANIMATIONS AU DÉFILEMENT
   Éléments portant la classe .anim deviennent
   visibles quand ils entrent dans le viewport.
   ────────────────────────────────────────────── */
function triggerAnims(container) {
    const elems = container.querySelectorAll('.anim');
    // Réinitialiser pour rejouer l'animation si on revient sur l'onglet
    elems.forEach(el => el.classList.remove('visible'));

    const obs = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.07 });

    elems.forEach(el => obs.observe(el));
}

/* Version globale pour les pages sans onglets */
function initAnims() {
    triggerAnims(document.body);
}

/* ──────────────────────────────────────────────
   3. MISE EN ÉVIDENCE DE L'ONGLET NAV COURANT
   Sur les pages autres que index.html,
   si la nav contient des liens de page (pas data-tab),
   on surligne le lien dont le href correspond
   à la page actuelle.
   ────────────────────────────────────────────── */
function initNavActive() {
    const pageCourante = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.tab-nav a:not([data-tab])').forEach(a => {
        const href = a.getAttribute('href') || '';
        if (href === pageCourante || href.endsWith('/' + pageCourante)) {
            a.classList.add('active');
        }
    });
}

/* ──────────────────────────────────────────────
   4. POINT D'ENTRÉE
   Appelé quand le DOM est prêt.
   ────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initOnglets();   // onglets index.html
    initNavActive(); // nav active sur autres pages
    initAnims();     // animations globales (pages sans onglets)
});
