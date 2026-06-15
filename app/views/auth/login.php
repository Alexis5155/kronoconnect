<?php
/**
 * Vue unifiée : login + register (optionnel) + mot de passe oublié
 * Utilise le layout auth.php via View::render()
 */
$allowRegister  = $appConfig['features']['registration']  ?? true;
$allowReset     = true;
// Panel initial
$initialPanel = 0;
if (!empty($registerErrors) || !empty($registerOld)) $initialPanel = 1;
if (!empty($forgotSent) || !empty($forgotError))     $initialPanel = $allowRegister ? 2 : 1;
if (!empty($registerSuccess))                        $initialPanel = 'success';

$wideClass = '';
if ($initialPanel === 1 && $allowRegister) $wideClass = 'wide-lg';
elseif ($initialPanel === 2 || ($initialPanel === 1 && !$allowRegister)) $wideClass = 'wide-md';

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

<div class="auth-panels-wrap" id="panelsWrap">

    <!-- ══ PANEL 0 — CONNEXION ══════════════════════════════════ -->
    <div class="auth-panel <?= $initialPanel === 0 ? 'active' : '' ?>" id="panel-0">

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


        <?php if ($allowRegister || $allowReset): ?>
        <div class="auth-dots">
            <div class="auth-dot" data-panel="0"></div>
            <?php if ($allowRegister): ?>
                <div class="auth-dot" data-panel="1"></div>
            <?php endif; ?>
            <?php if ($allowReset): ?>
                <div class="auth-dot" data-panel="<?= $allowRegister ? 2 : 1 ?>"></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('/login' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" id="formLogin">
            <?= csrf() ?>

            <div style="margin-bottom:1rem;">
                <label class="auth-label" for="login-email">Adresse e-mail</label>
                <div class="auth-field">
                    <input type="email" name="email" id="login-email" class="auth-input"
                           placeholder="jean.dupont@collectivite.fr"
                           value="<?= e($_POST['email'] ?? '') ?>"
                           required autofocus autocomplete="email">
                    <i class="bi bi-envelope auth-field-icon"></i>
                </div>
                <div class="field-error" id="err-login-email"></div>
            </div>

            <div style="margin-bottom:1.25rem;">
                <div style="margin-bottom:0.35rem;">
                    <label class="auth-label" style="margin:0;" for="login-password">Mot de passe</label>
                </div>
                <div class="auth-field">
                    <input type="password" name="password" id="login-password" class="auth-input"
                           placeholder="••••••••" required autocomplete="current-password">
                    <i class="bi bi-lock auth-field-icon"></i>
                </div>
                <div class="field-error" id="err-login-password"></div>
            </div>

            <div class="auth-options-row">
                <label class="auth-checkbox-label">
                    <input type="checkbox" name="remember_me" value="1">
                    Se souvenir de moi
                </label>
                <?php if ($allowReset && empty($maintenanceMode)): ?>
                <button type="button" class="auth-link" style="font-size: 0.85rem;"
                        onclick="goPanel(<?= $allowRegister ? 2 : 1 ?>)">
                    Mot de passe oublié ?
                </button>
                <?php endif; ?>
            </div>

            <?= \KronoConnect\Core\Captcha::render('login') ?>

            <button type="submit" class="auth-btn">
                Se connecter &nbsp;<i class="bi bi-arrow-right-short"></i>
            </button>
        </form>

        <?php if ($allowRegister && empty($maintenanceMode)): ?>
        <div class="auth-divider" style="margin-top:1.25rem;">
            <span>Nouveau sur <?= e($appConfig['name']) ?> ?</span>
        </div>
        <button type="button" class="auth-btn-ghost" onclick="goPanel(1)">
            <i class="bi bi-person-plus" style="margin-right:.5rem;"></i>Créer un compte
        </button>
        <?php endif; ?>

    </div><!-- /panel-0 -->


    <?php if ($allowRegister): ?>
    <!-- ══ PANEL 1 — INSCRIPTION ════════════════════════════════ -->
    <div class="auth-panel" id="panel-1">

        <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:1.5rem;">
            <button type="button" class="auth-link" onclick="goPanel(0)"
                    style="font-size:1.2rem; line-height:1;">
                <i class="bi bi-arrow-left"></i>
            </button>
            <div>
                <div class="auth-title" style="font-size:1.2rem;">Créer un compte</div>
                <div class="auth-subtitle" style="margin-top:.15rem; font-size:.62rem;">
                    Accès soumis à validation par un administrateur
                </div>
            </div>
        </div>

        <div class="auth-dots">
            <div class="auth-dot" data-panel="0"></div>
            <div class="auth-dot" data-panel="1"></div>
            <?php if ($allowReset): ?>
                <div class="auth-dot" data-panel="2"></div>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= url('/register' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" id="formRegister"
              data-server-errors="<?= e(json_encode($registerErrors ?? [])) ?>">
            <?= csrf() ?>

            <div class="auth-row" style="margin-bottom:.9rem;">
                <div>
                    <label class="auth-label" for="reg-prenom">Prénom</label>
                    <div class="auth-field">
                        <input type="text" name="prenom" id="reg-prenom" class="auth-input"
                               placeholder="Jean"
                               value="<?= e($registerOld['prenom'] ?? '') ?>" required>
                        <i class="bi bi-person auth-field-icon"></i>
                    </div>
                    <div class="field-error" id="err-prenom"></div>
                </div>
                <div>
                    <label class="auth-label" for="reg-nom">Nom</label>
                    <div class="auth-field">
                        <input type="text" name="nom" id="reg-nom" class="auth-input"
                               placeholder="Dupont"
                               value="<?= e($registerOld['nom'] ?? '') ?>" required>
                        <i class="bi bi-person auth-field-icon"></i>
                    </div>
                    <div class="field-error" id="err-nom"></div>
                </div>
            </div>

            <div style="margin-bottom:.9rem;">
                <label class="auth-label" for="reg-email">Adresse e-mail professionnelle</label>
                <div class="auth-field">
                    <input type="email" name="email" id="reg-email" class="auth-input"
                           placeholder="jean.dupont@collectivite.fr"
                           value="<?= e($registerOld['email'] ?? '') ?>" required>
                    <i class="bi bi-envelope auth-field-icon"></i>
                </div>
                <div class="field-error" id="err-email"></div>
            </div>

            <div class="auth-row" style="margin-bottom:.9rem;">
                <div>
                    <label class="auth-label" for="reg-password">Mot de passe</label>
                    <div class="auth-field">
                        <input type="password" name="password" id="reg-password" class="auth-input"
                               placeholder="8 car. min." required minlength="8">
                        <i class="bi bi-lock auth-field-icon"></i>
                    </div>
                    <div class="field-error" id="err-password"></div>
                </div>
                <div>
                    <label class="auth-label" for="reg-password2">Confirmer</label>
                    <div class="auth-field">
                        <input type="password" name="password2" id="reg-password2" class="auth-input"
                               placeholder="Confirmer" required>
                        <i class="bi bi-lock-fill auth-field-icon"></i>
                    </div>
                    <div class="field-error" id="err-password2"></div>
                </div>
            </div>
            
            <?= \KronoConnect\Core\Captcha::render('register') ?>

            <button type="submit" class="auth-btn" style="margin-top:.5rem;">
                <i class="bi bi-person-check" style="margin-right:.5rem;"></i>Créer mon compte
            </button>
        </form>

        <div style="text-align:center; margin-top:.9rem;">
            <button type="button" class="auth-link" onclick="goPanel(0)">
                Déjà un compte ? Se connecter
            </button>
        </div>

    </div><!-- /panel-1 -->
    <?php endif; ?>


    <?php if ($allowReset): ?>
    <!-- ══ PANEL 2 (ou 1) — MOT DE PASSE OUBLIÉ ════════════════ -->
    <div class="auth-panel" id="panel-<?= $allowRegister ? 2 : 1 ?>">

        <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:1.5rem;">
            <button type="button" class="auth-link" onclick="goPanel(0)"
                    style="font-size:1.2rem; line-height:1;">
                <i class="bi bi-arrow-left"></i>
            </button>
            <div>
                <div class="auth-title" style="font-size:1.2rem;">Mot de passe oublié</div>
                <div class="auth-subtitle" style="margin-top:.15rem; font-size:.62rem;">
                    Un lien de réinitialisation vous sera envoyé
                </div>
            </div>
        </div>

        <div class="auth-dots">
            <div class="auth-dot" data-panel="0"></div>
            <?php if ($allowRegister): ?>
                <div class="auth-dot" data-panel="1"></div>
            <?php endif; ?>
            <div class="auth-dot" data-panel="<?= $allowRegister ? 2 : 1 ?>"></div>
        </div>

        <form method="POST" action="<?= url('/reset-password' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" id="formForgot"
              data-server-error="<?= e($forgotError ?? '') ?>"
              data-server-success="<?= e(($forgotSent ?? false) ? 'Lien envoyé ! Vérifiez votre boîte mail.' : '') ?>">
            <?= csrf() ?>

            <div style="margin-bottom:1.25rem;">
                <label class="auth-label" for="forgot-email">Adresse e-mail du compte</label>
                <div class="auth-field">
                    <input type="email" name="email" id="forgot-email" class="auth-input"
                           placeholder="jean.dupont@collectivite.fr" required>
                    <i class="bi bi-envelope auth-field-icon"></i>
                </div>
                <div class="field-error" id="err-forgot-email"></div>
            </div>
            
            <?= \KronoConnect\Core\Captcha::render('reset') ?>

            <button type="submit" class="auth-btn">
                <i class="bi bi-send" style="margin-right:.5rem;"></i>Envoyer le lien
            </button>
        </form>

        <div style="text-align:center; margin-top:.9rem;">
            <button type="button" class="auth-link" onclick="goPanel(0)">
                Retour à la connexion
            </button>
        </div>

    </div><!-- /panel-forgot -->
    <?php endif; ?>


    <!-- ══ PANEL VERIFY (Code à 6 chiffres) ══════════════════════════════ -->
    <div class="auth-panel <?= $initialPanel === 'verify' ? 'active' : '' ?>" id="panel-verify">
        <div style="text-align:center; padding:.5rem 0 1rem;">
            <div class="auth-title" style="margin-bottom:.4rem;">Vérification</div>
            <div class="auth-subtitle" style="margin-bottom:1.5rem; line-height:1.4;">Un code à 6 chiffres a été envoyé à<br><strong id="verify-email-display" style="color:var(--krono-text);"></strong></div>
            <form id="formVerifyCode" method="POST" action="<?= url('/verify-code') ?>">
                <?= csrf() ?>
                <input type="hidden" name="email" id="verify-email-input">
                <div style="margin-bottom:1.25rem;">
                    <div class="auth-field" style="max-width:200px; margin:0 auto;">
                        <input type="text" name="code" id="verify-code-input" class="auth-input"
                               placeholder="123456" maxlength="6" pattern="\d{6}"
                               style="text-align:center; font-size:1.5rem; letter-spacing:0.3rem; padding: 0.75rem;" required autocomplete="off">
                    </div>
                    <div class="field-error" id="err-verify-code" style="text-align:center; margin-top:0.5rem;"></div>
                </div>
                <button type="submit" class="auth-btn" id="btnVerifyCode">
                    <i class="bi bi-shield-check" style="margin-right:.5rem;"></i>Valider mon e-mail
                </button>
            </form>
            <div style="text-align:center; margin-top:1.25rem;">
                <button type="button" class="auth-link" onclick="goPanel(0)">
                    Annuler et retourner à la connexion
                </button>
            </div>
        </div>
    </div><!-- /panel-verify -->

    <!-- ══ PANEL WAITING (Approbation) ══════════════════════════════ -->
    <div class="auth-panel" id="panel-waiting">
        <div style="text-align:center; padding:.5rem 0 1rem;">
            <div class="success-icon-wrap" style="margin-bottom:1.5rem; color:var(--krono-accent); border-color:var(--krono-accent-light);">
                <div class="success-ring" style="border-color:var(--krono-accent);"></div>
                <div class="success-icon" style="background:var(--krono-accent);">
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
            <button type="button" class="auth-btn" onclick="goPanel(0)">
                Retour à la connexion
            </button>
        </div>
    </div><!-- /panel-waiting -->

</div><!-- /panelsWrap -->

<script>
    const ALLOW_REGISTER = <?= json_encode($allowRegister) ?>;
    const ALLOW_RESET    = <?= json_encode($allowReset) ?>;
    const INITIAL_PANEL  = <?= json_encode($initialPanel) ?>;

    // Données flash passées depuis PHP
    window.KronoConnect_AUTH = {
        loginError:    '',
        flashSuccess:  '',
        registerErrors:<?= json_encode($registerErrors ?? []) ?>,
        forgotError:   <?= json_encode($forgotError ?? '') ?>,
        forgotSent:    <?= json_encode($forgotSent ?? false) ?>
    };
</script>