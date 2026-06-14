<?php
/** @var array $codes */
?>

<div class="organic-profile-container" style="max-width: 600px; opacity: 0; animation: fadeSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;">
    <div class="organic-glass-panel content-panel" style="min-height: auto;">
        
        <header class="tab-header" style="margin-bottom: 2rem;">
            <div class="tab-header-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;"><i class="bi bi-shield-lock-fill"></i></div>
            <div>
                <h2 class="tab-title">Codes de secours générés</h2>
                <p class="tab-subtitle">Conservez ces clés de récupération en lieu sûr.</p>
            </div>
        </header>

        <div style="background: rgba(245, 158, 11, 0.06); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; display: flex; gap: 0.75rem; align-items: flex-start;">
            <i class="bi bi-exclamation-triangle-fill" style="color: #F59E0B; font-size: 1.2rem; flex-shrink: 0; margin-top: 0.1rem;"></i>
            <div style="font-size: 0.85rem; color: var(--krono-text-2); line-height: 1.45;">
                Ces codes ne s'afficheront <strong>qu'une seule fois</strong>. Conservez-les en lieu sûr — ils sont votre filet de sécurité si vous perdez l'accès à votre application MFA.
            </div>
        </div>

        <!-- Codes Grid -->
        <div style="background: var(--krono-surface-2); border: 1px solid var(--krono-border); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; font-family: monospace; font-size: 1.1rem; font-weight: 700; text-align: center; letter-spacing: 1px;">
                <?php foreach ($codes as $c): ?>
                    <div style="padding: 0.6rem; background: var(--krono-surface); border: 1px solid var(--krono-border); border-radius: 8px; color: var(--krono-text);">
                        <?= e($c) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 1.25rem; display: flex; justify-content: center; gap: 1rem;">
                <button type="button" class="organic-btn" onclick="copyCodes()" style="display: inline-flex; align-items: center; gap: 0.5rem; background: var(--krono-surface-3); border: 1px solid var(--krono-border); color: var(--krono-text); font-size: 0.85rem; padding: 0.5rem 1.2rem; cursor: pointer; margin: 0;">
                    <i class="bi bi-clipboard"></i> Copier
                </button>
                <button type="button" class="organic-btn" onclick="printCodes()" style="display: inline-flex; align-items: center; gap: 0.5rem; background: var(--krono-surface-3); border: 1px solid var(--krono-border); color: var(--krono-text); font-size: 0.85rem; padding: 0.5rem 1.2rem; cursor: pointer; margin: 0;">
                    <i class="bi bi-printer"></i> Imprimer
                </button>
            </div>
        </div>

        <!-- Info: where to use recovery codes -->
        <div style="background: rgba(99, 102, 241, 0.06); border: 1px solid rgba(99, 102, 241, 0.18); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.75rem; display: flex; gap: 0.7rem; align-items: flex-start;">
            <i class="bi bi-info-circle-fill" style="color: var(--krono-accent); font-size: 1rem; flex-shrink: 0; margin-top: 0.15rem;"></i>
            <div style="font-size: 0.82rem; color: var(--krono-text-2); line-height: 1.45;">
                Lors de votre prochaine connexion, si vous ne pouvez pas générer un code MFA, entrez l'un de ces codes à la place dans le champ de vérification. <strong>Chaque code est à usage unique</strong> et sera invalidé après utilisation.
            </div>
        </div>

        <div style="display: flex; justify-content: center; border-top: 1px solid var(--krono-border); padding-top: 1.5rem;">
            <a href="<?= url('/profile') ?>" class="organic-btn primary" style="text-decoration: none; padding: 0.75rem 2rem;">
                J'ai sauvegardé les codes
            </a>
        </div>

    </div>
</div>

<script>
function copyCodes() {
    const codes = <?= json_encode($codes) ?>;
    const text = codes.join("\n");
    
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        navigator.clipboard.writeText(text).then(() => {
            window.kronoToast({ message: "Codes copiés dans le presse-papiers !", level: "success", duration: 2500 });
        }).catch(err => {
            fallbackCopyText(text);
        });
    } else {
        fallbackCopyText(text);
    }
}

function fallbackCopyText(text) {
    try {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        
        textArea.style.position = "fixed";
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.width = "2em";
        textArea.style.height = "2em";
        textArea.style.padding = "0";
        textArea.style.border = "none";
        textArea.style.outline = "none";
        textArea.style.boxShadow = "none";
        textArea.style.background = "transparent";
        
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        const successful = document.execCommand('copy');
        document.body.removeChild(textArea);
        
        if (successful) {
            window.kronoToast({ message: "Codes copiés dans le presse-papiers !", level: "success", duration: 2500 });
        } else {
            window.kronoToast({ message: "Erreur lors de la copie", level: "danger" });
        }
    } catch (err) {
        window.kronoToast({ message: "Erreur lors de la copie", level: "danger" });
    }
}


function printCodes() {
    const codes = <?= json_encode($codes) ?>;
    const codesHtml = codes.map(function(c) {
        return '<div class="code">' + c + '</div>';
    }).join('');
    const dateStr = new Date().toLocaleString();
    const printWindow = window.open('', '_blank');
    printWindow.document.write(
        '<html>' +
        '<head>' +
            '<title>Codes de secours MFA</title>' +
            '<style>' +
                'body { font-family: sans-serif; padding: 40px; text-align: center; }' +
                'h1 { font-size: 24px; margin-bottom: 10px; }' +
                'p { color: #666; margin-bottom: 30px; font-size: 14px; }' +
                '.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; max-width: 400px; margin: 0 auto; font-family: monospace; font-size: 18px; font-weight: bold; }' +
                '.code { padding: 10px; border: 1px solid #ccc; border-radius: 6px; background: #f9f9f9; }' +
                '.footer { margin-top: 40px; font-size: 12px; color: #999; }' +
            '</style>' +
        '</head>' +
        '<body>' +
            '<h1>Codes de secours MFA</h1>' +
            '<p>Conservez ces codes en lieu s\u00fbr. Chaque code est \u00e0 usage unique.</p>' +
            '<div class="grid">' + codesHtml + '</div>' +
            '<div class="footer">G\u00e9n\u00e9r\u00e9 le ' + dateStr + '</div>' +
            '<script>window.onload = function() { window.print(); window.close(); };<\/script>' +
        '</body>' +
        '</html>'
    );
    printWindow.document.close();
}
</script>
