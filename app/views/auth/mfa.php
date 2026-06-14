<?php
$error = \KronoConnect\Core\Session::getFlash('error');
$success = \KronoConnect\Core\Session::getFlash('success');
?>

<div class="auth-panels-wrap" style="height: auto;">
    <div class="auth-panel active" style="position: relative; opacity: 1; transform: none; pointer-events: auto;">

        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="auth-logo" style="margin:0 auto 1.1rem; background: var(--krono-accent-light); color: var(--krono-accent);">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div class="auth-title">Double authentification</div>
            <div class="auth-subtitle" style="margin-top:0.3rem;">Veuillez saisir le code de sécurité</div>
        </div>

        <?php if ($error): ?>
            <div style="background:var(--krono-danger-light); color:var(--krono-danger); padding:0.75rem 1rem; border-radius:6px; margin-bottom:1.5rem; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background:var(--krono-success-light); color:var(--krono-success); padding:0.75rem 1rem; border-radius:6px; margin-bottom:1.5rem; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="bi bi-check-circle-fill"></i>
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('/login/mfa' . (!empty($flowId) ? '?flow=' . e($flowId) : '')) ?>">
            <?= csrf() ?>

            <div style="margin-bottom:1.25rem;">
                <div class="auth-field" style="max-width:250px; margin:0 auto;">
                    <input type="text" name="code" class="auth-input"
                           placeholder="Code à 6 chiffres" maxlength="9"
                           style="text-align:center; font-size:1.1rem; letter-spacing:0.1rem; padding: 0.75rem;" required autofocus autocomplete="off">
                </div>
            </div>

            <button type="submit" class="auth-btn">
                <i class="bi bi-shield-check" style="margin-right:.5rem;"></i> Vérifier le code
            </button>
        </form>

        <?php if ($hasWebAuthn): ?>
            <div id="webauthn-login-container" style="display: none; margin-bottom: 1.25rem;">
                <div style="display: flex; align-items: center; margin: 1rem 0; color: var(--krono-text-3); font-size: 0.85rem;">
                    <div style="flex: 1; height: 1px; background: var(--krono-border);"></div>
                    <span style="padding: 0 0.75rem;">OU</span>
                    <div style="flex: 1; height: 1px; background: var(--krono-border);"></div>
                </div>
                <button type="button" id="btn-webauthn-login" onclick="startWebAuthnLogin()" class="auth-btn" style="background: var(--krono-surface-3); color: var(--krono-text); border: 1px solid var(--krono-border);">
                    <i class="bi bi-key-fill" style="margin-right:.5rem; color: var(--krono-accent);"></i> Clé de sécurité ou Biométrie
                </button>
                <div id="webauthn-login-error" style="display: none; color: #DC2626; font-size: 0.8rem; margin-top: 0.5rem; text-align: center; line-height: 1.4;"></div>
            </div>
        <?php endif; ?>

        <div style="text-align:center; margin-top:1rem;">
            <p style="font-size:0.8rem; color:var(--krono-text-3); margin:0 0 0.6rem;">
                Vous avez un code de secours ?
                <span style="color:var(--krono-text-2);">Saisissez-le à la place du code TOTP.</span>
            </p>
            <a href="<?= url('/logout') ?>" class="auth-link" style="font-size:0.85rem;">
                Annuler et se déconnecter
            </a>
        </div>

    </div>
</div>

<?php if ($hasWebAuthn): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const isSecure = window.isSecureContext && typeof navigator.credentials !== 'undefined';
    if (isSecure) {
        document.getElementById('webauthn-login-container').style.display = 'block';
    }
});

function base64urlToArrayBuffer(base64url) {
    let padding = '='.repeat((4 - base64url.length % 4) % 4);
    let base64 = (base64url + padding).replace(/\-/g, '+').replace(/_/g, '/');
    let raw = window.atob(base64);
    let array = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) {
        array[i] = raw.charCodeAt(i);
    }
    return array.buffer;
}

function arrayBufferToBase64(buffer) {
    let binary = '';
    let bytes = new Uint8Array(buffer);
    let len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

async function startWebAuthnLogin() {
    const errorDiv = document.getElementById('webauthn-login-error');
    const loginBtn = document.getElementById('btn-webauthn-login');

    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    loginBtn.disabled = true;
    loginBtn.textContent = 'Connexion à la clé...';

    try {
        const response = await fetch('<?= url("/login/webauthn/assertion-options") ?>');
        const options = await response.json();

        if (options.error) {
            throw new Error(options.error);
        }

        options.publicKey.challenge = base64urlToArrayBuffer(options.publicKey.challenge);
        if (options.publicKey.allowCredentials) {
            options.publicKey.allowCredentials.forEach(cred => {
                cred.id = base64urlToArrayBuffer(cred.id);
            });
        }

        const assertion = await navigator.credentials.get({
            publicKey: options.publicKey
        });

        if (!assertion) {
            throw new Error("Échec de la communication avec l'authentificateur.");
        }

        const clientDataJSON = arrayBufferToBase64(assertion.response.clientDataJSON);
        const authenticatorData = arrayBufferToBase64(assertion.response.authenticatorData);
        const signature = arrayBufferToBase64(assertion.response.signature);

        const payload = {
            id: assertion.id,
            response: {
                clientDataJSON: clientDataJSON,
                authenticatorData: authenticatorData,
                signature: signature
            }
        };

        const verifyResponse = await fetch('<?= url("/login/webauthn/verify") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await verifyResponse.json();
        if (result.success && result.redirect_url) {
            window.location.href = result.redirect_url;
        } else {
            throw new Error(result.error || "La validation de la signature a échoué.");
        }

    } catch (err) {
        console.error(err);
        let friendlyMessage = err.message;
        if (err.name === 'NotAllowedError') {
            friendlyMessage = "La demande de connexion a été annulée ou a expiré.";
        }
        errorDiv.textContent = friendlyMessage || "Une erreur inconnue est survenue.";
        errorDiv.style.display = 'block';
        loginBtn.disabled = false;
        loginBtn.textContent = 'Clé de sécurité ou Biométrie';
    }
}
</script>
<?php endif; ?>
