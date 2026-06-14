<?php
/**
 * View shown during automated setup flow when user refuses.
 * Auto-submits a POST form to the requesting application.
 */
?>
<div class="fade-in-up anim-delay-1 glass-card" style="max-width: 500px; margin: 4rem auto; padding: 3rem 2rem; text-align: center; border-color: var(--krono-danger);">
    
    <div style="font-size: 3rem; color: var(--krono-danger); margin-bottom: 1.5rem;">
        <i class="bi bi-x-circle-fill"></i>
    </div>
    
    <h2 style="margin-bottom: 1rem; font-size: 1.5rem; font-weight: 700;">Demande refusée</h2>
    <p style="color: var(--krono-text-3); margin-bottom: 2.5rem; line-height: 1.6;">
        L'association avec cette application a été annulée.<br>
        Redirection automatique vers l'application...
    </p>

    <div class="krono-spinner krono-spinner--lg" style="border-width: 4px; width: 48px; height: 48px; border-color: var(--krono-border); border-top-color: var(--krono-danger);"></div>

</div>

<style>
@keyframes kc-fade-up {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Wait 1.5 seconds for visual feedback, then redirect via GET to avoid SameSite Lax cookie issues
    setTimeout(() => {
        const setupUrl = new URL(<?= json_encode($setupUrl) ?>);
        setupUrl.searchParams.set('status', 'refused');
        setupUrl.searchParams.set('setup_token', <?= json_encode($setupToken) ?>);
        window.location.href = setupUrl.toString();
    }, 1500);
});
</script>
