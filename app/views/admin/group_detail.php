<?php
/**
 * Connect — Détail / édition d'un groupe
 * Variables : $group, $members, $allUsers, $accessibleApps, $kcPermissions, $grantedKcKeys
 */
$isSystem = (bool)($group['is_system'] ?? false);
?>

<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <a href="<?= url('/admin/groups') ?>">Groupes</a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current"><?= e($group['name']) ?></span>
</nav>

<div class="page-header">
    <div>
        <div class="page-header__title">
            <i class="bi bi-collection" style="color:var(--krono-text-3); margin-right:.5rem;"></i><?= e($group['name']) ?>
            <?php if ($isSystem): ?>
                <span class="badge-krono badge-krono--warning badge-no-dot" style="font-size:.65rem; margin-left:.5rem; vertical-align:middle;">Système</span>
            <?php endif; ?>
        </div>
        <div class="page-header__subtitle"><?= e($group['description']) ?: 'Aucune description' ?></div>
    </div>
</div>

<!-- ── Onglets : Informations / Membres / Permissions ── -->
<div class="krono-tabs">
    <button class="krono-tab-btn active" onclick="switchGroupTab('info', this)">
        <i class="bi bi-info-circle-fill"></i> Informations
    </button>
    <button class="krono-tab-btn" onclick="switchGroupTab('members', this)">
        <i class="bi bi-people"></i> Membres (<?= count($members) ?>)
    </button>
    <button class="krono-tab-btn" onclick="switchGroupTab('perms', this)">
        <i class="bi bi-shield-lock"></i> Permissions
    </button>
</div>
<form method="POST" action="<?= url('/admin/groups/' . $group['id'] . '/info') ?>" id="form-group-all" data-unsaved-detection>
    <?= csrf() ?>

<!-- SECTION : INFORMATIONS -->
<section id="tab-info" class="group-section active">
    <div class="fade-in-up anim-delay-1 glass-card" style="margin-bottom:1.5rem;">
        <div style="font-size:.95rem; font-weight:700; color:var(--krono-text); margin-bottom:1.25rem;
                    padding-bottom:.75rem; border-bottom:1px solid var(--krono-border);">
            <i class="bi bi-info-circle-fill" style="color:var(--krono-accent); margin-right:.5rem;"></i>
            Informations générales
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="krono-label" for="f-name">Nom du groupe <span class="text-danger">*</span></label>
                <input type="text" id="f-name" name="name" class="krono-input"
                       value="<?= e($group['name']) ?>"
                       <?= $isSystem ? 'readonly title="Lecture seule pour un groupe système"' : 'required' ?>>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="krono-label" for="f-tech-name">Nom technique (Clé)</label>
                <input type="text" id="f-tech-name" name="tech_name" class="krono-input"
                       value="<?= e($group['tech_name'] ?? '') ?>"
                       <?= $isSystem ? 'readonly title="Lecture seule pour un groupe système"' : 'pattern="[a-z0-9_]+"' ?>
                       placeholder="Ex: rh, support...">
            </div>
        </div>
        
        <div class="form-group" style="margin-top:1rem; margin-bottom:0;">
            <label class="krono-label" for="f-description">Description</label>
            <textarea id="f-description" name="description" class="krono-input" rows="2"
                      placeholder="Décrivez brièvement le groupe..."><?= e($group['description'] ?? '') ?></textarea>
        </div>

        <div style="margin-top:1.25rem; margin-bottom:0; display:flex; align-items:flex-start; gap:0.5rem;">
            <div class="krono-switch" style="margin-top:2px;">
                <input type="checkbox" id="f-require-mfa" name="require_mfa" value="1" <?= ($group['require_mfa'] ?? 0) ? 'checked' : '' ?>>
                <span class="krono-slider"></span>
            </div>
            <div>
                <label for="f-require-mfa" style="font-weight:600; cursor:pointer; color:var(--krono-text);">Obliger l'authentification à double facteur (MFA)</label>
                <div style="font-size:0.8rem; color:var(--krono-text-3); margin-top:2px;">Tous les membres de ce groupe devront configurer et utiliser le MFA pour se connecter.</div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION : MEMBRES -->
<section id="tab-members" class="group-section">
    <div class="fade-in-up anim-delay-2 glass-card" style="padding:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h2 class="krono-section-title" style="margin:0;"><i class="bi bi-people"></i> Membres du groupe</h2>
            <button type="button" class="btn-krono btn-krono--primary btn-krono--sm" onclick="document.getElementById('modalAddMember').classList.add('is-open')">
                <i class="bi bi-person-plus"></i> Ajouter un membre
            </button>
        </div>

        <div id="members-empty-placeholder" class="krono-table-empty" style="<?= empty($members) ? '' : 'display:none;' ?>">
            <i class="bi bi-people"></i>
            Ce groupe ne contient aucun membre.
        </div>

        <div class="fade-in-up anim-delay-3 krono-table-wrap" id="members-table-wrap" style="<?= empty($members) ? 'display:none;' : '' ?>">
            <table class="krono-table">
                <thead><tr><th>Utilisateur</th><th>Email</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody id="members-table-body">
                    <?php foreach ($members as $m): ?>
                    <tr id="member-row-<?= $m['id'] ?>">
                        <td style="font-weight:600;"><?= e($m['prenom'] . ' ' . $m['nom']) ?></td>
                        <td style="font-size:.85rem; color:var(--krono-text-3);"><?= e($m['email']) ?></td>
                        <td style="text-align:right;">
                            <div class="table-actions">
                                <input type="hidden" name="user_ids[]" value="<?= $m['id'] ?>">
                                <button type="button" class="btn-krono btn-krono--danger btn-krono--sm" title="Retirer du groupe" onclick="removeGroupMemberRow(<?= $m['id'] ?>)">
                                    <i class="bi bi-person-dash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- SECTION : PERMISSIONS -->
<section id="tab-perms" class="group-section">
    <p style="font-size:.85rem; color:var(--krono-text-3); margin-bottom:1rem;">
        Cochez les permissions que les membres de ce groupe recevront automatiquement. Cliquez sur Enregistrer en bas pour sauvegarder.
    </p>

    <!-- Permissions KronoConnect -->
    <div class="fade-in-up anim-delay-4 glass-card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:1rem;">
            <div style="width:32px; height:32px; border-radius:6px; background:var(--krono-accent-light); display:flex; align-items:center; justify-content:center; color:var(--krono-accent);">
                <i class="bi bi-shield-lock"></i>
            </div>
            <div>
                <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:var(--krono-text);">KronoConnect (Panel Admin)</h3>
                <span style="font-family:monospace; font-size:.7rem; color:var(--krono-text-3);">Système central</span>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:.75rem;">
            <?php foreach ($kcPermissions as $perm): ?>
                <?php 
                $isChecked = in_array($perm['key'], $grantedKcKeys); 
                $parentKey = $perm['parent_key'] ?? $perm['requires'] ?? null;
                $hasParent = !empty($parentKey);
                $parentIsChecked = $hasParent ? in_array($parentKey, $grantedKcKeys) : true;
                $isDisabled = $hasParent && !$parentIsChecked;
                ?>
                <label class="perm-card <?= $isDisabled ? 'is-disabled' : '' ?>"
                       data-perm="<?= e($perm['key']) ?>"
                       data-parent="<?= e($parentKey ?: '') ?>"
                       data-client="kc"
                       style="display:flex; flex-direction:column; align-items:stretch; justify-content:flex-start; gap:0; padding:.75rem; background:var(--krono-surface-2); border:1px solid var(--krono-border); border-radius:var(--krono-radius); cursor:<?= $isDisabled ? 'not-allowed' : 'pointer' ?>; opacity:<?= $isDisabled ? '0.6' : '1' ?>; transition: opacity 0.2s;">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; width:100%; gap:0.5rem;">
                        <div style="display:flex; gap:0.75rem; flex:1; min-width:0;">
                            <div style="flex-shrink:0; width:1.5rem; text-align:center; display:flex; justify-content:center;">
                                <?php 
                                $iconClass = ($isChecked && !$isDisabled) ? 'bi-shield-fill-check' : 'bi-shield-check';
                                $iconColor = ($isChecked && !$isDisabled) ? 'var(--krono-primary)' : 'var(--krono-text-3)';
                                ?>
                                <i class="bi <?= $iconClass ?> shield-icon" style="color: <?= $iconColor ?>; font-size: 1.1rem; margin-top: 2px;"></i>
                            </div>
                            <div style="display:flex; flex-direction:column; flex:1; min-width:0; gap:.15rem;">
                                <div style="display:flex; align-items:center; gap:0.4rem; margin-bottom: 0.15rem; max-width:100%;">
                                    <strong style="font-size:.85rem; color:var(--krono-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0;" title="<?= e($perm['label']) ?>"><?= e($perm['label']) ?></strong>
                                </div>
                                <span style="font-family:monospace; font-size:.7rem; color:var(--krono-text-3);"><?= e($perm['key']) ?></span>
                                <?php if ($hasParent): ?>
                                    <span style="font-size:.7rem; color:var(--krono-accent); font-weight:600; margin-top:.4rem;">
                                        <i class="bi bi-link-45deg"></i> Requis : <?= e($parentKey) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="krono-switch" style="margin:0; flex-shrink:0;">
                            <input type="checkbox" name="kc_perms[<?= e($perm['key']) ?>]" value="1" <?= $isChecked ? 'checked' : '' ?> <?= $isDisabled ? 'disabled' : '' ?> onchange="handlePermChange(this)">
                            <span class="krono-slider"></span>
                        </div>
                    </div>
                    <?php if (!empty($perm['description'])): ?>
                    <div style="padding-left: 2.25rem; padding-right: 0.5rem; margin-top:0.3rem;">
                        <span style="font-size:.75rem; color:var(--krono-text-2); line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis;" title="<?= e($perm['description']) ?>"><?= e($perm['description']) ?></span>
                    </div>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Permissions Apps Tierces -->
    <?php if (empty($accessibleApps)): ?>
        <div class="krono-alert krono-alert--info">
            <i class="bi bi-info-circle"></i> Aucune application SSO accessible (en mode 'Ouvert' ou configurée pour ce groupe) n'est disponible.
        </div>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            <?php foreach ($accessibleApps as $app): ?>
                <div class="fade-in-up anim-delay-5 glass-card" style="padding:1.5rem;">
                    <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:1rem;">
                        <div style="width:32px; height:32px; border-radius:6px; background:var(--krono-surface-2); display:flex; align-items:center; justify-content:center; color:var(--krono-text-3);">
                            <i class="bi bi-app-indicator"></i>
                        </div>
                        <div>
                            <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:var(--krono-text);"><?= e($app['app_name'] ?: $app['name']) ?></h3>
                            <span style="font-family:monospace; font-size:.7rem; color:var(--krono-text-3);"><?= e($app['client_id']) ?></span>
                        </div>
                    </div>

                    <?php if (empty($app['permissions_list'])): ?>
                        <p style="font-size:.85rem; color:var(--krono-text-3); margin:0;">
                            Aucune permission n'est déclarée. <br>
                            <a href="<?= url('/admin/clients') ?>" class="krono-link" style="font-weight:600;">Synchroniser le manifest d'abord</a>.
                        </p>
                    <?php else: ?>
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:.75rem;">
                            <?php foreach ($app['permissions_list'] as $perm): ?>
                                <?php 
                                $isChecked = in_array($perm['perm_key'], $app['granted_perms']); 
                                $parentKey = $perm['parent_key'] ?? null;
                                $hasParent = !empty($parentKey);
                                $parentIsChecked = $hasParent ? in_array($parentKey, $app['granted_perms']) : true;
                                $isDisabled = $hasParent && !$parentIsChecked;
                                ?>
                                <label class="perm-card <?= $isDisabled ? 'is-disabled' : '' ?>"
                                       data-perm="<?= e($perm['perm_key']) ?>"
                                       data-parent="<?= e($parentKey ?: '') ?>"
                                       data-client="<?= (int)$app['id'] ?>"
                                       style="display:flex; flex-direction:column; align-items:stretch; justify-content:flex-start; gap:0; padding:.75rem; background:var(--krono-surface-2); border:1px solid var(--krono-border); border-radius:var(--krono-radius); cursor:<?= $isDisabled ? 'not-allowed' : 'pointer' ?>; opacity:<?= $isDisabled ? '0.6' : '1' ?>; transition: opacity 0.2s;">
                                    <div style="display:flex; align-items:flex-start; justify-content:space-between; width:100%; gap:0.5rem;">
                                        <div style="display:flex; gap:0.75rem; flex:1; min-width:0;">
                                            <div style="flex-shrink:0; width:1.5rem; text-align:center; display:flex; justify-content:center;">
                                                <?php 
                                                $iconClass = ($isChecked && !$isDisabled) ? 'bi-shield-fill-check' : 'bi-shield-check';
                                                $iconColor = ($isChecked && !$isDisabled) ? 'var(--krono-primary)' : 'var(--krono-text-3)';
                                                ?>
                                                <i class="bi <?= $iconClass ?> shield-icon" style="color: <?= $iconColor ?>; font-size: 1.1rem; margin-top: 2px;"></i>
                                            </div>
                                            <div style="display:flex; flex-direction:column; flex:1; min-width:0; gap:.15rem;">
                                                <div style="display:flex; align-items:center; gap:0.4rem; margin-bottom: 0.15rem; max-width:100%;">
                                                    <strong style="font-size:.85rem; color:var(--krono-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0;" title="<?= e($perm['label']) ?>"><?= e($perm['label']) ?></strong>
                                                </div>
                                                <span style="font-family:monospace; font-size:.7rem; color:var(--krono-text-3);"><?= e($perm['perm_key']) ?></span>
                                                <?php if ($hasParent): ?>
                                                    <span style="font-size:.7rem; color:var(--krono-accent); font-weight:600; margin-top:.4rem;">
                                                        <i class="bi bi-link-45deg"></i> Requis : <?= e($parentKey) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="krono-switch" style="margin:0; flex-shrink:0;">
                                            <input type="checkbox" name="app_perms[<?= $app['id'] ?>][<?= e($perm['perm_key']) ?>]" value="1" <?= $isChecked ? 'checked' : '' ?> <?= $isDisabled ? 'disabled' : '' ?> onchange="handlePermChange(this)">
                                            <span class="krono-slider"></span>
                                        </div>
                                    </div>
                                    <?php if ($perm['description']): ?>
                                    <div style="padding-left: 2.25rem; padding-right: 0.5rem; margin-top:0.3rem;">
                                        <span style="font-size:.75rem; color:var(--krono-text-2); line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis;" title="<?= e($perm['description']) ?>"><?= e($perm['description']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

    <!-- BOUTON D'ENREGISTREMENT GLOBAL -->
    <div class="group-save-row" id="group-save-row" style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
        <button type="submit" class="btn-krono btn-krono--primary">
            <i class="bi bi-check-circle"></i> Enregistrer les modifications
        </button>
    </div>
</form>

<!-- Modale Ajout Membre -->
<div class="krono-modal-backdrop" id="modalAddMember">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:500px; padding:1.5rem; text-align:left; overflow:visible;">
        <h3 style="margin-top:0; margin-bottom:1rem;">Ajouter des membres</h3>
        
        <p style="font-size:0.85rem; color:var(--krono-text-3); margin-bottom:1rem;">
            Attention : Un utilisateur ne peut appartenir qu'à un seul groupe. S'il est déjà dans un autre groupe, il en sera retiré.
        </p>

        <div style="margin-bottom:1.5rem;">
            <label class="krono-label">Rechercher des utilisateurs</label>
            
            <div class="selected-users" id="selected-users-container" style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; min-height: 2px;"></div>
            
            <div class="user-search-wrap" style="position: relative;">
                <div style="position: relative;">
                    <i class="bi bi-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--krono-text-3);"></i>
                    <input type="text" id="user-search-input" class="krono-input" placeholder="Nom, prénom ou email..." autocomplete="off" style="padding-left: 2.5rem;">
                </div>
                <div class="user-search-results" id="user-search-results" style="position: absolute; top: 100%; left: 0; right: 0; background: var(--krono-surface); border: 1px solid var(--krono-border); border-radius: var(--krono-radius); max-height: 200px; overflow-y: auto; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: none;"></div>
            </div>
            
            <div id="hidden-inputs-container"></div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:.5rem;">
            <button type="button" class="btn-krono btn-krono--ghost" onclick="document.getElementById('modalAddMember').classList.remove('is-open')">Annuler</button>
            <button type="button" class="btn-krono btn-krono--primary" onclick="confirmAddSelectedUsers()">Ajouter la sélection</button>
        </div>
    </div>
</div>

<style>
.group-section { display: none; }
.group-section.active { display: block; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }

/* Styles pour la recherche d'utilisateurs */
.user-search-item { padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid var(--krono-border-light); display: flex; flex-direction: column; }
.user-search-item:hover { background: var(--krono-surface-2); }
.user-search-item:last-child { border-bottom: none; }
.user-search-item .name { font-weight: 600; color: var(--krono-text); font-size: 0.9rem; }
.user-search-item .email { font-size: 0.8rem; color: var(--krono-text-3); }

.user-pill { 
    background: var(--krono-accent-light); color: var(--krono-accent); 
    padding: 0.35rem 0.75rem; border-radius: 50px; font-size: 0.85rem; 
    display: inline-flex; align-items: center; gap: 0.5rem; border: 1px solid var(--krono-accent);
}
.user-pill button { 
    background: none; border: none; color: inherit; cursor: pointer; 
    padding: 0; display: flex; align-items: center; opacity: 0.7; transition: opacity 0.2s; font-size: 1rem;
}
.user-pill button:hover { opacity: 1; }
</style>

<script>
function handlePermChange(checkbox) {
    const label = checkbox.closest('label');
    if (!label) return;

    const permName = label.dataset.perm;
    const clientId = label.dataset.client;
    const granted = checkbox.checked;

    // Mise à jour de l'icône de bouclier
    const icon = label.querySelector('.shield-icon');
    if (icon) {
        icon.className = checkbox.checked ? 'bi bi-shield-fill-check shield-icon' : 'bi bi-shield-check shield-icon';
        icon.style.color = checkbox.checked ? 'var(--krono-primary)' : 'var(--krono-text-3)';
    }

    // Gestion récursive des dépendances descendants
    const selector = `.perm-card[data-parent="${permName}"][data-client="${clientId}"]`;
    document.querySelectorAll(selector).forEach(childCard => {
        const childInput = childCard.querySelector('input[type="checkbox"]');
        if (!childInput) return;

        if (!granted) {
            // Si le parent est décoché, le descendant est décoché et désactivé
            if (childInput.checked) {
                childInput.checked = false;
                handlePermChange(childInput);
            }
            childInput.disabled = true;
            childCard.classList.add('is-disabled');
            childCard.style.cursor = 'not-allowed';
            childCard.style.opacity = '0.6';
        } else {
            // Si le parent est coché, le descendant direct est réactivé
            childInput.disabled = false;
            childCard.classList.remove('is-disabled');
            childCard.style.cursor = 'pointer';
            childCard.style.opacity = '1';
        }
    });
}

function switchGroupTab(id, btn) {
    document.querySelectorAll('.group-section').forEach(s => s.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    document.querySelectorAll('.krono-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function removeGroupMemberRow(userId) {
    const row = document.getElementById('member-row-' + userId);
    if (row) {
        row.remove();
    }
    updateMembersUIState();
}

function updateMembersUIState() {
    const tbody = document.getElementById('members-table-body');
    const rowsCount = tbody ? tbody.querySelectorAll('tr').length : 0;
    
    // Mettre à jour le texte de l'onglet
    const tabBtn = document.querySelector('.krono-tab-btn[onclick*="members"]');
    if (tabBtn) {
        tabBtn.innerHTML = `<i class="bi bi-people"></i> Membres (${rowsCount})`;
    }
    
    // Afficher/masquer le tableau et le placeholder
    const tableWrap = document.getElementById('members-table-wrap');
    const placeholder = document.getElementById('members-empty-placeholder');
    if (rowsCount > 0) {
        if (tableWrap) tableWrap.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
    } else {
        if (tableWrap) tableWrap.style.display = 'none';
        if (placeholder) placeholder.style.display = 'block';
    }
}

// Logique de recherche et de sélection d'utilisateurs
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('user-search-input');
    const resultsContainer = document.getElementById('user-search-results');
    const pillsContainer = document.getElementById('selected-users-container');
    const hiddenInputsContainer = document.getElementById('hidden-inputs-container');
    
    let selectedUsers = [];
    let searchTimeout = null;

    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const q = e.target.value.trim();
        
        if (q.length < 2) {
            resultsContainer.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`<?= url('/admin/users/search') ?>?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(users => {
                    resultsContainer.innerHTML = '';
                    
                    // Récupérer dynamiquement les IDs des membres actuellement dans la table (DOM)
                    const currentMemberIds = Array.from(document.querySelectorAll('#members-table-body tr'))
                        .map(tr => parseInt(tr.id.replace('member-row-', ''), 10));
                    
                    // Filtrer ceux qui sont déjà membres ou déjà sélectionnés dans la modale
                    const filteredUsers = users.filter(u => {
                        const uid = parseInt(u.id, 10);
                        return !currentMemberIds.includes(uid) && !selectedUsers.find(su => su.id === uid);
                    });

                    if (filteredUsers.length === 0) {
                        resultsContainer.innerHTML = '<div style="padding: 1rem; color: var(--krono-text-3); text-align: center; font-size: 0.9rem;">Aucun utilisateur correspondant trouvé.</div>';
                    } else {
                        filteredUsers.forEach(u => {
                            const div = document.createElement('div');
                            div.className = 'user-search-item';
                            div.innerHTML = `<span class="name">${u.prenom} ${u.nom}</span><span class="email">${u.email}</span>`;
                            div.addEventListener('click', () => addUser(u));
                            resultsContainer.appendChild(div);
                        });
                    }
                    resultsContainer.style.display = 'block';
                });
        }, 300);
    });

    // Cacher les résultats au clic à l'extérieur
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });

    function addUser(user) {
        const uid = parseInt(user.id, 10);
        if (selectedUsers.find(u => u.id === uid)) return;
        
        selectedUsers.push(user);
        searchInput.value = '';
        resultsContainer.style.display = 'none';
        renderPills();
    }

    function removeUser(uid) {
        selectedUsers = selectedUsers.filter(u => u.id !== uid);
        renderPills();
    }

    window.removeSelectedUser = removeUser;

    function renderPills() {
        pillsContainer.innerHTML = '';
        hiddenInputsContainer.innerHTML = '';
        
        selectedUsers.forEach(u => {
            const pill = document.createElement('div');
            pill.className = 'user-pill';
            pill.innerHTML = `
                ${u.prenom} ${u.nom} 
                <button type="button" onclick="removeSelectedUser(${u.id})"><i class="bi bi-x"></i></button>
            `;
            pillsContainer.appendChild(pill);
        });
    }

    window.confirmAddSelectedUsers = function() {
        const tbody = document.getElementById('members-table-body');
        if (!tbody) return;

        selectedUsers.forEach(u => {
            const uid = parseInt(u.id, 10);
            if (document.getElementById('member-row-' + uid)) {
                return;
            }

            const tr = document.createElement('tr');
            tr.id = 'member-row-' + uid;
            tr.innerHTML = `
                <td style="font-weight:600;">${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</td>
                <td style="font-size:.85rem; color:var(--krono-text-3);">${escapeHtml(u.email)}</td>
                <td style="text-align:right;">
                    <div class="table-actions">
                        <input type="hidden" name="user_ids[]" value="${uid}">
                        <button type="button" class="btn-krono btn-krono--danger btn-krono--sm" title="Retirer du groupe" onclick="removeGroupMemberRow(${uid})">
                            <i class="bi bi-person-dash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Réinitialiser la sélection de la modale
        selectedUsers = [];
        pillsContainer.innerHTML = '';
        hiddenInputsContainer.innerHTML = '';
        searchInput.value = '';

        updateMembersUIState();

        document.getElementById('modalAddMember').classList.remove('is-open');
    };
});
</script>
