<div class="auth-panels-wrap">
    <div class="auth-panel active">
        <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:1.5rem;">
            <a href="<?= url('/login' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" class="auth-link" style="font-size:1.2rem; line-height:1; text-decoration:none;">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <div class="auth-title" style="font-size:1.2rem;">Mot de passe oublié</div>
                <div class="auth-subtitle" style="margin-top:.15rem; font-size:.62rem;">
                    Un lien de réinitialisation vous sera envoyé
                </div>
            </div>
        </div>

        <?php if (!empty($forgotSent)): ?>
            <div class="krono-alert krono-alert--success" style="margin-bottom: 1.5rem;">
                <i class="bi bi-check-circle-fill" style="margin-right:0.5rem"></i> Un lien de réinitialisation a été envoyé à cette adresse si elle existe dans notre système.
            </div>
            <div style="text-align:center; margin-top:1.5rem;">
                <a href="<?= url('/login' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" class="auth-btn" style="text-decoration:none; display:inline-block;">
                    Retour à la connexion
                </a>
            </div>
        <?php else: ?>
            <?php if (!empty($forgotError)): ?>
                <div class="krono-alert krono-alert--danger" style="margin-bottom: 1.5rem;">
                    <i class="bi bi-exclamation-triangle-fill" style="margin-right:0.5rem"></i> <?= e($forgotError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/reset-password' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" id="formForgot">
                <?= csrf() ?>

                <div style="margin-bottom:1.25rem;">
                    <label class="auth-label" for="forgot-email">Adresse e-mail du compte</label>
                    <div class="auth-field">
                        <input type="email" name="email" id="forgot-email" class="auth-input"
                               placeholder="jean.dupont@collectivite.fr" required autofocus>
                        <i class="bi bi-envelope auth-field-icon"></i>
                    </div>
                </div>
                
                <?= \KronoConnect\Core\Captcha::render('reset') ?>

                <button type="submit" class="auth-btn" style="margin-top:1rem;">
                    <i class="bi bi-send" style="margin-right:.5rem;"></i>Envoyer le lien
                </button>
            </form>

            <div style="text-align:center; margin-top:1.5rem;">
                <a href="<?= url('/login' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>" class="auth-link">
                    Retour à la connexion
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
