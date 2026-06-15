<?php
/**
 * Layout épuré pour l'espace utilisateur
 * Entre la page de login (trop étroite) et le panel admin (trop complexe)
 */
$authUser  = auth();
$title     = $title ?? 'Espace Personnel';
$userTheme = $authUser['theme'] ?? 'system';
?>
<!DOCTYPE html>
<html lang="fr" data-user-theme="<?= e($userTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= \KronoConnect\Core\Security::csrfToken() ?>">
    <meta name="view-transition" content="same-origin">
    <script src="<?= asset('scripts/theme.js') ?>"></script>
    <title><?= e($title) ?> — KronoConnect</title>
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://api.fontshare.com/v2/css?f[]=cabinet-grotesk@400,500,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('styles/krono-variables.css') ?>?v=1.2">
    <link rel="stylesheet" href="<?= asset('styles/krono-theme.css') ?>?v=1.2">
    <link rel="stylesheet" href="<?= asset('styles/krono-components.css') ?>?v=1.2">
    <link rel="stylesheet" href="<?= asset('styles/krono-auth.css') ?>?v=1.2"> <!-- On réutilise les bases de l'auth -->
    <link rel="stylesheet" href="<?= asset('styles/krono-animations.css') ?>?v=1.2">
    <link rel="stylesheet" href="<?= asset('styles/krono-portal.css') ?>?v=1.2">
    <link rel="stylesheet" href="<?= asset('styles/krono-layout.css') ?>?v=2.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="auth-body portal-body">

<div class="auth-bg"></div>
<div class="auth-halo auth-halo-1"></div>
<div class="auth-halo auth-halo-2"></div>

<div class="auth-wrap portal-wrap wide" style="width: <?= ($useCard ?? true) ? '900px' : '1200px' ?>; max-width: 95vw;">
    
    <!-- Navigation discrète au-dessus de la carte -->
    <nav class="user-simple-nav">
        <div class="nav-brand">
            <div class="krono-logo-mark">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <span>KronoConnect</span>
        </div>
        
        <div class="nav-links">
            <a href="<?= url('/') ?>" class="<?= ($activePage ?? '') === 'home' ? 'active' : '' ?>" title="Portail">
                <i class="bi bi-grid-1x2"></i> <span>Portail</span>
            </a>
            <a href="<?= url('/profile') ?>" class="<?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>" title="Profil">
                <i class="bi bi-person-circle"></i> <span>Profil</span>
            </a>
            <?php if (\KronoConnect\Core\Session::hasRole('admin', 'super_admin')): ?>
                <a href="<?= url('/admin') ?>" class="nav-link-admin" title="Administration">
                    <i class="bi bi-gear"></i> <span>Admin</span> <i class="bi bi-box-arrow-up-right desktop-only-icon" style="font-size: 0.7rem; margin-left: 0.1rem;"></i>
                </a>
            <?php endif; ?>
        </div>

        <div class="nav-actions">
            <div class="krono-notif-bell-wrap">
                <button type="button"
                        class="krono-notif-bell"
                        id="kronoNotifBell"
                        aria-haspopup="true"
                        aria-expanded="false"
                        title="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <span class="krono-notif-badge" id="kronoNotifBadge" style="display:none;">0</span>
                </button>

                <div class="krono-notif-overlay" id="kronoNotifOverlay" role="menu" aria-hidden="true">
                    <div class="krono-notif-header">
                        <div>
                            <div>Notifications</div>
                            <div id="kronoNotifCountText">0 non lue(s)</div>
                        </div>
                        <button type="button"
                                class="krono-notif-header__mark-all"
                                id="kronoNotifMarkAll"
                                title="Tout marquer comme lu">
                            <i class="bi bi-check2-all"></i>
                        </button>
                    </div>
                    <div class="krono-notif-body" id="kronoNotifList">
                        <div class="krono-notif-empty">
                            <i class="bi bi-bell-slash"></i>
                            <div>Aucune notification</div>
                        </div>
                    </div>
                    <div class="krono-notif-footer">
                        <a href="<?= url('/notifications') ?>">
                            Voir le centre de notifications
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </div>
                </div>
            </div>

            <form method="POST" action="<?= url('/logout') ?>" class="nav-logout-form">
                <?= csrf() ?>
                <button type="submit" class="logout-link" title="Se déconnecter">
                    <i class="bi bi-power"></i>
                </button>
            </form>
        </div>
    </nav>

    <?php if ($useCard ?? true): ?>
    <div class="auth-card" style="display: block; min-height: 500px;">
        <div class="user-content-padding">
            <?= $content ?>
        </div>
    </div>
    <?php else: ?>
    <div class="portal-wrapper">
        <?= $content ?>
    </div>
    <?php endif; ?>

    <div class="auth-footer" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; opacity: 0.85;">
        <i class="bi bi-github" style="color: var(--krono-text-3); font-size: 0.9rem;"></i>
        <a href="https://github.com/Alexis5155/kronoconnect" target="_blank" rel="noopener" style="font-weight: 600;">KronoConnect</a>
        <span style="opacity: 0.3;">•</span>
        <i class="bi bi-shield-shaded" style="font-size: 0.8rem;"></i>
        <a href="https://github.com/Alexis5155/kronoconnect/blob/main/LICENSE" target="_blank" rel="noopener" style="font-weight: 500; font-size: 0.7rem; letter-spacing: 0.5px;">AGPL-3.0 LICENSE</a>
        <?php
        $settingsModel = new \KronoConnect\Models\AdminModel();
        $globalSettings = $settingsModel->getSettings();
        $gdprPrivacyUrl = $globalSettings['gdpr_privacy_url'] ?? '';
        $gdprLegalUrl = $globalSettings['gdpr_legal_url'] ?? '';
        if (!empty($gdprPrivacyUrl) || !empty($gdprLegalUrl)):
        ?>
            <span style="opacity: 0.3;">•</span>
            <?php if (!empty($gdprPrivacyUrl)): ?>
                <a href="<?= htmlspecialchars($gdprPrivacyUrl) ?>" target="_blank" style="font-weight: 500; font-size: 0.7rem;">Politique de confidentialité</a>
            <?php endif; ?>
            <?php if (!empty($gdprLegalUrl)): ?>
                <?php if (!empty($gdprPrivacyUrl)): ?><span style="opacity: 0.3;">•</span><?php endif; ?>
                <a href="<?= htmlspecialchars($gdprLegalUrl) ?>" target="_blank" style="font-weight: 500; font-size: 0.7rem;">Mentions légales</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Bouton flottant de bascule de thème (en bas à gauche de la page) -->
<div class="theme-toggle-floating">
    <div class="theme-dropdown-container">
        <button type="button" 
                class="theme-toggle-btn" 
                id="floatingThemeToggleBtn" 
                data-url="<?= url('/profile/theme') ?>" 
                data-csrf="<?= \KronoConnect\Core\Security::csrfToken() ?>" 
                title="Changer de thème">
            <i class="bi bi-sun-fill theme-icon-sun"></i>
            <i class="bi bi-moon-stars-fill theme-icon-moon"></i>
        </button>
        <div class="theme-dropdown-menu">
            <button type="button" class="theme-dropdown-item" data-theme-val="light">
                <i class="bi bi-sun-fill"></i> Clair
            </button>
            <button type="button" class="theme-dropdown-item" data-theme-val="dark">
                <i class="bi bi-moon-stars-fill"></i> Sombre
            </button>
            <button type="button" class="theme-dropdown-item" data-theme-val="system">
                <i class="bi bi-display"></i> Système
            </button>
        </div>
    </div>
</div>

<script src="<?= asset('scripts/krono-core.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php
        $flashes = \KronoConnect\Core\Session::pullFlashes();
        foreach ($flashes as $type => $msg):
            $level = match($type) {
                'success' => 'success',
                'error'   => 'danger',
                'warning' => 'warning',
                default   => 'info',
            };
        ?>
        window.kronoToast({ message: <?= json_encode(e($msg)) ?>, level: '<?= $level ?>', duration: 6000 });
        <?php endforeach; ?>
    });
</script>

<script>
/**
 * Cloche de notifications — KronoConnect.
 * Polling toutes les 30s sur /notifications/unread, dropdown au clic,
 * marquage comme lu côté hub central (session, pas HMAC).
 */
(function () {
    'use strict';

    const POLL_MS    = 30000;
    const unreadUrl  = <?= json_encode(url('/notifications/unread')) ?>;
    const markUrl    = <?= json_encode(url('/notifications/mark-read')) ?>;
    const centerUrl  = <?= json_encode(url('/notifications')) ?>;

    const csrfMeta   = document.querySelector('meta[name="csrf-token"]');
    const csrfToken  = csrfMeta ? csrfMeta.getAttribute('content') : '';

    const btn        = document.getElementById('kronoNotifBell');
    const dropdown   = document.getElementById('kronoNotifOverlay');
    const badge      = document.getElementById('kronoNotifBadge');
    const countText  = document.getElementById('kronoNotifCountText');
    const list       = document.getElementById('kronoNotifList');
    const markAll    = document.getElementById('kronoNotifMarkAll');

    if (!btn || !dropdown) return; // Layout sans cloche

    // Sortir le dropdown du stacking context créé par le backdrop-filter de la nav.
    // Sans ça, le backdrop-filter du dropdown ne peut filtrer que l'intérieur de la nav.
    document.body.appendChild(dropdown);

    function positionDropdown() {
        const rect    = btn.getBoundingClientRect();
        const mobile  = window.innerWidth <= 768;
        if (mobile) {
            Object.assign(dropdown.style, {
                position:  'fixed',
                top:       'auto',
                bottom:    '1rem',
                right:     '1rem',
                left:      '1rem',
                width:     'auto',
                maxWidth:  'none',
            });
        } else {
            Object.assign(dropdown.style, {
                position:  'fixed',
                top:       (rect.bottom + 10) + 'px',
                right:     (window.innerWidth - rect.right) + 'px',
                left:      'auto',
                bottom:    'auto',
                width:     '400px',
                maxWidth:  'calc(100vw - 2rem)',
            });
        }
    }

    window.addEventListener('resize', () => {
        if (dropdown.classList.contains('is-open')) positionDropdown();
    });

    let pollTimer = null;
    let stopped   = false;
    const seen    = new Set();
    let firstLoad = true;

    const TYPE_ICONS = {
        info:    'bi-info-circle-fill',
        success: 'bi-check-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        error:   'bi-exclamation-octagon-fill',
    };

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function hexToRgb(hex) {
        let h = String(hex || '').replace('#', '');
        if (h.length === 3) h = h.split('').map(x => x + x).join('');
        if (h.length !== 6) return '59,95,192';
        const n = parseInt(h, 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255].join(',');
    }

    function setBadge(count) {
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
        if (countText) {
            countText.textContent = count + ' non lue' + (count > 1 ? 's' : '');
        }
    }

    function renderList(items) {
        if (!list) return;

        if (!items.length) {
            list.innerHTML =
                '<div class="krono-notif-empty">'
              + '  <i class="bi bi-bell-slash"></i>'
              + '  <div>Aucune notification</div>'
              + '</div>';
            return;
        }

        list.innerHTML = items.map(n => {
            const type     = TYPE_ICONS[n.type] ? n.type : 'info';
            const icon     = TYPE_ICONS[type];
            const appIcon  = (n.app_icon || 'app-indicator').replace(/^bi-/, '');
            const appColor = n.app_color || '#3b5fc0';
            const rgb      = hexToRgb(appColor);
            const created  = n.created_at ? n.created_at.replace(' ', 'T') : '';
            const url      = n.url ? escapeHtml(n.url) : '';
            const titleStr = escapeHtml(n.title || '');
            const msgStr   = escapeHtml(n.message || '');
            const appLabel = escapeHtml(n.app_name || '');
            const isRead   = !!n.read_at;

            return ''
              + '<a class="krono-notif-item krono-notif-item--' + type + (isRead ? '' : ' krono-notif-item--unread') + '" '
              + '   data-notif-id="' + n.id + '" '
              + ('   href="' + (url || (centerUrl + '#n-' + n.id)) + '" ')
              + '   style="--app-color:' + appColor + ';--app-color-rgb:' + rgb + ';">'
              + '  <span class="krono-notif-item__app" title="' + appLabel + '">'
              + '    <i class="bi bi-' + escapeHtml(appIcon) + '"></i>'
              + '  </span>'
              + '  <span class="krono-notif-item__body">'
              + '    <span class="krono-notif-item__title">'
              + '      <i class="bi ' + icon + ' krono-notif-item__type"></i>'
              + (titleStr || msgStr.substring(0, 80))
              + '    </span>'
              + (titleStr && msgStr ? '    <span class="krono-notif-item__msg">' + msgStr + '</span>' : '')
              + '    <span class="krono-notif-item__meta">'
              + '      <span class="krono-notif-item__app-label">' + appLabel + '</span>'
              + '      <span class="krono-notif-item__date">'
              + (created ? new Date(created).toLocaleString('fr-FR') : '')
              + '      </span>'
              + '    </span>'
              + '  </span>'
              + '</a>';
        }).join('');
    }

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
            keepalive: true,
        }).then(r => r.ok ? r.json() : null);
    }

    function poll() {
        if (stopped) return;
        fetch(unreadUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(res => {
                if (res.status === 401) { stopped = true; return null; }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(payload => {
                if (!payload) return;
                const data  = payload.data && typeof payload.data === 'object' ? payload.data : payload;
                const items = Array.isArray(data.notifications) ? data.notifications : [];
                const count = typeof data.unread_count === 'number' ? data.unread_count : items.length;

                setBadge(count);
                renderList(items);

                if (!firstLoad && typeof window.showRichNotification === 'function') {
                    items.forEach(n => {
                        const id = String(n.id);
                        if (!seen.has(id)) {
                            const levelMap = { success: 'success', error: 'danger', warning: 'warning', info: 'info' };
                            window.showRichNotification({
                                title: n.app_name || 'Notification',
                                message: (n.title ? n.title + ' — ' : '') + (n.message || ''),
                                level:   levelMap[n.type] || 'info',
                                link:    n.url || null,
                                duration: n.type === 'error' ? 8000 : 6000,
                            });
                        }
                        seen.add(id);
                    });
                } else if (!firstLoad && typeof window.kronoToast === 'function') {
                    // Fallback si showRichNotification n'est pas chargé
                    items.forEach(n => {
                        const id = String(n.id);
                        if (!seen.has(id)) {
                            const levelMap = { success: 'success', error: 'danger', warning: 'warning', info: 'info' };
                            window.kronoToast({
                                message: (n.title ? n.title + ' — ' : '') + (n.message || ''),
                                level:   levelMap[n.type] || 'info',
                                duration: n.type === 'error' ? 8000 : 6000,
                            });
                        }
                        seen.add(id);
                    });
                } else {
                    items.forEach(n => seen.add(String(n.id)));
                }
                firstLoad = false;
            })
            .catch(err => console.warn('[KC notif] poll error:', err));
    }

    // Dropdown ouvert/fermé
    function setOpen(open) {
        if (open) positionDropdown();
        dropdown.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        dropdown.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = !dropdown.classList.contains('is-open');
        setOpen(willOpen);
        if (willOpen) poll();
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') setOpen(false);
    });

    // Clic sur une ligne → marque comme lue avant nav
    list.addEventListener('click', (e) => {
        const row = e.target.closest('.krono-notif-item[data-notif-id]');
        if (!row) return;
        const id = row.getAttribute('data-notif-id');
        if (id) postMarkRead(id);
    });

    // Tout marquer comme lu
    if (markAll) {
        markAll.addEventListener('click', (e) => {
            e.stopPropagation();
            postMarkRead('all').then(resp => {
                if (resp && typeof resp.unread_count === 'number') {
                    setBadge(resp.unread_count);
                }
                renderList([]);
            });
        });
    }

    // Polling
    poll();
    pollTimer = setInterval(poll, POLL_MS);

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && pollTimer) {
            clearInterval(pollTimer); pollTimer = null;
        } else if (!document.hidden && !pollTimer && !stopped) {
            poll();
            pollTimer = setInterval(poll, POLL_MS);
        }
    });

    window.addEventListener('beforeunload', () => {
        if (pollTimer) clearInterval(pollTimer);
    });
})();
</script>

</body>
</html>
