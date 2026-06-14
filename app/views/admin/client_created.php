<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <a href="<?= url('/admin/clients') ?>">Clients SSO</a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Application créée</span>
</nav>

<div class="page-header" style="margin-bottom: 2rem;">
    <div>
        <h1 class="page-header__title">Application créée avec succès</h1>
        <p class="page-header__subtitle">L'application "<?= e($newClient['name'] ?? 'Inconnue') ?>" a été enregistrée.</p>
    </div>
</div>

<div class="fade-in-up anim-delay-1 glass-card" style="margin-bottom:2rem; padding:0; overflow:hidden; border:2px solid var(--krono-success);">
    <div style="background:var(--krono-success); color:white; padding:1.25rem 1.5rem; display:flex; align-items:center; gap:1rem;">
        <i class="bi bi-check-circle-fill" style="font-size:2rem; flex-shrink:0;"></i>
        <div>
            <h3 style="margin:0; font-size:1.2rem;">Identifiants générés</h3>
            <p style="margin:.2rem 0 0; opacity:.9; font-size:.9rem;">Veuillez copier ces informations dans la configuration de votre application cliente.</p>
        </div>
    </div>
    
    <div style="padding:2rem;">
        <div class="krono-alert krono-alert--danger" style="margin-bottom:2rem;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>Avertissement de sécurité important</strong><br>
                <span>Pour des raisons de sécurité, le <strong>Client Secret</strong> ne sera <strong>plus jamais affiché</strong>. Assurez-vous de le copier maintenant.</span>
            </div>
        </div>

        <div class="krono-grid-2" style="gap:2rem;">
            <div>
                <label class="krono-label" style="font-size:1rem; margin-bottom:.5rem;">Client ID</label>
                <div style="display:flex; gap:.5rem;">
                    <input type="text" class="krono-input" id="copy-client-id"
                           value="<?= e($newClient['client_id'] ?? '') ?>" readonly
                           style="font-family:monospace; font-size:1.1rem; padding:.75rem; background:var(--krono-surface-2);">
                    <button class="btn-krono btn-krono--secondary" onclick="copyField('copy-client-id', this)" title="Copier le Client ID" style="padding:0 1.25rem;">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <p style="margin-top:.5rem; font-size:.85rem; color:var(--krono-text-3);">Identifiant public de l'application.</p>
            </div>
            
            <div>
                <label class="krono-label" style="font-size:1rem; margin-bottom:.5rem;">Client Secret</label>
                <div style="display:flex; gap:.5rem;">
                    <input type="text" class="krono-input" id="copy-client-secret"
                           value="<?= e($newClient['client_secret'] ?? '') ?>" readonly
                           style="font-family:monospace; font-size:1.1rem; padding:.75rem; background:var(--krono-surface-2); border-color:var(--krono-primary);">
                    <button class="btn-krono btn-krono--primary" onclick="copyField('copy-client-secret', this)" title="Copier le Client Secret" style="padding:0 1.25rem;">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <p style="margin-top:.5rem; font-size:.85rem; color:var(--krono-text-3);">Clé privée à garder strictement confidentielle.</p>
            </div>
        </div>
    </div>
</div>

<div class="krono-form-actions fade-in-up anim-delay-2" style="justify-content: flex-end; margin-top: 2rem;">
    <a href="<?= url('/admin/clients') ?>" class="btn-krono btn-krono--primary btn-krono--lg">
        <i class="bi bi-arrow-right-circle"></i> Continuer vers la liste des clients
    </a>
</div>

<script>
function copyField(id, btn) {
    const el = document.getElementById(id);
    el.select(); 
    el.setSelectionRange(0, 99999);
    
    const onSuccess = () => {
        const icon = btn.querySelector('i');
        icon.className = 'bi bi-check-lg';
        btn.classList.add('btn-krono--success');
        btn.classList.remove('btn-krono--secondary', 'btn-krono--primary');
        setTimeout(() => {
            icon.className = 'bi bi-clipboard';
            btn.classList.remove('btn-krono--success');
            btn.classList.add(id === 'copy-client-id' ? 'btn-krono--secondary' : 'btn-krono--primary');
        }, 2000);
    };

    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        navigator.clipboard.writeText(el.value).then(onSuccess).catch(err => {
            fallbackCopyInput(onSuccess);
        });
    } else {
        fallbackCopyInput(onSuccess);
    }
}

function fallbackCopyInput(onSuccess) {
    try {
        const successful = document.execCommand('copy');
        if (successful && typeof onSuccess === 'function') {
            onSuccess();
        }
    } catch (err) {
        console.error('Fallback copy failed', err);
    }
}
</script>

