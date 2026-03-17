/**
 * utils.js — Utilitaires UI globaux
 */

// ── Toggle mot de passe (œil) ────────────────────────────────────────────────
// Transforme automatiquement chaque <input type="password"> en Bootstrap
// input-group avec un bouton œil attaché à droite du champ.
(function () {
    const SVG_EYE = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
    </svg>`;

    const SVG_EYE_OFF = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
                 a18.45 18.45 0 0 1 5.06-5.94"/>
        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
                 a18.5 18.5 0 0 1-2.16 3.19"/>
        <line x1="1" y1="1" x2="23" y2="23"/>
    </svg>`;

    document.querySelectorAll('input[type="password"]').forEach(input => {
        // Wrapper Bootstrap input-group
        const group = document.createElement('div');
        group.className = 'input-group';

        input.parentNode.insertBefore(group, input);
        group.appendChild(input);

        // Bouton œil en input-group-text attaché à droite
        const span = document.createElement('span');
        span.className = 'input-group-text pw-toggle-btn';
        span.setAttribute('role', 'button');
        span.setAttribute('aria-label', 'Afficher le mot de passe');
        span.setAttribute('tabindex', '0');
        span.innerHTML = SVG_EYE_OFF;
        group.appendChild(span);

        let visible = false;

        const toggle = () => {
            visible = !visible;
            input.type = visible ? 'text' : 'password';
            span.innerHTML = visible ? SVG_EYE : SVG_EYE_OFF;
            span.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            span.classList.toggle('active', visible);
        };

        span.addEventListener('click', toggle);
        span.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
    });
})();
