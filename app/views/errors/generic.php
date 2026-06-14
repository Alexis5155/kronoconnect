<div class="auth-panels-wrap">
    <div class="auth-panel active" style="position:relative; opacity:1; transform:none; pointer-events:auto; text-align:center;">

        <div class="auth-logo" style="margin-bottom:1.5rem; background:var(--krono-danger); box-shadow: 0 8px 28px rgba(220, 38, 38, 0.3);">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        
        <div style="font-size:3rem; font-weight:900; color:var(--krono-text-3); line-height:1; margin-bottom:.5rem;">
            <?= e($code ?? '!') ?>
        </div>
        
        <h1 class="auth-title"><?= e($title ?? 'Erreur') ?></h1>
        
        <p style="font-size:.9rem; color:var(--krono-text-2); margin:1rem 0 2rem; line-height:1.6;">
            <?= !empty($message) ? e($message) : "Une erreur inattendue est survenue lors de l'accès à cette page." ?>
        </p>

        <a href="<?= url('/') ?>" class="auth-btn">
            <i class="bi bi-house-door" style="margin-right:.5rem;"></i> Retour à l'accueil
        </a>

    </div>
    </div>