<?php
/**
 * Vue pour redéfinir son mot de passe après avoir cliqué sur le lien e-mail
 */

?>

<div class="auth-panels-wrap" id="panelsWrap">
    <div class="auth-panel active" style="opacity: 1; transform: scale(1); pointer-events: auto;">
        
        <div style="text-align:center; margin-bottom:1.75rem;">
            <div class="auth-logo mb-3" style="margin:0 auto 0.9rem;">
                <i class="bi bi-key-fill"></i>
            </div>
            <div class="auth-title">Nouveau mot de passe</div>
            <div class="auth-subtitle" style="margin-top:0.3rem;">
                Veuillez saisir votre nouveau mot de passe
            </div>
        </div>


        <form method="POST" action="<?= url('/reset-password/' . $token) ?>">
            <?= csrf() ?>

            <div style="margin-bottom:1.25rem;">
                <div style="margin-bottom:0.35rem;">
                    <label class="auth-label" style="margin:0;" for="password">Nouveau mot de passe</label>
                </div>
                <div class="auth-field">
                    <input type="password" name="password" id="password" class="auth-input"
                           placeholder="••••••••" required minlength="8" autofocus>
                    <i class="bi bi-lock auth-field-icon"></i>
                </div>
            </div>

            <div style="margin-bottom:1.5rem;">
                <div style="margin-bottom:0.35rem;">
                    <label class="auth-label" style="margin:0;" for="password_confirm">Confirmer le mot de passe</label>
                </div>
                <div class="auth-field">
                    <input type="password" name="password_confirm" id="password_confirm" class="auth-input"
                           placeholder="••••••••" required minlength="8">
                    <i class="bi bi-lock-fill auth-field-icon"></i>
                </div>
            </div>

            <button type="submit" class="auth-btn">
                Mettre à jour le mot de passe &nbsp;<i class="bi bi-check-lg"></i>
            </button>
        </form>

        <div style="text-align:center; margin-top:1.5rem;">
            <a href="<?= url('/login') ?>" class="auth-link">Retour à la connexion</a>
        </div>

    </div>
</div>
