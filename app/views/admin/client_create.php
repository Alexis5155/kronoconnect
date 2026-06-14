<?php
/**
 * Single-page manual client creation form.
 */
?>
<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <a href="<?= url('/admin/clients') ?>">Clients SSO</a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Nouvelle application</span>
</nav>

<div class="page-header" style="margin-bottom: 2rem;">
    <div>
        <h1 class="page-header__title">Ajouter une application</h1>
        <p class="page-header__subtitle">Enregistrez manuellement une nouvelle application tierce (OIDC/OAuth2) sur l'annuaire KronoConnect.</p>
    </div>
</div>

<div class="krono-alert krono-alert--warning" style="margin-bottom: 2rem;">
    <i class="bi bi-magic"></i>
    <div><strong>Recommandation :</strong> Il est préférable de passer par la fonctionnalité d'association automatique disponible directement depuis l'application cliente que vous souhaitez connecter, plutôt que de créer le client manuellement.</div>
</div>

<form method="POST" action="<?= url('/admin/clients') ?>">
    <?= csrf() ?>
    
    <div class="fade-in-up anim-delay-1 glass-card" style="margin-bottom: 1.5rem;">
        <div class="card-title-area" style="margin-bottom: 1.5rem;">
            <div class="card-icon"><i class="bi bi-braces-asterisk"></i></div>
            <div>
                <h3 style="margin: 0; font-size: 1.1rem;">Informations techniques</h3>
                <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--krono-text-3);">Définissez l'identité et les adresses de redirection de l'application cliente.</p>
            </div>
        </div>

        <div class="krono-grid-2">
            <div class="form-group">
                <label class="krono-label" for="name">Nom de l'application</label>
                <input type="text" name="name" id="name" class="krono-input" placeholder="ex. Logiciel Comptabilité" required autofocus>
            </div>
            
            <div class="form-group">
                <label class="krono-label" for="redirect_uri">URI de redirection (Callback URL)</label>
                <input type="url" name="redirect_uri" id="redirect_uri" class="krono-input" placeholder="https://app.domaine.fr/auth/callback" required>
                <div class="form-hint" style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--krono-text-3);"><i class="bi bi-info-circle"></i> L'URL où l'utilisateur sera redirigé après la connexion SSO.</div>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="krono-label" for="logout_url">URL de déconnexion globale (Single Logout)</label>
            <input type="url" name="logout_url" id="logout_url" class="krono-input" placeholder="https://app.domaine.fr/logout/back-channel">
            <div class="form-hint" style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--krono-text-3);"><i class="bi bi-info-circle"></i> Optionnel. URL appelée en arrière-plan (webhook) par KronoConnect lorsqu'une déconnexion globale est déclenchée.</div>
        </div>
        
        <div class="form-group" style="margin-top: 1rem; margin-bottom: 0;">
            <label class="krono-label" for="allowed_ips">Restriction par IP (Optionnel)</label>
            <input type="text" name="allowed_ips" id="allowed_ips" class="krono-input" placeholder="ex: 192.168.1.0/24, 10.0.0.1">
            <div class="form-hint" style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--krono-text-3);"><i class="bi bi-info-circle"></i> Liste d'IPs ou de blocs CIDR séparés par des virgules. Laissez vide pour autoriser toutes les connexions.</div>
        </div>
    </div>

    <div class="fade-in-up anim-delay-2 glass-card" style="margin-bottom: 2rem;">
        <div class="card-title-area" style="margin-bottom: 1.5rem;">
            <div class="card-icon" style="background: rgba(234,179,8,0.12); color: #eab308;"><i class="bi bi-shield-lock"></i></div>
            <div>
                <h3 style="margin: 0; font-size: 1.1rem;">Politique d'accès</h3>
                <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--krono-text-3);">Déterminez qui aura l'autorisation de se connecter à cette application.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <label class="setup-mode-card is-selected">
                <input type="radio" name="access_mode" value="open" checked>
                <div class="setup-mode-icon"><i class="bi bi-globe"></i></div>
                <div class="setup-mode-text">
                    <strong>Ouvert</strong>
                    <span>Tous les utilisateurs actifs.</span>
                </div>
            </label>
            <label class="setup-mode-card">
                <input type="radio" name="access_mode" value="group">
                <div class="setup-mode-icon"><i class="bi bi-collection"></i></div>
                <div class="setup-mode-text">
                    <strong>Par Groupe</strong>
                    <span>Membres des groupes autorisés.</span>
                </div>
            </label>
            <label class="setup-mode-card">
                <input type="radio" name="access_mode" value="manual">
                <div class="setup-mode-icon"><i class="bi bi-person-lock"></i></div>
                <div class="setup-mode-text">
                    <strong>Manuel</strong>
                    <span>Autorisation individuelle.</span>
                </div>
            </label>
        </div>
    </div>

    <div class="krono-alert krono-alert--info" style="margin-bottom: 2rem;">
        <i class="bi bi-key-fill"></i>
        <div>Le <strong>Client ID</strong> et le <strong>Client Secret</strong> seront générés automatiquement et affichés sur la page suivante une fois l'application créée.</div>
    </div>

    <!-- Hidden visual fields that can be modified later via edit -->
    <input type="hidden" name="app_color" value="#3B82F6">
    <input type="hidden" name="app_icon" value="app-indicator">
    <input type="hidden" name="permissions_json" value="[]">

    <div class="krono-form-actions" style="justify-content: flex-end;">
        <a href="<?= url('/admin/clients') ?>" class="btn-krono btn-krono--ghost">Annuler</a>
        <button type="submit" class="btn-krono btn-krono--primary">
            <i class="bi bi-plus-circle"></i> Créer l'application
        </button>
    </div>
</form>

<style>
/* En-tête des cartes */
.card-title-area { display: flex; gap: 1.25rem; align-items: center; margin-bottom: 1.5rem; }
.card-icon { 
    width: 48px; height: 48px; border-radius: 14px; background: var(--krono-accent-light); 
    color: var(--krono-accent); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;
}

/* Carte radio pour le mode d'accès */
.setup-mode-card {
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: 1rem 1.25rem;
    background: var(--krono-surface-2);
    border: 2px solid var(--krono-border);
    border-radius: var(--krono-radius);
    cursor: pointer;
    transition: var(--krono-transition);
}
.setup-mode-card:hover {
    border-color: var(--krono-accent);
    background: var(--krono-surface);
}
.setup-mode-card.is-selected {
    border-color: var(--krono-accent);
    background: var(--krono-surface);
    box-shadow: 0 0 0 1px var(--krono-accent);
}
.setup-mode-card input[type="radio"] {
    display: none;
}
.setup-mode-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--krono-radius);
    background: var(--krono-surface-2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--krono-text-3);
    flex-shrink: 0;
    transition: var(--krono-transition);
}
.setup-mode-card.is-selected .setup-mode-icon {
    background: var(--krono-accent);
    color: white;
}
.setup-mode-text {
    display: flex;
    flex-direction: column;
    gap: .15rem;
}
.setup-mode-text strong {
    font-size: .9rem;
    color: var(--krono-text);
}
.setup-mode-text span {
    font-size: .78rem;
    color: var(--krono-text-3);
}
</style>

<script>
document.querySelectorAll('.setup-mode-card input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.setup-mode-card').forEach(c => c.classList.remove('is-selected'));
        this.closest('.setup-mode-card').classList.add('is-selected');
    });
});
</script>