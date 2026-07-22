// ============================================================
//  SangGestion — script.js
//  Interactions UI : sidebar, toasts, modals, confirmations,
//  recherche live, graphiques Chart.js (si présent)
// ============================================================

/* ── Sidebar mobile ─────────────────────────────────────── */
function toggleSidebar() {
    document.getElementById('sidebar')?.classList.toggle('ouverte');
}

/* ── Toasts ─────────────────────────────────────────────── */
function afficherToast(message, type = 'success', duree = 3500) {
    const ic = { success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️' };
    const conteneur = document.getElementById('toastConteneur');
    if (!conteneur) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${ic[type] || ic.info}</span> ${message}`;
    conteneur.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all .3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duree);
}

// Afficher un toast depuis les params URL (après redirect PHP)
(function () {
    const params = new URLSearchParams(window.location.search);
    const msg  = params.get('msg');
    const type = params.get('type') || 'success';
    if (msg) {
        afficherToast(decodeURIComponent(msg), type);
        // Nettoyer l'URL sans recharger
        const url = new URL(window.location.href);
        url.searchParams.delete('msg');
        url.searchParams.delete('type');
        window.history.replaceState({}, '', url);
    }
})();

/* ── Modals ─────────────────────────────────────────────── */
function ouvrirModal(id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}

function fermerModal(id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
}

// Fermer au clic sur l'overlay
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// Fermer avec Échap
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.style.display = 'none';
            document.body.style.overflow = '';
        });
    }
});

/* ── Confirmation de suppression ─────────────────────────── */
function confirmerSuppression(url, message = 'Confirmer la suppression de cet enregistrement ?') {
    const modal = document.getElementById('modalConfirm');
    if (!modal) {
        // Fallback natif
        if (confirm(message)) window.location.href = url;
        return;
    }
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmOui').onclick = () => { window.location.href = url; };
    ouvrirModal('modalConfirm');
}

/* ── Recherche / filtre live sur tableau ─────────────────── */
function filtrerTableau(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', () => {
        const val = input.value.toLowerCase().trim();
        table.querySelectorAll('tbody tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(val) ? '' : 'none';
        });
    });
}

/* ── Onglets (auth + autres) ─────────────────────────────── */
function initOnglets(groupeClass) {
    document.querySelectorAll(`.${groupeClass} [data-onglet]`).forEach(btn => {
        btn.addEventListener('click', () => {
            const cible = btn.dataset.onglet;

            // Désactiver tous les onglets du groupe
            btn.closest(`.${groupeClass}`)
               .querySelectorAll('[data-onglet]')
               .forEach(b => b.classList.remove('actif'));
            btn.classList.add('actif');

            // Masquer tous les panneaux
            document.querySelectorAll(`[data-panneau]`).forEach(p => {
                p.style.display = 'none';
            });

            // Afficher le bon
            const panneau = document.querySelector(`[data-panneau="${cible}"]`);
            if (panneau) {
                panneau.style.display = 'block';
                panneau.style.animation = 'glissement .25s ease';
            }
        });
    });
}

/* ── Validation formulaire donneur ──────────────────────── */
function validerFormDonneur(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', e => {
        let ok = true;

        // Téléphone (optionnel, mais format si renseigné)
        const tel = form.querySelector('[name="telephone"]');
        if (tel && tel.value && !/^[\d\s\+\-\(\)]{7,20}$/.test(tel.value)) {
            marquerErreur(tel, 'Numéro de téléphone invalide');
            ok = false;
        }

        if (!ok) e.preventDefault();
    });
}

function marquerErreur(champ, msg) {
    champ.classList.add('erreur');
    let err = champ.parentElement.querySelector('.message-erreur');
    if (!err) {
        err = document.createElement('span');
        err.className = 'message-erreur';
        champ.parentElement.appendChild(err);
    }
    err.textContent = '⚠ ' + msg;

    champ.addEventListener('input', () => {
        champ.classList.remove('erreur');
        err.remove();
    }, { once: true });
}

/* ── Jauge stock (animation au chargement) ───────────────── */
function animerJauges() {
    document.querySelectorAll('.jauge-barre[data-pct]').forEach(barre => {
        const pct = parseFloat(barre.dataset.pct) || 0;
        barre.style.width = '0%';
        requestAnimationFrame(() => {
            setTimeout(() => { barre.style.width = pct + '%'; }, 80);
        });
    });
}

/* ── Compteurs animés (stat-card) ────────────────────────── */
function animerCompteurs() {
    document.querySelectorAll('.stat-valeur[data-val]').forEach(el => {
        const cible = parseInt(el.dataset.val, 10);
        if (isNaN(cible)) return;
        let courant = 0;
        const pas   = Math.ceil(cible / 40);
        const timer = setInterval(() => {
            courant = Math.min(courant + pas, cible);
            el.textContent = courant.toLocaleString('fr-FR');
            if (courant >= cible) clearInterval(timer);
        }, 20);
    });
}

/* ── Graphique dons par mois (Chart.js) ─────────────────── */
function initGraphiqueDons(canvas, labels, data) {
    if (!canvas || typeof Chart === 'undefined') return;
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Dons enregistrés',
                data,
                backgroundColor: 'rgba(192,21,42,.15)',
                borderColor: '#C0152A',
                borderWidth: 2,
                borderRadius: 6,
                hoverBackgroundColor: 'rgba(192,21,42,.3)',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1A1F2E',
                    titleFont: { size: 11 },
                    bodyFont: { size: 12 },
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: {
                    beginAtZero: true,
                    grid: { color: '#E2E8F0' },
                    ticks: { font: { size: 11 }, stepSize: 1 }
                }
            }
        }
    });
}

/* ── Graphique répartition stocks (doughnut) ─────────────── */
function initGraphiqueStocks(canvas, labels, data) {
    if (!canvas || typeof Chart === 'undefined') return;
    const couleurs = [
        '#E53E3E','#C53030','#3182CE','#2B6CB0',
        '#805AD5','#6B46C1','#38A169','#276749'
    ];
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: couleurs.slice(0, labels.length),
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { size: 11 }, padding: 10, usePointStyle: true }
                },
                tooltip: { backgroundColor: '#1A1F2E' }
            }
        }
    });
}

/* ── Afficher/masquer mot de passe ───────────────────────── */
function toggleMotDePasse(inputId, btnId) {
    const input = document.getElementById(inputId);
    const btn   = document.getElementById(btnId);
    if (!input || !btn) return;
    btn.addEventListener('click', () => {
        input.type = input.type === 'password' ? 'text' : 'password';
        btn.textContent = input.type === 'password' ? '👁' : '🙈';
    });
}

/* ── Init globale ────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    animerCompteurs();
    animerJauges();
    initOnglets('auth-onglets');

    // Recherche dans les tableaux
    filtrerTableau('rechercheTableau', 'tableauPrincipal');

    // Toggle sidebar mobile
    document.getElementById('btnMenuMobile')?.addEventListener('click', toggleSidebar);

    // Validation formulaires
    validerFormDonneur('formDonneur');

    // Toggle mot de passe
    toggleMotDePasse('motDePasse', 'toggleMdp');
    toggleMotDePasse('motDePasseConfirm', 'toggleMdpConfirm');

    // Fermer les modals via bouton [data-fermer-modal]
    document.querySelectorAll('[data-fermer-modal]').forEach(btn => {
        btn.addEventListener('click', () => fermerModal(btn.dataset.fermerModal));
    });

    // Ouvrir les modals via bouton [data-ouvrir-modal]
    document.querySelectorAll('[data-ouvrir-modal]').forEach(btn => {
        btn.addEventListener('click', () => ouvrirModal(btn.dataset.ouvrirModal));
    });
});
