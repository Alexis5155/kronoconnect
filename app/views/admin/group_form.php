<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <a href="<?= url('/admin/groups') ?>">Groupes</a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Nouveau</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-header__title">
            <i class="bi bi-collection" style="color:var(--krono-text-3); margin-right:.5rem;"></i>Nouveau groupe
        </h1>
        <p class="page-header__subtitle">Définissez le nom du groupe et configurez ses permissions globales.</p>
    </div>
</div>

<!-- Onglets -->
<div class="krono-tabs">
    <button type="button" class="krono-tab-btn active" onclick="switchGroupTab('info', this)">
        <i class="bi bi-info-circle-fill"></i> Informations
    </button>
    <button type="button" class="krono-tab-btn" onclick="switchGroupTab('perms', this)">
        <i class="bi bi-shield-lock-fill"></i> Permissions
    </button>
</div>

<form method="POST" action="<?= url('/admin/groups') ?>" data-unsaved-detection id="form-group">
    <?= csrf() ?>

    <!-- SECTION : Informations -->
    <section id="tab-info" class="group-section active">
        <div class="fade-in-up anim-delay-1 glass-card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <div style="font-size:.95rem; font-weight:700; color:var(--krono-text); margin-bottom:1.25rem;
                        padding-bottom:.75rem; border-bottom:1px solid var(--krono-border);">
                <i class="bi bi-info-circle-fill" style="color:var(--krono-accent);margin-right:.5rem;"></i>
                Informations générales
            </div>

            <div style="margin-bottom:1rem;">
                <label class="krono-label" for="f-name">Nom du groupe <span style="color:var(--krono-danger);">*</span></label>
                <input type="text" id="f-name" name="name" class="krono-input" required autofocus
                       placeholder="Ex: Marketing, Direction, Stagiaires...">
            </div>

            <div style="margin-bottom:1rem;">
                <label class="krono-label" for="f-tech-name">Nom technique (Clé) <span style="font-weight:normal; color:var(--krono-text-3);">(Optionnel)</span></label>
                <input type="text" id="f-tech-name" name="tech_name" class="krono-input"
                       placeholder="Ex: marketing, dir_gen, dev" pattern="[a-z0-9_]+">
                <div class="form-hint"><i class="bi bi-info-circle"></i> Utilisé par les applications connectées pour faire correspondre les rôles. Lettres minuscules, chiffres et tirets du bas uniquement.</div>
            </div>

            <div style="margin-bottom:1rem;">
                <label class="krono-label" for="f-description">Description (optionnelle)</label>
                <textarea id="f-description" name="description" class="krono-input" rows="2"
                          placeholder="Décrivez brièvement ce groupe..."></textarea>
            </div>

            <div style="margin-bottom:0; display:flex; align-items:flex-start; gap:0.5rem;">
                <div class="krono-switch" style="margin-top:2px;">
                    <input type="checkbox" id="f-require-mfa" name="require_mfa" value="1">
                    <span class="krono-slider"></span>
                </div>
                <div>
                    <label for="f-require-mfa" style="font-weight:600; cursor:pointer; color:var(--krono-text);">Obliger l'authentification à double facteur (MFA)</label>
                    <div style="font-size:0.8rem; color:var(--krono-text-3); margin-top:2px;">Tous les membres de ce groupe devront configurer et utiliser le MFA pour se connecter.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION : Permissions -->
    <section id="tab-perms" class="group-section">
        <div class="fade-in-up anim-delay-2 glass-card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:1rem;">
                <div style="width:32px; height:32px; border-radius:6px; background:var(--krono-accent-light); display:flex; align-items:center; justify-content:center; color:var(--krono-accent);">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:var(--krono-text);">KronoConnect (Panel Admin)</h3>
                    <span style="font-family:monospace; font-size:.7rem; color:var(--krono-text-3);">Système central</span>
                </div>
            </div>

            <p style="font-size:.85rem; color:var(--krono-text-3); margin-bottom:1rem;">
                Cochez les permissions que les membres de ce groupe recevront automatiquement.
            </p>

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:.75rem;">
                <?php foreach ($kcPermissions as $perm): ?>
                    <label style="display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; padding:.75rem; background:var(--krono-surface-2); border:1px solid var(--krono-border); border-radius:var(--krono-radius); cursor:pointer;">
                        <div style="display:flex; flex-direction:column;">
                            <strong style="font-size:.85rem; color:var(--krono-text);"><?= e($perm['label']) ?></strong>
                            <span style="font-family:monospace; font-size:.7rem; color:var(--krono-text-3);"><?= e($perm['key']) ?></span>
                            <span style="font-size:.75rem; color:var(--krono-text-2); margin-top:.2rem;"><?= e($perm['description']) ?></span>
                        </div>
                        <div class="krono-switch" style="margin:0; flex-shrink:0;">
                            <input type="checkbox" name="kc_permissions[]" value="<?= e($perm['key']) ?>">
                            <span class="krono-slider"></span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="krono-alert krono-alert--info" style="margin-bottom:1.5rem;">
            <i class="bi bi-info-circle"></i>
            Les permissions des applications tierces et la gestion des membres pourront être configurées après la création du groupe.
        </div>
    </section>

    <!-- Actions -->
    <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
        <button type="submit" class="btn-krono btn-krono--primary">
            <i class="bi bi-plus-lg"></i> Créer le groupe
        </button>
        <a href="<?= url('/admin/groups') ?>" class="btn-krono btn-krono--ghost">Annuler</a>
    </div>
</form>

<style>
.group-section { display: none; }
.group-section.active { display: block; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
</style>

<script>
function switchGroupTab(id, btn) {
    document.querySelectorAll('.group-section').forEach(s => s.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    document.querySelectorAll('.krono-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}
</script>
