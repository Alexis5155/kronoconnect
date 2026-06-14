<?php
/**
 * Admin — Liste des groupes (Connect)
 * Variables : $result (rows, total, page, totalPages), $filters
 */
?>

<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Groupes & RBAC</span>
</nav>

<div class="page-header">
    <div>
        <div class="page-header__title">Groupes & RBAC</div>
        <div class="page-header__subtitle">Gérez les groupes d'utilisateurs et leurs permissions globales.</div>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/admin/groups/new') ?>" class="btn-krono btn-krono--primary">
            <i class="bi bi-plus-lg"></i> Nouveau groupe
        </a>
    </div>
</div>

<div class="fade-in-up anim-delay-1 krono-table-wrap">
    <table class="krono-table">
        <thead>
            <tr>
                <th>Nom du groupe</th>
                <th>Description</th>
                <th style="text-align:center;">Membres</th>
                <th style="text-align:center;">Apps</th>
                <th>Créé le</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($result['rows'])): ?>
            <tr>
                <td colspan="6">
                    <div class="krono-table-empty">
                        <i class="bi bi-collection"></i>
                        Aucun groupe enregistré.
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($result['rows'] as $g): ?>
                <tr>
                    <td>
                        <div style="font-weight:600; color:var(--krono-text); display:flex; align-items:center; gap:.5rem;">
                            <i class="bi bi-collection" style="color:var(--krono-text-3);"></i>
                            <?= e($g['name']) ?>
                            <?php if ($g['is_system'] ?? false): ?>
                                <span class="badge-krono badge-krono--warning badge-no-dot" style="font-size:.6rem; padding:.15rem .4rem;">Système</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="color:var(--krono-text-3); font-size:.85rem;">
                        <?= e($g['description']) ?: '—' ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-krono badge-krono--neutral badge-no-dot"><?= (int)$g['members_count'] ?></span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-krono badge-krono--info badge-no-dot"><?= (int)$g['apps_count'] ?></span>
                    </td>
                    <td style="color:var(--krono-text-3); font-size:.83rem;">
                        <?= dateFormat($g['created_at']) ?>
                    </td>
                    <td style="text-align:right;">
                        <div class="table-actions">
                            <a href="<?= url('/admin/groups/' . e($g['id'])) ?>" class="btn-krono btn-krono--secondary btn-krono--sm" title="Modifier">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <?php if (!($g['is_system'] ?? false)): ?>
                                <form method="POST" action="<?= url('/admin/groups/delete') ?>" style="display:inline-block; margin:0;" data-confirm="Supprimer ce groupe ? Tous ses accès seront révoqués." data-type="danger">
                                    <?= csrf() ?>
                                    <input type="hidden" name="group_id" value="<?= e($g['id']) ?>">
                                    <button type="submit" class="btn-krono btn-krono--danger btn-krono--sm" title="Supprimer">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn-krono btn-krono--danger btn-krono--sm" title="Impossible de supprimer un groupe système" disabled style="opacity:.5; cursor:not-allowed;">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
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
            <?= number_format($result['total']) ?> groupe<?= $result['total'] > 1 ? 's' : '' ?>
        </div>
        <div class="krono-pagination" style="padding:0;">
            <?php
            $qs = http_build_query(array_filter($filters));
            $base = url('/admin/groups') . ($qs ? '?' . $qs . '&page=' : '?page=');
            $p    = $result['page'];
            $last = $result['totalPages'];
            $start = max(1, $p - 1);
            $end   = min($last, $p + 1);
            ?>
            <?php if ($p > 1): ?>
            <a href="<?= $base ?><?= $p - 1 ?>"><i class="bi bi-chevron-left"></i></a>
            <?php else: ?>
            <span class="disabled"><i class="bi bi-chevron-left"></i></span>
            <?php endif; ?>

            <?php if ($start > 1): ?>
                <a href="<?= $base ?>1">1</a>
                <?php if ($start > 2): ?>
                <span class="page-jump-btn" data-base="<?= $base ?>" data-max="<?= $last ?>" title="Aller à une page..." style="cursor:pointer;">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $p): ?>
                <span class="active"><?= $i ?></span>
                <?php else: ?>
                <a href="<?= $base ?><?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $last): ?>
                <?php if ($end < $last - 1): ?>
                <span class="page-jump-btn" data-base="<?= $base ?>" data-max="<?= $last ?>" title="Aller à une page..." style="cursor:pointer;">...</span>
                <?php endif; ?>
                <a href="<?= $base ?><?= $last ?>"><?= $last ?></a>
            <?php endif; ?>

            <?php if ($p < $last): ?>
            <a href="<?= $base ?><?= $p + 1 ?>"><i class="bi bi-chevron-right"></i></a>
            <?php else: ?>
            <span class="disabled"><i class="bi bi-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
