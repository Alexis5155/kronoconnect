<?php
/**
 * Admin — Détail d'un client SSO
 * Variables : $client, $permissions, $accessMode, $manualUsers, $groupAccess, $allUsers, $allGroups, $pingStatus
 */
?>

<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <a href="<?= url('/admin/clients') ?>">Clients SSO</a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current"><?= e($client['app_name'] ?: $client['name']) ?></span>
</nav>

<div class="page-header">
    <div style="display:flex; align-items:center; gap:1rem;">
        <div style="width:56px; height:56px; border-radius:12px; background:<?= e($client['app_color'] ?? 'var(--krono-surface-2)') ?>; display:flex; align-items:center; justify-content:center; color:white; font-size:1.6rem; flex-shrink:0;">
            <i class="bi bi-<?= e($client['app_icon'] ?? 'app-indicator') ?>"></i>
        </div>
        <div>
            <h1 class="page-header__title">
                <?= e($client['app_name'] ?: $client['name']) ?>
                <span class="badge-krono <?= $pingStatus ? 'badge-krono--success' : 'badge-krono--danger' ?> badge-no-dot" style="vertical-align:middle; margin-left:.5rem; font-size:0.75rem;">
                    <i class="bi <?= $pingStatus ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                    <?= $pingStatus ? 'En ligne' : 'Injoignable' ?>
                </span>
            </h1>
            <p class="page-header__subtitle">
                Client ID: <code style="font-family:monospace; font-weight:600; color:var(--krono-accent);"><?= e($client['client_id']) ?></code>
            </p>
        </div>
    </div>
    <div class="page-header__actions">
        <form method="POST" action="<?= url('/admin/clients/sync') ?>" style="margin:0;">
            <?= csrf() ?>
            <input type="hidden" name="id" value="<?= $client['id'] ?>">
            <button type="submit" class="btn-krono btn-krono--secondary">
                <i class="bi bi-arrow-repeat"></i> Synchroniser le manifest
            </button>
        </form>
    </div>
</div>

<!-- Onglets -->
<div class="krono-tabs">
    <button class="krono-tab-btn active" onclick="switchClientTab('info', this)">Vue d'ensemble</button>
    <button class="krono-tab-btn" onclick="switchClientTab('manifest', this)">Manifest</button>
    <button class="krono-tab-btn" onclick="switchClientTab('access', this)">Paramètres d'accès</button>
    <button class="krono-tab-btn" onclick="switchClientTab('perms', this)">Permissions (<?= count($permissions) ?>)</button>
</div>

<!-- SECTION : VUE D'ENSEMBLE -->
<section id="tab-info" class="client-section active">
    <div class="krono-grid-2">
        <div class="fade-in-up anim-delay-1 glass-card" style="padding:1.5rem;">
            <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-info-circle"></i> Informations techniques</h3>
            <ul style="list-style:none; padding:0; margin:1rem 0 0; display:flex; flex-direction:column; gap:.75rem;">
                <li style="display:flex; justify-content:space-between; padding-bottom:.5rem; border-bottom:1px solid var(--krono-border-strong);">
                    <span style="color:var(--krono-text-3);">Enregistré le</span>
                    <strong><?= dateFormat($client['created_at'], true) ?></strong>
                </li>
                <li style="display:flex; justify-content:space-between; padding-bottom:.5rem; border-bottom:1px solid var(--krono-border-strong);">
                    <span style="color:var(--krono-text-3);">Mode d'accès</span>
                    <strong>
                        <?php
                        echo match($client['access_mode']) {
                            'open'   => 'Ouvert (Implicite)',
                            'group'  => 'Par groupe',
                            'manual' => 'Manuel (Individuel)',
                            default  => ucfirst($client['access_mode'])
                        };
                        ?>
                    </strong>
                </li>
                <li style="display:flex; justify-content:space-between; padding-bottom:.5rem; border-bottom:1px solid var(--krono-border-strong);">
                    <span style="color:var(--krono-text-3);">Dernière synchro</span>
                    <strong><?= $client['manifest_synced_at'] ? dateFormat($client['manifest_synced_at'], true) : 'Jamais' ?></strong>
                </li>
            </ul>
        </div>

        <div class="fade-in-up anim-delay-2 glass-card" style="padding:1.5rem;">
            <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-key"></i> Identifiants de connexion</h3>
            <p style="font-size:.85rem; color:var(--krono-text-3); margin-bottom:1rem;">Ces identifiants permettent à l'application de communiquer avec KronoConnect.</p>
            
            <div style="margin-bottom:1rem;">
                <label class="krono-label">Client ID</label>
                <input type="text" class="krono-input" value="<?= e($client['client_id']) ?>" readonly style="font-family:monospace; background:var(--krono-surface-2);">
            </div>
            
            <div style="margin-bottom:1.5rem;">
                <label class="krono-label">Client Secret (Haché)</label>
                <div style="display:flex; gap:.5rem;">
                    <input type="text" class="krono-input" value="<?= e($client['client_secret']) ?>" readonly style="font-family:monospace; background:var(--krono-surface-2); font-size:.7rem;">
                    <form method="POST" action="<?= url('/admin/clients/'.$client['id'].'/regenerate-secret') ?>" style="margin:0;" data-confirm="Régénérer le secret ? L'application ne pourra plus se connecter tant qu'elle n'aura pas été mise à jour.">
                        <?= csrf() ?>
                        <button type="submit" class="btn-krono btn-krono--warning" title="Régénérer"><i class="bi bi-key"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION : MANIFEST -->
<section id="tab-manifest" class="client-section" style="display:none;">
    <div class="fade-in-up anim-delay-3 glass-card" style="padding:1.5rem;">
        <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-file-earmark-code"></i> Données déclarées dans le manifest</h3>
        <p style="font-size:.85rem; color:var(--krono-text-3); margin-bottom:1.5rem;">Voici les informations récupérées automatiquement depuis l'application distante.</p>
        
        <div class="fade-in-up anim-delay-4 krono-table-wrap">
            <table class="krono-table">
                <thead>
                    <tr>
                        <th style="width:200px;">Propriété</th>
                        <th>Valeur</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Nom (app_name)</strong></td>
                        <td><?= e($client['app_name'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Description</strong></td>
                        <td style="font-size:.9rem; color:var(--krono-text-2);"><?= e($client['app_description'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Icône</strong></td>
                        <td><i class="bi bi-<?= e($client['app_icon'] ?: 'app-indicator') ?>"></i> (<code><?= e($client['app_icon'] ?: '—') ?></code>)</td>
                    </tr>
                    <tr>
                        <td><strong>Couleur</strong></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:.5rem;">
                                <div style="width:20px; height:20px; border-radius:4px; background:<?= e($client['app_color'] ?: '#3B82F6') ?>;"></div>
                                <code><?= e($client['app_color'] ?: '—') ?></code>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>URIs de redirection</strong></td>
                        <td>
                            <?php $uris = json_decode($client['redirect_uris'], true) ?: []; ?>
                            <?php foreach ($uris as $uri): ?>
                                <div style="font-family:monospace; font-size:.85rem; margin-bottom:.25rem;"><?= e($uri) ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>URL de logout back-channel</strong></td>
                        <td style="font-family:monospace; font-size:.85rem;"><?= e($client['logout_url'] ?: '—') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- SECTION : PARAMÈTRES D'ACCÈS -->
<section id="tab-access" class="client-section" style="display:none;">
    <div class="krono-grid-2">
        <div>
            <div class="fade-in-up anim-delay-5 glass-card" style="padding:1.5rem;">
                <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-shield-lock"></i> Mode d'accès</h3>
                <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-mode') ?>">
                    <?= csrf() ?>
                    <div style="display:flex; flex-direction:column; gap:1rem; margin-top:1rem;">
                        <label class="krono-radio-card <?= $accessMode === 'open' ? 'active' : '' ?>">
                            <input type="radio" name="access_mode" value="open" <?= $accessMode === 'open' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <div class="krono-radio-card__icon"><i class="bi bi-globe"></i></div>
                            <div class="krono-radio-card__text">
                                <strong>Ouvert (Implicite)</strong>
                                <span>Tous les utilisateurs actifs peuvent se connecter sans restriction.</span>
                            </div>
                        </label>
                        <label class="krono-radio-card <?= $accessMode === 'group' ? 'active' : '' ?>">
                            <input type="radio" name="access_mode" value="group" <?= $accessMode === 'group' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <div class="krono-radio-card__icon"><i class="bi bi-collection"></i></div>
                            <div class="krono-radio-card__text">
                                <strong>Par Groupe</strong>
                                <span>Restreint aux membres des groupes autorisés.</span>
                            </div>
                        </label>
                        <label class="krono-radio-card <?= $accessMode === 'manual' ? 'active' : '' ?>">
                            <input type="radio" name="access_mode" value="manual" <?= $accessMode === 'manual' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <div class="krono-radio-card__icon"><i class="bi bi-person-lock"></i></div>
                            <div class="krono-radio-card__text">
                                <strong>Manuel (Individuel)</strong>
                                <span>Il faut autoriser chaque utilisateur un par un.</span>
                            </div>
                        </label>
                    </div>
                </form>
            </div>
            
            <div class="fade-in-up anim-delay-5 glass-card" style="padding:1.5rem; margin-top:1.5rem;">
                <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-geo-alt"></i> Restriction par IP</h3>
                <p style="font-size:0.85rem; color:var(--krono-text-3); margin-bottom:1rem;">Permet de restreindre l'accès à cette application à un réseau interne (Intranet) ou des IPs spécifiques. Laissez vide pour un accès depuis n'importe où.</p>
                <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/allowed-ips') ?>">
                    <?= csrf() ?>
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <input type="text" name="allowed_ips" class="krono-input" placeholder="ex: 192.168.1.0/24, 10.0.0.1" value="<?= e($client['allowed_ips'] ?? '') ?>">
                        <div style="text-align:right;">
                            <button type="submit" class="btn-krono btn-krono--primary btn-krono--sm">Enregistrer</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div>
            <?php if ($accessMode === 'open'): ?>
                <div class="fade-in-up anim-delay-6 glass-card" style="padding:2rem; text-align:center; color:var(--krono-text-3);">
                    <i class="bi bi-unlock" style="font-size:2.5rem; color:var(--krono-success); margin-bottom:1rem; display:block;"></i>
                    <h3 style="color:var(--krono-text); margin-bottom:0.5rem;">Accès public ouvert</h3>
                    <p style="font-size:0.9rem; max-width:300px; margin:0 auto;">Tout utilisateur avec un compte valide peut accéder à cette application.</p>
                </div>
            <?php elseif ($accessMode === 'manual'): ?>
                <div class="fade-in-up anim-delay-7 glass-card" style="padding:1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <h3 class="krono-section-title" style="margin:0;"><i class="bi bi-people"></i> Utilisateurs autorisés</h3>
                        <button type="button" class="btn-krono btn-krono--primary btn-krono--sm" onclick="document.getElementById('modalAddUser').classList.add('is-open')">
                            <i class="bi bi-plus-lg"></i> Ajouter
                        </button>
                    </div>
                    <?php if (empty($manualUsers)): ?>
                        <p style="text-align:center; color:var(--krono-text-3); font-size:0.9rem; padding:1rem 0;">Aucun utilisateur autorisé.</p>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <?php foreach ($manualUsers as $u): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; background:var(--krono-surface-2); border-radius:var(--krono-radius);">
                                    <div>
                                        <div style="font-weight:600;"><?= e($u['prenom'] . ' ' . $u['nom']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--krono-text-3);"><?= e($u['email']) ?></div>
                                    </div>
                                    <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-revoke') ?>" style="margin:0;">
                                        <?= csrf() ?>
                                        <input type="hidden" name="type" value="user">
                                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-krono btn-krono--danger btn-krono--sm"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($accessMode === 'group'): ?>
                <div class="fade-in-up anim-delay-8 glass-card" style="padding:1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <h3 class="krono-section-title" style="margin:0;"><i class="bi bi-collection"></i> Groupes autorisés</h3>
                        <button type="button" class="btn-krono btn-krono--primary btn-krono--sm" onclick="document.getElementById('modalAddGroup').classList.add('is-open')">
                            <i class="bi bi-plus-lg"></i> Ajouter
                        </button>
                    </div>
                    <?php if (empty($groupAccess)): ?>
                        <p style="text-align:center; color:var(--krono-text-3); font-size:0.9rem; padding:1rem 0;">Aucun groupe autorisé.</p>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <?php foreach ($groupAccess as $g): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; background:var(--krono-surface-2); border-radius:var(--krono-radius);">
                                    <div style="font-weight:600;"><i class="bi bi-folder2-open" style="margin-right:.4rem; color:var(--krono-text-3);"></i><?= e($g['name']) ?></div>
                                    <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-revoke') ?>" style="margin:0;">
                                        <?= csrf() ?>
                                        <input type="hidden" name="type" value="group">
                                        <input type="hidden" name="target_id" value="<?= $g['id'] ?>">
                                        <button type="submit" class="btn-krono btn-krono--danger btn-krono--sm"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- SECTION : PERMISSIONS -->
<section id="tab-perms" class="client-section" style="display:none;">
    <div class="fade-in-up anim-delay-9 glass-card" style="padding:1.5rem;">
        <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-shield-check"></i> Permissions déclarées</h3>
        <p style="font-size:.85rem; color:var(--krono-text-3); margin-bottom:1.5rem;">Voici les permissions exposées par l'application pour la gestion RBAC.</p>
        
        <?php if (empty($permissions)): ?>
            <div style="padding:2rem; text-align:center; color:var(--krono-text-3); border:1px dashed var(--krono-border-strong); border-radius:var(--krono-radius);">
                <i class="bi bi-shield-slash" style="font-size:2rem; display:block; margin-bottom:.5rem;"></i>
                Aucune permission déclarée par cette application.
            </div>
        <?php else: ?>
            <div class="fade-in-up anim-delay-10 krono-table-wrap">
                <table class="krono-table">
                    <thead>
                        <tr>
                            <th>Clé de permission</th>
                            <th>Label</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissions as $p): ?>
                            <tr>
                                <td style="font-family:monospace; font-weight:600; color:var(--krono-accent);">
                                    <?= e($p['perm_key']) ?>
                                    <?php if (!empty($p['parent_key'])): ?>
                                        <br>
                                        <span style="font-size:0.7rem; color:var(--krono-text-3); font-weight:normal;">
                                            <i class="bi bi-link-45deg"></i> Requis : <?= e($p['parent_key']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600;"><?= e($p['label']) ?></td>
                                <td style="font-size:.85rem; color:var(--krono-text-3);"><?= e($p['description']) ?: '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.client-section { animation: fadeInContent 0.3s ease; }
@keyframes fadeInContent { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script>
function switchClientTab(tabId, btn) {
    document.querySelectorAll('.client-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.krono-tab-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById('tab-' + tabId).style.display = 'block';
    btn.classList.add('active');
    
    // Sauvegarder l'onglet actif (optionnel)
    localStorage.setItem('activeClientTab_' + <?= $client['id'] ?>, tabId);
}

document.addEventListener('DOMContentLoaded', () => {
    const savedTab = localStorage.getItem('activeClientTab_' + <?= $client['id'] ?>);
    if (savedTab) {
        const btn = Array.from(document.querySelectorAll('.krono-tab-btn')).find(b => b.getAttribute('onclick').includes(savedTab));
        if (btn) btn.click();
    }
});
</script>

<!-- Modales d'ajout -->
<div class="krono-modal-backdrop" id="modalAddUser">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:400px; padding:1.5rem; text-align:left;">
        <h3 style="margin-top:0; margin-bottom:1rem;">Accorder l'accès à un utilisateur</h3>
        <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-grant') ?>">
            <?= csrf() ?>
            <input type="hidden" name="type" value="user">
            <div style="margin-bottom:1.5rem;">
                <label class="krono-label">Sélectionner l'utilisateur</label>
                <select name="target_id" class="krono-input" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['prenom'] . ' ' . $u['nom'] . ' (' . $u['email'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="this.closest('.krono-modal-backdrop').classList.remove('is-open')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--primary">Accorder</button>
            </div>
        </form>
    </div>
</div>

<div class="krono-modal-backdrop" id="modalAddGroup">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:400px; padding:1.5rem; text-align:left;">
        <h3 style="margin-top:0; margin-bottom:1rem;">Accorder l'accès à un groupe</h3>
        <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-grant') ?>">
            <?= csrf() ?>
            <input type="hidden" name="type" value="group">
            <div style="margin-bottom:1.5rem;">
                <label class="krono-label">Sélectionner le groupe</label>
                <select name="target_id" class="krono-input" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($allGroups as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="this.closest('.krono-modal-backdrop').classList.remove('is-open')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--primary">Accorder</button>
            </div>
        </form>
    </div>
</div>