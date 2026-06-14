<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Clients SSO</span>
</nav>


<div class="page-header">
    <div>
        <h1 class="page-header__title">Clients SSO</h1>
        <p class="page-header__subtitle">Applications autorisées à utiliser KronoConnect</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/admin/clients/create') ?>" class="btn-krono btn-krono--primary">
            <i class="bi bi-plus-lg"></i> Nouveau client
        </a>
    </div>
</div>

<!-- Barre de recherche -->
<?php $activeFilters = count(array_filter($filters ?? [])); ?>
<form method="GET" action="<?= url('/admin/clients') ?>" class="krono-filter-bar">

    <div class="krono-filter-bar__field krono-filter-bar__field--grow">
        <div class="krono-filter-search">
            <i class="bi bi-search krono-filter-search-icon"></i>
            <input type="text" name="q" class="krono-input"
                   aria-label="Recherche"
                   value="<?= e($filters['search'] ?? '') ?>"
                   placeholder="Nom, Client ID…">
        </div>
    </div>

    <div class="krono-filter-bar__sep"></div>

    <div class="krono-filter-bar__field krono-filter-bar__field--md">
        <select name="mode" class="krono-input" aria-label="Mode d'accès">
            <option value="">Tous les modes</option>
            <option value="open"   <?= ($filters['mode'] ?? '') === 'open'   ? 'selected' : '' ?>>Ouvert</option>
            <option value="group"  <?= ($filters['mode'] ?? '') === 'group'  ? 'selected' : '' ?>>Par groupe</option>
            <option value="manual" <?= ($filters['mode'] ?? '') === 'manual' ? 'selected' : '' ?>>Manuel</option>
        </select>
    </div>

    <div class="krono-filter-bar__actions">
        <?php if ($activeFilters > 0): ?>
        <span class="krono-filter-count" title="<?= $activeFilters ?> filtre<?= $activeFilters > 1 ? 's' : '' ?> actif<?= $activeFilters > 1 ? 's' : '' ?>">
            <i class="bi bi-funnel-fill"></i> <?= $activeFilters ?>
        </span>
        <a href="<?= url('/admin/clients') ?>" class="btn-krono btn-krono--ghost" title="Réinitialiser">
            <i class="bi bi-x-lg"></i>
        </a>
        <?php endif; ?>
        <button type="submit" class="btn-krono btn-krono--primary">
            <i class="bi bi-funnel-fill"></i> Filtrer
        </button>
    </div>

</form>

<!-- Tableau -->
<div class="fade-in-up anim-delay-2 krono-table-wrap">
    <table class="krono-table">
        <thead>
            <tr>
                <th>Application</th>
                <th>URI de redirection</th>
                <th>Mode d'accès</th>
                <th style="width:130px; text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($result['rows'])): ?>
            <tr>
                <td colspan="4">
                    <div class="krono-table-empty">
                        <i class="bi bi-app-indicator"></i>
                        Aucun client SSO enregistré.
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($result['rows'] as $c): ?>
                <?php
                $uris     = json_decode($c['redirect_uris'] ?? '[]', true) ?: [];
                $appColor = !empty($c['app_color']) ? $c['app_color'] : null;
                $appIcon  = !empty($c['app_icon'])  ? $c['app_icon']  : 'app-indicator';

                [$modeBadge, $modeIcon, $modeLabel] = match($c['access_mode'] ?? 'open') {
                    'open'   => ['badge-krono--success', 'bi-globe',       'Ouvert'],
                    'group'  => ['badge-krono--info',    'bi-collection',  'Par groupe'],
                    'manual' => ['badge-krono--warning', 'bi-person-lock', 'Manuel'],
                    default  => ['badge-krono--neutral', 'bi-question',    ucfirst($c['access_mode'])]
                };

                $firstUri  = $uris[0] ?? null;
                $uriHost   = $firstUri ? parse_url($firstUri, PHP_URL_HOST) : null;
                $uriExtra  = count($uris) - 1;
                ?>
                <tr>
                    <!-- Application -->
                    <td>
                        <div class="client-app-cell">
                            <div class="client-app-icon" style="<?= $appColor ? "background:{$appColor};" : '' ?>">
                                <i class="bi bi-<?= e($appIcon) ?>"></i>
                            </div>
                            <div class="client-app-info">
                                <span class="client-app-name"><?= e($c['app_name'] ?: $c['name']) ?></span>
                                <code class="client-app-id"><?= e($c['client_id']) ?></code>
                            </div>
                        </div>
                    </td>

                    <!-- URI -->
                    <td>
                        <?php if ($firstUri): ?>
                            <div class="client-uri-cell">
                                <?php if ($uriHost): ?>
                                    <span class="client-uri-host" title="<?= e($firstUri) ?>">
                                        <i class="bi bi-link-45deg" style="opacity:.5;"></i>
                                        <?= e($uriHost) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="client-uri-host" title="<?= e($firstUri) ?>"><?= e($firstUri) ?></span>
                                <?php endif; ?>
                                <?php if ($uriExtra > 0): ?>
                                    <span class="badge-krono badge-krono--neutral badge-no-dot" title="<?= e(implode("\n", array_slice($uris, 1))) ?>">
                                        +<?= $uriExtra ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span style="color:var(--krono-text-3); font-size:.83rem;">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Mode -->
                    <td>
                        <span class="badge-krono <?= $modeBadge ?> badge-no-dot">
                            <i class="bi <?= $modeIcon ?>" style="font-size:.65rem; opacity:.8;"></i>
                            <?= $modeLabel ?>
                        </span>
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="table-actions">
                            <a href="<?= url('/admin/clients/' . e($c['id'])) ?>"
                               class="btn-krono btn-krono--primary btn-krono--sm"
                               title="Voir les détails">
                                <i class="bi bi-eye"></i> Détails
                            </a>
                            <form method="POST" action="<?= url('/admin/clients/delete') ?>"
                                  data-confirm="ATTENTION : Supprimer le client «&nbsp;<?= e($c['app_name'] ?: $c['name']) ?>&nbsp;» coupera immédiatement ses connexions SSO. Si l'application cliente est active, elle risque d'être bloquée. Voulez-vous continuer ?"
                                  data-type="danger"
                                  style="margin:0;">
                                <?= csrf() ?>
                                <input type="hidden" name="client_id" value="<?= e($c['client_id']) ?>">
                                <button type="submit"
                                        class="btn-krono btn-krono--danger btn-krono--sm"
                                        title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Footer tableau : compteur + pagination -->
<?php if (!empty($result['rows'])): ?>
<div class="krono-table-footer">
    <span class="krono-table-count">
        <?= number_format($result['total']) ?> client<?= $result['total'] > 1 ? 's' : '' ?>
    </span>

    <?php if ($result['totalPages'] > 1): ?>
    <div class="krono-pagination">
        <?php
        $qs    = http_build_query(array_filter($filters ?? []));
        $base  = url('/admin/clients') . ($qs ? '?' . $qs . '&page=' : '?page=');
        $p     = $result['page'];
        $last  = $result['totalPages'];
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
                <span class="page-jump-btn" data-base="<?= $base ?>" data-max="<?= $last ?>" title="Aller à une page…" style="cursor:pointer;">…</span>
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
                <span class="page-jump-btn" data-base="<?= $base ?>" data-max="<?= $last ?>" title="Aller à une page…" style="cursor:pointer;">…</span>
            <?php endif; ?>
            <a href="<?= $base ?><?= $last ?>"><?= $last ?></a>
        <?php endif; ?>

        <?php if ($p < $last): ?>
            <a href="<?= $base ?><?= $p + 1 ?>"><i class="bi bi-chevron-right"></i></a>
        <?php else: ?>
            <span class="disabled"><i class="bi bi-chevron-right"></i></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
/* ── Cellule Application ─────────────────────────────────────── */
.client-app-cell {
    display: flex;
    align-items: center;
    gap: .85rem;
}
.client-app-icon {
    width: 36px; height: 36px;
    border-radius: var(--krono-radius-sm);
    background: var(--krono-surface-3);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.client-app-icon:not([style*="background:"]) {
    color: var(--krono-text-3);
}
.client-app-info {
    display: flex;
    flex-direction: column;
    gap: .1rem;
    min-width: 0;
}
.client-app-name {
    font-weight: 600;
    color: var(--krono-text);
    font-size: .9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.client-app-id {
    font-family: monospace;
    font-size: .72rem;
    color: var(--krono-text-3);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
}

/* ── Cellule URI ─────────────────────────────────────────────── */
.client-uri-cell {
    display: flex;
    align-items: center;
    gap: .5rem;
}
.client-uri-host {
    font-size: .83rem;
    color: var(--krono-text-2);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
    display: inline-flex;
    align-items: center;
    gap: .25rem;
}

</style>
