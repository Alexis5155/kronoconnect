<?php
/** @var array $user */
$userName  = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
$initiales = strtoupper(
    mb_substr($user['prenom'] ?? 'U', 0, 1) .
    mb_substr($user['nom']    ?? '',  0, 1)
);
?>

<div class="organic-profile-container">

    <div class="organic-profile-layout">
        
        <!-- Sidebar Navigation -->
        <aside class="organic-sidebar">
            <div class="organic-glass-panel sidebar-panel">
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar-glow"></div>
                    <div class="profile-avatar"><?= e($initiales) ?></div>
                    <h2 class="profile-name"><?= e($userName) ?></h2>
                    <span class="profile-badge"><?= e($user['role'] ?? 'Utilisateur') ?></span>
                </div>

                <nav class="organic-nav">
                    <button type="button" class="organic-nav-item active" onclick="switchTab('info', this)">
                        <div class="nav-icon"><i class="bi bi-person"></i></div>
                        <span>Informations</span>
                        <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
                    </button>
                    <button type="button" class="organic-nav-item" onclick="switchTab('security', this)">
                        <div class="nav-icon"><i class="bi bi-shield-lock"></i></div>
                        <span>Sécurité</span>
                        <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
                    </button>
                    <button type="button" class="organic-nav-item" onclick="switchTab('rgpd', this)">
                        <div class="nav-icon"><i class="bi bi-file-earmark-lock"></i></div>
                        <span>Confidentialité</span>
                        <i class="bi bi-chevron-right ms-auto nav-chevron"></i>
                    </button>
                </nav>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="organic-content">
            <div class="organic-glass-panel content-panel">

                <!-- TAB 1 : Informations -->
                <section id="tab-info" class="organic-tab-content active">
                    <header class="tab-header">
                        <div class="tab-header-icon"><i class="bi bi-person"></i></div>
                        <div>
                            <h2 class="tab-title">Informations personnelles</h2>
                            <p class="tab-subtitle">Gérez vos coordonnées de contact.</p>
                        </div>
                    </header>

                    <form method="POST" action="<?= url('/profile/update') ?>" class="organic-form">
                        <?= csrf() ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="organic-label">Prénom</label>
                                <input type="text" class="organic-input" value="<?= e($user['prenom']) ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label class="organic-label">Nom</label>
                                <input type="text" class="organic-input" value="<?= e($user['nom']) ?>" disabled>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="organic-label">Service / Direction</label>
                                <div class="organic-input-wrap">
                                    <i class="bi bi-building input-icon"></i>
                                    <input type="text" class="organic-input with-icon" value="<?= e($user['service_name'] ?? 'Non assigné') ?>" disabled>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="organic-label">Téléphone professionnel</label>
                                <div class="organic-input-wrap">
                                    <i class="bi bi-telephone input-icon"></i>
                                    <input type="text" name="phone" class="organic-input with-icon" value="<?= e($user['phone'] ?? '') ?>" placeholder="Ex: 06 12 34 56 78">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="organic-label">Adresse e-mail</label>
                            <div class="organic-input-wrap">
                                <i class="bi bi-envelope input-icon"></i>
                                <input type="email" name="email" class="organic-input with-icon" value="<?= e($user['email']) ?>" 
                                    <?= ($canChangeEmail ?? false) ? 'required' : 'disabled' ?>>
                            </div>
                            <?php if (!($canChangeEmail ?? false)): ?>
                                <div class="input-help-text">
                                    <i class="bi bi-info-circle"></i> Modification de l'e-mail désactivée par l'administrateur.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(var(--krono-text-rgb, 26,31,54), 0.08); display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="meta-info">
                                <span style="font-size: 0.75rem; color: var(--krono-text-3); text-transform: uppercase; font-weight: 800; display: block; margin-bottom: 0.25rem;">Membre depuis le</span>
                                <span style="font-weight: 700; color: var(--krono-text-2);"><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                            </div>
                            <div class="meta-info">
                                <span style="font-size: 0.75rem; color: var(--krono-text-3); text-transform: uppercase; font-weight: 800; display: block; margin-bottom: 0.25rem;">Dernière activité</span>
                                <span style="font-weight: 700; color: var(--krono-text-2);"><?= !empty($user['last_activity_at']) ? date('d/m/Y à H:i', strtotime($user['last_activity_at'])) : 'Jamais' ?></span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="organic-btn primary">
                                <i class="bi bi-check2"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </section>

                <!-- TAB 2 : Sécurité -->
                <section id="tab-security" class="organic-tab-content">
                    <header class="tab-header">
                        <div class="tab-header-icon"><i class="bi bi-shield-lock"></i></div>
                        <div>
                            <h2 class="tab-title">Sécurité du compte</h2>
                            <p class="tab-subtitle">Mettez à jour votre mot de passe pour sécuriser votre accès.</p>
                        </div>
                    </header>
                    
                    <form method="POST" action="<?= url('/profile/password') ?>" class="organic-form">
                        <?= csrf() ?>
                        
                        <div class="form-group">
                            <label class="organic-label">Mot de passe actuel</label>
                            <div class="organic-input-wrap">
                                <i class="bi bi-lock input-icon"></i>
                                <input type="password" name="current_password" class="organic-input with-icon" placeholder="••••••••" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="organic-label">Nouveau mot de passe</label>
                                <div class="organic-input-wrap">
                                    <i class="bi bi-key input-icon"></i>
                                    <input type="password" name="new_password" class="organic-input with-icon" placeholder="Min. 8 car." required minlength="8">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="organic-label">Confirmer le mot de passe</label>
                                <div class="organic-input-wrap">
                                    <i class="bi bi-key-fill input-icon"></i>
                                    <input type="password" name="confirm_password" class="organic-input with-icon" placeholder="Confirmer" required minlength="8">
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="organic-btn primary">
                                <i class="bi bi-arrow-repeat"></i> Mettre à jour le mot de passe
                            </button>
                        </div>
                    </form>

                    <div style="margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid rgba(var(--krono-text-rgb, 26,31,54), 0.08);">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--krono-text); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="bi bi-shield-check" style="color: var(--krono-accent);"></i> Authentification à double facteur (MFA)
                        </h3>
                        <p style="font-size: 0.85rem; color: var(--krono-text-3); margin-bottom: 1.5rem; line-height: 1.5;">
                            Protégez votre compte en ajoutant une étape supplémentaire de sécurité lors de la connexion.
                        </p>

                        <?php if ($user['mfa_enabled']): ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 1rem; display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <strong style="color: #059669; display: block; margin-bottom: 0.2rem;"><i class="bi bi-check-circle-fill"></i> MFA Activé</strong>
                                        <span style="font-size: 0.8rem; color: var(--krono-text-3);">Votre compte est protégé par le MFA.</span>
                                    </div>
                                    <form id="form-mfa-disable" method="POST" action="<?= url('/profile/mfa-disable') ?>">
                                        <?= csrf() ?>
                                        <button type="button" onclick="openModal('mfaDisableModal')" class="organic-btn" style="background: white; border: 1px solid var(--krono-border); color: #DC2626; padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 700; border-radius: 8px;">
                                            Désactiver
                                        </button>
                                    </form>
                                </div>

                                <div style="background: var(--krono-surface-2); border: 1px solid var(--krono-border); border-radius: 8px; padding: 1rem; display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <strong style="color: var(--krono-text); display: block; margin-bottom: 0.2rem;"><i class="bi bi-shield-lock-fill" style="color: var(--krono-accent);"></i> Codes de secours</strong>
                                        <span style="font-size: 0.8rem; color: var(--krono-text-3);">Il vous reste <?= (int)$recoveryCodesCount ?> code(s) de secours inutilisé(s).</span>
                                    </div>
                                    <form id="form-mfa-regen" method="POST" action="<?= url('/profile/mfa-regenerate-codes') ?>">
                                        <?= csrf() ?>
                                        <button type="button" onclick="openModal('mfaRegenModal')" class="organic-btn primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 8px; text-decoration: none;">
                                            Régénérer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="background: var(--krono-surface-2); border: 1px solid var(--krono-border); border-radius: 8px; padding: 1rem; display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <strong style="color: var(--krono-text); display: block; margin-bottom: 0.2rem;"><i class="bi bi-exclamation-circle" style="color: var(--krono-warning);"></i> MFA Désactivé</strong>
                                    <span style="font-size: 0.8rem; color: var(--krono-text-3);">Configurez le MFA pour sécuriser votre compte.</span>
                                </div>
                                <a href="<?= url('/profile/mfa-setup') ?>" class="organic-btn primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none;">
                                    Configurer le MFA
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="webauthn-section" style="margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid rgba(var(--krono-text-rgb, 26,31,54), 0.08); display: none;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--krono-text); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="bi bi-key-fill" style="color: var(--krono-accent);"></i> Clés de sécurité (WebAuthn)
                        </h3>
                        <p style="font-size: 0.85rem; color: var(--krono-text-3); margin-bottom: 1.5rem; line-height: 1.5;">
                            Utilisez une clé de sécurité matérielle (YubiKey) ou la biométrie (Windows Hello, Touch ID) pour sécuriser votre compte.
                        </p>

                        <div id="webauthn-secure-area">
                            <?php if (!empty($webAuthnKeys)): ?>
                                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                                    <?php foreach ($webAuthnKeys as $key): ?>
                                        <div style="background: var(--krono-surface-2); border: 1px solid var(--krono-border); border-radius: 8px; padding: 1rem; display: flex; align-items: center; justify-content: space-between;">
                                            <div>
                                                <strong style="color: var(--krono-text); display: block; margin-bottom: 0.2rem;">
                                                    <i class="bi bi-key-fill" style="color: var(--krono-accent);"></i> <?= e($key['name']) ?>
                                                </strong>
                                                <span style="font-size: 0.8rem; color: var(--krono-text-3);">
                                                    Enregistrée le <?= date('d/m/Y', strtotime($key['created_at'])) ?>
                                                </span>
                                            </div>
                                            <button type="button" class="organic-btn btn-delete-key" style="background: white; border: 1px solid var(--krono-border); color: #DC2626; padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 700; border-radius: 8px;" onclick="confirmDeleteKey(<?= $key['id'] ?>, '<?= e(addslashes($key['name'])) ?>')">
                                                Supprimer
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="background: var(--krono-surface-2); border: 1px solid var(--krono-border); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; color: var(--krono-text-3); font-size: 0.85rem;">
                                    Aucune clé de sécurité enregistrée sur ce compte.
                                </div>
                            <?php endif; ?>

                            <button id="btn-add-webauthn" type="button" onclick="openModal('webauthnRegisterModal')" class="organic-btn primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 8px; text-decoration: none;">
                                Enregistrer une clé de sécurité
                            </button>
                        </div>
                    </div>
                </section>

                <!-- TAB 3 : RGPD -->
                <section id="tab-rgpd" class="organic-tab-content">
                    <header class="tab-header">
                        <div class="tab-header-icon"><i class="bi bi-file-earmark-lock"></i></div>
                        <div>
                            <h2 class="tab-title">Données & Confidentialité</h2>
                            <p class="tab-subtitle">Gérez vos données personnelles et vos droits d'accès.</p>
                        </div>
                    </header>

                    <div style="background: var(--krono-surface-2); border-radius: 1rem; border: 1px solid var(--krono-border); padding: 1.5rem; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1rem; font-weight: 700; color: var(--krono-text); margin: 0 0 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="bi bi-download" style="color: var(--krono-accent);"></i> Exporter mes données
                        </h3>
                        <p style="font-size: 0.85rem; color: var(--krono-text-3); margin-bottom: 1rem; line-height: 1.5;">
                            Téléchargez une copie de toutes vos informations personnelles stockées sur KronoConnect au format JSON.
                        </p>
                        <form method="POST" action="<?= url('/profile/export') ?>">
                            <?= csrf() ?>
                            <button type="submit" class="organic-btn" style="background: var(--krono-surface-3); border: 1px solid var(--krono-border); color: var(--krono-text);">
                                Télécharger l'archive
                            </button>
                        </form>
                    </div>

                    <div style="background: rgba(220, 38, 38, 0.05); border-radius: 1rem; border: 1px solid rgba(220, 38, 38, 0.2); padding: 1.5rem;">
                        <h3 style="font-size: 1rem; font-weight: 700; color: #DC2626; margin: 0 0 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="bi bi-exclamation-triangle"></i> Supprimer mon compte
                        </h3>
                        <p style="font-size: 0.85rem; color: var(--krono-text-2); margin-bottom: 1rem; line-height: 1.5;">
                            La suppression de votre compte est définitive. Toutes vos données seront effacées et vous perdrez l'accès à toutes les applications de l'écosystème.
                        </p>
                        <button type="button" class="organic-btn" style="background: #DC2626; color: white; border: none;" onclick="openDeleteModal()">
                            Supprimer mon compte
                        </button>
                    </div>
                </section>

            </div>
        </main>
    </div>
</div>

<style>
@keyframes modalOverlayIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
@keyframes modalOverlayOut {
    from { opacity: 1; }
    to   { opacity: 0; }
}
@keyframes modalCardIn {
    from { opacity: 0; transform: translateY(20px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
}
@keyframes modalCardOut {
    from { opacity: 1; transform: translateY(0)    scale(1); }
    to   { opacity: 0; transform: translateY(12px) scale(0.97); }
}

.krono-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.krono-modal-overlay.is-open {
    display: flex;
    animation: modalOverlayIn 0.2s ease forwards;
}
.krono-modal-overlay.is-closing {
    display: flex;
    animation: modalOverlayOut 0.18s ease forwards;
}

.krono-modal-card {
    width: 100%;
    max-width: 420px;
    position: relative;
    border-radius: 1.25rem;
    padding: 2rem;
    animation: modalCardIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.krono-modal-overlay.is-closing .krono-modal-card {
    animation: modalCardOut 0.18s ease forwards;
}

.krono-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    border-radius: 6px;
    font-size: 1.25rem;
    line-height: 1;
    color: var(--krono-text-3);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.krono-modal-close:hover {
    background: rgba(0, 0, 0, 0.06);
    color: var(--krono-text);
}

.krono-modal-icon {
    width: 62px;
    height: 62px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    margin: 0 auto 1.1rem;
}

.krono-modal-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-top: 1.75rem;
}
.krono-modal-actions .organic-btn {
    justify-content: center;
    text-align: center;
}
</style>

<!-- Modale : Désactiver le MFA -->

<div id="mfaDisableModal" class="krono-modal-overlay">
    <div class="krono-modal-card glass-card">
        <button type="button" class="krono-modal-close" onclick="closeModal('mfaDisableModal')" aria-label="Fermer">&times;</button>

        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="krono-modal-icon" style="background:rgba(220,38,38,0.1); color:#DC2626;">
                <i class="bi bi-shield-x"></i>
            </div>
            <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:var(--krono-text);">Désactiver le MFA ?</h3>
            <p style="margin:0.6rem 0 0; font-size:0.88rem; color:var(--krono-text-3); line-height:1.5;">Votre compte sera <strong>moins sécurisé</strong>. Cette action peut être annulée en reconfigurant le MFA.</p>
        </div>

        <div class="krono-modal-actions">
            <button type="button" onclick="closeModal('mfaDisableModal')" class="organic-btn" style="background:var(--krono-surface-3); border:1px solid var(--krono-border); color:var(--krono-text);">Annuler</button>
            <button type="button" onclick="submitAndClose('form-mfa-disable', 'mfaDisableModal')" class="organic-btn" style="background:#DC2626; color:white; border:none;">Désactiver</button>
        </div>
    </div>
</div>

<!-- Modale : Régénérer les codes de secours -->
<div id="mfaRegenModal" class="krono-modal-overlay">
    <div class="krono-modal-card glass-card">
        <button type="button" class="krono-modal-close" onclick="closeModal('mfaRegenModal')" aria-label="Fermer">&times;</button>

        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="krono-modal-icon" style="background:rgba(245,158,11,0.1); color:#F59E0B;">
                <i class="bi bi-arrow-clockwise"></i>
            </div>
            <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:var(--krono-text);">Régénérer les codes de secours ?</h3>
            <p style="margin:0.6rem 0 0; font-size:0.88rem; color:var(--krono-text-3); line-height:1.5;">Vos codes actuels seront <strong>définitivement invalidés</strong>. De nouveaux codes seront générés et affichés immédiatement.</p>
        </div>

        <div class="krono-modal-actions">
            <button type="button" onclick="closeModal('mfaRegenModal')" class="organic-btn" style="background:var(--krono-surface-3); border:1px solid var(--krono-border); color:var(--krono-text);">Annuler</button>
            <button type="button" onclick="submitAndClose('form-mfa-regen', 'mfaRegenModal')" class="organic-btn primary">Régénérer</button>
        </div>
    </div>
</div>

<!-- Modale de suppression de compte -->
<div id="deleteModal" class="krono-modal-overlay">
    <div class="krono-modal-card glass-card" style="max-width:450px;">
        <button type="button" class="krono-modal-close" onclick="closeModal('deleteModal')" aria-label="Fermer">&times;</button>

        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="krono-modal-icon" style="background:rgba(220,38,38,0.1); color:#DC2626; width:64px; height:64px; font-size:2rem;">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <h3 style="margin:0; font-size:1.25rem; font-weight:800; color:var(--krono-text);">Confirmation requise</h3>
            <p style="margin:0.5rem 0 0; font-size:0.9rem; color:var(--krono-text-3); line-height:1.5;">Veuillez saisir votre mot de passe pour confirmer la suppression définitive de votre compte.</p>
        </div>

        <form method="POST" action="<?= url('/profile/delete') ?>">
            <?= csrf() ?>
            <div class="form-group">
                <label class="organic-label">Mot de passe de confirmation</label>
                <div class="organic-input-wrap">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" class="organic-input with-icon" placeholder="••••••••" required>
                </div>
            </div>

            <div class="krono-modal-actions">
                <button type="button" onclick="closeModal('deleteModal')" class="organic-btn" style="background:var(--krono-surface-3); border:1px solid var(--krono-border); color:var(--krono-text);">Annuler</button>
                <button type="submit" class="organic-btn" style="background:#DC2626; color:white; border:none;">Supprimer définitivement</button>
            </div>
        </form>
    </div>
</div>

<!-- Modale : Enregistrer une clé de sécurité -->
<div id="webauthnRegisterModal" class="krono-modal-overlay">
    <div class="krono-modal-card glass-card">
        <button type="button" class="krono-modal-close" onclick="closeModal('webauthnRegisterModal')" aria-label="Fermer">&times;</button>

        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="krono-modal-icon" style="background:rgba(26,115,232,0.1); color:var(--krono-accent);">
                <i class="bi bi-key"></i>
            </div>
            <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:var(--krono-text);">Enregistrer une clé</h3>
            <p style="margin:0.6rem 0 0; font-size:0.88rem; color:var(--krono-text-3); line-height:1.5;">Donnez un nom descriptif à votre clé de sécurité pour l'identifier facilement.</p>
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem; text-align: left;">
            <label class="organic-label">Nom de la clé</label>
            <div class="organic-input-wrap">
                <i class="bi bi-tag input-icon"></i>
                <input type="text" id="webauthn-key-name" class="organic-input with-icon" placeholder="Ex: Ma YubiKey" required>
            </div>
        </div>

        <div id="webauthn-register-error" style="display: none; background: rgba(220, 38, 38, 0.05); border: 1px solid rgba(220, 38, 38, 0.2); border-radius: 8px; padding: 0.75rem; margin-bottom: 1rem; color: #DC2626; font-size: 0.8rem; text-align: left; line-height: 1.4;">
        </div>

        <div class="krono-modal-actions">
            <button type="button" onclick="closeModal('webauthnRegisterModal')" class="organic-btn" style="background:var(--krono-surface-3); border:1px solid var(--krono-border); color:var(--krono-text);">Annuler</button>
            <button type="button" onclick="startWebAuthnRegistration()" class="organic-btn primary" id="btn-webauthn-register-submit">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Modale : Supprimer une clé de sécurité -->
<div id="webauthnDeleteModal" class="krono-modal-overlay">
    <div class="krono-modal-card glass-card">
        <button type="button" class="krono-modal-close" onclick="closeModal('webauthnDeleteModal')" aria-label="Fermer">&times;</button>

        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="krono-modal-icon" style="background:rgba(220,38,38,0.1); color:#DC2626;">
                <i class="bi bi-shield-x"></i>
            </div>
            <h3 style="margin:0; font-size:1.2rem; font-weight:800; color:var(--krono-text);">Supprimer la clé ?</h3>
            <p style="margin:0.6rem 0 0; font-size:0.88rem; color:var(--krono-text-3); line-height:1.5;">Voulez-vous vraiment supprimer la clé <strong id="delete-key-name-label"></strong> ? Si vous n'avez pas d'autre méthode MFA active, la double authentification sera désactivée.</p>
        </div>

        <form id="form-webauthn-delete" method="POST" action="<?= url('/profile/webauthn/delete') ?>">
            <?= csrf() ?>
            <input type="hidden" name="key_id" id="delete-key-id">
            
            <div class="krono-modal-actions">
                <button type="button" onclick="closeModal('webauthnDeleteModal')" class="organic-btn" style="background:var(--krono-surface-3); border:1px solid var(--krono-border); color:var(--krono-text);">Annuler</button>
                <button type="submit" class="organic-btn" style="background:#DC2626; color:white; border:none;">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(id, btn) {
    document.querySelectorAll('.organic-tab-content').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    document.querySelectorAll('.organic-nav-item').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('is-closing');
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-closing');
    modal.addEventListener('animationend', function handler() {
        modal.classList.remove('is-open', 'is-closing');
        document.body.style.overflow = '';
        modal.removeEventListener('animationend', handler);
    });
}

function submitAndClose(formId, modalId) {
    closeModal(modalId);
    // On attend la fin de l'animation de fermeture avant de soumettre
    setTimeout(function() {
        const form = document.getElementById(formId);
        if (form) form.submit();
    }, 200);
}

// Compatibilité rétroactive
function openDeleteModal()  { openModal('deleteModal'); }
function closeDeleteModal() { closeModal('deleteModal'); }

// Fermer au clic en dehors
document.querySelectorAll('.krono-modal-overlay').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

function confirmDeleteKey(id, name) {
    document.getElementById('delete-key-id').value = id;
    document.getElementById('delete-key-name-label').textContent = name;
    openModal('webauthnDeleteModal');
}

function base64urlToArrayBuffer(base64url) {
    let padding = '='.repeat((4 - base64url.length % 4) % 4);
    let base64 = (base64url + padding).replace(/\-/g, '+').replace(/_/g, '/');
    let raw = window.atob(base64);
    let array = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) {
        array[i] = raw.charCodeAt(i);
    }
    return array.buffer;
}

function arrayBufferToBase64(buffer) {
    let binary = '';
    let bytes = new Uint8Array(buffer);
    let len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

async function startWebAuthnRegistration() {
    const nameInput = document.getElementById('webauthn-key-name');
    const name = nameInput.value.trim() || 'Clé de sécurité';
    const errorDiv = document.getElementById('webauthn-register-error');
    const submitBtn = document.getElementById('btn-webauthn-register-submit');

    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Connexion à la clé...';

    try {
        const response = await fetch('<?= url("/profile/webauthn/register-options") ?>');
        const options = await response.json();

        if (options.error) {
            throw new Error(options.error);
        }

        options.publicKey.challenge = base64urlToArrayBuffer(options.publicKey.challenge);
        options.publicKey.user.id = base64urlToArrayBuffer(options.publicKey.user.id);

        if (options.publicKey.excludeCredentials) {
            options.publicKey.excludeCredentials.forEach(cred => {
                cred.id = base64urlToArrayBuffer(cred.id);
            });
        }

        const credential = await navigator.credentials.create({
            publicKey: options.publicKey
        });

        if (!credential) {
            throw new Error("Échec de la communication avec l'authentificateur.");
        }

        const clientDataJSON = arrayBufferToBase64(credential.response.clientDataJSON);
        const attestationObject = arrayBufferToBase64(credential.response.attestationObject);

        const payload = {
            name: name,
            response: {
                clientDataJSON: clientDataJSON,
                attestationObject: attestationObject
            }
        };

        const registerResponse = await fetch('<?= url("/profile/webauthn/register") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await registerResponse.json();
        if (result.success) {
            closeModal('webauthnRegisterModal');
            window.location.reload();
        } else {
            throw new Error(result.error || "Une erreur est survenue lors de la validation.");
        }

    } catch (err) {
        console.error(err);
        let friendlyMessage = err.message;
        if (err.name === 'NotAllowedError') {
            friendlyMessage = "L'enregistrement a été annulé ou a expiré.";
        }
        errorDiv.textContent = friendlyMessage || "Une erreur inconnue est survenue.";
        errorDiv.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Enregistrer';
    }
}

// Détection HTTPS
document.addEventListener("DOMContentLoaded", function() {
    const isSecure = window.isSecureContext && typeof navigator.credentials !== 'undefined';
    if (isSecure) {
        const webauthnSec = document.getElementById('webauthn-section');
        if (webauthnSec) webauthnSec.style.display = 'block';
    }
});
</script>