<?php
/**
 * Centre de notifications — KronoConnect (hub).
 *
 * Agrège toutes les notifications reçues par l'utilisateur depuis l'ensemble
 * des instances clientes SSO, plus celles émises par KronoConnect lui-même
 * (`client_id IS NULL` ⇒ badge "KronoConnect").
 *
 * Schéma de chaque ligne (cf. NotificationCenterController::shapeForUi) :
 *   { id, client_id, type, title, message, url, read_at, created_at,
 *     app_name, app_color, app_icon, is_hub }
 *
 * Variables : $result { rows, total, page, perPage, totalPages }
 */
$result = $result ?? ['rows' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1];
$rows   = $result['rows'] ?? [];

$typeIcons = [
    'info'    => 'bi-info-circle-fill',
    'success' => 'bi-check-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'error'   => 'bi-exclamation-octagon-fill',
];
$typeLabel = [
    'info'    => 'Info',
    'success' => 'Succès',
    'warning' => 'Avertissement',
    'error'   => 'Alerte',
];

$unreadTotal = 0;
foreach ($rows as $r) { if (empty($r['read_at'])) { $unreadTotal++; } }
?>

<div class="krono-notif-center">
    <header class="krono-notif-center__header">
        <div>
            <h1 class="krono-notif-center__title">
                <i class="bi bi-bell-fill"></i> Centre de notifications
            </h1>
            <p class="krono-notif-center__sub">
                Toutes vos alertes, agrégées depuis l'ensemble de vos applications.
            </p>
        </div>
        <div class="krono-notif-center__actions">
            <button type="button" class="btn-krono btn-krono--primary"
                    id="btnMarkAllReadPage"
                    <?= $unreadTotal === 0 ? 'disabled' : '' ?>>
                <i class="bi bi-check2-all"></i>
                <span>Tout marquer comme lu</span>
            </button>
        </div>
    </header>

    <?php if (empty($rows)): ?>
        <div class="krono-notif-center__empty">
            <div style="font-size:3.2rem; color:var(--krono-text-3); opacity:0.4; margin-bottom:1rem;">
                <i class="bi bi-bell-slash"></i>
            </div>
            <h3 style="font-size:1.3rem; font-weight:800; color:var(--krono-text-2); margin:0 0 0.4rem 0;">
                Aucune notification pour l'instant
            </h3>
            <p style="margin:0; color:var(--krono-text-3);">
                Les messages envoyés par vos applications connectées apparaîtront ici.
            </p>
        </div>
    <?php else: ?>
        <div class="krono-notif-list">
            <?php foreach ($rows as $n):
                $isRead = !empty($n['read_at']);
                $type   = $n['type'] ?? 'info';
                if (!isset($typeIcons[$type])) { $type = 'info'; }
                $icon   = $typeIcons[$type];

                // client_id NULL ⇒ notif émise par KronoConnect lui-même.
                $isHub    = empty($n['client_id']);
                $appName  = $isHub ? 'KronoConnect' : (trim((string) ($n['app_name']  ?? '')) ?: 'Application');
                $appColor = $isHub ? '#3b5fc0'      : (trim((string) ($n['app_color'] ?? '')) ?: '#3b5fc0');
                $appIcon  = $isHub ? 'shield-lock-fill' : (trim((string) ($n['app_icon'] ?? '')) ?: 'app-indicator');
                if (!$isHub && str_starts_with($appIcon, 'bi-')) { $appIcon = substr($appIcon, 3); }

                $hex = ltrim($appColor, '#');
                if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
                $rgb = strlen($hex) === 6
                    ? hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2))
                    : '59,95,192';

                $title   = (string) ($n['title']   ?? '');
                $message = (string) ($n['message'] ?? '');
                $url     = $n['url'] ?? null;
            ?>
                <article class="krono-notif-card krono-notif-card--<?= e($type) ?> <?= $isRead ? 'is-read' : 'is-unread' ?>"
                         data-notif-id="<?= (int) $n['id'] ?>"
                         style="--app-color: <?= e($appColor) ?>; --app-color-rgb: <?= $rgb ?>;">

                    <div class="krono-notif-card__app" title="<?= e($appName) ?>">
                        <i class="bi bi-<?= e($appIcon) ?>"></i>
                    </div>

                    <div class="krono-notif-card__body">
                        <div class="krono-notif-card__meta">
                            <span class="krono-notif-card__app-label"><?= e($appName) ?></span>
                            <span class="krono-notif-card__sep">·</span>
                            <span class="krono-notif-pill--<?= e($type) ?>">
                                <i class="bi <?= $icon ?>"></i> <?= e($typeLabel[$type] ?? 'Info') ?>
                            </span>
                            <span class="krono-notif-card__sep">·</span>
                            <span class="krono-notif-card__date">
                                <i class="bi bi-clock"></i>
                                <?= date('d/m/Y à H:i', strtotime((string) $n['created_at'])) ?>
                            </span>
                        </div>

                        <?php if ($title !== ''): ?>
                            <div class="krono-notif-card__title">
                                <?php if ($url): ?>
                                    <a href="<?= e($url) ?>"
                                       class="krono-notif-card__link"
                                       data-notif-id="<?= (int) $n['id'] ?>">
                                        <?= e($title) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e($title) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($message !== ''): ?>
                            <div class="krono-notif-card__message"><?= nl2br(e($message)) ?></div>
                        <?php endif; ?>

                        <?php if ($title === '' && $url): ?>
                            <a href="<?= e($url) ?>" class="krono-notif-card__cta" data-notif-id="<?= (int) $n['id'] ?>">
                                Ouvrir <i class="bi bi-arrow-right-short"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isRead): ?>
                        <button type="button"
                                class="krono-notif-card__read-btn"
                                title="Marquer comme lue"
                                aria-label="Marquer comme lue">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (($result['totalPages'] ?? 1) > 1):
            $p     = (int) $result['page'];
            $last  = (int) $result['totalPages'];
            $base  = url('/notifications') . '?page=';
            $start = max(1, $p - 2);
            $end   = min($last, $p + 2);
        ?>
        <nav class="krono-notif-pagination" aria-label="Pagination">
            <div class="krono-notif-pagination__count">
                <?= number_format($result['total']) ?> notification<?= $result['total'] > 1 ? 's' : '' ?>
            </div>
            <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                <?php if ($p > 1): ?>
                    <a href="<?= $base ?><?= $p - 1 ?>" class="krono-notif-page-btn"><i class="bi bi-chevron-left"></i></a>
                <?php else: ?>
                    <span class="krono-notif-page-btn is-disabled"><i class="bi bi-chevron-left"></i></span>
                <?php endif; ?>

                <?php if ($start > 1): ?>
                    <a href="<?= $base ?>1" class="krono-notif-page-btn">1</a>
                    <?php if ($start > 2): ?><span class="krono-notif-page-btn is-disabled">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $p): ?>
                        <span class="krono-notif-page-btn is-active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $base ?><?= $i ?>" class="krono-notif-page-btn"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $last): ?>
                    <?php if ($end < $last - 1): ?><span class="krono-notif-page-btn is-disabled">…</span><?php endif; ?>
                    <a href="<?= $base ?><?= $last ?>" class="krono-notif-page-btn"><?= $last ?></a>
                <?php endif; ?>

                <?php if ($p < $last): ?>
                    <a href="<?= $base ?><?= $p + 1 ?>" class="krono-notif-page-btn"><i class="bi bi-chevron-right"></i></a>
                <?php else: ?>
                    <span class="krono-notif-page-btn is-disabled"><i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';

    const csrfMeta  = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    const markUrl   = <?= json_encode(url('/notifications/mark-read')) ?>;

    function postMarkRead(id) {
        const body = new URLSearchParams();
        body.set('id', String(id));
        body.set('csrf_token', csrfToken);
        return fetch(markUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
            },
            body: body.toString(),
            keepalive: id !== 'all',
        }).then(r => r.ok ? r.json() : null).catch(() => null);
    }

    function updateBellBadge(count) {
        const badge = document.getElementById('kronoNotifBadge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    document.querySelectorAll('.krono-notif-card__read-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.krono-notif-card');
            if (!card) return;
            const id = card.getAttribute('data-notif-id');
            card.classList.remove('is-unread');
            card.classList.add('is-read');
            btn.remove();
            postMarkRead(id).then(resp => {
                if (resp && typeof resp.unread_count === 'number') {
                    updateBellBadge(resp.unread_count);
                }
            });
        });
    });

    document.querySelectorAll('.krono-notif-card__link, .krono-notif-card__cta').forEach(link => {
        link.addEventListener('click', () => {
            const id = link.getAttribute('data-notif-id');
            if (id) postMarkRead(id);
        });
    });

    const btnAll = document.getElementById('btnMarkAllReadPage');
    if (btnAll) {
        btnAll.addEventListener('click', () => {
            document.querySelectorAll('.krono-notif-card.is-unread').forEach(card => {
                card.classList.remove('is-unread');
                card.classList.add('is-read');
                const b = card.querySelector('.krono-notif-card__read-btn');
                if (b) b.remove();
            });
            btnAll.disabled = true;
            postMarkRead('all').then(resp => {
                if (resp && typeof resp.unread_count === 'number') {
                    updateBellBadge(resp.unread_count);
                }
            });
        });
    }
})();
</script>
