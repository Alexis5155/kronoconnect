/* ══════════════════════════════════════════════════════════════
   KronoCore — Theme Manager
   Gère l'application du thème (clair/sombre/système) au chargement
   et fournit une API pour le modifier dynamiquement.
 ══════════════════════════════════════════════════════════════ */

(function() {
    'use strict';

    const html = document.documentElement;
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    function applyTheme() {
        const userTheme = html.getAttribute('data-user-theme') || 'system';
        let activeTheme = userTheme;

        if (userTheme === 'system') {
            activeTheme = mediaQuery.matches ? 'dark' : 'light';
        }

        html.setAttribute('data-theme', activeTheme);
        html.style.colorScheme = activeTheme;
    }

    // Appliquer le thème dès que le script est lu (bloque le rendu)
    applyTheme();

    // Écouter les changements du système
    mediaQuery.addEventListener('change', () => {
        if (html.getAttribute('data-user-theme') === 'system') {
            applyTheme();
        }
    });

    // API globale
    window.KronoTheme = {
        set: function(theme) {
            html.setAttribute('data-user-theme', theme);
            applyTheme();
        },
        get: function() {
            return html.getAttribute('data-user-theme') || 'system';
        }
    };

    // Gestionnaire du menu de bascule de thème
    document.addEventListener('DOMContentLoaded', function () {
        applyTheme();

        // 1. Gérer l'ouverture/fermeture des dropdowns
        const toggleBtns = document.querySelectorAll('.theme-toggle-btn');
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                const container = btn.closest('.theme-dropdown-container');
                if (!container) return;
                
                const menu = container.querySelector('.theme-dropdown-menu');
                if (!menu) return;
                
                // Fermer les autres menus
                document.querySelectorAll('.theme-dropdown-menu').forEach(m => {
                    if (m !== menu) m.classList.remove('active');
                });
                
                menu.classList.toggle('active');
            });
        });

        // 2. Fermer au clic extérieur
        document.addEventListener('click', function () {
            document.querySelectorAll('.theme-dropdown-menu').forEach(menu => {
                menu.classList.remove('active');
            });
        });

        // 3. Gérer le choix d'un thème
        const menuItems = document.querySelectorAll('.theme-dropdown-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function (e) {
                e.stopPropagation();
                const selectedTheme = item.getAttribute('data-theme-val');
                if (!selectedTheme) return;
                
                const container = item.closest('.theme-dropdown-container');
                const btn = container ? container.querySelector('.theme-toggle-btn') : null;
                const menu = container ? container.querySelector('.theme-dropdown-menu') : null;
                
                if (menu) menu.classList.remove('active');
                
                // Transition immédiate côté client
                window.KronoTheme.set(selectedTheme);
                
                // Mettre à jour la classe .active dans le dropdown
                if (container) {
                    container.querySelectorAll('.theme-dropdown-item').forEach(el => {
                        el.classList.toggle('active', el.getAttribute('data-theme-val') === selectedTheme);
                    });
                }
                
                // Persistance asynchrone
                if (btn) {
                    const url = btn.getAttribute('data-url');
                    const csrf = btn.getAttribute('data-csrf');
                    if (url) {
                        const formData = new FormData();
                        formData.append('theme', selectedTheme);
                        if (csrf) {
                            formData.append('csrf_token', csrf);
                        }
                        
                        fetch(url, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.success) {
                                console.log('[KronoTheme] Thème synchronisé :', selectedTheme);
                            }
                        })
                        .catch(err => {
                            console.warn('[KronoTheme] Erreur réseau lors de la bascule :', err);
                        });
                    }
                }
            });
        });

        // 4. Initialiser la classe active sur les items au chargement
        const initialTheme = html.getAttribute('data-user-theme') || 'system';
        document.querySelectorAll('.theme-dropdown-container').forEach(container => {
            container.querySelectorAll('.theme-dropdown-item').forEach(item => {
                item.classList.toggle('active', item.getAttribute('data-theme-val') === initialTheme);
            });
        });
    });
})();
