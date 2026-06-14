<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Utilisateurs</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Utilisateurs</h1>
        <p class="page-header__subtitle">Gestion des comptes enregistrés sur KronoConnect</p>
    </div>
    <div class="page-header__actions">
        <button type="button" class="btn-krono btn-krono--secondary" onclick="openApprovalsModal()" style="position:relative; margin-right:.5rem;">
            <i class="bi bi-person-check-fill"></i> Approbations
            <?php if (!empty($pending)): ?>
                <span class="krono-notif-badge" style="display:flex;"><?= count($pending) ?></span>
            <?php endif; ?>
        </button>
        <button type="button" class="btn-krono btn-krono--primary" onclick="openUserModal()">
            <i class="bi bi-plus-lg"></i> Nouvel utilisateur
        </button>
    </div>
</div>

<!-- Filtres -->
<?php $activeFilters = count(array_filter($filters ?? [])); ?>
<form method="GET" action="<?= url('/admin/users') ?>" class="krono-filter-bar">

    <div class="krono-filter-bar__field krono-filter-bar__field--grow">
        <div class="krono-filter-search">
            <i class="bi bi-search krono-filter-search-icon"></i>
            <input type="text" name="q" class="krono-input"
                   aria-label="Recherche"
                   placeholder="Nom, prénom, e-mail…"
                   value="<?= e($filters['search'] ?? '') ?>">
        </div>
    </div>

    <div class="krono-filter-bar__sep"></div>

    <div class="krono-filter-bar__field krono-filter-bar__field--md">
        <select name="status" class="krono-input" aria-label="Statut">
            <option value="">Tous les statuts</option>
            <option value="actif"              <?= ($filters['status'] ?? '') === 'actif'              ? 'selected' : '' ?>>Actif</option>
            <option value="desactive"          <?= ($filters['status'] ?? '') === 'desactive'          ? 'selected' : '' ?>>Désactivé</option>
            <option value="attente_validation" <?= ($filters['status'] ?? '') === 'attente_validation' ? 'selected' : '' ?>>En attente</option>
            <option value="verification_mail"  <?= ($filters['status'] ?? '') === 'verification_mail'  ? 'selected' : '' ?>>Vérif e-mail</option>
        </select>
    </div>

    <div class="krono-filter-bar__field krono-filter-bar__field--md">
        <select name="role" class="krono-input" aria-label="Groupe">
            <option value="">Tous les groupes</option>
            <?php foreach ($allGroups as $g): ?>
                <option value="<?= e($g['tech_name']) ?>" <?= ($filters['role'] ?? '') === $g['tech_name'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="krono-filter-bar__actions">
        <?php if ($activeFilters > 0): ?>
        <span class="krono-filter-count" title="<?= $activeFilters ?> filtre<?= $activeFilters > 1 ? 's' : '' ?> actif<?= $activeFilters > 1 ? 's' : '' ?>">
            <i class="bi bi-funnel-fill"></i> <?= $activeFilters ?>
        </span>
        <a href="<?= url('/admin/users') ?>" class="btn-krono btn-krono--ghost" title="Réinitialiser">
            <i class="bi bi-x-lg"></i>
        </a>
        <?php endif; ?>
        <button type="submit" class="btn-krono btn-krono--primary">
            <i class="bi bi-funnel-fill"></i> Filtrer
        </button>
    </div>

</form>

<div class="fade-in-up anim-delay-1 krono-table-wrap">
    <table class="krono-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Adresse e-mail</th>
                <th>Groupe</th>
                <th>Statut</th>
                <th>Inscrit le</th>
                <th style="width:100px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($result['rows'])): ?>
            <tr>
                <td colspan="6">
                    <div class="krono-table-empty">
                        <i class="bi bi-people"></i>
                        Aucun utilisateur enregistré.
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($result['rows'] as $u): ?>
                <?php $isSelf = ((int)$u['id']) === \KronoConnect\Core\Session::userId(); ?>
                <tr>
                    <td style="font-weight:600;">
                        <?= e($u['prenom'] . ' ' . $u['nom']) ?>
                        <?php if ($isSelf): ?>
                            <span class="badge-krono badge-krono--neutral" style="font-size:.7rem;">Vous</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.85rem;color:var(--krono-text-3);"><?= e($u['email']) ?></td>
                    <td>
                        <?php
                        $roleBadge = match($u['role']) {
                            'super_admin' => 'badge-krono badge-krono--danger',
                            'admin'       => 'badge-krono badge-krono--warning',
                            default       => 'badge-krono badge-krono--neutral',
                        };
                        ?>
                        <span class="<?= $roleBadge ?>"><?= e($u['group_name'] ?? 'Aucun') ?></span>
                    </td>
                    <td>
                        <?php
                        $status = $u['status'] ?? ($u['is_active'] ? 'actif' : 'desactive');
                        $statusColor = match($status) {
                            'actif'              => 'success',
                            'desactive'          => 'danger',
                            'attente_validation' => 'warning',
                            'verification_mail'  => 'info',
                            default              => 'neutral',
                        };
                        $statusLabel = match($status) {
                            'actif'              => 'Actif',
                            'desactive'          => 'Désactivé',
                            'attente_validation' => 'En attente',
                            'verification_mail'  => 'Vérif e-mail',
                            default              => 'Inconnu',
                        };
                        ?>
                        <span class="badge-krono badge-krono--<?= $statusColor ?>"><?= $statusLabel ?></span>
                    </td>
                    <td style="font-size:.83rem;color:var(--krono-text-3);"><?= dateFormat($u['created_at']) ?></td>
                    <td>
                        <div style="display:flex; gap:.5rem;">
                            <a href="<?= url('/admin/users/' . e($u['id'])) ?>" class="btn-krono btn-krono--primary btn-krono--sm" title="Gérer l'utilisateur">
                                <i class="bi bi-gear"></i>
                            </a>
                            <?php if (!$isSelf): ?>
                            <form method="POST" action="<?= url('/admin/users/toggle') ?>" style="margin:0;">
                                <?= csrf() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit"
                                        class="btn-krono <?= $u['is_active'] ? 'btn-krono--danger' : 'btn-krono--success' ?> btn-krono--sm"
                                        title="<?= $u['is_active'] ? 'Désactiver' : 'Activer' ?>">
                                    <i class="bi bi-<?= $u['is_active'] ? 'person-slash' : 'person-check' ?>"></i>
                                </button>
                            </form>
                            <?php if (\KronoConnect\Core\Session::hasPermission('kc.users.delete')): ?>
                            <button type="button"
                                    class="btn-krono btn-krono--danger btn-krono--sm"
                                    title="Supprimer définitivement"
                                    onclick="confirmUserDeletion(<?= (int)$u['id'] ?>, '<?= e($u['prenom'] . ' ' . $u['nom']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($result['totalPages'] > 1): ?>
<div style="padding-top:1.25rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem;">
        <div style="font-size:.78rem; color:var(--krono-text-3);">
            <?= number_format($result['total']) ?> utilisateur<?= $result['total'] > 1 ? 's' : '' ?>
        </div>
        <div class="krono-pagination" style="padding:0;">
            <?php
            $qs = http_build_query(array_filter($filters));
            $base = url('/admin/users') . ($qs ? '?' . $qs . '&page=' : '?page=');
            $p    = $result['page'];
            $last = $result['totalPages'];
            $start = max(1, $p - 1);
            $end   = min($last, $p + 1);
            ?>
            <!-- Précédent -->
            <?php if ($p > 1): ?>
            <a href="<?= $base ?><?= $p - 1 ?>"><i class="bi bi-chevron-left"></i></a>
            <?php else: ?>
            <span class="disabled"><i class="bi bi-chevron-left"></i></span>
            <?php endif; ?>

            <!-- Première page et "..." -->
            <?php if ($start > 1): ?>
                <a href="<?= $base ?>1">1</a>
                <?php if ($start > 2): ?>
                <span class="page-jump-btn" data-base="<?= $base ?>" data-max="<?= $last ?>" title="Aller à une page..." style="cursor:pointer;">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Pages -->
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $p): ?>
                <span class="active"><?= $i ?></span>
                <?php else: ?>
                <a href="<?= $base ?><?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Dernière page et "..." -->
            <?php if ($end < $last): ?>
                <?php if ($end < $last - 1): ?>
                <span class="page-jump-btn" data-base="<?= $base ?>" data-max="<?= $last ?>" title="Aller à une page..." style="cursor:pointer;">...</span>
                <?php endif; ?>
                <a href="<?= $base ?><?= $last ?>"><?= $last ?></a>
            <?php endif; ?>

            <!-- Suivant -->
            <?php if ($p < $last): ?>
            <a href="<?= $base ?><?= $p + 1 ?>"><i class="bi bi-chevron-right"></i></a>
            <?php else: ?>
            <span class="disabled"><i class="bi bi-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modale Utilisateur (Création uniquement ici) -->
<div class="krono-modal-backdrop" id="modalUser">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:450px; padding:1.5rem; text-align:left;">
        <h3 style="margin-top:0; margin-bottom:1rem;" id="modalUserTitle">Nouvel utilisateur</h3>
        <form method="POST" action="<?= url('/admin/users/save') ?>" id="formUser">
            <?= csrf() ?>
            <input type="hidden" name="user_id" id="modalUserId" value="">
            
            <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                <div style="flex:1;">
                    <label class="krono-label">Prénom</label>
                    <input type="text" name="prenom" id="modalUserPrenom" class="krono-input" required>
                </div>
                <div style="flex:1;">
                    <label class="krono-label">Nom</label>
                    <input type="text" name="nom" id="modalUserNom" class="krono-input" required>
                </div>
            </div>
            
            <div style="margin-bottom:1rem;">
                <label class="krono-label">Adresse e-mail</label>
                <input type="email" name="email" id="modalUserEmail" class="krono-input" required>
            </div>

            <div style="margin-bottom:1rem;">
                <label class="krono-label">Groupe</label>
                <select name="group_id" id="modalUserGroup" class="krono-input" required>
                    <?php foreach ($allGroups as $g): ?>
                        <option value="<?= (int)$g['id'] ?>" data-tech="<?= e($g['tech_name']) ?>"><?= e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:1.5rem;">
                <label class="krono-label">Mot de passe <span id="modalUserPwdHint" style="font-size:0.75rem; color:var(--krono-text-3); font-weight:normal;">(Obligatoire)</span></label>
                <input type="password" name="password" id="modalUserPwd" class="krono-input" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="document.getElementById('modalUser').classList.remove('is-open')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--primary" id="modalUserSubmit">Créer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUserModal() {
    const modal = document.getElementById('modalUser');
    const idInput = document.getElementById('modalUserId');
    const prenomInput = document.getElementById('modalUserPrenom');
    const nomInput = document.getElementById('modalUserNom');
    const emailInput = document.getElementById('modalUserEmail');
    const groupInput = document.getElementById('modalUserGroup');
    const pwdInput = document.getElementById('modalUserPwd');

    idInput.value = '';
    prenomInput.value = '';
    nomInput.value = '';
    emailInput.value = '';
    
    // Select the default 'user' group
    if (groupInput) {
        const defaultOption = Array.from(groupInput.options).find(opt => opt.getAttribute('data-tech') === 'user');
        if (defaultOption) {
            groupInput.value = defaultOption.value;
        }
    }
    
    pwdInput.value = '';

    modal.classList.add('is-open');
}

function openApprovalsModal() {
    const modal = document.getElementById('approvalsModal');
    if (!modal) return;
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('is-open'), 10);
}
function closeApprovalsModal() {
    const modal = document.getElementById('approvalsModal');
    if (!modal) return;
    modal.classList.remove('is-open');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}
function confirmUserDeletion(userId, userName) {
    const modal = document.getElementById('modalDeleteUser');
    const form = document.getElementById('formDeleteUser');
    const nameSpan = document.getElementById('deleteUserName');
    if (!modal || !form || !nameSpan) return;
    form.action = "<?= url('/admin/users/') ?>" + userId + "/delete";
    nameSpan.textContent = userName;
    modal.classList.add('is-open');
}

document.getElementById('approvalsModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeApprovalsModal();
});
</script>

<!-- Modale Approbations -->
<div class="krono-modal-backdrop" id="approvalsModal" style="display:none;">
    <div class="glass-card krono-modal-content" style="max-width:600px; width:90%; text-align:left;">
        <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--krono-border); padding-bottom:1rem; margin-bottom:1rem;">
            <div style="display:flex; align-items:center; gap:.75rem;">
                <div style="width:42px; height:42px; border-radius:12px; background:var(--krono-info-bg, var(--krono-surface-2)); color:var(--krono-info, var(--krono-accent)); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--krono-text);">Approbations en attente</h3>
                    <div style="font-size:.8rem; color:var(--krono-text-3); margin-top:.1rem;">
                        <?= count($pending ?? []) ?> compte<?= count($pending ?? []) > 1 ? 's' : '' ?> en attente de validation
                    </div>
                </div>
            </div>
            <button type="button" class="btn-krono btn-krono--ghost btn-krono--sm" onclick="closeApprovalsModal()" title="Fermer" style="padding:.4rem .6rem; border:none;">
                <i class="bi bi-x-lg" style="font-size:1.2rem;"></i>
            </button>
        </div>

        <div style="max-height:400px; overflow-y:auto; padding-right:.5rem;">
            <?php if (empty($pending)): ?>
                <div style="text-align:center; padding:2rem; color:var(--krono-text-3);">
                    <i class="bi bi-check-circle" style="font-size:2rem; margin-bottom:.5rem; display:block;"></i>
                    Aucune approbation en attente
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <?php foreach ($pending as $u): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border:1px solid var(--krono-border); border-radius:var(--krono-radius); background:var(--krono-surface-3, var(--krono-surface-2));">
                            <div style="display:flex; align-items:center; gap:.75rem;">
                                <div style="width:36px; height:36px; border-radius:50%; background:var(--krono-warning-bg, var(--krono-surface-2)); color:var(--krono-warning, var(--krono-accent)); display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700;">
                                    <?= strtoupper(substr($u['prenom'],0,1) . substr($u['nom'],0,1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600; color:var(--krono-text);"><?= e($u['prenom'] . ' ' . $u['nom']) ?></div>
                                    <div style="font-size:.78rem; color:var(--krono-text-3);"><?= e($u['email']) ?></div>
                                    <div style="font-size:.70rem; color:var(--krono-text-3); margin-top:.1rem;">
                                        Inscrit le <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex; gap:.5rem;">
                                <form method="POST" action="<?= url("/admin/users/{$u['id']}/approve") ?>" style="margin:0;">
                                    <?= csrf() ?>
                                    <button type="submit" class="btn-krono btn-krono--success btn-krono--sm" title="Approuver le compte" data-confirm="Approuver et activer ce compte ?">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <form method="POST" action="<?= url("/admin/users/{$u['id']}/reject") ?>" style="margin:0;">
                                    <?= csrf() ?>
                                    <button type="submit" class="btn-krono btn-krono--danger btn-krono--sm" title="Rejeter et supprimer" data-confirm="Rejeter définitivement cette inscription ? Le compte sera supprimé.">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
            <button type="button" class="btn-krono btn-krono--secondary" onclick="closeApprovalsModal()">Fermer</button>
        </div>
    </div>
</div>

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