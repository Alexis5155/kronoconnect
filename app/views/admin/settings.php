<?php
/** @var array $settings Réglages clé => valeur (BDD) */

$s = fn($key, $default = '') => $settings[$key] ?? $default;
?>

<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Réglages</span>
</nav>

<style>
/* ── LAYOUT & TABS ────────────────────────────────────────── */
.settings-layout { display: flex; gap: 2rem; align-items: flex-start; }
.settings-sidebar { width: 280px; flex-shrink: 0; position: sticky; top: calc(var(--krono-topbar-height) + 2rem); }
.settings-content { flex: 1; min-width: 0; }

.settings-nav { display: flex; flex-direction: column; gap: 0.5rem; }
.settings-nav-btn {
    display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem;
    border: none; background: transparent; color: var(--krono-text-2);
    font-size: 0.9rem; font-weight: 600; border-radius: var(--krono-radius);
    cursor: pointer; transition: all var(--krono-transition); text-align: left; width: 100%;
}
.settings-nav-btn i { font-size: 1.1rem; opacity: 0.7; }
.settings-nav-btn:hover { background: var(--krono-surface-3); color: var(--krono-accent); }
.settings-nav-btn.active { background: var(--krono-accent-light); color: var(--krono-accent); }
.settings-nav-btn.active i { opacity: 1; }

.settings-tab { display: none; animation: fadeInUp 0.4s ease both; }
.settings-tab.active { display: block; }

/* ── CARDS & SECTIONS ─────────────────────────────────────── */
.card-title-area { display: flex; gap: 1.25rem; align-items: center; margin-bottom: 2rem; }
.card-icon {
    width: 48px; height: 48px; border-radius: 14px; background: var(--krono-accent-light);
    color: var(--krono-accent); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;
}
.card-title-area h3 { margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--krono-text); }
.card-title-area p { margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--krono-text-3); }

/* ── TOGGLES ──────────────────────────────────────────────── */
.settings-toggles { display: flex; flex-direction: column; gap: 0.5rem; }
.toggle-item {
    display: flex; align-items: center; justify-content: space-between; padding: 1rem;
    background: var(--krono-surface-2); border-radius: 12px; border: 1px solid var(--krono-border); transition: all 0.3s ease;
}
.toggle-info { display: flex; flex-direction: column; gap: 0.15rem; }
.toggle-title { font-size: 0.9rem; font-weight: 700; color: var(--krono-text); }
.toggle-desc { font-size: 0.8rem; color: var(--krono-text-3); }
.form-hint { font-size: 0.8rem; color: var(--krono-text-3); margin-top: 0.4rem; display: flex; align-items: center; gap: 0.4rem; opacity: 0.8; }

/* ── UPDATES ──────────────────────────────────────────────── */
.update-hero { background: var(--krono-surface-2); border: 1px solid var(--krono-border); border-radius: 16px; padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
.u-hero-main { display: flex; align-items: center; gap: 1.25rem; }
.u-hero-icon { width: 56px; height: 56px; background: var(--krono-accent); color: white; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; box-shadow: 0 8px 20px rgba(var(--krono-accent-rgb), 0.3); }
.u-hero-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--krono-text-3); letter-spacing: 0.5px; }
.u-hero-value { font-size: 1.5rem; font-weight: 900; color: var(--krono-text); }
.u-hero-status { font-weight: 700; font-size: 0.9rem; }

.terminal-box { margin-top: 1.5rem; background: #0f172a; border-radius: 12px; overflow: hidden; border: 1px solid #1e293b; font-family: 'Fira Code', monospace; }
.terminal-head { padding: 0.5rem 1rem; background: #1e293b; color: #94a3b8; font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; }
.terminal-body { padding: 1rem; color: #e2e8f0; font-size: 0.85rem; line-height: 1.6; max-height: 300px; overflow-y: auto; white-space: pre-wrap; }

/* ── RESPONSIVE ───────────────────────────────────────────── */
@media (max-width: 1024px) {
    .settings-layout { flex-direction: column; gap: 1.5rem; }
    .settings-sidebar { width: 100%; position: static; }
    .settings-nav { flex-direction: row; flex-wrap: wrap; gap: 0.5rem; }
    .settings-nav-btn { flex: 1 1 auto; justify-content: center; min-width: 140px; }
}

@media (max-width: 768px) {
    .krono-grid-2 { grid-template-columns: 1fr; }
    .krono-form-row { flex-direction: column; }
    .krono-form-row > * { width: 100%; }
    .update-hero { flex-direction: column; text-align: center; gap: 1.25rem; }
}
</style>

<div class="page-header">
    <div>
        <div class="page-header__title">Paramètres globaux</div>
        <div class="page-header__subtitle">Configuration de la passerelle centrale KronoConnect.</div>
    </div>
</div>

<div class="settings-layout">
    <!-- Navigation latérale -->
    <aside class="settings-sidebar">
        <div class="fade-in-up anim-delay-1 glass-card settings-nav-card">
            <nav class="settings-nav">
                <button type="button" class="settings-nav-btn active" data-tab="identite">
                    <i class="bi bi-display"></i> <span>Identité</span>
                </button>
                <button type="button" class="settings-nav-btn" data-tab="comptes">
                    <i class="bi bi-shield-lock"></i> <span>Comptes</span>
                </button>
                <button type="button" class="settings-nav-btn" data-tab="smtp">
                    <i class="bi bi-envelope-at"></i> <span>E-mails</span>
                </button>
                <button type="button" class="settings-nav-btn" data-tab="captcha">
                    <i class="bi bi-shield-check"></i> <span>Captcha</span>
                </button>
                <button type="button" class="settings-nav-btn" data-tab="updates">
                    <i class="bi bi-cloud-arrow-down"></i> <span>Mises à jour</span>
                </button>
                <button type="button" class="settings-nav-btn" data-tab="bdd">
                    <i class="bi bi-database-gear"></i> <span>Base de données</span>
                </button>
                <button type="button" class="settings-nav-btn" data-tab="rgpd">
                    <i class="bi bi-file-earmark-lock"></i> <span>RGPD</span>
                </button>
            </nav>
        </div>
    </aside>

    <!-- Contenu principal -->
    <main class="settings-content">
        <form method="POST" action="<?= url('/admin/settings') ?>" id="form-settings" enctype="multipart/form-data">
            <?= csrf() ?>

            <!-- ══ TAB: IDENTITÉ ══ -->
            <div class="settings-tab active" id="tab-identite">
                <div class="glass-card">
                    <div class="card-title-area">
                        <div class="card-icon"><i class="bi bi-card-heading"></i></div>
                        <div>
                            <h3>Identité du site</h3>
                            <p>Personnalisez le nom et les textes publics de votre instance.</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="krono-label">Nom de l'application</label>
                        <input type="text" name="app_name" class="krono-input" value="<?= e($s('app_name', 'KronoConnect')) ?>">
                        <div class="form-hint"><i class="bi bi-info-circle"></i> Utilisé comme titre principal dans les onglets et les communications.</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="krono-label">Nom de la collectivité</label>
                        <input type="text" name="collectivite" class="krono-input" value="<?= e($s('collectivite', 'Ma Mairie')) ?>">
                        <div class="form-hint"><i class="bi bi-info-circle"></i> S'affiche sur vos documents et dans le pied de page du site.</div>
                    </div>

                    <div class="form-group">
                        <label class="krono-label">Phrase d'accroche du portail (Sous-titre)</label>
                        <input type="text" name="portal_hero_sub" class="krono-input" value="<?= e($s('portal_hero_sub', 'Accéder à toutes vos applications métier avec un seul compte.')) ?>">
                        <div class="form-hint"><i class="bi bi-info-circle"></i> S'affiche sur le portail des applications après la connexion.</div>
                    </div>

                    <div class="krono-divider"></div>

                    <div class="form-group">
                        <label class="krono-label">Logo de la collectivité (carré)</label>
                        <div style="display: flex; gap: 1.25rem; align-items: center;">
                            <div id="logo-preview-container" style="width: 72px; height: 72px; border-radius: 14px; background: var(--krono-surface-3); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px dashed var(--krono-border-strong); flex-shrink: 0;">
                                <?php if ($s('logo_uuid')): ?>
                                    <img src="<?= url('/public/logo') ?>?v=<?= $s('logo_uuid') ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; background: white;" id="logo-preview-img">
                                    <i class="bi bi-image" style="font-size: 1.8rem; color: var(--krono-text-3); display: none;" id="logo-preview-icon"></i>
                                <?php else: ?>
                                    <img src="" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; background: white; display: none;" id="logo-preview-img">
                                    <i class="bi bi-image" style="font-size: 1.8rem; color: var(--krono-text-3);" id="logo-preview-icon"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <input type="file" name="logo" id="logo-upload" accept="image/png, image/jpeg, image/webp" class="krono-input" style="padding: 0.5rem; cursor: pointer;">
                                <div class="form-hint" style="margin-top: 0.5rem;"><i class="bi bi-magic"></i> Le logo sera automatiquement recadré en carré (1:1) et converti au format PNG pour une intégration parfaite.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ TAB: COMPTES ══ -->
            <div class="settings-tab" id="tab-comptes">
                <div class="fade-in-up anim-delay-2 glass-card">
                    <div class="card-title-area">
                        <div class="card-icon"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <h3>Gestion des comptes</h3>
                            <p>Règles d'inscription et de modification des profils utilisateurs.</p>
                        </div>
                    </div>

                    <div class="settings-toggles">
                        <div class="toggle-item" style="border-color: rgba(245, 158, 11, 0.35); background: rgba(245, 158, 11, 0.03);">
                            <div class="toggle-info">
                                <span class="toggle-title" style="color: #d97706; display: flex; align-items: center; gap: 0.4rem;">
                                    <i class="bi bi-gear-wide-connected" style="display: inline-block;"></i> Activer le Mode Maintenance
                                </span>
                                <span class="toggle-desc">Restreint l'accès à la passerelle centrale KronoConnect. Seuls les administrateurs avec la permission <code>kc.toggle.maintenance</code> pourront se connecter.</span>
                            </div>
                            <label class="krono-switch">
                                <input type="checkbox" name="maintenance_mode" value="1" <?= (($settings['maintenance_mode'] ?? '0') === '1') ? 'checked' : '' ?>>
                                <span class="krono-slider"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <span class="toggle-title">Autoriser la création de compte</span>
                                <span class="toggle-desc">Les utilisateurs peuvent s'inscrire eux-mêmes via le formulaire public.</span>
                            </div>
                            <label class="krono-switch">
                                <input type="checkbox" name="allow_self_register" value="1" <?= (!isset($settings['allow_self_register']) || $settings['allow_self_register'] === '1') ? 'checked' : '' ?>>
                                <span class="krono-slider"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <span class="toggle-title">Approbation manuelle des inscriptions</span>
                                <span class="toggle-desc">Les nouveaux comptes restent en attente jusqu'à validation par un administrateur.</span>
                            </div>
                            <label class="krono-switch">
                                <input type="checkbox" name="manual_approval" value="1" <?= (($settings['manual_approval'] ?? '0') === '1') ? 'checked' : '' ?>>
                                <span class="krono-slider"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <span class="toggle-title">Autoriser la modification d'e-mail</span>
                                <span class="toggle-desc">Les utilisateurs peuvent modifier leur e-mail depuis leur profil (si autorisé individuellement).</span>
                            </div>
                            <label class="krono-switch">
                                <input type="checkbox" name="allow_email_change" value="1" <?= (isset($settings['allow_email_change']) && $settings['allow_email_change'] === '1') ? 'checked' : '' ?>>
                                <span class="krono-slider"></span>
                            </label>
                        </div>
                    </div>

                    <?php
                    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                    if (!$isHttps): ?>
                        <div style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 12px; padding: 1.25rem; margin-top: 1.5rem; display: flex; align-items: flex-start; gap: 0.75rem;">
                            <div style="font-size: 1.25rem; color: #D97706; line-height: 1;"><i class="bi bi-shield-exclamation"></i></div>
                            <div>
                                <strong style="color: #D97706; display: block; font-size: 0.9rem; margin-bottom: 0.2rem;">
                                    Connexion non sécurisée (HTTP)
                                </strong>
                                <span style="font-size: 0.8rem; color: var(--krono-text-3); line-height: 1.4; display: block;">
                                    KronoConnect n'est pas accessible en HTTPS. Les clés de sécurité (WebAuthn/FIDO2) sont automatiquement masquées pour les utilisateurs sur leur profil. Configurez un certificat SSL/TLS pour activer le support des clés de sécurité.
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ TAB: SMTP ══ -->
            <div class="settings-tab" id="tab-smtp">
                <div class="fade-in-up anim-delay-3 glass-card">
                    <div class="card-title-area">
                        <div class="card-icon"><i class="bi bi-envelope-at"></i></div>
                        <div>
                            <h3>Configuration E-mail</h3>
                            <p>Serveur SMTP utilisé pour les e-mails d'inscription et de réinitialisation de mot de passe.</p>
                        </div>
                    </div>

                    <div class="krono-form-row">
                        <div class="form-group" style="flex: 2;">
                            <label class="krono-label">Serveur SMTP</label>
                            <input type="text" name="smtp_host" class="krono-input" placeholder="smtp.example.com" value="<?= e($s('smtp_host')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="krono-label">Port</label>
                            <input type="number" name="smtp_port" class="krono-input" placeholder="587" value="<?= e($s('smtp_port')) ?>">
                        </div>
                    </div>

                    <div class="krono-grid-2">
                        <div class="form-group">
                            <label class="krono-label">Utilisateur / Login</label>
                            <input type="text" name="smtp_user" autocomplete="off" class="krono-input" placeholder="user@example.com" value="<?= e($s('smtp_user')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="krono-label">Mot de passe</label>
                            <input type="password" name="smtp_pass" autocomplete="new-password" class="krono-input" placeholder="<?= !empty($s('smtp_pass')) ? '•••••••• (enregistré)' : '••••••••' ?>">
                            <small class="krono-form-help" style="color: #6c757d; font-size: 0.85em; display: block; margin-top: 4px;">Laissez vide pour conserver le mot de passe actuel.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="krono-label">Sécurité du transport</label>
                        <select name="smtp_encryption" class="krono-input">
                            <option value="tls" <?= ($s('smtp_encryption', 'tls')) === 'tls' ? 'selected' : '' ?>>STARTTLS (Recommandé)</option>
                            <option value="ssl" <?= $s('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL/TLS</option>
                            <option value="none" <?= $s('smtp_encryption') === 'none' ? 'selected' : '' ?>>Aucune (non sécurisé)</option>
                        </select>
                    </div>

                    <div class="krono-grid-2">
                        <div class="form-group">
                            <label class="krono-label">E-mail de l'expéditeur</label>
                            <input type="email" name="from_email" class="krono-input" placeholder="noreply@example.com" value="<?= e($s('from_email')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="krono-label">Nom de l'expéditeur</label>
                            <input type="text" name="from_name" class="krono-input" placeholder="KronoConnect" value="<?= e($s('from_name')) ?>">
                        </div>
                    </div>
                </div>

                <div class="fade-in-up anim-delay-4 glass-card glass-card--sm" style="margin-top: 1.5rem;">
                    <div class="krono-section-title" style="margin-top: 0; margin-bottom: 1rem;">
                        <i class="bi bi-send-check"></i> Tester la configuration
                    </div>
                    <label class="krono-label">Adresse de destination</label>
                    <div style="display: flex; gap: 0.75rem; align-items: stretch; flex-wrap: wrap;">
                        <input type="email" id="test_email_dest" class="krono-input" placeholder="destinataire@exemple.com" value="<?= e(\KronoConnect\Core\Session::user()['email'] ?? '') ?>" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                        <button type="button" class="btn-krono btn-krono--secondary" onclick="sendTestEmail()" id="btn-test-smtp" style="white-space: nowrap;">
                            <i class="bi bi-send"></i> Envoyer le test
                        </button>
                    </div>
                    <div id="test-smtp-status" style="margin-top: 1rem; display: none;"></div>
                </div>
            </div>

            <!-- ══ TAB: CAPTCHA ══ -->
            <div class="settings-tab" id="tab-captcha">
                <div class="fade-in-up anim-delay-5 glass-card">
                    <div class="card-title-area">
                        <div class="card-icon"><i class="bi bi-shield-check"></i></div>
                        <div>
                            <h3>Protection anti-robot</h3>
                            <p>Configurez le système de validation CAPTCHA pour sécuriser les formulaires publics.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="krono-label">Fournisseur de CAPTCHA</label>
                        <select name="captcha_provider" class="krono-input" id="captcha-provider">
                            <option value="none" <?= ($s('captcha_provider', 'none')) === 'none' ? 'selected' : '' ?>>Aucun (désactivé)</option>
                            <option value="image" <?= $s('captcha_provider') === 'image' ? 'selected' : '' ?>>Image locale (natif, pas de clé requise)</option>
                            <option value="recaptcha" <?= $s('captcha_provider') === 'recaptcha' ? 'selected' : '' ?>>Google reCAPTCHA v2</option>
                            <option value="hcaptcha" <?= $s('captcha_provider') === 'hcaptcha' ? 'selected' : '' ?>>hCaptcha</option>
                            <option value="turnstile" <?= $s('captcha_provider') === 'turnstile' ? 'selected' : '' ?>>Cloudflare Turnstile</option>
                        </select>
                        <div class="form-hint" id="hint-image" style="display:none;">
                            <i class="bi bi-info-circle"></i> Génère une image de sécurité sur votre serveur. Aucune API externe requise.
                        </div>
                        <div class="form-hint" id="hint-external" style="display:none; justify-content: flex-end; margin-top: 0.25rem;">
                            <a href="#" id="captcha-link" target="_blank" rel="noopener" style="text-decoration: none; color: var(--krono-accent); font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem;">
                                Obtenir mes clés sur le site du fournisseur <i class="bi bi-box-arrow-up-right" style="font-size: 0.7rem;"></i>
                            </a>
                        </div>
                    </div>

                    <div id="captcha-keys" style="<?= in_array($s('captcha_provider'), ['recaptcha', 'hcaptcha', 'turnstile']) ? '' : 'display:none;' ?>">
                        <div class="form-group">
                            <label class="krono-label">Clé du site (Site Key)</label>
                            <input type="text" name="captcha_site_key" class="krono-input" placeholder="0x0000000000000000000000" value="<?= e($s('captcha_site_key')) ?>">
                            <div class="form-hint" id="hint-recaptcha-v2" style="display:none;"><i class="bi bi-exclamation-circle"></i> Utilisez une clé <strong>reCAPTCHA v2 "Case à cocher"</strong>. Les clés v3 ne sont pas compatibles.</div>
                            <div class="form-hint" id="hint-turnstile" style="display:none;"><i class="bi bi-info-circle"></i> Assurez-vous d'avoir autorisé votre domaine (ex: <code>localhost</code>) dans votre console Cloudflare.</div>
                        </div>
                        <div class="form-group">
                            <label class="krono-label">Clé secrète (Secret Key)</label>
                            <input type="password" name="captcha_secret_key" class="krono-input" placeholder="<?= $s('captcha_secret_key') ? '•••••••• (enregistrée)' : 'Saisir la clé secrète' ?>" value="">
                        </div>
                    </div>

                    <div id="captcha-preview-area" style="margin-top: 1.5rem; padding: 1.25rem; background: var(--krono-surface-2); border-radius: var(--krono-radius); border: 1px dashed var(--krono-border-strong); display: none;">
                        <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--krono-text-3); margin-bottom: 1rem; letter-spacing: 0.5px;">Aperçu du rendu</div>
                        <div id="captcha-render-placeholder" style="display: flex; justify-content: center; min-height: 50px; align-items: center;"></div>
                    </div>

                    <div class="settings-toggles" style="margin-top: 1.5rem;">
                        <div class="toggle-item">
                            <div class="toggle-info">
                                <span class="toggle-title">Activer sur la page de connexion</span>
                                <span class="toggle-desc">Affiche le CAPTCHA lors de la saisie des identifiants.</span>
                            </div>
                            <label class="krono-switch">
                                <input type="checkbox" name="captcha_login" value="1" <?= (($settings['captcha_login'] ?? '0') === '1') ? 'checked' : '' ?>>
                                <span class="krono-slider"></span>
                            </label>
                        </div>
                        <div class="toggle-item">
                            <div class="toggle-info">
                                <span class="toggle-title">Activer sur l'inscription</span>
                                <span class="toggle-desc">Protège le formulaire de création de compte public.</span>
                            </div>
                            <label class="krono-switch">
                                <input type="checkbox" name="captcha_register" value="1" <?= (($settings['captcha_register'] ?? '1') === '1') ? 'checked' : '' ?>>
                                <span class="krono-slider"></span>
                            </label>
                        </div>
                        <div class="toggle-item">
                            <div class="toggle-info">
                                <span class="toggle-title">Activer sur l'oubli de mot de passe</span>
                                <span class="toggle-desc">Empêche l'énumération d'e-mails via la récupération.</span>
                            </div>
                            <label class="krono-switch">
                                <input type="checkbox" name="captcha_reset" value="1" <?= (($settings['captcha_reset'] ?? '1') === '1') ? 'checked' : '' ?>>
                                <span class="krono-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ TAB: RGPD ══ -->
            <div class="settings-tab" id="tab-rgpd">
                <div class="fade-in-up anim-delay-5 glass-card" style="margin-bottom: 1.5rem;">
                    <div class="card-title-area">
                        <div class="card-icon"><i class="bi bi-file-earmark-lock"></i></div>
                        <div>
                            <h3>Conservation des données</h3>
                            <p>Définissez les délais de conservation pour respecter la réglementation (RGPD).</p>
                        </div>
                    </div>
                    <div class="krono-grid-2">
                        <div class="form-group">
                            <label class="krono-label">Purge des comptes inactifs (mois)</label>
                            <input type="number" name="gdpr_retention_accounts_months" class="krono-input" value="<?= htmlspecialchars($s('gdpr_retention_accounts_months', '36')) ?>" min="1" max="120" required>
                            <div class="form-hint"><i class="bi bi-info-circle"></i> La CNIL recommande 36 mois (3 ans) après le dernier contact.</div>
                        </div>
                        <div class="form-group">
                            <label class="krono-label">Conservation des journaux (mois)</label>
                            <input type="number" name="gdpr_retention_logs_months" class="krono-input" value="<?= htmlspecialchars($s('gdpr_retention_logs_months', '6')) ?>" min="1" max="24" required>
                            <div class="form-hint"><i class="bi bi-info-circle"></i> La CNIL recommande entre 6 et 12 mois pour les logs techniques de connexion.</div>
                        </div>
                    </div>
                </div>

                <div class="fade-in-up anim-delay-6 glass-card" style="margin-bottom: 1.5rem;">
                    <div class="card-title-area">
                        <div class="card-icon"><i class="bi bi-link-45deg"></i></div>
                        <div>
                            <h3>Pages légales</h3>
                            <p>Si renseignées, ces URL s'afficheront sur les pages publiques (inscription, connexion).</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="krono-label">URL Politique de confidentialité</label>
                        <input type="url" name="gdpr_privacy_url" class="krono-input" value="<?= htmlspecialchars($s('gdpr_privacy_url', '')) ?>" placeholder="https://monsite.fr/politique-confidentialite">
                        <div class="form-hint"><i class="bi bi-info-circle"></i> Si renseignée, une case de consentement sera ajoutée au formulaire d'inscription.</div>
                    </div>
                    <div class="form-group" style="margin-top: 1rem;">
                        <label class="krono-label">URL Mentions légales</label>
                        <input type="url" name="gdpr_legal_url" class="krono-input" value="<?= htmlspecialchars($s('gdpr_legal_url', '')) ?>" placeholder="https://monsite.fr/mentions-legales">
                    </div>
                </div>
            </div>

            <!-- ACTIONS -->
            <div class="krono-form-actions" id="static-save-row">
                <button type="submit" class="btn-krono btn-krono--primary">
                    <i class="bi bi-check-circle"></i> Enregistrer les paramètres
                </button>
            </div>
        </form>

        <!-- ══ TAB: BDD ══ -->
        <div class="settings-tab" id="tab-bdd">
                <!-- Santé et Métriques -->
                <div class="fade-in-up anim-delay-6 glass-card" style="margin-bottom: 1.5rem;">
                    <div class="card-title-area">
                        <div class="card-icon"><i class="bi bi-activity"></i></div>
                        <div>
                            <h3>Santé de la Base de données</h3>
                            <p>Aperçu en temps réel de l'état du stockage et des migrations.</p>
                        </div>
                    </div>
                    <div class="update-hero" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; background: transparent; padding: 0; border: none; margin-bottom: 0;">
                        <div class="glass-card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
                            <div class="u-hero-icon" style="background: var(--krono-surface-3); color: var(--krono-text); width: 48px; height: 48px; font-size: 1.5rem; box-shadow: none;"><i class="bi bi-hdd-stack"></i></div>
                            <div>
                                <div class="u-hero-label">Taille Globale</div>
                                <div class="u-hero-value" style="font-size: 1.25rem;"><?= number_format($dbMetrics['size_mb'] ?? 0, 2) ?> <span style="font-size: 0.8rem; color: var(--krono-text-3);">Mo</span></div>
                            </div>
                        </div>
                        <div class="glass-card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
                            <div class="u-hero-icon" style="background: var(--krono-surface-3); color: var(--krono-text); width: 48px; height: 48px; font-size: 1.5rem; box-shadow: none;"><i class="bi bi-table"></i></div>
                            <div>
                                <div class="u-hero-label">Tables</div>
                                <div class="u-hero-value" style="font-size: 1.25rem;"><?= $dbMetrics['tables'] ?? 0 ?></div>
                            </div>
                        </div>
                        <div class="glass-card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
                            <?php $pending = $dbMetrics['pending_migrations'] ?? 0; ?>
                            <div class="u-hero-icon" style="background: <?= $pending > 0 ? 'var(--krono-danger)' : 'var(--krono-success)' ?>; width: 48px; height: 48px; font-size: 1.5rem; box-shadow: none; color: white;">
                                <i class="bi <?= $pending > 0 ? 'bi-exclamation-triangle' : 'bi-check-circle' ?>"></i>
                            </div>
                            <div>
                                <div class="u-hero-label">Migrations Appliquées</div>
                                <div class="u-hero-value" style="font-size: 1.25rem;">
                                    <?= $dbMetrics['applied_migrations'] ?? 0 ?> / <?= $dbMetrics['total_migrations'] ?? 0 ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (($dbMetrics['pending_migrations'] ?? 0) > 0): ?>
                    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--krono-radius); color: #ef4444; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="bi bi-exclamation-octagon-fill"></i> <strong>Attention :</strong> <?= $dbMetrics['pending_migrations'] ?> migration(s) en attente. Une mise à jour système est peut-être incomplète.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Outils de maintenance -->
                <div class="fade-in-up anim-delay-7 glass-card" style="margin-bottom: 1.5rem;">
                    <div class="card-title-area">
                        <div class="card-icon"><i class="bi bi-tools"></i></div>
                        <div>
                            <h3>Outils de maintenance</h3>
                            <p>Sauvegarde manuelle des données et de la structure SQL.</p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--krono-surface-2); padding: 1.25rem; border-radius: var(--krono-radius); border: 1px solid var(--krono-border);">
                        <div>
                            <div style="font-weight: 700; margin-bottom: 0.25rem;">Générer une sauvegarde SQL</div>
                            <div style="font-size: 0.85rem; color: var(--krono-text-3);">Télécharge un fichier <code>.sql</code> contenant l'intégralité des tables et données actuelles de l'application.</div>
                        </div>
                        <a href="<?= url('/admin/settings/db/export') ?>" class="btn-krono btn-krono--secondary" target="_blank" style="white-space: nowrap;">
                            <i class="bi bi-download"></i> Télécharger le dump
                        </a>
                    </div>
                </div>

                <!-- Paramètres BDD -->
                <div class="fade-in-up anim-delay-8 glass-card">
                    <div class="card-title-area">
                        <div class="card-icon" style="background:var(--krono-danger-bg, rgba(239,68,68,0.1)); color:var(--krono-danger, #ef4444);"><i class="bi bi-database-fill-exclamation"></i></div>
                        <div>
                            <h3 class="text-danger" style="color: #ef4444; margin: 0; font-size: 1.1rem; font-weight: 800;">Paramètres de connexion</h3>
                            <p class="text-danger-muted" style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--krono-text-3);">Paramètres critiques. Une modification erronée peut rendre le site inaccessible.</p>
                        </div>
                    </div>
                    <form action="<?= url('/admin/settings/db') ?>" method="POST" id="form-db">
                        <?= csrf() ?>
                        <div class="krono-form-row">
                            <div class="form-group" style="flex: 2;">
                                <label class="krono-label">Hôte SQL</label>
                                <input type="text" name="db_host" class="krono-input" value="<?= e($dbConfig['host'] ?? 'localhost') ?>">
                            </div>
                            <div class="form-group">
                                <label class="krono-label">Port</label>
                                <input type="number" name="db_port" class="krono-input" value="<?= e($dbConfig['port'] ?? 3306) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="krono-label">Nom de la base</label>
                            <input type="text" name="db_name" class="krono-input" value="<?= e($dbConfig['database'] ?? '') ?>">
                        </div>
                        <div class="krono-grid-2">
                            <div class="form-group">
                                <label class="krono-label">Utilisateur SQL</label>
                                <input type="text" name="db_username" class="krono-input" value="<?= e($dbConfig['username'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="krono-label">Mot de passe SQL</label>
                                <input type="password" name="db_password" class="krono-input" placeholder="••••••••">
                            </div>
                        </div>
                        <div class="krono-form-actions" style="border-top: none; padding-top: 0; background: transparent; justify-content: flex-end;">
                            <button type="button" class="btn-krono btn-krono--danger" onclick="confirmDbChange()">
                                <i class="bi bi-shield-lock"></i> Tester et enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ══ TAB: UPDATES ══ -->
            <div class="settings-tab" id="tab-updates">
                <div class="fade-in-up anim-delay-6 glass-card" style="margin-bottom: 1.5rem;">
                    <div class="card-title-area">
                        <div class="card-icon"><i class="bi bi-cloud-arrow-down"></i></div>
                        <div>
                            <h3>Mise à jour KronoConnect</h3>
                            <p>Maintenance et évolution du système KronoConnect.</p>
                        </div>
                    </div>
                    <div class="update-hero">
                        <div class="u-hero-main">
                            <div class="u-hero-icon"><i class="bi bi-hexagon-fill"></i></div>
                            <div>
                                <div class="u-hero-label">Version actuelle</div>
                                <div class="u-hero-value">v<?= e($appConfig['version'] ?? '0.0.1') ?></div>
                            </div>
                        </div>
                        <div class="u-hero-status" id="u-status-badge"><span class="u-spinner"></span> Vérification...</div>
                    </div>
                    <div class="update-section-head" style="display:flex; justify-content: space-between; align-items:center; margin-bottom:1rem; flex-wrap: wrap; gap: 1rem;">
                        <h4 style="margin:0; font-size:0.9rem; font-weight:800;">État du serveur</h4>
                        <button type="button" class="btn-krono btn-krono--ghost btn-krono--sm" onclick="checkUpdate()">
                            <i class="bi bi-arrow-clockwise"></i> Actualiser
                        </button>
                    </div>
                    <div id="u-update-area"></div>
                </div>

                <div class="fade-in-up anim-delay-7 glass-card" id="u-changelog-area" style="display:none; margin-bottom: 1.5rem;">
                    <div class="card-title-area" style="margin-bottom: 1rem;">
                        <div class="card-icon"><i class="bi bi-journal-text"></i></div>
                        <div>
                            <h3 id="u-changelog-title">Notes de la dernière mise à jour</h3>
                            <p>Modifications apportées par la dernière version récupérée depuis GitHub.</p>
                        </div>
                    </div>
                    <div id="u-changelog-body" style="background: var(--krono-surface-2); border: 1px solid var(--krono-border); border-radius: 12px; padding: 0.6rem 1.25rem; max-height: 300px; overflow-y: auto;">
                    </div>
                </div>

                <div class="fade-in-up anim-delay-8 glass-card" style="background:transparent; border:none; padding:0; box-shadow:none;">
                    <div class="terminal-box" id="u-terminal" style="display:none; margin-top: 0;">
                        <div class="terminal-head">CONSOLE DE MAINTENANCE</div>
                        <div class="terminal-body" id="u-term-body"></div>
                    </div>
                </div>
            </div>
    </main>
</div>

<!-- Modale — Recadrage Logo -->
<div class="krono-modal-backdrop" id="logo-crop-modal">
    <div class="glass-card krono-modal-content" style="max-width: 600px; width: 90%;">
        <h3 class="modal-title">Recadrer le logo</h3>
        <p class="modal-text">Ajustez la zone pour obtenir un logo parfaitement carré.</p>
        <div style="margin-top: 1rem; max-height: 50vh; overflow: hidden; background: #fafafa; border-radius: 8px;">
            <img id="logo-cropper-img" src="" style="max-width: 100%; display: block;">
        </div>
        <div class="modal-buttons" style="margin-top: 1.5rem;">
            <button type="button" class="btn-krono btn-krono--secondary" id="btn-cancel-crop">Annuler</button>
            <button type="button" class="btn-krono btn-krono--primary" id="btn-save-crop">Valider le recadrage</button>
        </div>
    </div>
</div>

<!-- Modale — BDD -->
<div class="krono-modal-backdrop" id="dbModal">
    <div class="glass-card krono-modal-content">
        <div class="modal-icon-box" style="background:var(--krono-danger-bg, rgba(239,68,68,0.1)); color:var(--krono-danger, #ef4444);"><i class="bi bi-shield-lock-fill"></i></div>
        <h3 class="modal-title">Authentification requise</h3>
        <p class="modal-text">Entrez votre mot de passe administrateur pour confirmer la modification de la base de données.</p>
        <div class="form-group" style="margin-top:1.25rem;">
            <input type="password" id="confirm_password_db" class="krono-input" placeholder="Votre mot de passe">
        </div>
        <div class="modal-buttons">
            <button type="button" class="btn-krono btn-krono--secondary" onclick="document.getElementById('dbModal').classList.remove('is-open')">Annuler</button>
            <button type="button" class="btn-krono btn-krono--danger" onclick="submitDbForm()">Confirmer</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>

<script>
function confirmDbChange() { document.getElementById('dbModal').classList.add('is-open'); }
function submitDbForm() {
    const pwd = document.getElementById('confirm_password_db').value;
    if (!pwd) return alert('Mot de passe requis.');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'confirm_password';
    input.value = pwd;
    document.getElementById('form-db').appendChild(input);
    document.getElementById('form-db').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.settings-nav-btn');
    const sections = document.querySelectorAll('.settings-tab');

    function switchTab(tabId) {
        tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
        sections.forEach(s => s.classList.toggle('active', s.id === 'tab-' + tabId));
        const isExcluded = ['updates', 'bdd'].includes(tabId);
        const saveRow = document.getElementById('static-save-row');
        if (saveRow) saveRow.style.display = isExcluded ? 'none' : 'flex';
        window.location.hash = tabId;
        if (tabId === 'updates') checkUpdate();
    }

    tabs.forEach(tab => tab.addEventListener('click', () => switchTab(tab.dataset.tab)));

    // Prévisualisation et recadrage du logo
    let cropper = null;

    function closeLogoCropModal() {
        document.getElementById('logo-crop-modal').classList.remove('is-open');
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    const btnCancelCrop = document.getElementById('btn-cancel-crop');
    if (btnCancelCrop) {
        btnCancelCrop.addEventListener('click', function() {
            closeLogoCropModal();
            document.getElementById('logo-upload').value = '';
        });
    }

    const logoUpload = document.getElementById('logo-upload');
    if (logoUpload) {
        logoUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) {
                closeLogoCropModal();
                return;
            }

            const url = URL.createObjectURL(file);
            const imgElement = document.getElementById('logo-cropper-img');
            const modalElement = document.getElementById('logo-crop-modal');
            
            if (modalElement.parentNode !== document.body) {
                document.body.appendChild(modalElement);
            }
            
            imgElement.onload = function() {
                modalElement.classList.add('is-open');

                setTimeout(() => {
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(imgElement, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 1,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                    });
                }, 100);
            };
            imgElement.src = url;
        });
    }

    const btnSaveCrop = document.getElementById('btn-save-crop');
    if (btnSaveCrop) {
        btnSaveCrop.addEventListener('click', function() {
            if (!cropper) return;
            const canvas = cropper.getCroppedCanvas({ width: 512, height: 512, fillColor: 'transparent' });
            
            canvas.toBlob(blob => {
                const dt = new DataTransfer();
                dt.items.add(new File([blob], 'logo.png', { type: 'image/png' }));
                document.getElementById('logo-upload').files = dt.files;
                
                const previewImg = document.getElementById('logo-preview-img');
                const previewIcon = document.getElementById('logo-preview-icon');
                if (previewImg) {
                    previewImg.src = URL.createObjectURL(blob);
                    previewImg.style.display = 'block';
                }
                if (previewIcon) {
                    previewIcon.style.display = 'none';
                }

                closeLogoCropModal();
            }, 'image/png');
        });
    }

    // Toggle Captcha Keys
    const providerSelect = document.getElementById('captcha-provider');
    const keysWrap = document.getElementById('captcha-keys');
    const hintImage = document.getElementById('hint-image');
    const hintRecaptchaV2 = document.getElementById('hint-recaptcha-v2');
    const hintTurnstile = document.getElementById('hint-turnstile');
    const hintExternal = document.getElementById('hint-external');
    const captchaLink = document.getElementById('captcha-link');
    const previewArea = document.getElementById('captcha-preview-area');
    const previewPlaceholder = document.getElementById('captcha-render-placeholder');

    const providerLinks = {
        'recaptcha': 'https://www.google.com/recaptcha/admin',
        'hcaptcha': 'https://dashboard.hcaptcha.com/',
        'turnstile': 'https://dash.cloudflare.com/?to=/:account/turnstile'
    };

    function loadCaptchaScript(id, src, callback) {
        if (window.grecaptcha && id === 'recaptcha-js') return callback();
        if (window.hcaptcha && id === 'hcaptcha-js') return callback();
        if (window.turnstile && id === 'turnstile-js') return callback();
        
        if (document.getElementById(id)) return; // Already loading
        
        const s = document.createElement('script');
        s.id = id; s.src = src; s.async = true; s.defer = true;
        s.onload = callback;
        document.head.appendChild(s);
    }

    if (providerSelect) {
        providerSelect.addEventListener('change', function() {
            const v = this.value;
            const siteKeyInput = document.getElementsByName('captcha_site_key')[0];
            const siteKey = siteKeyInput ? siteKeyInput.value.trim() : '';
            const isExternal = ['recaptcha', 'hcaptcha', 'turnstile'].includes(v);
            
            keysWrap.style.display = isExternal ? 'block' : 'none';
            hintImage.style.display = (v === 'image') ? 'flex' : 'none';
            hintRecaptchaV2.style.display = (v === 'recaptcha') ? 'flex' : 'none';
            hintTurnstile.style.display = (v === 'turnstile') ? 'flex' : 'none';
            previewArea.style.display = (v === 'none') ? 'none' : 'block';
            
            if (isExternal && providerLinks[v]) {
                captchaLink.href = providerLinks[v];
                hintExternal.style.display = 'flex';
                
                if (siteKey) {
                    previewPlaceholder.innerHTML = '<div id="captcha-preview-widget"></div>';
                    const container = document.getElementById('captcha-preview-widget');
                    
                    if (v === 'recaptcha') {
                        loadCaptchaScript('recaptcha-js', 'https://www.google.com/recaptcha/api.js?render=explicit', () => {
                            const interval = setInterval(() => {
                                if (window.grecaptcha && window.grecaptcha.render) {
                                    clearInterval(interval);
                                    try { window.grecaptcha.render(container, { 'sitekey': siteKey }); } catch(e) {}
                                }
                            }, 100);
                        });
                    } else if (v === 'hcaptcha') {
                        loadCaptchaScript('hcaptcha-js', 'https://js.hcaptcha.com/1/api.js?render=explicit', () => {
                            const interval = setInterval(() => {
                                if (window.hcaptcha && window.hcaptcha.render) {
                                    clearInterval(interval);
                                    try { window.hcaptcha.render(container, { 'sitekey': siteKey }); } catch(e) {}
                                }
                            }, 100);
                        });
                    } else if (v === 'turnstile') {
                        loadCaptchaScript('turnstile-js', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', () => {
                            const interval = setInterval(() => {
                                if (window.turnstile && window.turnstile.render) {
                                    clearInterval(interval);
                                    try { window.turnstile.render(container, { 'sitekey': siteKey }); } catch(e) {}
                                }
                            }, 100);
                        });
                    }
                } else {
                    previewPlaceholder.innerHTML = '<div style="text-align:center; color:var(--krono-text-3); font-size:0.85rem;"><i class="bi bi-eye-slash" style="font-size:1.5rem; display:block; margin-bottom:0.5rem;"></i>Saisissez votre clé du site pour voir l\'aperçu.</div>';
                }
            } else {
                hintExternal.style.display = 'none';
                if (v === 'image') {
                    previewPlaceholder.innerHTML = '<div style="text-align:center;">' +
                        '<img src="<?= url("/captcha/image") ?>?' + Math.random() + '" alt="CAPTCHA" style="border-radius: 4px; border: 1px solid var(--krono-border);">' +
                        '<div class="auth-field" style="margin-top: 0.5rem; width: 200px; margin-left: auto; margin-right: auto;">' +
                        '<input type="text" class="auth-input" placeholder="Saisir le code" disabled style="cursor:not-allowed;">' +
                        '<i class="bi bi-shield-lock auth-field-icon"></i></div></div>';
                }
            }
        });
        
        // Update preview when key changes
        const skInput = document.getElementsByName('captcha_site_key')[0];
        if (skInput) {
            skInput.addEventListener('input', function() {
                if (['recaptcha', 'hcaptcha', 'turnstile'].includes(providerSelect.value)) {
                    providerSelect.dispatchEvent(new Event('change'));
                }
            });
        }
        
        // Init state
        providerSelect.dispatchEvent(new Event('change'));
    }

    const hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById('tab-' + hash)) { switchTab(hash); }
});

async function sendTestEmail() {
    const dest = document.getElementById('test_email_dest').value;
    const status = document.getElementById('test-smtp-status');
    const btn = document.getElementById('btn-test-smtp');
    if (!dest) {
        status.style.display = 'block';
        status.innerHTML = '<div class="krono-alert krono-alert--danger"><i class="bi bi-exclamation-triangle"></i> Veuillez saisir une adresse e-mail de destination.</div>';
        return;
    }
    status.style.display = 'block';
    status.innerHTML = '<div class="badge-krono badge-krono--neutral"><span class="u-spinner"></span> Tentative d\'envoi en cours...</div>';
    btn.disabled = true;
    try {
        const formData = new FormData();
        formData.append('email', dest);
        formData.append('_csrf_token', '<?= \KronoConnect\Core\Security::csrfToken() ?>');

        const fields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption', 'from_email', 'from_name'];
        fields.forEach(f => {
            const el = document.getElementsByName(f)[0];
            if (el) formData.append(f, el.value);
        });
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 secondes max

        const r = await fetch('<?= url('/admin/settings/test-email') ?>', { 
            method: 'POST', 
            body: formData,
            signal: controller.signal
        });
        clearTimeout(timeoutId);
        
        const data = await r.json();
        if (data.success) {
            status.innerHTML = '<div class="krono-alert krono-alert--success"><i class="bi bi-check-circle"></i> ' + data.message + '</div>';
        } else {
            status.innerHTML = '<div class="krono-alert krono-alert--danger"><i class="bi bi-x-circle"></i> ' + data.message + '</div>';
        }
    } catch (e) {
        if (e.name === 'AbortError') {
            status.innerHTML = '<div class="krono-alert krono-alert--danger"><i class="bi bi-exclamation-triangle"></i> Délai d\'attente dépassé. Le serveur SMTP ne répond pas.</div>';
        } else {
            status.innerHTML = '<div class="krono-alert krono-alert--danger"><i class="bi bi-exclamation-triangle"></i> Erreur réseau ou serveur.</div>';
        }
    } finally {
        btn.disabled = false;
    }
}

async function checkUpdate() {
    const area = document.getElementById('u-update-area');
    const badge = document.getElementById('u-status-badge');
    if (!area || !badge) return;

    area.innerHTML = '<div class="krono-alert krono-alert--info" style="background:rgba(59,130,246,0.1); color:#2563eb; padding:1rem; border-radius:8px; border:1px solid rgba(59,130,246,0.2); display:flex; align-items:center; gap:0.75rem;"><i class="bi bi-arrow-repeat spin"></i> Consultation du serveur de mise à jour...</div>';
    try {
        const formData = new FormData();
        formData.append('_csrf_token', '<?= \KronoConnect\Core\Security::csrfToken() ?>');

        const r = await fetch('<?= url('/admin/updates/check') ?>', {
            method:'POST',
            body: formData
        });
        const data = await r.json();
        if (data.error) throw new Error(data.error);

        // Récupération du changelog depuis la réponse API GitHub
        const changelogArea = document.getElementById('u-changelog-area');
        const changelogBody = document.getElementById('u-changelog-body');
        const changelogTitle = document.getElementById('u-changelog-title');
        if (changelogArea && changelogBody && data.changelog) {
            changelogTitle.textContent = 'Notes de version (v' + data.latest_version + ')';
            changelogBody.innerHTML = parseMarkdown(data.changelog);
            changelogArea.style.display = 'block';
        } else if (changelogArea) {
            changelogArea.style.display = 'none';
        }

        if (!data.update_available) {
            badge.innerHTML = '<i class="bi bi-check-lg text-success"></i> À jour';
            area.innerHTML = '<div class="krono-alert krono-alert--success" style="background:rgba(34,197,94,0.1); color:#16a34a; padding:1rem; border-radius:8px; border:1px solid rgba(34,197,94,0.2); display:flex; align-items:center; gap:0.75rem;"><i class="bi bi-check-circle-fill"></i> <div>Votre instance est à jour (v'+data.latest_version+').</div></div>';
        } else {
            badge.innerHTML = '<i class="bi bi-exclamation-circle-fill text-warning"></i> Mise à jour disponible';
            area.innerHTML = `
                <div class="krono-alert krono-alert--warning" style="background:rgba(234,179,8,0.1); color:#ca8a04; padding:1rem; border-radius:8px; border:1px solid rgba(234,179,8,0.2); display:flex; align-items:flex-start; gap:0.75rem;">
                    <i class="bi bi-info-circle-fill" style="margin-top:0.1rem;"></i>
                    <div>
                        <strong>Version v${data.latest_version} disponible.</strong><br>
                        Une nouvelle version stable est prête à être installée.
                    </div>
                </div>
                <div style="margin-top: 1rem;">
                    <button type="button" class="btn-krono btn-krono--primary" onclick="startUpdate('${data.latest_version}')">
                        <i class="bi bi-cloud-arrow-down"></i> Mettre à jour vers v${data.latest_version}
                    </button>
                </div>`;
        }
    } catch(e) {
        badge.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i> Erreur';
        area.innerHTML = '<div class="krono-alert krono-alert--danger" style="background:rgba(239,68,68,0.1); color:#dc2626; padding:1rem; border-radius:8px; border:1px solid rgba(239,68,68,0.2); display:flex; align-items:center; gap:0.75rem;"><i class="bi bi-exclamation-triangle"></i> ' + e.message + '</div>';
        const changelogArea = document.getElementById('u-changelog-area');
        if (changelogArea) changelogArea.style.display = 'none';
    }
}

function parseMarkdown(md) {
    if (!md) return '<p style="color:var(--krono-text-3); font-size:0.85rem;">Aucune note de version disponible.</p>';
    
    const escapeHtml = (text) => {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    const lines = md.split('\n');
    let html = '';
    let inList = false;

    for (let line of lines) {
        line = line.trim();
        if (line === '') {
            if (inList) {
                html += '</ul>\n';
                inList = false;
            }
            continue;
        }

        // H1, H2, H3
        if (line.startsWith('# ')) {
            if (inList) { html += '</ul>\n'; inList = false; }
            html += `<h3 style="margin-top:1rem; margin-bottom:0.4rem; font-weight:800; font-size:1.1rem; color:var(--krono-text);">${escapeHtml(line.substring(2))}</h3>\n`;
        } else if (line.startsWith('## ')) {
            if (inList) { html += '</ul>\n'; inList = false; }
            html += `<h4 style="margin-top:0.8rem; margin-bottom:0.3rem; font-weight:700; font-size:1rem; color:var(--krono-text);">${escapeHtml(line.substring(3))}</h4>\n`;
        } else if (line.startsWith('### ')) {
            if (inList) { html += '</ul>\n'; inList = false; }
            html += `<h5 style="margin-top:0.6rem; margin-bottom:0.2rem; font-weight:700; font-size:0.9rem; color:var(--krono-accent);">${escapeHtml(line.substring(4))}</h5>\n`;
        }
        // Lists
        else if (line.startsWith('- ') || line.startsWith('* ')) {
            if (!inList) {
                html += '<ul style="margin-bottom:0.6rem; padding-left:1.25rem; list-style-type:disc;">\n';
                inList = true;
            }
            let itemContent = escapeHtml(line.substring(2));
            itemContent = itemContent.replace(/`([^`]+)`/g, '<code style="background:var(--krono-surface-3); padding:0.15rem 0.35rem; border-radius:4px; font-size:0.85em; font-family:monospace;">$1</code>');
            html += `<li style="margin-bottom:0.25rem; font-size:0.85rem; color:var(--krono-text-2); line-height:1.4;">${itemContent}</li>\n`;
        } else {
            if (inList) { html += '</ul>\n'; inList = false; }
            let itemContent = escapeHtml(line);
            itemContent = itemContent.replace(/`([^`]+)`/g, '<code style="background:var(--krono-surface-3); padding:0.15rem 0.35rem; border-radius:4px; font-size:0.85em; font-family:monospace;">$1</code>');
            html += `<p style="margin-bottom:0.6rem; font-size:0.85rem; color:var(--krono-text-2); line-height:1.4;">${itemContent}</p>\n`;
        }
    }

    if (inList) {
        html += '</ul>\n';
    }

    return html;
}

async function startUpdate(version) {
    const confirmed = await window.KronoConnect.confirm('Voulez-vous vraiment lancer la mise à jour vers la version v' + version + ' ?', {
        title: 'Mise à jour du système',
        type: 'warning',
        confirmText: 'Lancer la mise à jour'
    });
    if (!confirmed) return;
    document.getElementById('u-terminal').style.display = 'block';
    const body = document.getElementById('u-term-body');
    body.innerHTML = '<span style="color:#94a3b8"># Initialisation du processus...</span>\n';
    try {
        const resp = await fetch('<?= url('/admin/updates/apply') ?>', { 
            method: 'POST', 
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': '<?= \KronoConnect\Core\Security::csrfToken() ?>'
            }, 
            body: 'version=' + version + '&_csrf_token=' + encodeURIComponent('<?= \KronoConnect\Core\Security::csrfToken() ?>')
        });

        if (!resp.ok) {
            let errorMsg = 'Erreur HTTP ' + resp.status;
            try {
                const text = await resp.text();
                const obj = JSON.parse(text);
                if (obj.error) errorMsg = obj.error;
            } catch(e) {}
            throw new Error(errorMsg);
        }

        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        while (true) {
            const {done, value} = await reader.read();
            if (done) break;
            const text = decoder.decode(value);
            const lines = text.split('\n');
            for(const line of lines) {
                if (!line.trim()) continue;
                try {
                    const obj = JSON.parse(line);
                    if (obj.success === false || obj.error || obj.type === 'error') {
                        const errorMsg = obj.error || obj.msg || 'Une erreur est survenue.';
                        body.innerHTML += `<span style="color:#ef4444">! ERREUR : ${errorMsg}</span>\n`;
                        body.scrollTop = body.scrollHeight;
                        return; // Arrêt du processus
                    }
                    body.innerHTML += `<span style="color:#22c55e">></span> ${obj.msg}\n`;
                    body.scrollTop = body.scrollHeight;
                } catch(e) { 
                    if(line.includes('error')) {
                        body.innerHTML += `<span style="color:#ef4444">! ${line}</span>\n`; 
                    }
                }
            }
        }
        body.innerHTML += '\n<span style="color:#facc15"># Mise à jour terminée avec succès. Redémarrage...</span>';
        setTimeout(() => window.location.reload(), 2000);
    } catch(e) { 
        body.innerHTML += `<span style="color:#ef4444">!! ERREUR CRITIQUE : ${e.message}</span>\n`; 
    }
}
</script>
