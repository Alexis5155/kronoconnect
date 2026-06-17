<?php
/** @var string $content Vue injectée par View::render() */
$content ??= '';
$title   ??= 'KronoConnect';
$userTheme = \KronoConnect\Core\Session::get('user')['theme'] ?? 'system';
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
    <link href="https://api.fontshare.com/v2/css?f[]=cabinet-grotesk@400,500,700,800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('styles/krono-variables.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= asset('styles/krono-theme.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= asset('styles/krono-components.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= asset('styles/krono-auth.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= asset('styles/krono-animations.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= asset('styles/krono-layout.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="auth-body">

<div class="auth-bg"></div>
<div class="auth-halo auth-halo-1"></div>
<div class="auth-halo auth-halo-2"></div>
<div class="auth-halo auth-halo-3"></div>

<div class="auth-wrap <?= ($wide ?? false) ? 'wide' : '' ?> <?= $wideClass ?? '' ?> <?= ($sso_layout ?? false) ? 'sso-layout' : '' ?>" id="authWrap">
    <div class="auth-card" id="authCard">
        <?= $content ?>
    </div>

    <?php
    $settingsModel = new \KronoConnect\Models\AdminModel();
    $globalSettings = $settingsModel->getSettings();
    $gdprPrivacyUrl = $globalSettings['gdpr_privacy_url'] ?? '';
    $gdprLegalUrl = $globalSettings['gdpr_legal_url'] ?? '';
    ?>
    <div class="auth-footer" style="display: flex; flex-direction: column; align-items: center; gap: 0.4rem; opacity: 0.85;">
        <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: center;">
            <i class="bi bi-github" style="color: var(--krono-text-3);"></i>
            <a href="https://github.com/Alexis5155/kronoconnect" target="_blank" rel="noopener" style="font-weight: 600; text-decoration: none; color: inherit;"><?= e($appConfig['name'] ?? 'KronoConnect') ?></a>
            <span style="opacity: 0.3;">•</span>
            <i class="bi bi-shield-shaded" style="font-size: 0.8rem;"></i>
            <a href="https://github.com/Alexis5155/kronoconnect/blob/main/LICENSE" target="_blank" rel="noopener" style="font-weight: 500; font-size: 0.7rem; letter-spacing: 0.5px;">AGPL-3.0 LICENSE</a>
        </div>
        <?php if (!empty($gdprPrivacyUrl) || !empty($gdprLegalUrl)): ?>
        <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: center; font-size: 0.72rem;">
            <?php if (!empty($gdprPrivacyUrl)): ?>
                <a href="<?= htmlspecialchars($gdprPrivacyUrl) ?>" target="_blank" style="color: var(--krono-text-3); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='var(--krono-text)'" onmouseout="this.style.color='var(--krono-text-3)'">Politique de confidentialité</a>
            <?php endif; ?>
            <?php if (!empty($gdprPrivacyUrl) && !empty($gdprLegalUrl)): ?>
                <span style="opacity: 0.3; color: var(--krono-text-3);">•</span>
            <?php endif; ?>
            <?php if (!empty($gdprLegalUrl)): ?>
                <a href="<?= htmlspecialchars($gdprLegalUrl) ?>" target="_blank" style="color: var(--krono-text-3); text-decoration: none; transition: color 0.3s;" onmouseover="this.style.color='var(--krono-text)'" onmouseout="this.style.color='var(--krono-text-3)'">Mentions légales</a>
            <?php endif; ?>
        </div>
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
<script src="<?= asset('scripts/krono-auth.js') ?>"></script>

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
    });
</script>

</body>
</html>