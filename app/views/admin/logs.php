<?php
/**
 * Admin — Journal des actions (krono_logs)
 * Variables : $result, $filters
 */
$levelColors = [
    'info'    => 'info',
    'warning' => 'warning',
    'error'   => 'danger',
    'debug'   => 'neutral',
];
$levelIcons = [
    'info'    => 'bi-info-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'error'   => 'bi-x-circle-fill',
    'debug'   => 'bi-bug-fill',
];
?>

<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Journal des logs</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Journal des actions</h1>
        <p class="page-header__subtitle">Historique des événements système et actions administrateurs</p>
    </div>
</div>

<!-- Filtres -->
<?php $activeFilters = count(array_filter($filters)); ?>
<form method="GET" action="<?= url('/admin/logs') ?>" class="krono-filter-bar">

    <div class="krono-filter-bar__field krono-filter-bar__field--grow">
        <div class="krono-filter-search">
            <i class="bi bi-search krono-filter-search-icon"></i>
            <input type="text" name="q" class="krono-input"
                   aria-label="Recherche"
                   value="<?= e($filters['search']) ?>" placeholder="Rechercher dans les messages…">
        </div>
    </div>

    <div class="krono-filter-bar__sep"></div>

    <div class="krono-filter-bar__field krono-filter-bar__field--sm">
        <select name="level" class="krono-input" aria-label="Niveau">
            <option value="">Tous les niveaux</option>
            <?php foreach (['info','warning','error','debug'] as $lvl): ?>
            <option value="<?= $lvl ?>" <?= $filters['level'] === $lvl ? 'selected' : '' ?>>
                <?= ucfirst($lvl) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="krono-filter-bar__field krono-filter-bar__field--sm">
        <input type="date" name="date_from" class="krono-input"
               aria-label="Du" title="Date de début"
               value="<?= e($filters['date_from']) ?>">
    </div>

    <span class="krono-filter-bar__date-sep">→</span>

    <div class="krono-filter-bar__field krono-filter-bar__field--sm">
        <input type="date" name="date_to" class="krono-input"
               aria-label="Au" title="Date de fin"
               value="<?= e($filters['date_to']) ?>">
    </div>

    <div class="krono-filter-bar__actions">
        <?php if ($activeFilters > 0): ?>
        <span class="krono-filter-count" title="<?= $activeFilters ?> filtre<?= $activeFilters > 1 ? 's' : '' ?> actif<?= $activeFilters > 1 ? 's' : '' ?>">
            <i class="bi bi-funnel-fill"></i> <?= $activeFilters ?>
        </span>
        <a href="<?= url('/admin/logs') ?>" class="btn-krono btn-krono--ghost" title="Réinitialiser">
            <i class="bi bi-x-lg"></i>
        </a>
        <?php endif; ?>
        <button type="submit" class="btn-krono btn-krono--primary">
            <i class="bi bi-funnel-fill"></i> Filtrer
        </button>
    </div>

</form>

<!-- Journal -->
<div class="fade-in-up anim-delay-1 krono-table-wrap">
    <table class="krono-table krono-table--compact">
        <thead>
            <tr>
                <th style="width:100px;">Niveau</th>
                <th>Message</th>
                <?php
                $currentDir = $filters['dir'] ?? 'desc';
                $newDir = $currentDir === 'desc' ? 'asc' : 'desc';
                $sortIcon = $currentDir === 'desc' ? 'bi-sort-down' : 'bi-sort-up';
                
                $qsParams = array_filter($filters, function($v) { return $v !== ''; });
                $qsParams['dir'] = $newDir;
                $sortUrl = url('/admin/logs') . '?' . http_build_query($qsParams);
                ?>
                <th style="width:160px;">
                    <a href="<?= $sortUrl ?>" style="color:inherit; text-decoration:none; display:flex; align-items:center; gap:0.3rem;" title="Trier par date">
                        Date <i class="bi <?= $sortIcon ?>"></i>
                    </a>
                </th>
                <th style="width:80px; text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($result['rows'])): ?>
            <tr><td colspan="4">
                <div class="krono-table-empty">
                    <i class="bi bi-journal-x"></i>
                    Aucune entrée dans le journal
                </div>
            </td></tr>
        <?php else: ?>
            <?php foreach ($result['rows'] as $log):
                $lvl     = $log['level'] ?? 'info';
                $color   = $levelColors[$lvl]  ?? 'neutral';
                $icon    = $levelIcons[$lvl]   ?? 'bi-dot';
                $context = !empty($log['context']) ? json_decode($log['context'], true) : [];
            ?>
            <tr style="cursor:pointer;" onclick="toggleLogContext(this)">
                <td style="padding: 0.5rem 1rem;">
                    <span class="badge-krono badge-krono--<?= $color ?> badge-no-dot" style="font-size:0.65rem; padding:0.2rem 0.4rem;">
                        <i class="bi <?= $icon ?>" style="font-size:.65rem; margin-right:3px;"></i><?= ucfirst($lvl) ?>
                    </span>
                </td>
                <td style="padding: 0.5rem 1rem; font-size:.85rem; color:var(--krono-text); max-width:400px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= e($log['message']) ?>">
                    <?= e($log['message']) ?>
                </td>
                <td style="padding: 0.5rem 1rem; font-size:.78rem; color:var(--krono-text-3); white-space:nowrap;">
                    <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                </td>
                <td style="padding: 0.5rem 1rem; text-align:right; white-space:nowrap;">
                    <button type="button" class="btn-krono btn-krono--ghost" style="padding:0.2rem 0.4rem; min-height:0; margin-right:.4rem;" onclick="copyLog(event, this)" data-log="<?= htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8') ?>" title="Copier le détail">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <i class="bi bi-chevron-down" style="font-size:.75rem; color:var(--krono-text-3); transition: transform 0.2s;"></i>
                </td>
            </tr>
            <tr style="display:none; background:var(--krono-surface-3);">
                <td colspan="4" style="padding: 1.25rem 1.5rem; border-top: 1px solid var(--krono-border-light); box-shadow: inset 0 3px 6px rgba(0,0,0,0.02);">
                    <div style="font-size:.85rem; color:var(--krono-text); margin-bottom: 1rem;">
                        <strong>Message complet :</strong><br>
                        <?= nl2br(e($log['message'])) ?>
                    </div>
                    <?php if (!empty($context) && is_array($context)): ?>
                    <div style="font-size:.85rem; color:var(--krono-text); margin-bottom: .5rem;">
                        <strong>Contexte :</strong>
                    </div>
                    <div style="background:var(--krono-surface-1); border:1px solid var(--krono-border-light); border-radius:6px; overflow:hidden;">
                        <table style="width:100%; border-collapse:collapse; font-size:.8rem;">
                            <tbody>
                                <?php $i = 0; $cCount = count($context); foreach ($context as $k => $v): $i++; ?>
                                <tr>
                                    <td style="padding:.5rem .75rem; <?= $i < $cCount ? 'border-bottom:1px solid var(--krono-border-light);' : '' ?> font-weight:600; color:var(--krono-text-2); width:30%; vertical-align:top; border-right:1px solid var(--krono-border-light);">
                                        <?= e((string)$k) ?>
                                    </td>
                                    <td style="padding:.5rem .75rem; <?= $i < $cCount ? 'border-bottom:1px solid var(--krono-border-light);' : '' ?> color:var(--krono-text); word-break:break-word;">
                                        <?= e(is_array($v) || is_object($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$v) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function toggleLogContext(row) {
    const nextRow = row.nextElementSibling;
    const chevron = row.querySelector('.bi-chevron-down, .bi-chevron-up');
    
    if (nextRow && nextRow.tagName === 'TR') {
        if (nextRow.style.display === 'none') {
            nextRow.style.display = 'table-row';
            if (chevron) {
                chevron.classList.remove('bi-chevron-down');
                chevron.classList.add('bi-chevron-up');
            }
            row.style.backgroundColor = 'var(--krono-surface-2)';
        } else {
            nextRow.style.display = 'none';
            if (chevron) {
                chevron.classList.remove('bi-chevron-up');
                chevron.classList.add('bi-chevron-down');
            }
            row.style.backgroundColor = '';
        }
    }
}

function fallbackCopyTextToClipboard(text, onSuccess) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    
    // Eviter de scroller la page
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";

    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        var successful = document.execCommand('copy');
        if (successful && typeof onSuccess === 'function') {
            onSuccess();
        }
    } catch (err) {
        console.error('Fallback: Oops, unable to copy', err);
    }

    document.body.removeChild(textArea);
}

function copyLog(e, btn) {
    e.stopPropagation(); // empêcher l'ouverture/fermeture de la ligne
    try {
        const logData = JSON.parse(btn.getAttribute('data-log'));
        let contextStr = '{}';
        if (logData.context) {
            try {
                contextStr = typeof logData.context === 'string' ? JSON.stringify(JSON.parse(logData.context), null, 2) : JSON.stringify(logData.context, null, 2);
            } catch(err) {
                contextStr = logData.context;
            }
        }
        
        let textToCopy = `[${logData.created_at || ''}] [${(logData.level || 'info').toUpperCase()}] ${logData.message || ''}`;
        if (contextStr !== '{}' && contextStr !== '[]' && contextStr !== '') {
            textToCopy += `\nContexte:\n${contextStr}`;
        }
        
        const onSuccess = () => {
            const icon = btn.querySelector('i');
            icon.className = 'bi bi-check2';
            icon.style.color = 'var(--krono-success)';
            setTimeout(() => { 
                icon.className = 'bi bi-clipboard'; 
                icon.style.color = '';
            }, 2000);
        };

        // L'API Clipboard moderne nécessite un contexte sécurisé (HTTPS / localhost)
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy).then(onSuccess).catch(err => {
                console.warn('Clipboard API a échoué, utilisation du fallback.', err);
                fallbackCopyTextToClipboard(textToCopy, onSuccess);
            });
        } else {
            // Utilisation de la méthode classique pour les environnements HTTP (ex: environnement local non sécurisé)
            fallbackCopyTextToClipboard(textToCopy, onSuccess);
        }
    } catch(err) {
        console.error('Erreur parsing data-log:', err);
    }
}
</script>
<!-- Footer tableau : compteur + pagination -->
<?php if (!empty($result['rows'])): ?>
<div class="krono-table-footer">
    <span class="krono-table-count">
        <?= number_format($result['total']) ?> entrée<?= $result['total'] > 1 ? 's' : '' ?>
    </span>

    <?php if ($result['totalPages'] > 1): ?>
    <div class="krono-pagination">
        <?php
        $qs    = http_build_query(array_filter($filters));
        $base  = url('/admin/logs') . ($qs ? '?' . $qs . '&page=' : '?page=');
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