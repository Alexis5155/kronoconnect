<div class="auth-panels-wrap">
    <div class="auth-panel active" style="position:relative; opacity:1; transform:none; pointer-events:auto;">

<?php
$appName = !empty($client['app_name']) ? $client['app_name'] : $client['name'];
$appIcon = !empty($client['app_icon']) ? $client['app_icon'] : 'app-indicator';
if (str_starts_with($appIcon, 'bi-')) {
    $appIcon = substr($appIcon, 3);
}
$appColor = !empty($client['app_color']) ? $client['app_color'] : '#3B82F6';
$appRgb = hexToRgb($appColor);
$appRgbStr = implode(',', $appRgb);
?>
<style>
    .sso-header-container {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1.25rem;
        margin-bottom: 1.25rem;
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
        font-size: 1.1em;
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
    
    .auth-btn {
        background: linear-gradient(135deg, <?= e($appColor) ?> 0%, color-mix(in srgb, <?= e($appColor) ?> 85%, #000 15%) 100%) !important;
        box-shadow: 0 8px 28px rgba(<?= $appRgbStr ?>, 0.38) !important;
    }
    .auth-btn:hover {
        background: linear-gradient(135deg, color-mix(in srgb, <?= e($appColor) ?> 95%, #fff 5%) 0%, color-mix(in srgb, <?= e($appColor) ?> 80%, #000 20%) 100%) !important;
        box-shadow: 0 12px 40px rgba(<?= $appRgbStr ?>, 0.5) !important;
    }
</style>

        <div style="text-align:center; margin-bottom: 1.5rem;">
            <div class="sso-header-container" style="--app-brand-color: <?= e($appColor) ?>; --app-brand-rgb: <?= $appRgbStr ?>;">
                <div class="sso-logo-box krono-logo-anim">
                    <div class="auth-logo" style="margin: 0;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                </div>
                <div class="sso-arrow-anim">
                    <i class="bi bi-arrow-right"></i>
                </div>
                <div class="sso-logo-box app-logo-box" style="background: <?= e($appColor) ?>;">
                    <i class="bi bi-<?= e($appIcon) ?>"></i>
                </div>
            </div>
            
            <h1 class="auth-title">Autorisation d'accès</h1>
            <p class="auth-subtitle" style="margin-top:0.6rem;">
                <span class="sso-app-name-highlight"><?= e($appName) ?></span> souhaite accéder à votre compte.
            </p>
        </div>

        <div class="fade-in-up anim-delay-1 glass-card" style="margin:1.5rem 0;padding:1.25rem;">
            <p style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--krono-text-3);margin:0 0 1rem;">
                Permissions demandées
            </p>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.75rem;">
                <li style="display:flex;align-items:center;gap:.75rem;font-size:.9rem;">
                    <i class="bi bi-check-circle-fill" style="color:var(--krono-success);flex-shrink:0;"></i>
                    Identité (Nom et prénom)
                </li>
                <li style="display:flex;align-items:center;gap:.75rem;font-size:.9rem;">
                    <i class="bi bi-check-circle-fill" style="color:var(--krono-success);flex-shrink:0;"></i>
                    Contact (Adresse e-mail)
                </li>
                <li style="display:flex;align-items:center;gap:.75rem;font-size:.9rem;">
                    <i class="bi bi-check-circle-fill" style="color:var(--krono-success);flex-shrink:0;"></i>
                    Rôle et habilitations
                </li>
            </ul>
        </div>

        <p style="font-size:.82rem;color:var(--krono-text-3);margin-bottom:1.5rem;text-align:center;">
            Connecté en tant que <strong><?= e(auth()['prenom'] . ' ' . auth()['nom']) ?></strong><br>
            <a href="<?= url('/logout') ?>" class="auth-link" style="font-weight:700;margin-top:.35rem;display:inline-block;">Changer de compte</a>
        </p>

        <form method="POST" action="<?= url('/sso/authorize') ?>">
            <?= csrf() ?>
            <input type="hidden" name="client_id"    value="<?= e($client['client_id']) ?>">
            <input type="hidden" name="redirect_uri" value="<?= e($redirectUri) ?>">
            <input type="hidden" name="state"        value="<?= e($state ?? '') ?>">

            <div class="auth-row" style="gap:1rem;">
                <button type="submit" name="allow" value="1" class="auth-btn">
                    <i class="bi bi-check-lg" style="margin-right:.4rem;"></i> Autoriser
                </button>
                <button type="submit" name="deny" value="1" class="auth-btn-ghost">
                    <i class="bi bi-x-lg" style="margin-right:.4rem;"></i> Refuser
                </button>
            </div>
        </form>

    </div>
    </div>