<?php
/**
 * Layout admin KronoConnect — topbar + sidebar + contenu + footer
 * Aligné sur kronocore/app/views/layouts/main.php
 *
 * Variables disponibles : $title, $page, $content
 */
$authUser  = auth();
$page      = $page ?? '';
$title     = $title ?? 'Administration';
$userTheme = $authUser['theme'] ?? 'system';

$userName  = trim(($authUser['prenom'] ?? '') . ' ' . ($authUser['nom'] ?? ''));
$userRole  = $authUser['role'] ?? 'admin';
$initiales = strtoupper(
    mb_substr($authUser['prenom'] ?? 'A', 0, 1) .
    mb_substr($authUser['nom']    ?? '',  0, 1)
);

$navSections = [
    'Général' => [
        ['href' => '/admin',         'icon' => 'bi-speedometer2',  'label' => 'Tableau de bord', 'page' => 'dashboard'],
        ['href' => '/admin/logs',    'icon' => 'bi-journal-text',  'label' => 'Journal',         'page' => 'logs'],
    ],
    'SSO & Portail' => [
        ['href' => '/admin/clients', 'icon' => 'bi-app-indicator', 'label' => 'Clients SSO',     'page' => 'clients'],
        ['href' => '/admin/links',   'icon' => 'bi-link-45deg',    'label' => 'Liens externes',  'page' => 'links'],
    ],
    'Organisation' => [
        ['href' => '/admin/services','icon' => 'bi-diagram-3',     'label' => 'Services',        'page' => 'services'],
        ['href' => '/admin/users',   'icon' => 'bi-people-fill',   'label' => 'Utilisateurs',    'page' => 'users'],
        ['href' => '/admin/groups',  'icon' => 'bi-collection',    'label' => 'Groupes & RBAC',  'page' => 'groups'],
    ],
    'Configuration' => [
        ['href' => '/admin/settings','icon' => 'bi-gear',          'label' => 'Paramètres',      'page' => 'settings'],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr" data-user-theme="<?= e($userTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= \KronoConnect\Core\Security::csrfToken() ?>">
    <script src="<?= asset('scripts/theme.js') ?>"></script>
    <title><?= e($title) ?> — KronoConnect</title>
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="stylesheet" href="<?= asset('styles/krono-variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('styles/krono-theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('styles/krono-components.css') ?>">
    <link rel="stylesheet" href="<?= asset('styles/krono-layout.css') ?>?v=2.0">
    <link rel="stylesheet" href="<?= asset('styles/krono-animations.css') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<div class="krono-app">

    <!-- Blobs décoratifs glassmorphisme -->
    <div class="glass-blob glass-blob--1"></div>
    <div class="glass-blob glass-blob--2"></div>
    <div class="glass-blob glass-blob--3"></div>
    <div class="glass-blob glass-blob--4"></div>

    <!-- ══ SIDEBAR ══════════════════════════════════════════════════════ -->
    <aside class="krono-sidebar" id="sidebar" role="navigation" aria-label="Navigation principale">

        <!-- Logo -->
        <a href="<?= url('/admin') ?>" class="sidebar-logo">
            <div class="sidebar-logo__icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div class="sidebar-logo__text">
                <div class="sidebar-logo__name">KronoConnect</div>
                <div class="sidebar-logo__module">SSO · Administration</div>
            </div>
        </a>

        <!-- Navigation -->
        <nav class="sidebar-nav">
            <?php foreach ($navSections as $sectionTitle => $links): ?>
                <span class="sidebar-section-title"><?= e($sectionTitle) ?></span>

                <?php foreach ($links as $link):
                    $isActive = ($page === $link['page']);
                ?>
                    <a href="<?= url($link['href']) ?>"
                       class="krono-nav-item <?= $isActive ? 'active' : '' ?>">
                        <i class="bi <?= e($link['icon']) ?>"></i>
                        <?= e($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Footer Sidebar avec bouton de bascule de thème -->
        <div class="sidebar-footer">
            <div class="theme-dropdown-container">
                <button type="button" 
                        class="theme-toggle-btn" 
                        id="sidebarThemeToggleBtn" 
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

    </aside>

    <!-- Overlay mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ══ ZONE PRINCIPALE ══════════════════════════════════════════════ -->
    <div class="krono-main">

        <!-- Topbar -->
        <header class="krono-topbar">
            <div class="topbar-left">
                <button class="topbar-hamburger" id="hamburger" aria-label="Menu" aria-expanded="false">
                    <i class="bi bi-list"></i>
                </button>
                <span class="topbar-page-title"><?= e($title) ?></span>
            </div>

            <div class="topbar-right">
                <!-- Retour au portail SSO -->
                <a href="<?= url('/') ?>" class="topbar-icon-btn" title="Retour au portail">
                    <i class="bi bi-house"></i>
                </a>

                <!-- Chip utilisateur -->
                <div class="topbar-user" style="cursor:default;">
                    <div class="topbar-avatar"><?= e($initiales) ?></div>
                    <span class="topbar-user__name"><?= e($userName) ?></span>
                </div>

                <!-- Déconnexion -->
                <form method="POST" action="<?= url('/logout') ?>" style="margin:0;display:inline;">
                    <?= csrf() ?>
                    <button type="submit" class="topbar-icon-btn" title="Se déconnecter">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </header>

        <!-- Contenu -->
        <main class="krono-content" id="main-content">
            <div class="krono-container">
                <?= $content ?>
            </div>
        </main>

        <!-- Modal de confirmation universelle -->
        <div class="krono-modal-backdrop" id="KronoConnectConfirmModal">
            <div class="glass-card krono-modal-content">
                <div class="modal-icon-box modal-icon-box--warning" id="kConfirmIconBox">
                    <i class="bi bi-exclamation-triangle-fill" id="kConfirmIcon"></i>
                </div>
                <h3 class="modal-title" id="kConfirmTitle">Confirmation</h3>
                <p class="modal-text" id="kConfirmText">Êtes-vous sûr de vouloir continuer ?</p>
                <div class="modal-buttons">
                    <button type="button" class="btn-krono btn-krono--ghost" id="kConfirmCancel">Annuler</button>
                    <button type="button" class="btn-krono btn-krono--primary" id="kConfirmBtn">Confirmer</button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="krono-footer">
            <div class="krono-container" style="display:flex;justify-content:space-between;align-items:center;">
                <div style="display: flex; align-items: center; gap: 0.5rem; opacity: 0.85;">
                    <i class="bi bi-github" style="color: var(--krono-text-3);"></i>
                    <a href="https://github.com/Alexis5155/kronoconnect" target="_blank" rel="noopener" style="font-weight: 600;">KronoConnect</a>
                    <span style="opacity: 0.3;">•</span>
                    <i class="bi bi-shield-shaded" style="font-size: 0.8rem;"></i>
                    <a href="https://github.com/Alexis5155/kronoconnect/blob/main/LICENSE" target="_blank" rel="noopener" style="font-weight: 500; font-size: 0.7rem; letter-spacing: 0.5px;">AGPL-3.0 LICENSE</a>
                </div>
                <a href="https://github.com/Alexis5155/kronoconnect" target="_blank" rel="noopener" style="text-decoration: none;">
                    <span class="krono-footer__version">
                        <i class="bi bi-tag-fill"></i>
                        v<?= e($appConfig['version'] ?? '0.0.1') ?>
                    </span>
                </a>
            </div>
        </footer>

    </div><!-- /krono-main -->

</div><!-- /krono-app -->

<!-- JS principal -->
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
        window.kronoToast({ message: <?= json_encode(e($msg)) ?>, level: '<?= $level ?>', duration: <?= $level === 'danger' ? 8000 : 6000 ?> });
        <?php endforeach; ?>
        
        <?php if (!empty($missingDependencies) && can('kc.settings.manage')): ?>
        window.kronoToast({
            title: 'Dépendances manquantes',
            message: <?= json_encode('Les dépendances suivantes sont requises mais absentes : ' . implode(', ', array_map('e', $missingDependencies))) ?>,
            level: 'danger',
            duration: -1
        });
        <?php endif; ?>
    });
</script>

</body>
</html>