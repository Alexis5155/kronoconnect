<div class="auth-panels-wrap">
    <div class="auth-panel active">

        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="auth-logo" style="margin:0 auto 1rem;">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <h1 class="auth-title">Créer un compte</h1>
            <p class="auth-subtitle">Rejoignez l'écosystème Krono</p>
        </div>

        <form method="POST" action="<?= url('/register') ?>" novalidate>
            <?= csrf() ?>

            <div class="auth-row">
                <div class="form-group">
                    <label class="auth-label" for="prenom">Prénom</label>
                    <div class="auth-field">
                        <input type="text" id="prenom" name="prenom" class="auth-input" autocomplete="given-name" maxlength="100" required>
                        <i class="bi bi-person auth-field-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="auth-label" for="nom">Nom</label>
                    <div class="auth-field">
                        <input type="text" id="nom" name="nom" class="auth-input" autocomplete="family-name" maxlength="100" required>
                        <i class="bi bi-person auth-field-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top:1rem;">
                <label class="auth-label" for="email">Adresse e-mail</label>
                <div class="auth-field">
                    <input type="email" id="email" name="email" class="auth-input" autocomplete="email" required>
                    <i class="bi bi-envelope auth-field-icon"></i>
                </div>
            </div>

            <div class="form-group" style="margin-top:1rem;">
                <label class="auth-label" for="password">Mot de passe</label>
                <div class="auth-field">
                    <input type="password" id="password" name="password" class="auth-input" autocomplete="new-password" minlength="8" required>
                    <i class="bi bi-lock auth-field-icon"></i>
                </div>
                <span style="font-size:.7rem; color:var(--krono-text-3); margin-top:.3rem; display:block;">8 caractères minimum</span>
            </div>

            <?php
            $settingsModel = new \KronoConnect\Models\AdminModel();
            $globalSettings = $settingsModel->getSettings();
            $gdprPrivacyUrl = $globalSettings['gdpr_privacy_url'] ?? '';
            ?>
            <?php if (!empty($gdprPrivacyUrl)): ?>
            <div class="form-group" style="margin-top:1.5rem; display: flex; align-items: flex-start; gap: 0.75rem;">
                <input type="checkbox" id="rgpd_consent" name="rgpd_consent" value="1" required style="margin-top: 0.2rem; cursor: pointer;">
                <label for="rgpd_consent" style="font-size: 0.85rem; color: var(--krono-text-2); cursor: pointer; line-height: 1.4;">
                    J'ai lu et j'accepte la <a href="<?= htmlspecialchars($gdprPrivacyUrl) ?>" target="_blank" style="color: var(--krono-accent); text-decoration: underline;">politique de confidentialité</a>. *
                </label>
            </div>
            <?php endif; ?>

            <button type="submit" class="auth-btn" style="margin-top:1.5rem;">
                <i class="bi bi-check-lg"></i> Créer mon compte
            </button>
        </form>

        <div style="text-align:center; margin-top:1.5rem;">
            <a href="<?= url('/login') ?>" class="auth-link">
                Déjà un compte ? Se connecter
            </a>
        </div>

    </div>
    </div>