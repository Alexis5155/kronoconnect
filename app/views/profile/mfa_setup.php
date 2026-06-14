<?php
/** @var array $user */
$error = \KronoConnect\Core\Session::getFlash('error');
?>

<div class="organic-profile-container" style="max-width: 650px; opacity: 0; animation: fadeSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;">
    <div class="organic-glass-panel content-panel" style="min-height: auto;">
        <header class="tab-header" style="margin-bottom: 2rem;">
            <div class="tab-header-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;"><i class="bi bi-shield-check"></i></div>
            <div>
                <h2 class="tab-title">Configuration du MFA</h2>
                <p class="tab-subtitle">Sécurisez votre compte en activant la double authentification.</p>
            </div>
        </header>

        <?php if ($error): ?>
            <div style="background:var(--krono-danger-light); color:var(--krono-danger); padding:0.75rem 1rem; border-radius:6px; margin-bottom:1.5rem; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('/profile/mfa-setup') ?>" class="organic-form">
            <?= csrf() ?>

            <div class="form-group">
                <label class="organic-label">Étape 1 : Scanner le QR Code</label>
                <p style="font-size: 0.85rem; color: var(--krono-text-3); margin-bottom: 0.5rem;">
                    Ouvrez une application d'authentification (Google Authenticator, Authy...) sur votre téléphone et scannez ce code :
                </p>
                
                <div style="display: flex; justify-content: center; margin: 1rem 0;">
                    <div style="background: white; padding: 12px; border-radius: 12px; border: 1px solid var(--krono-border); box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: inline-block;">
                        <img src="<?= e($qrCodeImage) ?>" alt="QR Code MFA" style="width: 160px; height: 160px; display: block;">
                    </div>
                </div>
                
                <div style="text-align: center; padding: 0.75rem 1rem; background: rgba(var(--krono-text-rgb, 26,31,54), 0.03); border-radius: 12px; border: 1px solid var(--krono-border); font-size: 0.85rem; color: var(--krono-text-2);">
                    <span style="display: block; margin-bottom: 0.4rem; color: var(--krono-text-3);">Vous ne pouvez pas scanner ? Saisissez ce code manuellement :</span>
                    <code style="color: var(--krono-accent); font-family: monospace; font-size: 1.1rem; font-weight: 700; letter-spacing: 2px;">
                        <?= e(implode(' ', str_split($secret, 4))) ?>
                    </code>
                </div>
            </div>

            <div class="form-group" style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--krono-border);">
                <label class="organic-label">Étape 2 : Valider la configuration</label>
                <p style="font-size: 0.85rem; color: var(--krono-text-3); margin-bottom: 0.75rem;">
                    Saisissez le code à 6 chiffres généré par l'application pour confirmer l'activation.
                </p>
                
                <div class="organic-input-wrap" style="max-width: 220px; margin: 0 auto;">
                    <i class="bi bi-shield-lock input-icon" style="left: 1rem;"></i>
                    <input type="text" name="code" class="organic-input with-icon" placeholder="123456" maxlength="6" pattern="\d{6}" required autofocus autocomplete="off" style="letter-spacing: 2px; font-weight: 600; text-align: center; padding-left: 3rem;">
                </div>
            </div>

            <div class="form-actions" style="margin-top: 0; padding-top: 1rem; border-top: 1px solid var(--krono-border); display: flex; gap: 1rem; align-items: center; justify-content: center;">
                <button type="submit" class="organic-btn primary">
                    <i class="bi bi-check2-circle"></i> Activer le MFA
                </button>
                <a href="<?= url('/profile') ?>" class="organic-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; background: var(--krono-surface-3); border: 1px solid var(--krono-border); color: var(--krono-text); padding: 0.65rem 1.2rem;">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

