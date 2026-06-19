<?php
/**
 * Vue unifiée : login "Identifier-first" + Passwordless
 */
$allowRegister  = $appConfig['features']['registration']  ?? true;
$allowReset     = true;

$wideClass = '';
?>

<?php if (!empty($ssoClient)): 
    $appColor = $ssoClient['app_color'] ?: '#3B82F6';
    $appRgb = hexToRgb($appColor);
    $appRgbStr = implode(',', $appRgb);
?>
<style>
    .auth-btn {
        background: linear-gradient(135deg, <?= e($appColor) ?> 0%, color-mix(in srgb, <?= e($appColor) ?> 85%, #000 15%) 100%) !important;
        box-shadow: 0 8px 28px rgba(<?= $appRgbStr ?>, 0.38) !important;
    }
    .auth-btn:hover {
        background: linear-gradient(135deg, color-mix(in srgb, <?= e($appColor) ?> 95%, #fff 5%) 0%, color-mix(in srgb, <?= e($appColor) ?> 80%, #000 20%) 100%) !important;
        box-shadow: 0 12px 40px rgba(<?= $appRgbStr ?>, 0.5) !important;
    }

    .sso-header-container {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1.25rem;
        margin-bottom: 1.5rem;
        padding: 0.5rem;
    }
    .sso-logo-box {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .krono-logo-anim {
        animation: krono-slide 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    @keyframes krono-slide {
        from { transform: translateX(52px) rotate(-10deg); opacity: 0; filter: blur(5px); }
        to { transform: translateX(0) rotate(0deg); opacity: 1; filter: blur(0); }
    }
    .sso-arrow-anim {
        font-size: 1.4rem;
        color: var(--app-brand-color);
        opacity: 0;
        transform: scale(0.4);
        animation: arrow-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.6s both;
    }
    @keyframes arrow-pop {
        to { opacity: 0.7; transform: scale(1); }
    }
    .app-logo-box {
        width: 64px; height: 64px;
        border-radius: 18px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem; color: white;
        box-shadow: 0 10px 30px rgba(var(--app-brand-rgb, 0,0,0), 0.3);
        opacity: 0;
        transform: translateX(-40px) scale(0.6);
        animation: app-logo-reveal 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
    }
    @keyframes app-logo-reveal {
        to { opacity: 1; transform: translateX(0) scale(1); }
    }
    .sso-app-name-highlight {
        color: var(--app-brand-color);
        font-weight: 800;
        position: relative;
        display: inline-block;
    }
    .sso-app-name-highlight::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 4px;
        background: var(--app-brand-color);
        opacity: 0.2;
        border-radius: 2px;
    }
</style>
<?php endif; ?>

<style>
    /* Animations & Utils pour le flux dynamique */
    .step-section {
        display: none;
        animation: fade-in 0.3s ease forwards;
    }
    .step-section.active {
        display: block;
    }
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .webauthn-icon-anim {
        font-size: 3rem;
        color: var(--krono-accent);
        margin-bottom: 1rem;
        animation: pulse-glow 2s infinite ease-in-out;
    }
    @keyframes pulse-glow {
        0%, 100% { filter: drop-shadow(0 0 5px rgba(59,130,246,0.3)); transform: scale(1); }
        50% { filter: drop-shadow(0 0 15px rgba(59,130,246,0.7)); transform: scale(1.05); }
    }
    .email-display-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--krono-surface-2);
        border: 1px solid var(--krono-border);
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--krono-text);
        margin-bottom: 1.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .email-display-pill:hover {
        background: var(--krono-surface-3);
        border-color: var(--krono-text-3);
    }
</style>

<div class="auth-panels-wrap">
    <div class="auth-panel active" id="login-panel">

        <?php if (!empty($ssoClient)): ?>
            <div style="text-align:center; margin-bottom: 1.5rem;">
                <div class="sso-header-container" style="--app-brand-color: <?= e($appColor) ?>; --app-brand-rgb: <?= $appRgbStr ?>; margin-bottom: 1.25rem;">
                    <div class="sso-logo-box krono-logo-anim">
                        <div class="auth-logo">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                    </div>
                    <div class="sso-arrow-anim">
                        <i class="bi bi-arrow-right"></i>
                    </div>
                    <div class="sso-logo-box app-logo-box" style="background: <?= e($appColor) ?>;">
                        <i class="bi bi-<?= e($ssoClient['app_icon'] ?: 'app-indicator') ?>"></i>
                    </div>
                </div>
                <div class="auth-title"><?= e($appConfig['name']) ?></div>
                <div class="auth-subtitle" style="margin-top:0.3rem;">
                    Continuer vers <span class="sso-app-name-highlight"><?= e($ssoClient['app_name'] ?: $ssoClient['name']) ?></span>
                </div>
            </div>
        <?php elseif (empty($ssoClient)): ?>
        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="auth-logo" style="margin:0 auto 1.1rem;">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div class="auth-title"><?= e($appConfig['name']) ?></div>
            <div class="auth-subtitle" style="margin-top:0.3rem;">Service d'authentification</div>
        </div>
        <?php endif; ?>

        <?php if (!empty($maintenanceMode)): ?>
            <div class="krono-alert krono-alert--warning" style="margin-bottom: 1.5rem; text-align: left; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.25); color: #d97706; padding: 0.85rem 1rem; border-radius: 8px; font-size: 0.82rem; display: flex; gap: 0.6rem; align-items: flex-start;">
                <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.15rem; flex-shrink: 0; line-height: 1;"></i>
                <div>
                    <strong style="font-weight: 800;">Mode Maintenance Actif</strong><br>
                    Seuls les comptes disposant de la permission de maintenance peuvent se connecter.
                </div>
            </div>
        <?php endif; ?>

        <div id="global-error" class="krono-alert krono-alert--danger" style="display:none; margin-bottom: 1.5rem;"></div>

        <form method="POST" action="<?= url('/login' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" id="formLogin">
            <?= csrf() ?>

            <!-- ÉTAPE 1 : Saisie de l'e-mail -->
            <div id="step-email" class="step-section active">
                <div style="margin-bottom:1rem;">
                    <label class="auth-label" for="login-email">Adresse e-mail</label>
                    <div class="auth-field">
                        <input type="email" name="email" id="login-email" class="auth-input"
                               placeholder="jean.dupont@collectivite.fr"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               required autofocus autocomplete="username">
                        <i class="bi bi-envelope auth-field-icon"></i>
                    </div>
                </div>

                <?= \KronoConnect\Core\Captcha::render('login') ?>

                <button type="button" id="btn-next" class="auth-btn" style="margin-bottom: 1.5rem;">
                    Suivant &nbsp;<i class="bi bi-arrow-right-short"></i>
                </button>
            </div>

            <!-- EN-TÊTE DYNAMIQUE POUR LES ÉTAPES SUIVANTES -->
            <div id="step-header" style="display:none; text-align:center;">
                <div class="email-display-pill" id="btn-change-email" title="Modifier l'adresse e-mail">
                    <i class="bi bi-person-circle"></i>
                    <span id="display-email"></span>
                    <i class="bi bi-pencil-square" style="opacity:0.5; margin-left:4px;"></i>
                </div>
            </div>

            <!-- ÉTAPE 2A : WebAuthn (Passwordless) -->
            <div id="step-webauthn" class="step-section" style="text-align: center;">
                <div class="webauthn-icon-anim">
                    <i class="bi bi-fingerprint"></i>
                </div>
                <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Clé de sécurité requise</h3>
                <p style="color: var(--krono-text-3); font-size: 0.85rem; margin-bottom: 1.5rem;">
                    Veuillez utiliser votre clé de sécurité matérielle ou la biométrie (Touch ID, Windows Hello) pour vous connecter.
                </p>
                <button type="button" id="btn-trigger-webauthn" class="auth-btn" style="margin-bottom: 1rem;">
                    S'authentifier avec la clé
                </button>
                <button type="button" id="btn-use-password" class="auth-btn-ghost" style="font-size: 0.85rem;">
                    Utiliser un mot de passe à la place
                </button>
            </div>

            <!-- ÉTAPE 2B : Mot de passe -->
            <div id="step-password" class="step-section">
                <div style="margin-bottom:1.25rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                        <label class="auth-label" style="margin:0;" for="login-password">Mot de passe</label>
                        <?php if ($allowReset && empty($maintenanceMode)): ?>
                            <a href="<?= url('/forgot-password' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" class="auth-link" style="font-size: 0.8rem; font-weight: 500;">Oublié ?</a>
                        <?php endif; ?>
                    </div>
                    <div class="auth-field">
                        <input type="password" name="password" id="login-password" class="auth-input"
                               placeholder="••••••••" autocomplete="current-password">
                        <i class="bi bi-lock auth-field-icon"></i>
                    </div>
                </div>

                <div class="auth-options-row">
                    <label class="auth-checkbox-label">
                        <input type="checkbox" name="remember_me" id="remember-me" value="1">
                        Se souvenir de moi
                    </label>
                </div>

                <button type="submit" id="btn-submit-login" class="auth-btn">
                    Se connecter &nbsp;<i class="bi bi-arrow-right-short"></i>
                </button>
            </div>
        </form>

        <?php if ($allowRegister && empty($maintenanceMode)): ?>
        <div class="auth-divider" id="register-divider" style="margin-top:1.25rem;">
            <span>Nouveau sur <?= e($appConfig['name']) ?> ?</span>
        </div>
        <a href="<?= url('/register' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" id="register-btn" class="auth-btn-ghost" style="text-decoration:none; display:flex;">
            <i class="bi bi-person-plus" style="margin-right:.5rem;"></i>Créer un compte
        </a>
        <?php endif; ?>

    </div>

    <!-- ══ PANEL VERIFY (Code e-mail) ════════════════════════════════ -->
    <div class="auth-panel" id="panel-verify">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="auth-logo" style="margin:0 auto 1.1rem; color:var(--krono-accent); border-color:var(--krono-accent-light);">
                <i class="bi bi-envelope-check"></i>
            </div>
            <div class="auth-title">Vérification de l'e-mail</div>
            <div class="auth-subtitle" style="margin-top:0.3rem;">Un code à 6 chiffres a été envoyé à votre adresse e-mail.</div>
        </div>

        <form method="POST" action="<?= url('/verify-code') ?>" id="formVerifyCode">
            <?= csrf() ?>
            <input type="hidden" name="email" id="verify-email" value="">
            <div style="margin-bottom:1.5rem;">
                <div class="auth-field" style="justify-content:center;">
                    <input type="text" name="code" class="auth-input" style="text-align:center; font-size:1.5rem; letter-spacing:.5rem; font-weight:700;" placeholder="000000" maxlength="6" required autocomplete="off">
                </div>
            </div>
            <button type="submit" class="auth-btn">
                Valider &nbsp;<i class="bi bi-check-lg"></i>
            </button>
        </form>
    </div>

    <!-- ══ PANEL WAITING (Approbation) ══════════════════════════════ -->
    <div class="auth-panel" id="panel-waiting">
        <div style="text-align:center; padding:.5rem 0 1rem;">
            <div class="success-icon-wrap" style="margin-bottom:1.5rem; color:var(--krono-accent); border-color:var(--krono-accent-light);">
                <div class="success-ring" style="border-color:var(--krono-accent);"></div>
                <div class="success-icon" style="background:var(--krono-accent); border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; margin:0 auto; color:white; font-size:2rem;">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
            <div class="auth-title" style="margin-bottom:.4rem;">En attente d'approbation</div>
            <div class="auth-subtitle" style="margin-bottom:1.5rem;">Votre adresse e-mail a été validée avec succès.</div>
            <p style="color:var(--krono-text-2); font-size:.88rem; line-height:1.7; margin-bottom:1.5rem;">
                Un administrateur doit maintenant valider votre compte avant que vous puissiez y accéder.<br>
                <span style="color:var(--krono-text-3); font-size:.78rem;">
                    Vous recevrez un e-mail de confirmation dès son activation.
                </span>
            </p>
            <button type="button" class="auth-btn" id="btn-back-login">
                Retour à la connexion
            </button>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const stepEmail = document.getElementById('step-email');
    const stepPassword = document.getElementById('step-password');
    const stepWebauthn = document.getElementById('step-webauthn');
    const stepHeader = document.getElementById('step-header');
    
    const emailInput = document.getElementById('login-email');
    const passwordInput = document.getElementById('login-password');
    const displayEmail = document.getElementById('display-email');
    
    const btnNext = document.getElementById('btn-next');
    const btnChangeEmail = document.getElementById('btn-change-email');
    const btnUsePassword = document.getElementById('btn-use-password');
    const btnTriggerWebauthn = document.getElementById('btn-trigger-webauthn');
    const formLogin = document.getElementById('formLogin');
    
    const errorBox = document.getElementById('global-error');
    const registerDivider = document.getElementById('register-divider');
    const registerBtn = document.getElementById('register-btn');
    const loginPanel = document.getElementById('login-panel');
    const panelVerify = document.getElementById('panel-verify');
    const panelWaiting = document.getElementById('panel-waiting');

    // Vérifier les flash messages
    const serverError = <?= json_encode(\KronoConnect\Core\Session::getFlash('error')) ?>;
    const flashSuccess = <?= json_encode(\KronoConnect\Core\Session::getFlash('success')) ?>;
    
    if (serverError) {
        showError(serverError);
    }
    
    if (flashSuccess) {
        if (flashSuccess.includes('code envoyé')) {
            showPanel(panelVerify);
            // Extraire l'email de l'URL si possible, ou du dernier post (si passé via session ?)
            // Généralement c'est l'utilisateur qui l'a saisi, ou c'est envoyé par le backend.
        } else if (flashSuccess.includes('validée avec succès') || flashSuccess.includes('attente')) {
            showPanel(panelWaiting);
        } else {
            showError(flashSuccess); // Affiche le message de succès dans la boite (en vert si on modifie le style)
            errorBox.classList.remove('krono-alert--danger');
            errorBox.classList.add('krono-alert--success');
        }
    }

    function showPanel(panelEl) {
        document.querySelectorAll('.auth-panel').forEach(p => p.classList.remove('active'));
        panelEl.classList.add('active');
    }

    document.getElementById('btn-back-login')?.addEventListener('click', () => showPanel(loginPanel));

    function showError(msg) {
        errorBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="margin-right:0.5rem"></i> ' + msg;
        errorBox.style.display = 'block';
    }
    function hideError() {
        errorBox.style.display = 'none';
    }

    function showStep(stepId) {
        document.querySelectorAll('.step-section').forEach(s => s.classList.remove('active'));
        document.getElementById(stepId).classList.add('active');
        
        if (stepId === 'step-email') {
            stepHeader.style.display = 'none';
            if (registerDivider) registerDivider.style.display = 'flex';
            if (registerBtn) registerBtn.style.display = 'flex';
            emailInput.focus();
        } else {
            stepHeader.style.display = 'block';
            displayEmail.textContent = emailInput.value;
            if (registerDivider) registerDivider.style.display = 'none';
            if (registerBtn) registerBtn.style.display = 'none';
        }
    }

    btnNext.addEventListener('click', async () => {
        const email = emailInput.value.trim();
        if (!email || !emailInput.checkValidity()) {
            showError("Veuillez saisir une adresse e-mail valide.");
            return;
        }
        hideError();

        // Afficher l'état de chargement
        const originalText = btnNext.innerHTML;
        btnNext.innerHTML = '<i class="spinner-border spinner-border-sm" style="margin-right:8px;"></i> Vérification...';
        btnNext.disabled = true;

        try {
            const fd = new FormData(formLogin);

            const res = await fetch('<?= url("/login/check-email") ?>', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (!res.ok || !data.success) {
                showError(data.error || "Une erreur est survenue.");
                return;
            }

            if (data.has_webauthn) {
                showStep('step-webauthn');
                initWebAuthn();
            } else {
                showStep('step-password');
                passwordInput.setAttribute('required', 'required');
                passwordInput.focus();
            }
        } catch (err) {
            showError("Erreur réseau de vérification.");
            console.error(err);
        } finally {
            btnNext.innerHTML = originalText;
            btnNext.disabled = false;
        }
    });

    emailInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            btnNext.click();
        }
    });

    btnChangeEmail.addEventListener('click', () => {
        hideError();
        showStep('step-email');
        passwordInput.removeAttribute('required');
    });

    btnUsePassword.addEventListener('click', () => {
        hideError();
        showStep('step-password');
        passwordInput.setAttribute('required', 'required');
        passwordInput.focus();
    });

    // ── Logique WebAuthn Passwordless ──
    async function initWebAuthn() {
        try {
            const email = encodeURIComponent(emailInput.value.trim());
            const optRes = await fetch(`<?= url("/login/webauthn/assertion-options") ?>?email=${email}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const getArgs = await optRes.json();

            if (getArgs.error) {
                showError(getArgs.error);
                return;
            }

            const publicKey = getArgs.publicKey || {};
            const allowCreds = (publicKey.allowCredentials || []).map(c => ({
                ...c,
                id: Uint8Array.from(atob(c.id.replace(/-/g, "+").replace(/_/g, "/")), c => c.charCodeAt(0))
            }));

            const challengeBytes = Uint8Array.from(atob((publicKey.challenge || '').replace(/-/g, "+").replace(/_/g, "/")), c => c.charCodeAt(0));

            const credential = await navigator.credentials.get({
                publicKey: {
                    ...publicKey,
                    challenge: challengeBytes,
                    allowCredentials: allowCreds
                }
            });

            const authData = new Uint8Array(credential.response.authenticatorData);
            const clientDataJSON = new Uint8Array(credential.response.clientDataJSON);
            const signature = new Uint8Array(credential.response.signature);

            const remember = document.getElementById('remember-me').checked ? 1 : 0;
            const flowId = <?= json_encode($flowId ?? '') ?>;
            const verifyPayload = {
                id: credential.id,
                type: credential.type,
                response: {
                    authenticatorData: btoa(String.fromCharCode.apply(null, authData)),
                    clientDataJSON: btoa(String.fromCharCode.apply(null, clientDataJSON)),
                    signature: btoa(String.fromCharCode.apply(null, signature))
                }
            };

            const verifRes = await fetch(`<?= url("/login/webauthn/verify") ?>?flow=${flowId}&remember=${remember}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(verifyPayload)
            });

            const verifData = await verifRes.json();
            if (verifData.success) {
                window.location.href = verifData.redirect_url;
            } else {
                showError(verifData.error || "Échec de l'authentification WebAuthn.");
            }
        } catch (e) {
            console.error('WebAuthn error:', e);
            if (e.name === 'NotAllowedError') {
                showError("L'authentification par clé a été annulée ou refusée.");
            } else {
                showError("Erreur avec la clé de sécurité. Veuillez réessayer ou utiliser votre mot de passe.");
            }
        }
    }

    btnTriggerWebauthn.addEventListener('click', initWebAuthn);

    // Initialisation si un email est déjà prérempli (ex: retour après erreur mdp)
    if (emailInput.value.trim() && serverError) {
        btnNext.click();
    }
});
</script>