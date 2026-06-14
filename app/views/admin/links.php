<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Liens externes</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Liens externes</h1>
        <p class="page-header__subtitle">Liens personnalisés affichés sur le portail utilisateur</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/admin/links/create') ?>" class="btn-krono btn-krono--primary">
            <i class="bi bi-plus-lg"></i> Nouveau lien
        </a>
    </div>
</div>

<div class="fade-in-up anim-delay-1 krono-table-wrap">
    <table class="krono-table">
        <thead>
            <tr>
                <th>Lien</th>
                <th>URL de destination</th>
                <th>Mode d'accès</th>
                <th style="width:130px; text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($links)): ?>
            <tr>
                <td colspan="4">
                    <div class="krono-table-empty">
                        <i class="bi bi-link-45deg"></i>
                        Aucun lien personnalisé enregistré.
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($links as $l): ?>
                <?php
                [$modeBadge, $modeIcon, $modeLabel] = match($l['access_mode'] ?? 'open') {
                    'open'   => ['badge-krono--success', 'bi-globe',       'Ouvert'],
                    'group'  => ['badge-krono--info',    'bi-collection',  'Par groupe'],
                    'manual' => ['badge-krono--warning', 'bi-person-lock', 'Manuel'],
                    default  => ['badge-krono--neutral', 'bi-question',    ucfirst($l['access_mode'])]
                };
                $uriHost = parse_url($l['url'], PHP_URL_HOST);
                
                $hexColor = $l['color'] ?? '#3b5fc0';
                $rgb = hexToRgb($hexColor);
                $bgStyle = "background: rgba({$rgb[0]}, {$rgb[1]}, {$rgb[2]}, 0.15); color: {$hexColor};";
                
                $iconName = $l['icon'] ?: 'link-45deg';
                if (str_starts_with($iconName, 'bi-')) $iconName = substr($iconName, 3);
                ?>
                <tr>
                    <td>
                        <div class="client-app-cell">
                            <div class="client-app-icon" style="<?= $bgStyle ?> box-shadow: none;">
                                <i class="bi bi-<?= e($iconName) ?>"></i>
                            </div>
                            <div class="client-app-info">
                                <span class="client-app-name"><?= e($l['title']) ?></span>
                                <span style="font-size: .72rem; color: var(--krono-text-3);"><?= e($l['description'] ?: 'Aucune description') ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="client-uri-host" title="<?= e($l['url']) ?>">
                            <i class="bi bi-link-45deg" style="opacity:.5;"></i>
                            <?= e($uriHost ?: $l['url']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge-krono <?= $modeBadge ?> badge-no-dot">
                            <i class="bi <?= $modeIcon ?>" style="font-size:.65rem; opacity:.8;"></i>
                            <?= $modeLabel ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="<?= url('/admin/links/' . e($l['id'])) ?>"
                               class="btn-krono btn-krono--primary btn-krono--sm"
                               title="Modifier">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="<?= url('/admin/links/delete') ?>"
                                  data-confirm="Supprimer le lien «&nbsp;<?= e($l['title']) ?>&nbsp;» ?"
                                  data-type="danger"
                                  style="margin:0;">
                                <?= csrf() ?>
                                <input type="hidden" name="id" value="<?= e($l['id']) ?>">
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

<style>
.client-app-cell { display: flex; align-items: center; gap: .85rem; }
.client-app-icon {
    width: 36px; height: 36px; border-radius: var(--krono-radius-sm);
    display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;
}
.client-app-info { display: flex; flex-direction: column; min-width: 0; }
.client-app-name { font-weight: 600; color: var(--krono-text); font-size: .9rem; }
.client-uri-host { font-size: .83rem; color: var(--krono-text-2); display: inline-flex; align-items: center; gap: .25rem; }
</style>
