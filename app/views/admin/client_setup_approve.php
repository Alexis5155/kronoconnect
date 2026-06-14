<?php
/**
 * Vue d'approbation d'une demande d'association automatisée (Streamlined Auto-Approve)
 */

$appName = $manifest['name'] ?? 'Nouvelle Application';
$appColor = $manifest['color'] ?? '#3b5fc0'; // Default to KronoCore blue
$appIcon = preg_replace('/^bi-/i', '', trim($manifest['icon'] ?? 'app-indicator'));
$redirectUri = $appUrl . '/auth/callback';
$permissionsCount = isset($manifest['permissions']) ? count($manifest['permissions']) : 0;
?>

<style>
.kc-approve-hero {
    text-align: center;
    margin-bottom: 2.5rem;
    animation: kc-fade-down 0.5s ease-out;
}
.kc-approve-hero__title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--krono-text);
    margin-bottom: 0.5rem;
}
.kc-approve-hero__subtitle {
    font-size: 1rem;
    color: var(--krono-text-3);
    max-width: 500px;
    margin: 0 auto;
    line-height: 1.6;
}

.connection-graphic {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    margin: 2rem 0 3rem;
}
.connection-node {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    position: relative;
    z-index: 2;
}
.node-kc {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: white;
    border: 1px solid rgba(255,255,255,0.1);
}
.node-app {
    background: linear-gradient(135deg, <?= e($appColor) ?>, color-mix(in srgb, <?= e($appColor) ?> 70%, black));
    color: white;
}
.connection-link {
    flex-grow: 1;
    max-width: 120px;
    height: 4px;
    background: var(--krono-surface-3);
    position: relative;
    border-radius: 2px;
    overflow: hidden;
}
.connection-link::after {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 30%;
    background: var(--krono-accent);
    animation: kc-flow 1.5s infinite linear;
    border-radius: 2px;
}

@keyframes kc-flow {
    0% { transform: translateX(-100%); opacity: 0; }
    50% { opacity: 1; }
    100% { transform: translateX(350%); opacity: 0; }
}
@keyframes kc-fade-down {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes kc-fade-up {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.app-details-box {
    background: var(--krono-surface);
    border-radius: var(--krono-radius-lg);
    border: 1px solid var(--krono-border);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
}
.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--krono-border-light);
}
.detail-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.detail-label {
    color: var(--krono-text-3);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.detail-value {
    font-weight: 600;
    color: var(--krono-text);
    font-size: 0.95rem;
}
</style>

<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <a href="<?= url('/admin/clients') ?>">Clients SSO</a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Demande d'association</span>
</nav>

<div class="kc-approve-hero">
    <div class="connection-graphic">
        <div class="connection-node node-app">
            <i class="bi bi-<?= e($appIcon) ?>"></i>
        </div>
        <div class="connection-link"></div>
        <div class="connection-node node-kc">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
    </div>
    <h1 class="kc-approve-hero__title">Demande de connexion</h1>
    <p class="kc-approve-hero__subtitle">L'application située à l'adresse <strong><?= e($appUrl) ?></strong> souhaite utiliser KronoConnect pour son authentification.</p>
</div>

<div class="fade-in-up anim-delay-1 glass-card" style="max-width: 600px; margin: 0 auto; padding: 2rem; ">
    <form method="POST" action="<?= url('/admin/clients') ?>">
        <?= csrf() ?>
        <input type="hidden" name="setup_url" value="<?= e($setupUrl) ?>">
        <input type="hidden" name="setup_token" value="<?= e($setupToken) ?>">
        
        <input type="hidden" name="name" value="<?= e($appName) ?>">
        <input type="hidden" name="redirect_uri" value="<?= e($redirectUri) ?>">
        <input type="hidden" name="access_mode" value="open">
        <input type="hidden" name="app_color" value="<?= e($appColor) ?>">
        <input type="hidden" name="app_icon" value="<?= e($appIcon) ?>">
        <input type="hidden" name="logout_url" value="<?= e($appUrl) ?>">
        <input type="hidden" name="permissions_json" value="<?= e(json_encode($manifest['permissions'] ?? [])) ?>">

        <div class="app-details-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--krono-border);">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: <?= e($appColor) ?>; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        <i class="bi bi-<?= e($appIcon) ?>"></i>
                    </div>
                    <div>
                        <strong style="display: block; font-size: 1.1rem; color: var(--krono-text);"><?= e($appName) ?></strong>
                        <span style="font-size: 0.85rem; color: var(--krono-text-3); font-family: monospace;"><?= e($appUrl) ?></span>
                    </div>
                </div>
                <?php if ($manifest): ?>
                    <span class="badge-krono badge-krono--success">
                        <i class="bi bi-shield-check" style="margin-right: 4px;"></i> Identité vérifiée
                    </span>
                <?php else: ?>
                    <span class="badge-krono badge-krono--warning">
                        <i class="bi bi-exclamation-triangle" style="margin-right: 4px;"></i> Non vérifié
                    </span>
                <?php endif; ?>
            </div>

            <div class="detail-row">
                <span class="detail-label"><i class="bi bi-globe"></i> Mode d'accès par défaut</span>
                <span class="detail-value">Ouvert à tous</span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="bi bi-list-check"></i> Permissions demandées</span>
                <span class="detail-value"><?= $permissionsCount ?> requises</span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="bi bi-key"></i> Création des clés (ID/Secret)</span>
                <span class="detail-value" style="color: var(--krono-success);">Automatique</span>
            </div>
        </div>

        <div class="krono-alert krono-alert--info" style="margin-bottom: 2rem;">
            <i class="bi bi-info-circle-fill"></i>
            <div>En approuvant, les clés d'API seront générées et transmises de manière sécurisée à l'application. Vous pourrez restreindre l'accès à certains groupes plus tard.</div>
        </div>

        <div class="krono-form-actions" style="justify-content: space-between; border-top: 1px solid var(--krono-border); padding-top: 1.5rem;">
            <button type="button" class="btn-krono btn-krono--ghost" onclick="document.getElementById('form-refuse').submit();">
                <i class="bi bi-x-circle"></i> Refuser la demande
            </button>
            <button type="submit" class="btn-krono btn-krono--primary btn-krono--lg">
                <i class="bi bi-check-circle"></i> Approuver & Connecter
            </button>
        </div>
    </form>
</div>

<form id="form-refuse" method="POST" action="<?= url('/admin/clients/setup-refuse') ?>" style="display:none;">
    <?= csrf() ?>
    <input type="hidden" name="setup_url" value="<?= e($setupUrl) ?>">
    <input type="hidden" name="setup_token" value="<?= e($setupToken) ?>">
</form>
