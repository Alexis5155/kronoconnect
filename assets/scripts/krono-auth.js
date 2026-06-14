/* ══════════════════════════════════════════════════════════════
   KronoConnectCore — Auth JS
   Gestion des panels, toasts, validation inline, navigation
══════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    const wrap   = document.getElementById('authWrap');
    const card   = document.getElementById('authCard');
    const pwrap  = document.getElementById('panelsWrap');
    if (!card) return; // Sécurité si le script charge sur une autre page

    let currentPanel = window.INITIAL_PANEL ?? 0;

    // ── Toasts ────────────────────────────────────────────────

    function showToast(message, type = 'danger', duration = 4500) {
        if (!message) return;
        if (typeof window.kronoToast === 'function') {
            window.kronoToast({ message: message, level: type, duration: duration });
        } else {
            console.warn("kronoToast is not defined, falling back to alert:", message);
            alert(message);
        }
    }

    // ── Erreurs inline ────────────────────────────────────────

    function setFieldError(input, errEl, msg) {
        input.classList.add('input-error');
        if (errEl) {
            errEl.innerHTML = `<i class="bi bi-exclamation-circle-fill" style="font-size:.65rem;"></i> ${msg}`;
            errEl.classList.add('show');
        }
        input.addEventListener('input', () => {
            input.classList.remove('input-error');
            if (errEl) errEl.classList.remove('show');
        }, { once: true });
    }

    // ── Animation shake ───────────────────────────────────────

    function shakeCard() {
        card.classList.remove('shake');
        void card.offsetWidth; // reflow pour reset animation
        card.classList.add('shake');
        card.addEventListener('animationend', () => card.classList.remove('shake'), { once: true });
    }

    // ── Navigation panels ─────────────────────────────────────

    window.goPanel = function (index) {
        if (index === currentPanel) return;

        const oldPanel = pwrap.querySelector('.auth-panel.active');
        const newPanel = document.getElementById(`panel-${index}`) || document.getElementById(`panel-${index === 2 && !window.ALLOW_REGISTER ? 1 : index}`);
        if (!newPanel) return;

        let targetWideClass = '';
        if (index === 1 && window.ALLOW_REGISTER) {
            targetWideClass = 'wide-lg';
        } else if (index === 2 || (index === 1 && !window.ALLOW_REGISTER)) {
            targetWideClass = 'wide-md';
        }

        const startHeight = card.offsetHeight;

        // Geler la hauteur
        card.style.height = startHeight + 'px';

        // Mesurer la cible
        const originalTransition = card.style.transition;
        card.style.transition = 'none';
        if (wrap) {
            wrap.style.transition = 'none';
            wrap.classList.remove('wide-md', 'wide-lg');
            if (targetWideClass) wrap.classList.add(targetWideClass);
        }
        
        if (oldPanel) oldPanel.classList.remove('active');
        newPanel.classList.add('active');
        
        const targetHeight = newPanel.offsetHeight;
        
        // Revenir temporairement pour l'animation
        newPanel.classList.remove('active');
        if (oldPanel) oldPanel.classList.add('active');
        if (wrap) {
            wrap.classList.remove('wide-md', 'wide-lg');
            let oldWideClass = '';
            if (currentPanel === 1 && window.ALLOW_REGISTER) oldWideClass = 'wide-lg';
            else if (currentPanel === 2 || (currentPanel === 1 && !window.ALLOW_REGISTER)) oldWideClass = 'wide-md';
            if (oldWideClass) wrap.classList.add(oldWideClass);
        }
        
        void card.offsetHeight; // force reflow
        
        // Appliquer l'état final
        card.style.transition = originalTransition;
        if (wrap) {
            wrap.style.transition = '';
            wrap.classList.remove('wide-md', 'wide-lg');
            if (targetWideClass) wrap.classList.add(targetWideClass);
        }
        
        if (oldPanel) oldPanel.classList.remove('active');
        newPanel.classList.add('active');

        currentPanel = index;
        updateDots(index);

        if (targetHeight > 0 && targetHeight !== startHeight) {
            card.style.height = targetHeight + 'px';
            const onEnd = (e) => {
                if (e.propertyName === 'height') {
                    card.style.height = '';
                    card.removeEventListener('transitionend', onEnd);
                }
            };
            card.addEventListener('transitionend', onEnd);
            // Sécurité si transitionend ne tire pas
            setTimeout(() => {
                card.style.height = '';
            }, 600);
        } else {
            card.style.height = '';
        }

        // Focus
        setTimeout(() => {
            const first = newPanel.querySelector('input:not([type=hidden])');
            if (first) first.focus();
        }, 300);
    };

    function updateDots(active) {
        document.querySelectorAll('.auth-dot').forEach(dot => {
            const isPanelIndex = !isNaN(parseInt(dot.dataset.panel));
            dot.classList.toggle('active', isPanelIndex && parseInt(dot.dataset.panel) === active);
        });
    }

    // ── Validation login ──────────────────────────────────────

    const formLogin = document.getElementById('formLogin');
    if (formLogin) {
        formLogin.addEventListener('submit', function (e) {
            let hasError = false;
            const checks = [
                { id: 'login-email',    err: 'err-login-email',
                  check: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), msg: 'Adresse e-mail invalide' },
                { id: 'login-password', err: 'err-login-password',
                  check: v => v.length > 0, msg: 'Mot de passe requis' },
            ];
            checks.forEach(f => {
                const input = document.getElementById(f.id);
                const errEl = document.getElementById(f.err);
                if (!f.check(input.value)) {
                    setFieldError(input, errEl, f.msg);
                    hasError = true;
                }
            });
            if (hasError) { e.preventDefault(); shakeCard(); }
        });
    }

    // ── Validation register (AJAX) ─────────────────────────────

    const formRegister = document.getElementById('formRegister');
    if (formRegister) {
        formRegister.addEventListener('submit', function (e) {
            e.preventDefault();
            
            let hasError = false;
            const checks = [
                { id: 'reg-prenom',   err: 'err-prenom',
                  check: v => v.trim().length > 0, msg: 'Prénom obligatoire' },
                { id: 'reg-nom',      err: 'err-nom',
                  check: v => v.trim().length > 0, msg: 'Nom obligatoire' },
                { id: 'reg-email',    err: 'err-email',
                  check: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), msg: 'Adresse e-mail invalide' },
                { id: 'reg-password', err: 'err-password',
                  check: v => v.length >= 8, msg: '8 caractères minimum' },
            ];
            checks.forEach(f => {
                const input = document.getElementById(f.id);
                const errEl = document.getElementById(f.err);
                if (input && !f.check(input.value)) {
                    setFieldError(input, errEl, f.msg);
                    hasError = true;
                }
            });
            // Confirmation mot de passe
            const p1  = document.getElementById('reg-password');
            const p2  = document.getElementById('reg-password2');
            const ep2 = document.getElementById('err-password2');
            if (p1 && p2 && p1.value && p2.value !== p1.value) {
                setFieldError(p2, ep2, 'Les mots de passe ne correspondent pas');
                hasError = true;
            }
            
            if (hasError) { 
                shakeCard(); 
                return;
            }

            const submitBtn = formRegister.querySelector('button[type="submit"]');
            const ogText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite;"></i> Création en cours...';
            submitBtn.disabled = true;

            const formData = new URLSearchParams(new FormData(formRegister));

            fetch(formRegister.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.innerHTML = ogText;
                submitBtn.disabled = false;

                if (data.success) {
                    // Succès : on passe au panel de vérification
                    document.getElementById('verify-email-display').textContent = data.email;
                    document.getElementById('verify-email-input').value = data.email;
                    document.getElementById('verify-code-input').value = '';
                    goPanel('verify');
                } else {
                    // Erreurs du serveur (ex: email déjà utilisé)
                    if (data.errors && data.errors.length > 0) {
                        showToast(data.errors.join(' — '), 'danger', 6000);
                    }
                    shakeCard();
                }
            })
            .catch(err => {
                submitBtn.innerHTML = ogText;
                submitBtn.disabled = false;
                showToast("Une erreur réseau est survenue.", 'danger');
                shakeCard();
            });
        });
    }

    // ── Validation Code (AJAX) ─────────────────────────────────
    
    const formVerifyCode = document.getElementById('formVerifyCode');
    if (formVerifyCode) {
        formVerifyCode.addEventListener('submit', function (e) {
            e.preventDefault();
            
            const codeInput = document.getElementById('verify-code-input');
            const errEl = document.getElementById('err-verify-code');
            
            if (codeInput.value.length !== 6) {
                setFieldError(codeInput, errEl, 'Le code doit contenir 6 chiffres');
                shakeCard();
                return;
            }

            const submitBtn = document.getElementById('btnVerifyCode');
            const ogText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite;"></i> Vérification...';
            submitBtn.disabled = true;

            const formData = new URLSearchParams(new FormData(formVerifyCode));

            fetch(formVerifyCode.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.innerHTML = ogText;
                submitBtn.disabled = false;

                if (data.success) {
                    if (data.needs_approval) {
                        goPanel('waiting');
                    } else if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    setFieldError(codeInput, errEl, data.message || 'Code invalide.');
                    shakeCard();
                }
            })
            .catch(err => {
                submitBtn.innerHTML = ogText;
                submitBtn.disabled = false;
                showToast("Erreur de connexion.", 'danger');
                shakeCard();
            });
        });
        
        // Auto-submit si on tape 6 chiffres
        const codeInput = document.getElementById('verify-code-input');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, ''); // Que des chiffres
                if (this.value.length === 6) {
                    formVerifyCode.dispatchEvent(new Event('submit'));
                }
            });
        }
    }

    // ── Initialisation ────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        const auth = window.KronoConnect_AUTH || {};

        // Toasts serveur
        if (auth.loginError)   showToast(auth.loginError, 'danger');
        if (auth.flashSuccess) showToast(auth.flashSuccess, 'success');
        if (auth.forgotError)  showToast(auth.forgotError, 'danger');
        if (auth.forgotSent)   showToast('Lien de réinitialisation envoyé ! Vérifiez votre messagerie.', 'success', 7000);

        // Erreurs serveur register
        if (auth.registerErrors && auth.registerErrors.length > 0) {
            showToast(auth.registerErrors.join(' — '), 'danger', 6000);
        }

        // Panel initial
        const initPanel = document.getElementById(`panel-${window.INITIAL_PANEL ?? 0}`);
        if (initPanel) initPanel.classList.add('active');
        
        if (wrap) {
            let initialWideClass = '';
            if (window.INITIAL_PANEL === 1 && window.ALLOW_REGISTER) initialWideClass = 'wide-lg';
            else if (window.INITIAL_PANEL === 2 || (window.INITIAL_PANEL === 1 && !window.ALLOW_REGISTER)) initialWideClass = 'wide-md';
            if (initialWideClass) wrap.classList.add(initialWideClass);
        }
        
        updateDots(window.INITIAL_PANEL ?? 0);
    });

    // Escape → panel 0
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && currentPanel !== 0) goPanel(0);
    });

})();