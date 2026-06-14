<?php
/**
 * Admin — Détail d'un utilisateur
 * Variables : $user, $kcPermissions, $kcGroupPermKeys, $kcUserOverrides, $accessibleApps, $manualApps, $services
 */
?>

<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <a href="<?= url('/admin/users') ?>">Utilisateurs</a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current"><?= e($user['prenom'] . ' ' . $user['nom']) ?></span>
</nav>

<div class="page-header">
    <div style="display:flex; align-items:center; gap:1rem;">
        <div style="width:56px; height:56px; border-radius:50%; background:var(--krono-accent-gradient); display:flex; align-items:center; justify-content:center; color:white; font-size:1.4rem; font-weight:800; flex-shrink:0;">
            <?= e(strtoupper(mb_substr($user['prenom'], 0, 1) . mb_substr($user['nom'], 0, 1))) ?>
        </div>
        <div>
            <h1 class="page-header__title">
                <?= e($user['prenom'] . ' ' . $user['nom']) ?>
                <?php if (!$user['is_active']): ?>
                    <span class="badge-krono badge-krono--danger badge-no-dot" style="vertical-align:middle; margin-left:.5rem;">Désactivé</span>
                <?php endif; ?>
            </h1>
            <p class="page-header__subtitle">
                <i class="bi bi-envelope"></i> <?= e($user['email']) ?>
                <span style="margin:0 .5rem;">&middot;</span>
                <i class="bi bi-people-fill"></i> <?= e(!empty($groups) ? $groups[0]['name'] : 'Aucun groupe') ?>
            </p>
        </div>
    </div>
    <div class="page-header__actions">
        <div style="display:flex; gap:.5rem;">
            <form method="POST" action="<?= url('/admin/users/toggle') ?>" style="margin:0;">
                <?= csrf() ?>
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                <button type="submit" class="btn-krono <?= $user['is_active'] ? 'btn-krono--danger' : 'btn-krono--success' ?>">
                    <i class="bi <?= $user['is_active'] ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                    <?= $user['is_active'] ? 'Désactiver' : 'Activer' ?>
                </button>
            </form>
            <?php if ((\KronoConnect\Core\Session::userId() !== (int)$user['id']) && \KronoConnect\Core\Session::hasPermission('kc.users.delete')): ?>
            <button type="button" class="btn-krono btn-krono--danger" onclick="confirmUserDeletion(<?= (int)$user['id'] ?>, '<?= e($user['prenom'] . ' ' . $user['nom']) ?>')">
                <i class="bi bi-trash"></i>
                Supprimer l'utilisateur
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Onglets -->
<div class="krono-tabs">
    <button class="krono-tab-btn active" onclick="switchUserTab('info', this)">Modification & Infos</button>
    <button class="krono-tab-btn" onclick="switchUserTab('access', this)">Accès Applications</button>
    <button class="krono-tab-btn" onclick="switchUserTab('perms', this)">Permissions</button>
</div>

<!-- SECTION : MODIFICATION & INFOS -->
<section id="tab-info" class="user-section active">
    <div class="krono-grid-2">
        <div class="fade-in-up anim-delay-1 glass-card" style="padding:1.5rem;">
            <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-pencil-square"></i> Modifier le profil</h3>
            <form method="POST" action="<?= url('/admin/users/save') ?>">
                <?= csrf() ?>
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                
                <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                    <div style="flex:1;">
                        <label class="krono-label">Prénom</label>
                        <input type="text" name="prenom" value="<?= e($user['prenom']) ?>" class="krono-input" required>
                    </div>
                    <div style="flex:1;">
                        <label class="krono-label">Nom</label>
                        <input type="text" name="nom" value="<?= e($user['nom']) ?>" class="krono-input" required>
                    </div>
                </div>
                
                <div style="margin-bottom:1rem;">
                    <label class="krono-label">Adresse e-mail</label>
                    <input type="email" name="email" value="<?= e($user['email']) ?>" class="krono-input" required>
                </div>

                <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                    <div style="flex:1;">
                        <label class="krono-label">Téléphone professionnel</label>
                        <input type="text" name="phone" value="<?= e($user['phone'] ?? '') ?>" class="krono-input" placeholder="Ex: 06 00 00 00 00">
                    </div>
                    <div style="flex:1;">
                        <label class="krono-label">Service / Direction</label>
                        <select name="service_id" class="krono-input">
                            <option value="">-- Aucun (Non assigné) --</option>
                            <?php
                            function renderServiceOptions(array $nodes, $selectedId, $prefix = '') {
                                foreach ($nodes as $n) {
                                    $sel = ((int)$n['id'] === (int)$selectedId) ? 'selected' : '';
                                    echo '<option value="' . $n['id'] . '" ' . $sel . '>' . e($prefix . $n['name']) . '</option>';
                                    if (!empty($n['children'])) {
                                        renderServiceOptions($n['children'], $selectedId, $prefix . '   ');
                                    }
                                }
                            }
                            renderServiceOptions($services ?? [], $user['service_id'] ?? '');
                            ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:1rem;">
                    <label class="krono-label">Groupe</label>
                    <select name="group_id" class="krono-input" required>
                        <?php 
                        $userGroupId = !empty($groups) ? (int)$groups[0]['id'] : 0;
                        foreach ($allGroups as $g): 
                        ?>
                            <option value="<?= (int)$g['id'] ?>" <?= $userGroupId === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:1.5rem;">
                    <label class="krono-label">Nouveau mot de passe <span style="font-size:0.75rem; color:var(--krono-text-3); font-weight:normal;">(Laisser vide pour ne pas changer)</span></label>
                    <input type="password" name="password" class="krono-input">
                </div>

                <button type="submit" class="btn-krono btn-krono--primary" style="width:100%;">Enregistrer les modifications</button>
            </form>
        </div>

        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            <div class="fade-in-up anim-delay-2 glass-card" style="padding:1.5rem;">
                <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-info-circle"></i> État du compte</h3>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:.75rem;">
                    <li style="display:flex; justify-content:space-between; padding-bottom:.5rem; border-bottom:1px solid var(--krono-border-strong);">
                        <span style="color:var(--krono-text-3);">ID Interne</span>
                        <strong>#<?= $user['id'] ?></strong>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding-bottom:.5rem; border-bottom:1px solid var(--krono-border-strong);">
                        <span style="color:var(--krono-text-3);">Inscrit le</span>
                        <strong><?= dateFormat($user['created_at']) ?></strong>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding-bottom:.5rem; border-bottom:1px solid var(--krono-border-strong);">
                        <span style="color:var(--krono-text-3);">Dernier thème</span>
                        <strong><?= e($user['theme'] ?? 'system') ?></strong>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding-bottom:.5rem; border-bottom:1px solid var(--krono-border-strong);">
                        <span style="color:var(--krono-text-3);">Dernière activité</span>
                        <strong><?= !empty($user['last_activity_at']) ? dateFormat($user['last_activity_at']) : 'Jamais' ?></strong>
                    </li>
                </ul>
            </div>

            <div class="fade-in-up anim-delay-2 glass-card" style="padding:1.5rem;">
                <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-shield-lock"></i> Sécurité MFA</h3>
                
                <?php if (!empty($user['mfa_enabled'])): ?>
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                        <span class="badge-krono badge-krono--success badge-no-dot" style="padding:0.4rem 0.8rem; font-weight:800; font-size:0.8rem; border-radius: 8px;">
                            <i class="bi bi-check-circle-fill"></i> MFA Activé
                        </span>
                    </div>
                    <p style="font-size:0.85rem; color:var(--krono-text-3); margin-bottom:1.5rem; line-height:1.4;">
                        L'utilisateur a activé la double authentification. Si l'accès est bloqué ou s'il a perdu son appareil, vous pouvez désactiver son MFA en un clic.
                    </p>
                    
                    <form method="POST" action="<?= url('/admin/users/' . $user['id'] . '/mfa-disable') ?>" onsubmit="return confirm('Êtes-vous sûr de vouloir désactiver et réinitialiser le MFA de cet utilisateur ?');">
                        <?= csrf() ?>
                        <button type="submit" class="btn-krono btn-krono--danger" style="width:100%;">
                            <i class="bi bi-shield-slash"></i> Désactiver le MFA
                        </button>
                    </form>
                <?php else: ?>
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                        <span class="badge-krono badge-krono--neutral badge-no-dot" style="padding:0.4rem 0.8rem; font-weight:800; font-size:0.8rem; background: var(--krono-surface-3); color: var(--krono-text-3); border-radius: 8px;">
                            <i class="bi bi-slash-circle"></i> MFA Inactif
                        </span>
                    </div>
                    <p style="font-size:0.85rem; color:var(--krono-text-3); margin:0; line-height:1.4;">
                        La double authentification n'est pas activée sur ce compte.
                    </p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<!-- SECTION : ACCES APPS -->
<section id="tab-access" class="user-section">
    <div class="fade-in-up anim-delay-3 glass-card" style="padding:1.5rem;">
        <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-person-lock"></i> Accès individuels (Mode Manuel)</h3>
        <p style="font-size:.85rem; color:var(--krono-text-3); margin-bottom:1.5rem;">
            Applications pour lesquelles cet utilisateur a reçu un accès nominatif spécifique.
        </p>

        <?php if (empty($manualApps)): ?>
            <p style="text-align:center; color:var(--krono-text-3); font-size:.9rem;">Aucun accès manuel accordé.</p>
        <?php else: ?>
            <div class="fade-in-up anim-delay-4 krono-table-wrap">
                <table class="krono-table">
                    <thead><tr><th>Application</th><th>Accordé le</th></tr></thead>
                    <tbody>
                        <?php foreach ($manualApps as $ma): ?>
                        <tr>
                            <td style="font-weight:600;"><i class="bi bi-app-indicator" style="margin-right:.5rem;color:var(--krono-text-3);"></i><?= e($ma['app_name'] ?: $ma['name']) ?></td>
                            <td style="font-size:.85rem; color:var(--krono-text-3);"><?= dateFormat($ma['granted_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- SECTION : PERMISSIONS INDIVIDUELLES -->
<section id="tab-perms" class="user-section">
    <p style="font-size:.85rem; color:var(--krono-text-3); margin-bottom:1.5rem; background:var(--krono-surface-2); padding:.75rem; border-radius:var(--krono-radius); border-left:4px solid var(--krono-accent);">
        Les permissions marquées <span class="badge-krono badge-krono--success badge-no-dot" style="font-size:0.6rem; padding:0.1rem 0.3rem;"><i class="bi bi-diagram-3"></i> Grade</span> sont héritées du rôle ou des groupes de l'utilisateur. Vous pouvez les révoquer individuellement ou accorder des droits supplémentaires.
    </p>

    <!-- Permissions KronoConnect -->
    <div class="fade-in-up anim-delay-5 glass-card" style="padding:1.5rem; margin-bottom:1.5rem;">
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
                $k = $perm['key'];
                $inherited = in_array($k, $kcGroupPermKeys) ? '1' : '0';
                $override = isset($kcUserOverrides[$k]) ? (string)$kcUserOverrides[$k] : '';
                ?>
                <div class="perm-row" data-client="" data-key="<?= e($k) ?>" data-parent="<?= e($perm['parent_key'] ?? $perm['requires'] ?? '') ?>" data-label="<?= e($perm['label']) ?>" data-description="<?= e($perm['description'] ?? '') ?>" data-inherited="<?= $inherited ?>" data-override="<?= $override ?>" style="display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; padding:.75rem; background:var(--krono-surface-2); border:1px solid var(--krono-border); border-radius:var(--krono-radius);">
                    <!-- Le contenu sera rendu par JS -->
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Permissions Apps Tierces -->
    <?php if (empty($accessibleApps)): ?>
        <div class="krono-alert krono-alert--info">
            L'utilisateur n'a actuellement accès à aucune application, il n'y a donc pas de permissions à surcharger.
        </div>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            <?php foreach ($accessibleApps as $app): ?>
                <div class="fade-in-up anim-delay-6 glass-card" style="padding:1.5rem;">
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
                        <p style="font-size:.85rem; color:var(--krono-text-3); margin:0;">Aucune permission déclarée par l'application.</p>
                    <?php else: ?>
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:.75rem;">
                            <?php foreach ($app['permissions_list'] as $perm): ?>
                                <?php 
                                $k = $perm['perm_key'];
                                $inherited = in_array($k, $app['group_perms']) ? '1' : '0';
                                $override = isset($app['user_perms'][$k]) ? (string)$app['user_perms'][$k] : '';
                                ?>
                                <div class="perm-row" data-client="<?= $app['id'] ?>" data-key="<?= e($k) ?>" data-parent="<?= e($perm['parent_key'] ?? '') ?>" data-label="<?= e($perm['label']) ?>" data-description="<?= e($perm['description'] ?? '') ?>" data-inherited="<?= $inherited ?>" data-override="<?= $override ?>" style="display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; padding:.75rem; background:var(--krono-surface-2); border:1px solid var(--krono-border); border-radius:var(--krono-radius);">
                                    <!-- Le contenu sera rendu par JS -->
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<style>
.user-section { display: none; }
.user-section.active { display: block; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
.krono-slider.disabled { opacity: 0.6; cursor: not-allowed; }
</style>

<script>
function switchUserTab(id, btn) {
    document.querySelectorAll('.user-section').forEach(s => s.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    document.querySelectorAll('.krono-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function toggleOverride(permKey, clientId, isChecked) {
    const row = document.querySelector(`.perm-row[data-key="${permKey}"][data-client="${clientId === null ? '' : clientId}"]`);
    if (!row) return;
    const inherited = row.dataset.inherited === '1';

    if (inherited) {
        // Si hérité (activé par défaut) :
        // - Décoché (false) -> Révocation explicite (granted = false)
        // - Coché (true) -> Retour à l'état hérité (granted = null)
        if (!isChecked) {
            updateUserPerm(permKey, clientId, false);
        } else {
            updateUserPerm(permKey, clientId, null);
        }
    } else {
        // Si non hérité (désactivé par défaut) :
        // - Coché (true) -> Attribution explicite (granted = true)
        // - Décoché (false) -> Retour à l'état par défaut (granted = null)
        if (isChecked) {
            updateUserPerm(permKey, clientId, true);
        } else {
            updateUserPerm(permKey, clientId, null);
        }
    }
}

function isParentActive(parentKey, clientId) {
    if (!parentKey) return true;
    const cIdStr = clientId === null ? '' : clientId;
    const parentRow = document.querySelector(`.perm-row[data-key="${parentKey}"][data-client="${cIdStr}"]`);
    if (!parentRow) return false;

    const inherited = parentRow.dataset.inherited === '1';
    const override = parentRow.dataset.override; // "1", "0", or ""
    const finalState = override === "1" ? true : (override === "0" ? false : inherited);

    if (!finalState) return false;

    // Check parent's parent recursively
    const grandParentKey = parentRow.dataset.parent;
    return isParentActive(grandParentKey, clientId);
}

function refreshChildren(parentKey, clientId) {
    const cIdStr = clientId === null ? '' : clientId;
    document.querySelectorAll(`.perm-row[data-parent="${parentKey}"][data-client="${cIdStr}"]`).forEach(childRow => {
        refreshRow(childRow, clientId);
        // Recursively refresh children of children
        refreshChildren(childRow.dataset.key, clientId);
    });
}

function refreshRow(row, clientId) {
    const key = row.dataset.key;
    const label = row.dataset.label;
    const parentKey = row.dataset.parent || '';
    const hasParent = parentKey !== '';
    const parentIsActive = isParentActive(parentKey, clientId);
    const isParentDisabled = hasParent && !parentIsActive;

    const inherited = row.dataset.inherited === '1';
    const override = row.dataset.override; // "1", "0", or ""
    const description = row.dataset.description || '';
    
    // L'état final d'attribution (détermine si le switch est coché et si la carte est active)
    const finalState = isParentDisabled ? false : (override === "1" ? true : (override === "0" ? false : inherited));
    const isChecked = finalState;
    
    if (typeof bootstrap !== 'undefined') {
        const oldTooltips = row.querySelectorAll('[data-bs-toggle="tooltip"]');
        oldTooltips.forEach(t => {
            const instance = bootstrap.Tooltip.getInstance(t);
            if (instance) instance.dispose();
        });
    }

    let badgeHtml = '';
    let cardIconHtml = '';

    if (isParentDisabled) {
        cardIconHtml = '<i class="bi bi-shield-slash" style="color: var(--krono-text-3); font-size: 1.1rem; margin-top: 2px;"></i>';
        badgeHtml = '<span class="badge-krono badge-krono--warning badge-no-dot" style="flex-shrink:0; font-size:0.65rem; padding:0.2rem 0.5rem; display:inline-flex; align-items:center; vertical-align:middle;" data-bs-toggle="tooltip" data-bs-placement="top" title="Désactivé : parent manquant"><i class="bi bi-link-45deg"></i> Inactif</span>';
    } else if (inherited) {
        cardIconHtml = '<i class="bi bi-people-fill" style="color: var(--krono-success); font-size: 1.1rem; margin-top: 2px;"></i>';
        
        if (override === "1") {
            badgeHtml = '<span class="badge-krono badge-krono--info badge-no-dot" style="flex-shrink:0; font-size:0.65rem; padding:0.2rem 0.5rem; display:inline-flex; align-items:center; vertical-align:middle;" data-bs-toggle="tooltip" data-bs-placement="top" title="Hérité du groupe (Forcé individuellement)"><i class="bi bi-people-fill"></i></span>';
        } else if (override === "0") {
            cardIconHtml = '<i class="bi bi-people" style="color: var(--krono-text-3); font-size: 1.1rem; margin-top: 2px;"></i>';
            badgeHtml = '<span class="badge-krono badge-krono--danger badge-no-dot" style="flex-shrink:0; font-size:0.65rem; padding:0.2rem 0.5rem; display:inline-flex; align-items:center; vertical-align:middle;" data-bs-toggle="tooltip" data-bs-placement="top" title="Hérité du groupe (Révoqué individuellement)"><i class="bi bi-person-x-fill"></i></span>';
        } else {
            badgeHtml = '<span class="badge-krono badge-krono--success badge-no-dot" style="flex-shrink:0; font-size:0.65rem; padding:0.2rem 0.5rem; display:inline-flex; align-items:center; vertical-align:middle;" data-bs-toggle="tooltip" data-bs-placement="top" title="Hérité du groupe"><i class="bi bi-people-fill"></i></span>';
        }
    } else {
        cardIconHtml = '<i class="bi bi-shield-check" style="color: var(--krono-text-3); font-size: 1.1rem; margin-top: 2px;"></i>';
        if (override === "1") {
            cardIconHtml = '<i class="bi bi-shield-fill-check" style="color: var(--krono-primary); font-size: 1.1rem; margin-top: 2px;"></i>';
            badgeHtml = '<span class="badge-krono badge-krono--info badge-no-dot" style="flex-shrink:0; font-size:0.65rem; padding:0.2rem 0.5rem; display:inline-flex; align-items:center; vertical-align:middle;" data-bs-toggle="tooltip" data-bs-placement="top" title="Accordé individuellement"><i class="bi bi-person-fill-add"></i></span>';
        } else if (override === "0") {
            badgeHtml = '<span class="badge-krono badge-krono--danger badge-no-dot" style="flex-shrink:0; font-size:0.65rem; padding:0.2rem 0.5rem; display:inline-flex; align-items:center; vertical-align:middle;" data-bs-toggle="tooltip" data-bs-placement="top" title="Révoqué individuellement"><i class="bi bi-person-fill-dash"></i></span>';
        }
    }

    const cIdStr = clientId === null ? 'null' : clientId;
    const toggleHtml = `
        <label class="krono-switch" style="margin:0; flex-shrink:0; cursor:${isParentDisabled ? 'not-allowed' : 'pointer'};">
            <input type="checkbox" onchange="toggleOverride('${key}', ${cIdStr}, this.checked)" ${isChecked ? 'checked' : ''} ${isParentDisabled ? 'disabled' : ''}>
            <span class="krono-slider ${isParentDisabled ? 'disabled' : ''}"></span>
        </label>
    `;

    let parentBadgeHtml = '';
    if (hasParent) {
        parentBadgeHtml = `
            <span style="font-size:.7rem; color:var(--krono-accent); font-weight:600; margin-top:.3rem; display:block;">
                <i class="bi bi-link-45deg"></i> Requis : ${parentKey}
            </span>
        `;
    }

    row.innerHTML = `
        <div style="display:flex; align-items:flex-start; justify-content:space-between; width:100%; gap:0.5rem;">
            <div style="display:flex; gap:0.75rem; flex:1; min-width:0;">
                <div style="flex-shrink:0; width:1.5rem; text-align:center; display:flex; justify-content:center;">
                    ${cardIconHtml}
                </div>
                <div style="display:flex; flex-direction:column; flex:1; min-width:0; gap:.15rem;">
                    <div style="display:flex; align-items:center; gap:0.4rem; margin-bottom: 0.15rem; max-width:100%; min-height: 24px;">
                        <strong style="font-size:.85rem; color:var(--krono-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0;" title="${label}">${label}</strong>
                        ${badgeHtml}
                    </div>
                    <span style="font-family:monospace; font-size:.7rem; color:var(--krono-text-3);">${key}</span>
                    ${parentBadgeHtml}
                </div>
            </div>
            <div style="flex-shrink:0;">
                ${toggleHtml}
            </div>
        </div>
        ${description ? `
        <div style="padding-left: 2.25rem; padding-right: 0.5rem; margin-top:0.3rem;">
            <span style="font-size:.75rem; color:var(--krono-text-2); line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis;" title="${description}">${description}</span>
        </div>` : ''}
    `;
    
    // Style de la ligne
    row.style.flexDirection = 'column';
    row.style.alignItems = 'stretch';
    row.style.justifyContent = 'flex-start';
    row.style.gap = '0';
    if (isParentDisabled) {
        row.style.borderColor = 'var(--krono-border)';
        row.style.background = 'var(--krono-surface-2)';
        row.style.opacity = '0.6';
    } else if (inherited && override !== "0") {
        row.style.borderColor = 'var(--krono-success-border)';
        row.style.background = 'var(--krono-success-bg)';
        row.style.opacity = '1';
    } else {
        row.style.borderColor = finalState ? 'var(--krono-accent-light)' : 'var(--krono-border)';
        row.style.background = finalState ? 'rgba(59, 130, 246, 0.03)' : 'var(--krono-surface-2)';
        row.style.opacity = '1';
    }

    if (typeof bootstrap !== 'undefined') {
        const newTooltips = row.querySelectorAll('[data-bs-toggle="tooltip"]');
        newTooltips.forEach(t => new bootstrap.Tooltip(t));
    }
}

function updateUserPerm(permKey, clientId, granted) {
    const row = document.querySelector(`.perm-row[data-key="${permKey}"][data-client="${clientId === null ? '' : clientId}"]`);
    if(!row) return;
    
    fetch('<?= url("/admin/users/" . $user["id"] . "/permissions") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= \KronoConnect\Core\Security::csrfToken() ?>' },
        body: JSON.stringify({ client_id: clientId, perm_key: permKey, granted: granted })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            if (granted === null) row.dataset.override = "";
            else if (granted === true) row.dataset.override = "1";
            else if (granted === false) row.dataset.override = "0";
            
            refreshRow(row, clientId);
            refreshChildren(permKey, clientId);
            window.kronoToast({message: "Permission mise à jour", level: "success", duration: 2000});
        } else {
            window.kronoToast({message: data.error || "Erreur serveur", level: "danger"});
            refreshRow(row, clientId); // revert
        }
    })
    .catch(err => {
        window.kronoToast({message: "Erreur réseau", level: "danger"});
        refreshRow(row, clientId); // revert
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.perm-row').forEach(row => {
        const cId = row.dataset.client;
        refreshRow(row, cId === "" ? null : parseInt(cId));
    });
});

function confirmUserDeletion(userId, userName) {
    const modal = document.getElementById('modalDeleteUser');
    const form = document.getElementById('formDeleteUser');
    const nameSpan = document.getElementById('deleteUserName');
    if (!modal || !form || !nameSpan) return;
    form.action = "<?= url('/admin/users/') ?>" + userId + "/delete";
    nameSpan.textContent = userName;
    modal.classList.add('is-open');
}
</script>

<!-- Modale de suppression définitive d'un utilisateur -->
<div class="krono-modal-backdrop" id="modalDeleteUser">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:450px; padding:1.5rem; text-align:left;">
        <div class="modal-icon-box modal-icon-box--danger">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title" style="text-align:center;">Supprimer le compte ?</h3>
        <p class="modal-text" style="margin-bottom:1.5rem; text-align:center;">
            Vous êtes sur le point de supprimer définitivement le compte de <strong id="deleteUserName"></strong>.
        </p>
        
        <div style="background:var(--krono-danger-bg, rgba(220, 38, 38, 0.1)); border-left:4px solid var(--krono-danger, #DC2626); padding:1rem; border-radius:8px; margin-bottom:1.5rem; font-size:.85rem; color:var(--krono-text);">
            <h4 style="margin:0 0 .5rem 0; font-weight:700; color:var(--krono-danger, #DC2626);"><i class="bi bi-exclamation-circle-fill"></i> Risques et conséquences :</h4>
            <ul style="margin:0; padding-left:1.2rem; line-height:1.4;">
                <li>Perte immédiate et définitive d'accès à <strong>toutes</strong> les applications.</li>
                <li>Les logs d'audit seront anonymisés.</li>
                <li><strong>Risque de données orphelines (fantômes) :</strong> La suppression du compte sur KronoConnect n'étant pas propagée sur les bases de données locales des applications clientes, cela peut rompre les liaisons de données, casser des variables ou laisser des références de compte orphelines dans ces applications.</li>
            </ul>
        </div>
        
        <div style="background:var(--krono-surface-3, #f3f4f6); border-left:4px solid var(--krono-text-3, #9ca3af); padding:1rem; border-radius:8px; margin-bottom:1.5rem; font-size:.85rem; color:var(--krono-text-2);">
            <i class="bi bi-info-circle-fill"></i> <strong>Alternative recommandée :</strong> Dans la majorité des situations, une simple <strong>désactivation</strong> du compte suffit pour suspendre l'accès sans détruire les données.
        </div>

        <form id="formDeleteUser" method="POST" action="">
            <?= csrf() ?>
            <div class="modal-buttons" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="document.getElementById('modalDeleteUser').classList.remove('is-open')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--danger">Confirmer la suppression</button>
            </div>
        </form>
    </div>
</div>