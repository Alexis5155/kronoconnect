<?php
$error = \KronoConnect\Core\Session::getFlash('error');
?>

<div class="auth-panels-wrap" style="height: auto; max-width: 450px;">
    <div class="auth-panel active" style="position: relative; opacity: 1; transform: none; pointer-events: auto;">

        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="auth-logo" style="margin:0 auto 1.1rem; background: var(--krono-warning-light); color: var(--krono-warning);">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <div class="auth-title">Configuration requise</div>
            <div class="auth-subtitle" style="margin-top:0.3rem;">Votre groupe impose l'utilisation du MFA</div>
        </div>

        <?php if ($error): ?>
            <div style="background:var(--krono-danger-light); color:var(--krono-danger); padding:0.75rem 1rem; border-radius:6px; margin-bottom:1.5rem; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div style="background: var(--krono-surface-2); border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; text-align: center;">
            <p style="font-size: 0.9rem; color: var(--krono-text); margin-bottom: 1rem; font-weight: 600;">
                1. Scannez ce QR Code avec une application d'authentification (Google Authenticator, Authy, etc.) :
            </p>
            
            <div style="background: white; padding: 10px; display: inline-block; border-radius: 8px; margin-bottom: 1rem;">
                <img src="<?= e($qrCodeImage) ?>" alt="QR Code MFA" style="width: 180px; height: 180px;">
            </div>

            <p style="font-size: 0.8rem; color: var(--krono-text-3); margin-bottom: 0;">
                Si vous ne pouvez pas scanner, saisissez ce code manuellement :<br>
                <code style="font-size: 1rem; color: var(--krono-accent); font-weight: bold; margin-top: 0.5rem; display: inline-block; letter-spacing: 2px;">
                    <?= e(implode(' ', str_split($secret, 4))) ?>
                </code>
            </p>
        </div>

        <form method="POST" action="<?= url('/login/mfa-setup' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>">
            <?= csrf() ?>

            <div style="margin-bottom:1.25rem;">
                <p style="font-size: 0.9rem; color: var(--krono-text); margin-bottom: 0.5rem; font-weight: 600; text-align: center;">
                    2. Validez votre configuration
                </p>
                <p style="text-align:center; font-size: 0.85rem; color: var(--krono-text-2); margin-bottom: 1rem;">
                    Saisissez le code à 6 chiffres généré par votre application :
                </p>
                
                <div class="auth-field" style="max-width:200px; margin:0 auto;">
                    <input type="text" name="code" class="auth-input"
                           placeholder="123456" maxlength="6" pattern="\d{6}"
                           style="text-align:center; font-size:1.5rem; letter-spacing:0.3rem; padding: 0.75rem;" required autofocus autocomplete="off">
                </div>
            </div>

            <button type="submit" class="auth-btn">
                <i class="bi bi-check-lg" style="margin-right:.5rem;"></i> Activer et se connecter
            </button>
        </form>

        <div style="text-align:center; margin-top:1.25rem;">
            <a href="<?= url('/logout') ?>" class="auth-link">
                Annuler et se déconnecter
            </a>
        </div>

    </div>
</div>
