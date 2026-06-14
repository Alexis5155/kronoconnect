/* ══════════════════════════════════════════════════════════════
   KronoConnectCore — JS principal
   Sidebar mobile, flash auto-dismiss, CSRF auto-inject
══════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    // ── Sidebar mobile ────────────────────────────────────────

    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const hamburger = document.getElementById('hamburger');

    function openSidebar() {
        sidebar?.classList.add('open');
        overlay?.classList.add('visible');
        hamburger?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('visible');
        hamburger?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    hamburger?.addEventListener('click', () => {
        sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeSidebar();
    });

    // ── Flash messages auto-dismiss ───────────────────────────
    // Les alertes dans la topbar disparaissent après 4s

    document.querySelectorAll('.krono-topbar .krono-alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity    = '0';
            alert.style.transform  = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 400);
        }, 4000);
    });

    // ── CSRF auto-inject ──────────────────────────────────────
    // Ajoute automatiquement le token CSRF à tous les fetch() POST

    const originalFetch = window.fetch;
    window.fetch = function (url, options = {}) {
        if (options.method && options.method.toUpperCase() === 'POST') {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                options.headers = options.headers || {};
                options.headers['X-CSRF-Token'] = csrfMeta.content;
            }
        }
        return originalFetch(url, options);
    };

    // ── Système de Modal de Confirmation ─────────────────────
    
    window.KronoConnect = window.KronoConnect || {};

    /**
     * Affiche une modal d'alerte stylisée
     * @param {string} message 
     * @param {Object} options 
     */
    window.KronoConnect.alert = function (message, options = {}) {
        const modal = document.getElementById('KronoConnectConfirmModal');
        const titleEl = document.getElementById('kConfirmTitle');
        const textEl = document.getElementById('kConfirmText');
        const btnConfirm = document.getElementById('kConfirmBtn');
        const btnCancel = document.getElementById('kConfirmCancel');
        const iconBox = document.getElementById('kConfirmIconBox');
        const icon = document.getElementById('kConfirmIcon');

        if (!modal) return alert(message);

        titleEl.textContent = options.title || 'Information';
        textEl.innerHTML = message.replace(/\n/g, '<br>');
        btnCancel.style.display = 'none'; // Masquer annuler pour une alerte
        
        const type = options.type || 'info';
        iconBox.className = `modal-icon-box modal-icon-box--${type}`;
        icon.className = `bi bi-${type === 'danger' ? 'trash-fill' : (type === 'warning' ? 'exclamation-triangle-fill' : 'info-circle-fill')}`;
        
        btnConfirm.className = `btn-krono btn-krono--primary`;
        btnConfirm.textContent = options.confirmText || 'OK';

        return new Promise((resolve) => {
            const onConfirm = () => {
                modal.classList.remove('is-open');
                btnConfirm.removeEventListener('click', onConfirm);
                btnCancel.style.display = ''; // Restaurer pour les prochains appels
                resolve();
            };
            btnConfirm.addEventListener('click', onConfirm);
            modal.classList.add('is-open');
        });
    };

    /**
     * Affiche une modal de confirmation stylisée
     * @param {string} message - Le message à afficher
     * @param {Object} options - Options (title, type: 'warning'|'danger'|'info')
     * @returns {Promise<boolean>} - Résout true si confirmé, false si annulé
     */
    window.KronoConnect.confirm = function (message, options = {}) {
        const modal = document.getElementById('KronoConnectConfirmModal');
        const titleEl = document.getElementById('kConfirmTitle');
        const textEl = document.getElementById('kConfirmText');
        const btnConfirm = document.getElementById('kConfirmBtn');
        const btnCancel = document.getElementById('kConfirmCancel');
        const iconBox = document.getElementById('kConfirmIconBox');
        const icon = document.getElementById('kConfirmIcon');

        if (!modal) return Promise.resolve(window.confirm(message));

        // Configuration
        titleEl.textContent = options.title || 'Confirmation';
        textEl.innerHTML = message.replace(/\n/g, '<br>');
        btnCancel.style.display = ''; // Assurer que le bouton Annuler est visible
        
        const type = options.type || 'warning';
        iconBox.className = `modal-icon-box modal-icon-box--${type}`;
        icon.className = `bi bi-${type === 'danger' ? 'trash-fill' : (type === 'warning' ? 'exclamation-triangle-fill' : 'info-circle-fill')}`;
        
        btnConfirm.className = `btn-krono btn-krono--${type === 'danger' ? 'danger' : 'primary'}`;
        btnConfirm.textContent = options.confirmText || 'Confirmer';

        return new Promise((resolve) => {
            const onConfirm = () => {
                cleanup();
                resolve(true);
            };
            const onCancel = () => {
                cleanup();
                resolve(false);
            };
            const cleanup = () => {
                modal.classList.remove('is-open');
                btnConfirm.removeEventListener('click', onConfirm);
                btnCancel.removeEventListener('click', onCancel);
                modal.removeEventListener('click', onOutsideClick);
            };
            const onOutsideClick = (e) => { if (e.target === modal) onCancel(); };

            btnConfirm.addEventListener('click', onConfirm);
            btnCancel.addEventListener('click', onCancel);
            modal.addEventListener('click', onOutsideClick);
            
            modal.classList.add('is-open');
        });
    };

    // ── Confirmations data-confirm ────────────────────────────
    // Intercepte les clics sur les éléments ayant data-confirm pour utiliser la nouvelle modal

    document.addEventListener('click', async function (e) {
        const el = e.target.closest('[data-confirm]');
        if (!el) return;

        // Si l'attribut data-confirmed est présent, on laisse passer (déjà validé)
        if (el.dataset.confirmed === 'true') return;

        e.preventDefault();
        e.stopImmediatePropagation();

        const msg = el.dataset.confirm || 'Confirmer cette action ?';
        const type = el.classList.contains('btn-krono--danger') ? 'danger' : 'warning';
        
        const confirmed = await window.KronoConnect.confirm(msg, { 
            title: el.title || 'Confirmation',
            type: type 
        });

        if (confirmed) {
            el.dataset.confirmed = 'true';
            if (el.tagName === 'FORM') {
                el.submit();
            } else {
                el.click(); // Re-déclenche le clic
            }
            delete el.dataset.confirmed; // Nettoyage immédiat pour les clics futurs
        }
    });

    // ── Thème (Clair / Sombre / Système) ─────────────────────

    const html = document.documentElement;

    function updateTheme() {
        const userTheme = html.getAttribute('data-user-theme') || 'system';
        if (userTheme === 'system') {
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            html.setAttribute('data-theme', systemTheme);
        } else {
            html.setAttribute('data-theme', userTheme);
        }
    }

    // Écoute les changements de thème système
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (html.getAttribute('data-user-theme') === 'system') {
            updateTheme();
        }
    });

    // Initialisation
    updateTheme();

    // Changement dynamique (aperçu live)
    document.addEventListener('change', (e) => {
        if (e.target.name === 'theme') {
            html.setAttribute('data-user-theme', e.target.value);
            updateTheme();
        }
    });

    // ── Détection de changements (Unsaved changes) ───────────

    const floatingBar = document.getElementById('floatingUnsavedChanges');
    const btnSave     = document.getElementById('btnSaveFloating');
    const footer      = document.querySelector('.krono-footer');
    const forms       = document.querySelectorAll('form[data-unsaved-detection]');

    if (floatingBar && forms.length > 0) {
        
        // 1. Détection des changements et comparaison d'état
        forms.forEach(form => {
            // Capture de l'état initial (serialize)
            const getFormState = (f) => JSON.stringify(Object.fromEntries(new FormData(f).entries()));
            const initialState = getFormState(form);

            const checkDirty = () => {
                const currentState = getFormState(form);
                if (initialState !== currentState) {
                    form.classList.add('is-dirty');
                    floatingBar.classList.add('visible');
                } else {
                    form.classList.remove('is-dirty');
                    // On ne cache la barre que si AUCUN formulaire n'est "sale"
                    if (!document.querySelector('form.is-dirty')) {
                        floatingBar.classList.remove('visible');
                    }
                }
            };

            // Debounce simple pour éviter de recalculer à chaque milliseconde
            let timeout = null;
            const debouncedCheck = () => {
                clearTimeout(timeout);
                timeout = setTimeout(checkDirty, 150);
            };

            form.addEventListener('input', debouncedCheck);
            form.addEventListener('change', checkDirty); // Pour les radios/checkboxes (immédiat)
        });

        // 2. Action du bouton "Enregistrer" universel
        btnSave?.addEventListener('click', () => {
            const dirtyForm = document.querySelector('form.is-dirty');
            if (dirtyForm) {
                // Déclencher le bouton submit réel du formulaire (pour le loading, validation HTML5, etc.)
                const submitBtn = dirtyForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.click();
                else dirtyForm.submit();
            }
        });

        // 3. Positionnement intelligent au-dessus du footer
        if (footer) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Le footer est visible, on remonte la barre
                        const footerHeight = footer.offsetHeight;
                        floatingBar.style.bottom = (footerHeight + 20) + 'px';
                    } else {
                        // Le footer n'est pas visible
                        floatingBar.style.bottom = '2rem';
                    }
                });
            }, { threshold: 0 });

            observer.observe(footer);
        }
    }

    // ── Boutons loading data-loading ──────────────────────────
    // <button data-loading="Enregistrement..."> → désactivé au submit

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type="submit"][data-loading]');
            if (!btn) return;
            const label = btn.dataset.loading;
            btn.disabled = true;
            btn.innerHTML = `<span class="krono-spinner krono-spinner--sm"></span> ${label}`;
        });
    });

    // ── Système de Notifications (Cloche & Polling) ───────────

    const notifBtn = document.getElementById('btnToggleNotifs');
    const notifOverlay = document.getElementById('notifOverlay');
    const notifBadge = document.getElementById('notifBadge');
    const notifCountText = document.getElementById('notifCountText');
    const notifList = document.getElementById('notifList');
    const btnMarkAllRead = document.getElementById('btnMarkAllRead');
    
    // Suivi des IDs pour détecter les nouvelles notifs (popup)
    let knownNotifIds = new Set();
    let isFirstLoad = true;

    // ── Système de Toasts (Simple - en bas à droite) ─────────────────
    let toastContainer = document.getElementById('KronoConnectToastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'KronoConnectToastContainer';
        document.body.appendChild(toastContainer);
    }

    window.kronoToast = window.KronoConnectToast = function(options, type = 'info') {
        if (!toastContainer) return;

        const config = typeof options === 'string' ? { message: options, level: type } : options;
        const level  = config.level || config.type || 'info';
        
        const icons = {
            info: 'bi-info-circle-fill',
            success: 'bi-check-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            danger: 'bi-exclamation-octagon-fill',
            alert: 'bi-exclamation-octagon-fill'
        };

        const toast = document.createElement('div');
        toast.className = `krono-toast krono-toast--${level}`;
        toast.innerHTML = `
            <i class="bi ${icons[level] || 'bi-info-circle-fill'}"></i>
            <span class="krono-toast__message">${config.message}</span>
        `;

        toast.addEventListener('click', () => {
            hideToast(toast);
        });

        toastContainer.appendChild(toast);

        const duration = config.duration || 4000;
        if (duration > 0) {
            setTimeout(() => hideToast(toast), duration);
        }
    };

    function hideToast(toast) {
        if (!toast || toast.classList.contains('hiding')) return;
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 400);
    }

    // ── Système d'Alertes de Notifications Riches (Top-Right) ──────────
    let notifAlertContainer = document.getElementById('KronoConnectNotifAlertContainer');
    if (!notifAlertContainer) {
        notifAlertContainer = document.createElement('div');
        notifAlertContainer.id = 'KronoConnectNotifAlertContainer';
        document.body.appendChild(notifAlertContainer);
    }

    window.showRichNotification = window.KronoConnectShowRichNotification = function(options) {
        if (!notifAlertContainer) return;

        const colors = {
            info: 'info', success: 'success', warning: 'warning', danger: 'danger', alert: 'danger'
        };
        const icons = {
            info: 'bi-info-circle-fill',
            success: 'bi-check-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            danger: 'bi-exclamation-octagon-fill',
            alert: 'bi-exclamation-octagon-fill'
        };

        const level = options.level || 'info';
        const colorVal = colors[level] || 'info';

        const alertEl = document.createElement('div');
        alertEl.className = 'krono-notif-alert';
        alertEl.style.setProperty('--notif-color', `var(--krono-${colorVal})`);
        alertEl.innerHTML = `
            <div style="display:flex; align-items:flex-start; gap:1rem; width:100%;">
                <div style="width:40px; height:40px; border-radius:12px; background:var(--krono-${colorVal}-bg); color:var(--krono-${colorVal}); display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0; box-shadow:0 4px 10px rgba(0,0,0,0.05);">
                    <i class="bi ${icons[level] || 'bi-bell-fill'}"></i>
                </div>
                <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:0.25rem; padding-top:0.1rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.6px; color:var(--krono-${colorVal});">
                            ${options.title || 'Notification'}
                        </div>
                        <span style="font-size:0.7rem; color:var(--krono-text-3); font-weight:500;">À l'instant</span>
                    </div>
                    <div style="font-size:0.9rem; font-weight:600; color:var(--krono-text); line-height:1.4;">
                        ${options.message}
                    </div>
                </div>
                <button type="button" style="background:none; border:none; color:var(--krono-text-3); cursor:pointer; padding:0; font-size:1.3rem; line-height:1; opacity:0.5; margin-left:0.5rem; transition:opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.5" onclick="event.stopPropagation(); this.closest('.krono-notif-alert').classList.add('hiding'); setTimeout(()=>this.closest('.krono-notif-alert').remove(), 400);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;

        if (options.link || options.onclick) {
            alertEl.addEventListener('click', (e) => {
                if (e.target.closest('button')) return;
                if (options.onclick) options.onclick();
                if (options.link) window.location.href = options.link;
                hideRichNotification(alertEl);
            });
        }

        notifAlertContainer.prepend(alertEl);

        const duration = options.duration || 6000;
        if (duration > 0) {
            setTimeout(() => hideRichNotification(alertEl), duration);
        }
    };

    function hideRichNotification(el) {
        if (!el || el.classList.contains('hiding')) return;
        el.classList.add('hiding');
        setTimeout(() => el.remove(), 400);
    }

    // Compatibilité avec l'ancien système de notifs
    function showToast(notif) {
        window.showRichNotification({
            title: 'Nouvelle notification',
            message: notif.message,
            level: notif.level,
            link: notif.link,
            id: notif.id
        });
    }

    if (notifBtn && notifOverlay) {
        
        // Exposer la fonction de rafraîchissement globalement pour les autres pages
        window.refreshNotifications = function() {
            const baseUrl = window.KronoConnect_BASE_URL || '';
            fetch(baseUrl + '/api/notifications')
                .then(res => res.json())
                .then(data => {
                    if (data.error) return; // Non connecté ou erreur

                    // MAJ du badge
                    const count = parseInt(data.count);
                    if (count > 0) {
                        notifBadge.textContent = count > 99 ? '99+' : count;
                        notifBadge.style.display = 'block';
                        notifCountText.textContent = `${count} non lue(s)`;
                    } else {
                        notifBadge.style.display = 'none';
                        notifCountText.textContent = `0 non lue(s)`;
                    }

                    // MAJ de la liste
                    if (data.items && data.items.length > 0) {
                        const levelIcons = {
                            info: 'bi-info-circle-fill',
                            success: 'bi-check-circle-fill',
                            warning: 'bi-exclamation-triangle-fill',
                            alert: 'bi-exclamation-octagon-fill'
                        };
                        const levelColors = {
                            info: 'info', success: 'success', warning: 'warning', alert: 'danger'
                        };

                        let newItemsHtml = '';
                        let currentIds = new Set();

                        data.items.forEach(n => {
                            currentIds.add(n.id);
                            
                            // Détection de nouvelle notification
                            if (!isFirstLoad && !knownNotifIds.has(n.id)) {
                                showToast(n);
                            }

                            newItemsHtml += `
                                <a href="${n.link || '#'}" class="krono-notif-item krono-notif-item--unread" onclick="${n.link ? '' : 'event.preventDefault();'}">
                                    <div style="width:36px; height:36px; border-radius:50%; background:var(--krono-${levelColors[n.level] || 'info'}-bg); color:var(--krono-${levelColors[n.level] || 'info'}); display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
                                        <i class="bi ${levelIcons[n.level] || 'bi-dot'}"></i>
                                    </div>
                                    <div style="flex:1; min-width:0;">
                                        <div style="font-size:.85rem; font-weight:700; color:var(--krono-text); line-height:1.3; margin-bottom:.2rem;">
                                            ${n.message.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                                        </div>
                                        <div style="font-size:.7rem; color:var(--krono-text-3);">
                                            Il y a un instant
                                        </div>
                                    </div>
                                </a>
                            `;
                        });
                        
                        knownNotifIds = currentIds;
                        notifList.innerHTML = newItemsHtml;
                    } else {
                        knownNotifIds.clear();
                        notifList.innerHTML = `
                            <div style="padding:2rem; text-align:center; color:var(--krono-text-3); font-size:.85rem;">
                                <i class="bi bi-bell-slash" style="font-size:1.5rem; display:block; margin-bottom:.5rem; opacity:.5;"></i>
                                Aucune notification
                            </div>
                        `;
                    }

                    isFirstLoad = false;
                })
                .catch(err => console.error('Erreur notifications:', err));
        };

        // Polling toutes les 15 secondes
        setInterval(window.refreshNotifications, 15000);
        // Premier appel au chargement
        window.refreshNotifications();

        // Toggle overlay
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifOverlay.classList.toggle('is-open');
        });

        // Fermer au clic ailleurs
        document.addEventListener('click', (e) => {
            if (!notifOverlay.contains(e.target) && !notifBtn.contains(e.target)) {
                notifOverlay.classList.remove('is-open');
            }
        });

        // Tout marquer comme lu depuis l'overlay (s'il n'est pas déjà géré ailleurs)
        if (btnMarkAllRead) {
            btnMarkAllRead.addEventListener('click', () => {
                // Mettre à jour visuellement le compteur tout de suite (Optimistic UI)
                if (notifBadge) notifBadge.style.display = 'none';
                if (notifCountText) notifCountText.textContent = '0 non lue(s)';
                btnMarkAllRead.style.pointerEvents = 'none';
                btnMarkAllRead.style.opacity = '0.5';

                // Animation de sortie en cascade pour chaque notification
                const items = notifList.querySelectorAll('.krono-notif-item');
                items.forEach((item, index) => {
                    item.style.transition = 'all 0.3s ease';
                    item.style.transitionDelay = (index * 0.04) + 's';
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(30px)';
                });

                // Calcul du temps total de l'animation
                const delay = items.length > 0 ? (items.length * 40 + 300) : 0;

                // On attend la fin de l'animation avant de lancer la requête
                setTimeout(() => {
                    fetch((window.KronoConnect_BASE_URL || '') + '/api/notifications/read', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=all'
                    })
                    .then(res => res.json())
                    .then(data => {
                        btnMarkAllRead.style.pointerEvents = 'auto';
                        btnMarkAllRead.style.opacity = '1';
                        if (data.success) window.refreshNotifications();
                    });
                }, delay);
            });
        }
    }

    // ── Pagination Jump ───────────────────────────────────────
    
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.page-jump-btn');
        if (!btn) return;
        
        e.preventDefault();
        
        // Si l'input existe déjà, on ignore
        if (btn.nextElementSibling && btn.nextElementSibling.tagName === 'INPUT') return;

        const maxPage = parseInt(btn.dataset.max || '100', 10);
        const base    = btn.dataset.base;

        const input = document.createElement('input');
        input.type = 'number';
        input.min = 1;
        input.max = maxPage;
        input.className = 'krono-input';
        // Style minimaliste pour s'intégrer au design des boutons de pagination
        input.style.width = '60px';
        input.style.height = '34px';
        input.style.padding = '0.2rem 0.5rem';
        input.style.textAlign = 'center';
        input.style.margin = '0 0.15rem';
        input.style.borderRadius = 'var(--krono-radius-sm)';
        input.placeholder = '...';

        btn.style.display = 'none';
        btn.parentNode.insertBefore(input, btn.nextSibling);
        input.focus();

        const goToPage = () => {
            const val = parseInt(input.value, 10);
            if (!isNaN(val) && val >= 1 && val <= maxPage) {
                window.location.href = base + val;
            } else {
                // Annuler si la valeur est invalide ou vide
                input.remove();
                btn.style.display = '';
            }
        };

        input.addEventListener('blur', goToPage);
        input.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') goToPage();
            if (ev.key === 'Escape') {
                input.remove();
                btn.style.display = '';
            }
        });
    });

    // ── Fix z-index pour les modales ──────────────────────────
    // Déplace toutes les modales à la racine du body pour s'assurer qu'elles passent
    // au-dessus de la sidebar (z-index 110) et de la topbar (z-index 90), évitant ainsi
    // les pièges de stacking context générés par les animations de .krono-content
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.krono-modal-backdrop').forEach(modal => {
            document.body.appendChild(modal);
        });
    });

})();