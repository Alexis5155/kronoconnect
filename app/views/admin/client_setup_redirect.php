<?php
/**
 * View shown during automated setup flow.
 * Auto-submits a POST form to the requesting application.
 */
?>
<div class="fade-in-up anim-delay-1 glass-card" style="max-width: 500px; margin: 4rem auto; padding: 3rem 2rem; text-align: center; ">
    
    <div style="font-size: 3rem; color: var(--krono-primary); margin-bottom: 1.5rem; animation: pulse-exchange 2s infinite ease-in-out;">
        <i class="bi bi-arrow-left-right"></i>
    </div>
    
    <h2 style="margin-bottom: 1rem; font-size: 1.5rem; font-weight: 700;">Connexion en cours...</h2>
    <p style="color: var(--krono-text-3); margin-bottom: 2.5rem; line-height: 1.6;">
        L'application a été approuvée.<br>
        Redirection automatique pour transmettre les clés sécurisées.
    </p>

    <div class="krono-spinner krono-spinner--lg" style="border-width: 4px; width: 48px; height: 48px; border-color: var(--krono-border); border-top-color: var(--krono-primary);"></div>

    <form id="autoSubmitForm" action="<?= e($setupUrl) ?>" method="POST" style="display:none;">
        <input type="hidden" name="setup_token" value="<?= e($setupToken) ?>">
        <input type="hidden" name="client_id" value="<?= e($clientId) ?>">
        <input type="hidden" name="client_secret" value="<?= e($clientSecret) ?>">
        <input type="hidden" name="kc_url" value="<?= e($kcUrl) ?>">
    </form>
</div>

<style>
@keyframes pulse-exchange {
    0% { transform: scale(0.95); opacity: 0.6; }
    50% { transform: scale(1.1); opacity: 1; color: var(--krono-accent); }
    100% { transform: scale(0.95); opacity: 0.6; }
}
@keyframes kc-fade-up {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Wait 1.5 seconds for visual feedback, then submit
    setTimeout(() => {
        document.getElementById('autoSubmitForm').submit();
    }, 1500);
});
</script>