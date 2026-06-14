<div class="auth-panel" style="text-align: center; padding: 2.5rem 2rem;">
    <div style="margin-bottom: 2.25rem;">
        <div class="maintenance-icon-wrap" style="width: 84px; height: 84px; margin: 0 auto; background: var(--krono-accent-light); color: var(--krono-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.75rem; animation: pulse 2s infinite;">
            <i class="bi bi-gear-wide-connected"></i>
        </div>
    </div>
    
    <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--krono-text); margin-bottom: 1rem; letter-spacing: -0.5px;">Plateforme en maintenance</h2>
    
    <p style="color: var(--krono-text-3); font-size: 0.92rem; line-height: 1.65; margin-bottom: 2.25rem; max-width: 420px; margin-left: auto; margin-right: auto;">
        Notre passerelle centrale subit actuellement des opérations de maintenance afin d'améliorer la performance et la sécurité du réseau.
    </p>
    
    <div class="glass-card" style="padding: 1.25rem; border: 1px solid var(--krono-border); border-radius: 14px; background: var(--krono-surface-2); max-width: 320px; margin: 0 auto 1.5rem;">
        <div style="font-size: 0.82rem; color: var(--krono-text-2); font-weight: 700; margin-bottom: 0.85rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
            <i class="bi bi-shield-lock-fill" style="color: var(--krono-accent); font-size: 0.95rem;"></i> Zone Administration
        </div>
        <a href="<?= url('/login') ?>" class="btn-krono btn-krono--secondary btn-krono--sm" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; justify-content: center; width: 100%;">
            <i class="bi bi-box-arrow-in-right"></i> Se connecter
        </a>
    </div>
</div>

<style>
@keyframes pulse {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(var(--krono-accent-rgb), 0.4); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(var(--krono-accent-rgb), 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(var(--krono-accent-rgb), 0); }
}
.maintenance-icon-wrap i {
    animation: spin-gear 12s linear infinite;
    display: inline-block;
}
@keyframes spin-gear {
    100% { transform: rotate(360deg); }
}
</style>
