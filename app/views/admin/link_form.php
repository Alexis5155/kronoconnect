<?php
$isEdit = !empty($link);
$id     = $isEdit ? $link['id'] : 0;
?>

<!-- FORMULAIRE PRINCIPAL GLOBAL -->
<form id="linkForm" method="POST" action="<?= url('/admin/links') ?>">
    <?= csrf() ?>
    <input type="hidden" name="id" value="<?= e((string)$id) ?>">
</form>

<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <a href="<?= url('/admin/links') ?>">Liens externes</a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current"><?= $isEdit ? e($link['title']) : 'Nouveau lien' ?></span>
</nav>

<div class="page-header">
    <div style="display:flex; align-items:center; gap:1rem;">
        <?php if ($isEdit): ?>
            <div style="width:56px; height:56px; border-radius:12px; background:<?= e($link['color'] ?? 'var(--krono-surface-2)') ?>; display:flex; align-items:center; justify-content:center; color:white; font-size:1.6rem; flex-shrink:0;">
                <i class="bi bi-<?= e($link['icon'] ?? 'link-45deg') ?>"></i>
            </div>
            <div>
                <h1 class="page-header__title"><?= e($link['title']) ?></h1>
                <p class="page-header__subtitle">Lien personnalisé</p>
            </div>
        <?php else: ?>
            <a href="<?= url('/admin/links') ?>" class="btn-krono btn-krono--ghost" style="padding:.5rem;">
                <i class="bi bi-arrow-left" style="font-size:1.2rem;"></i>
            </a>
            <div>
                <h1 class="page-header__title">Nouveau lien personnalisé</h1>
                <p class="page-header__subtitle">Ajout d'un lien externe sur le portail</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isEdit): ?>
<!-- Onglets -->
<div class="krono-tabs">
    <button class="krono-tab-btn active" onclick="switchLinkTab('config', this)">Configuration</button>
    <button class="krono-tab-btn" onclick="switchLinkTab('access', this)">Paramètres d'accès</button>
</div>
<?php endif; ?>

<!-- SECTION : CONFIGURATION -->
<section id="tab-config" class="link-section active">
    <div class="link-config-layout">
        <!-- Informations principales -->
        <div class="fade-in-up anim-delay-1 glass-card">
            <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-gear"></i> Informations du lien</h3>

            <div class="form-group">
                <label class="krono-label">Titre du lien</label>
                <input type="text" name="title" form="linkForm" class="krono-input" 
                       value="<?= e($link['title'] ?? '') ?>" 
                       placeholder="Ex: Site de la mairie" required>
            </div>

            <div class="form-group">
                <label class="krono-label">URL de destination</label>
                <input type="url" name="url" form="linkForm" class="krono-input" 
                       value="<?= e($link['url'] ?? '') ?>" 
                       placeholder="https://..." required>
            </div>

            <div class="krono-grid-2">
                <div class="form-group">
                    <label class="krono-label">Icône (Bootstrap Icons)</label>
                    <div style="display:flex; gap:.5rem;">
                        <input type="text" name="icon" form="linkForm" id="icon-input" class="krono-input" 
                               value="<?= e($link['icon'] ?? 'link-45deg') ?>" 
                               placeholder="link-45deg">
                        <div id="icon-preview-box" class="btn-krono btn-krono--secondary" style="width:45px; display:flex; align-items:center; justify-content:center;">
                            <i id="icon-preview" class="bi bi-<?= e($link['icon'] ?? 'link-45deg') ?>"></i>
                        </div>
                    </div>
                    <div class="form-hint"><i class="bi bi-info-circle"></i> Nom de l'icône sans le préfixe "bi-". <a href="https://icons.getbootstrap.com/" target="_blank">Catalogue ici</a>.</div>
                </div>

                <div class="form-group">
                    <label class="krono-label">Couleur de la carte</label>
                    <div style="display:flex; gap:.5rem;">
                        <input type="color" name="color" form="linkForm" id="color-input" class="krono-input" style="width:60px; padding:2px; height:42px;" 
                               value="<?= e($link['color'] ?? '#3b5fc0') ?>">
                        <input type="text" id="color-text" class="krono-input" value="<?= e($link['color'] ?? '#3b5fc0') ?>" style="flex:1;" readonly>
                    </div>
                    <div class="form-hint"><i class="bi bi-info-circle"></i> Cette couleur sera utilisée pour l'icône et l'effet de survol.</div>
                </div>
            </div>

            <!-- Ne proposer le mode d'accès direct que lors de la création -->
            <?php if (!$isEdit): ?>
            <div class="form-group">
                <label class="krono-label">Mode d'accès par défaut</label>
                <select name="access_mode" form="linkForm" class="krono-input">
                    <option value="open">Ouvert à tous</option>
                    <option value="group">Groupes sélectionnés</option>
                    <option value="manual">Individus sélectionnés</option>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="access_mode" form="linkForm" id="access_mode_hidden" value="<?= e($link['access_mode'] ?? 'open') ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="krono-label">Description (optionnelle)</label>
                <textarea name="description" form="linkForm" class="krono-input" rows="3" placeholder="S'affichera au survol ou dans les détails..."><?= e($link['description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Aide / Preview -->
        <div class="fade-in-up anim-delay-2 glass-card">
            <h3 class="krono-section-title" style="margin-top:0;">Aperçu visuel</h3>
            <p style="font-size:.85rem; color:var(--krono-text-3); margin-bottom:1.5rem;">Voici comment le lien apparaîtra sur le portail utilisateur.</p>
            
            <div id="preview-card" class="organic-card" style="--app-color: <?= e($link['color'] ?? '#3b5fc0') ?>; --app-color-rgb: <?= implode(',', hexToRgb($link['color'] ?? '#3b5fc0')) ?>; pointer-events: none; margin: 0 auto;">
                <div class="card-glass-panel" style="background: var(--krono-surface);">
                    <div class="card-inner-glow"></div>
                    <div class="card-header" style="padding: 1.25rem;">
                        <div id="preview-icon-wrapper" class="card-icon-wrapper" style="background: var(--app-color); color: white;">
                            <i id="preview-card-icon" class="bi bi-<?= e($link['icon'] ?? 'link-45deg') ?>"></i>
                        </div>
                        <div class="card-text-wrapper">
                            <h3 id="preview-card-title" class="card-app-title"><?= e($link['title'] ?? 'Mon lien personnalisé') ?></h3>
                            <p id="preview-card-desc" class="card-app-desc-short"><?= e(mb_strimwidth($link['description'] ?? 'Description de mon lien...', 0, 50, '...')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($isEdit): ?>
<!-- SECTION : PARAMÈTRES D'ACCÈS -->
<section id="tab-access" class="link-section" style="display:none;">
    <div class="krono-grid-2">
        <!-- Colonne de gauche : Mode d'accès -->
        <div>
            <div class="fade-in-up anim-delay-3 glass-card" style="padding:1.5rem;">
                <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-shield-lock"></i> Mode d'accès</h3>
                
                <div style="display:flex; flex-direction:column; gap:1rem; margin-top:1rem;">
                    
                    <label class="krono-radio-card <?= $accessMode === 'open' ? 'active' : '' ?>">
                        <!-- Le radio button envoie sa valeur au form principal, plus de form local -->
                        <input type="radio" name="access_mode_radio" form="linkForm" value="open" <?= $accessMode === 'open' ? 'checked' : '' ?>>
                        <div class="krono-radio-card__icon"><i class="bi bi-globe"></i></div>
                        <div class="krono-radio-card__text">
                            <strong>Ouvert (Public)</strong>
                            <span>Tous les utilisateurs actifs peuvent voir ce lien.</span>
                        </div>
                    </label>

                    <label class="krono-radio-card <?= $accessMode === 'group' ? 'active' : '' ?>">
                        <input type="radio" name="access_mode_radio" form="linkForm" value="group" <?= $accessMode === 'group' ? 'checked' : '' ?>>
                        <div class="krono-radio-card__icon"><i class="bi bi-collection"></i></div>
                        <div class="krono-radio-card__text">
                            <strong>Par Groupe</strong>
                            <span>Le lien est visible uniquement par les membres des groupes autorisés.</span>
                        </div>
                    </label>

                    <label class="krono-radio-card <?= $accessMode === 'manual' ? 'active' : '' ?>">
                        <input type="radio" name="access_mode_radio" form="linkForm" value="manual" <?= $accessMode === 'manual' ? 'checked' : '' ?>>
                        <div class="krono-radio-card__icon"><i class="bi bi-person-lock"></i></div>
                        <div class="krono-radio-card__text">
                            <strong>Manuel (Individuel)</strong>
                            <span>Il faut autoriser chaque utilisateur un par un.</span>
                        </div>
                    </label>

                </div>
            </div>
        </div>

        <!-- Colonne de droite : Liste (Groupes ou Utilisateurs) -->
        <div>
            <!-- Panneau : Ouvert -->
            <div id="panel-open" class="fade-in-up anim-delay-4 access-panel glass-card" style="display: <?= $accessMode === 'open' ? 'block' : 'none' ?>; padding:2rem; text-align:center;">
                <i class="bi bi-unlock" style="font-size:2.5rem; color:var(--krono-success); margin-bottom:1rem; display:block;"></i>
                <h3 style="color:var(--krono-text); margin-bottom:0.5rem;">Accès public ouvert</h3>
                <p style="font-size:0.9rem; max-width:300px; margin:0 auto; color:var(--krono-text-3);">Aucune restriction n'est appliquée.<br>Tout utilisateur avec un compte valide peut voir ce lien.</p>
            </div>

            <!-- Panneau : Manuel -->
            <div id="panel-manual" class="fade-in-up anim-delay-5 access-panel glass-card" style="display: <?= $accessMode === 'manual' ? 'block' : 'none' ?>; padding:1.5rem;">
                <?php if ($accessMode !== 'manual'): ?>
                    <p style="text-align:center; color:var(--krono-text-3); font-size:0.9rem; padding:1rem 0;">Veuillez enregistrer les modifications pour pouvoir gérer les utilisateurs autorisés.</p>
                <?php else: ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <h3 class="krono-section-title" style="margin:0;"><i class="bi bi-people"></i> Utilisateurs autorisés</h3>
                        <button type="button" class="btn-krono btn-krono--primary btn-krono--sm" onclick="openModal('modalAddUser')">
                            <i class="bi bi-plus-lg"></i> Ajouter
                        </button>
                    </div>

                    <?php if (empty($manualUsers)): ?>
                        <p style="text-align:center; color:var(--krono-text-3); font-size:0.9rem; padding:1rem 0;">Aucun utilisateur n'est autorisé pour le moment.</p>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <?php foreach ($manualUsers as $u): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; background:var(--krono-surface-2); border-radius:var(--krono-radius);">
                                    <div>
                                        <div style="font-weight:600;"><?= e($u['prenom'] . ' ' . $u['nom']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--krono-text-3);"><?= e($u['email']) ?></div>
                                    </div>
                                    <form method="POST" action="<?= url('/admin/links/' . $link['id'] . '/access-revoke') ?>" style="margin:0;">
                                        <?= csrf() ?>
                                        <input type="hidden" name="type" value="user">
                                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-krono btn-krono--danger btn-krono--sm" title="Révoquer l'accès">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Panneau : Groupes -->
            <div id="panel-group" class="fade-in-up anim-delay-6 access-panel glass-card" style="display: <?= $accessMode === 'group' ? 'block' : 'none' ?>; padding:1.5rem;">
                <?php if ($accessMode !== 'group'): ?>
                    <p style="text-align:center; color:var(--krono-text-3); font-size:0.9rem; padding:1rem 0;">Veuillez enregistrer les modifications pour pouvoir gérer les groupes autorisés.</p>
                <?php else: ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <h3 class="krono-section-title" style="margin:0;"><i class="bi bi-collection"></i> Groupes autorisés</h3>
                        <button type="button" class="btn-krono btn-krono--primary btn-krono--sm" onclick="openModal('modalAddGroup')">
                            <i class="bi bi-plus-lg"></i> Ajouter
                        </button>
                    </div>

                    <?php if (empty($groupAccess)): ?>
                        <p style="text-align:center; color:var(--krono-text-3); font-size:0.9rem; padding:1rem 0;">Aucun groupe n'est autorisé pour le moment.</p>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <?php foreach ($groupAccess as $g): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; background:var(--krono-surface-2); border-radius:var(--krono-radius);">
                                    <div>
                                        <div style="font-weight:600;"><i class="bi bi-folder2-open" style="margin-right:.4rem; color:var(--krono-text-3);"></i><?= e($g['name']) ?></div>
                                    </div>
                                    <form method="POST" action="<?= url('/admin/links/' . $link['id'] . '/access-revoke') ?>" style="margin:0;">
                                        <?= csrf() ?>
                                        <input type="hidden" name="type" value="group">
                                        <input type="hidden" name="target_id" value="<?= $g['id'] ?>">
                                        <button type="submit" class="btn-krono btn-krono--danger btn-krono--sm" title="Révoquer l'accès au groupe">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Modales -->
<div class="krono-modal-backdrop" id="modalAddUser">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:400px; padding:1.5rem; text-align:left;">
        <h3 style="margin-top:0; margin-bottom:1rem;">Accorder l'accès à un utilisateur</h3>
        <form method="POST" action="<?= url('/admin/links/' . $link['id'] . '/access-grant') ?>">
            <?= csrf() ?>
            <input type="hidden" name="type" value="user">
            <div style="margin-bottom:1.5rem;">
                <label class="krono-label">Sélectionner l'utilisateur</label>
                <select name="target_id" class="krono-input" required>
                    <option value="">-- Choisir un utilisateur --</option>
                    <?php if (isset($allUsers)): foreach ($allUsers as $u): ?>
                        <?php 
                        $alreadyGranted = false;
                        foreach ($manualUsers as $mu) {
                            if ($mu['id'] == $u['id']) { $alreadyGranted = true; break; }
                        }
                        ?>
                        <option value="<?= $u['id'] ?>" <?= $alreadyGranted ? 'disabled' : '' ?>>
                            <?= e($u['prenom'] . ' ' . $u['nom'] . ' (' . $u['email'] . ')') ?>
                            <?= $alreadyGranted ? ' — Déjà autorisé' : '' ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="closeModal('modalAddUser')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--primary">Accorder</button>
            </div>
        </form>
    </div>
</div>

<div class="krono-modal-backdrop" id="modalAddGroup">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:400px; padding:1.5rem; text-align:left;">
        <h3 style="margin-top:0; margin-bottom:1rem;">Accorder l'accès à un groupe</h3>
        <form method="POST" action="<?= url('/admin/links/' . $link['id'] . '/access-grant') ?>">
            <?= csrf() ?>
            <input type="hidden" name="type" value="group">
            <div style="margin-bottom:1.5rem;">
                <label class="krono-label">Sélectionner le groupe</label>
                <select name="target_id" class="krono-input" required>
                    <option value="">-- Choisir un groupe --</option>
                    <?php if (isset($allGroups)): foreach ($allGroups as $g): ?>
                        <?php 
                        $alreadyGranted = false;
                        foreach ($groupAccess as $mg) {
                            if ($mg['id'] == $g['id']) { $alreadyGranted = true; break; }
                        }
                        ?>
                        <option value="<?= $g['id'] ?>" <?= $alreadyGranted ? 'disabled' : '' ?>>
                            <?= e($g['name']) ?>
                            <?= $alreadyGranted ? ' — Déjà autorisé' : '' ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="closeModal('modalAddGroup')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--primary">Accorder</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ACTIONS -->
<div class="krono-form-actions">
    <button type="submit" form="linkForm" class="btn-krono btn-krono--primary">
        <i class="bi bi-check-circle"></i> <?= $isEdit ? 'Enregistrer les modifications' : 'Créer le lien' ?>
    </button>
</div>

<script>
<?php if (!function_exists('hexToRgb')): ?>
function hexToRgb(hex) {
    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? parseInt(result[1], 16) + "," + parseInt(result[2], 16) + "," + parseInt(result[3], 16) : "59,95,192";
}
<?php endif; ?>

const colorInput = document.getElementById('color-input');
const colorText = document.getElementById('color-text');
const previewCard = document.getElementById('preview-card');

if (colorInput) {
    colorInput.addEventListener('input', function(e) {
        const val = e.target.value;
        colorText.value = val;
        previewCard.style.setProperty('--app-color', val);
        previewCard.style.setProperty('--app-color-rgb', hexToRgb(val));
    });
}

const iconInput = document.getElementById('icon-input');
if (iconInput) {
    iconInput.addEventListener('input', function(e) {
        const val = e.target.value || 'link-45deg';
        document.getElementById('icon-preview').className = 'bi bi-' + val;
        document.getElementById('preview-card-icon').className = 'bi bi-' + val;
    });
}

const titleInput = document.querySelector('input[name="title"]');
if (titleInput) {
    titleInput.addEventListener('input', function(e) {
        document.getElementById('preview-card-title').textContent = e.target.value || 'Mon lien personnalisé';
    });
}

const descInput = document.querySelector('textarea[name="description"]');
if (descInput) {
    descInput.addEventListener('input', function(e) {
        const val = e.target.value || 'Description de mon lien...';
        document.getElementById('preview-card-desc').textContent = val.length > 50 ? val.substring(0, 47) + '...' : val;
    });
}

<?php if ($isEdit): ?>
// Radio buttons logic
const radios = document.querySelectorAll('input[name="access_mode_radio"]');
const hiddenInput = document.getElementById('access_mode_hidden');

radios.forEach(radio => {
    radio.addEventListener('change', function() {
        // Update hidden input used by the main form
        if (hiddenInput) {
            hiddenInput.value = this.value;
        }
        
        // Update styling
        document.querySelectorAll('.krono-radio-card').forEach(c => c.classList.remove('active'));
        this.closest('.krono-radio-card').classList.add('active');
        
        // Update panels
        document.querySelectorAll('.access-panel').forEach(p => p.style.display = 'none');
        const panel = document.getElementById('panel-' + this.value);
        if (panel) panel.style.display = 'block';
    });
});

function openModal(id) { document.getElementById(id).classList.add('is-open'); }
function closeModal(id) { document.getElementById(id).classList.remove('is-open'); }

function switchLinkTab(tabId, btn) {
    document.querySelectorAll('.link-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.krono-tab-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById('tab-' + tabId).style.display = 'block';
    btn.classList.add('active');
    
    localStorage.setItem('activeLinkTab_' + <?= $link['id'] ?>, tabId);
}

document.addEventListener('DOMContentLoaded', () => {
    const savedTab = localStorage.getItem('activeLinkTab_' + <?= $link['id'] ?>);
    if (savedTab) {
        const btn = Array.from(document.querySelectorAll('.krono-tab-btn')).find(b => b.getAttribute('onclick').includes(savedTab));
        if (btn) btn.click();
    }
});
<?php endif; ?>
</script>

<style>
.organic-card {
    width: 100%; max-width: 350px; border-radius: 24px; position: relative; overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: var(--krono-surface);
}
.card-icon-wrapper {
    width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;
}
.card-text-wrapper { flex: 1; min-width: 0; }
.card-app-title { font-size: 1rem; font-weight: 800; color: var(--krono-text); margin: 0; }
.card-app-desc-short { font-size: 0.8rem; color: var(--krono-text-3); margin: 0.15rem 0 0; }
.card-header { display: flex; align-items: center; gap: 1rem; }

.link-section { animation: fadeInContent 0.3s ease; }
@keyframes fadeInContent { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.krono-radio-card {
    display: flex; align-items: center; gap: 1rem;
    padding: 1rem; border: 1.5px solid var(--krono-border-strong);
    border-radius: var(--krono-radius);
    background: var(--krono-surface-2);
    cursor: pointer; transition: all 0.2s ease;
}
.krono-radio-card:hover { border-color: var(--krono-accent-light); }
.krono-radio-card.active { border-color: var(--krono-accent); background: var(--krono-accent-light); }
.krono-radio-card input { display: none; }
.krono-radio-card__icon { font-size: 1.5rem; color: var(--krono-text-2); }
.krono-radio-card.active .krono-radio-card__icon { color: var(--krono-accent); }
.krono-radio-card__text { display: flex; flex-direction: column; }
.krono-radio-card__text strong { color: var(--krono-text); font-size: 0.95rem; margin-bottom: 0.2rem; }
.krono-radio-card.active .krono-radio-card__text strong { color: var(--krono-accent); }
.krono-radio-card__text span { color: var(--krono-text-3); font-size: 0.8rem; line-height: 1.4; }

.krono-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.krono-modal-backdrop.is-open { display:flex; animation: fadeInModal 0.2s ease; }
@keyframes fadeInModal { from { opacity:0; } to { opacity:1; } }

.form-hint { font-size: 0.8rem; color: var(--krono-text-3); margin-top: 0.4rem; display: flex; align-items: center; gap: 0.4rem; opacity: 0.8; }

.link-config-layout { display: flex; gap: 1.5rem; align-items: flex-start; }
.link-config-layout > div:first-child { flex: 1; min-width: 0; }
.link-config-layout > div:last-child { flex: 0 0 380px; }
@media (max-width: 992px) {
    .link-config-layout { flex-direction: column; }
    .link-config-layout > div:last-child { flex: auto; width: 100%; }
}
</style>
