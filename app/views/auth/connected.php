<?php
$authUser = auth();
$items = $items ?? [];
?>

<div class="organic-portal">
    <!-- Hero Section Organique -->
    <div class="organic-hero">
        <div class="morph-blob blob-1"></div>
        <div class="morph-blob blob-2"></div>
        
        <div class="hero-content">
            <h1 class="hero-greeting"><?= e(get_dynamic_greeting($authUser['prenom'])) ?></h1>
            <p class="hero-sub"><?= e(setting('portal_hero_sub', 'Accéder à toutes vos applications métier avec un seul compte.')) ?></p>
        </div>
        
        <?php if (setting('logo_uuid')): ?>
            <div class="hero-logo-raw">
                <img src="<?= url('/public/logo') ?>?v=<?= setting('logo_uuid') ?>" alt="Logo <?= e(setting('collectivite', 'Collectivité')) ?>">
            </div>
        <?php endif; ?>
    </div>

    <!-- App Grid -->
    <?php if (empty($items)): ?>
    <div class="organic-empty">
        <div class="empty-glass-icon"><i class="bi bi-wind"></i></div>
        <h3>Aucune application ou lien assigné</h3>
        <p>Contactez votre administrateur pour obtenir des accès.</p>
    </div>
    <?php else: ?>
    <div class="organic-grid" id="portal-sortable">
        <?php foreach ($items as $item): 
            $type = $item['item_type'] ?? 'app';
            
            if ($type === 'app') {
                $uris = json_decode($item['redirect_uris'], true);
                $firstUri = !empty($uris) ? $uris[0] : '';
                $link = url('/sso/authorize') . '?' . http_build_query([
                    'client_id'    => $item['client_id'],
                    'redirect_uri' => $firstUri,
                    'source'       => 'portal'
                ]);
                $name = $item['app_name'] ?: $item['name'];
                $desc = $item['app_description'] ?: 'Application connectée via SSO';
                $icon = $item['app_icon'] ?: 'app-indicator';
                $color = $item['app_color'] ?: '#3b5fc0';
                $id = $item['id'];
                $dataId = $item['client_id'];
            } else {
                $link = $item['url'];
                $name = $item['title'];
                $desc = $item['description'] ?: 'Lien personnalisé';
                $icon = $item['icon'] ?: 'link-45deg';
                $color = $item['color'] ?: '#3b5fc0';
                $id = $item['id'];
                $dataId = $item['id'];
            }

            if (str_starts_with($icon, 'bi-')) $icon = substr($icon, 3);

            $hex = ltrim($color, '#');
            if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
            $rgb = hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
        ?>
        <?php
            $isRestricted = !empty($item['is_ip_restricted']);
            $finalLink = $isRestricted ? 'javascript:void(0)' : $link;
            $restrictedClass = $isRestricted ? 'is-restricted' : '';
            $externalClass = $type === 'link' ? 'is-external-link' : '';
            $onClick = $isRestricted ? 'onclick="if(window.KronoConnectToast){ window.KronoConnectToast({ message: \'Application inaccessible depuis ce réseau\', level: \'danger\', duration: 5000 }); } else { alert(\'Application inaccessible depuis ce réseau\'); }"' : '';
            $targetAttr = ($type === 'link' && !$isRestricted) ? 'target="_blank" rel="noopener noreferrer"' : '';
        ?>
        <a href="<?= e($finalLink) ?>" 
           class="organic-card <?= $externalClass ?> <?= $restrictedClass ?>" 
           data-id="<?= e((string)$dataId) ?>" 
           data-type="<?= e($type) ?>"
           data-internal-id="<?= e((string)$id) ?>"
           style="--app-color: <?= e($color) ?>; --app-color-rgb: <?= $rgb ?>;"
           <?= $targetAttr ?> <?= $onClick ?>>
            
            <div class="card-inner-glow"></div>
                <div class="card-header">
                    <div class="card-icon-wrapper">
                        <i class="bi bi-<?= e($icon) ?>"></i>
                    </div>
                    <div class="card-text-wrapper">
                        <h3 class="card-app-title"><?= e($name) ?></h3>
                        <p class="card-app-desc-short"><?= e(mb_strimwidth($desc, 0, 50, '...')) ?></p>
                    </div>
                    <div class="card-action-indicator">
                        <div class="arrow-circle">
                            <i class="bi bi-<?= $type === 'app' ? 'arrow-right-short' : 'box-arrow-up-right' ?>"></i>
                        </div>
                        <svg class="progress-ring hidden" width="32" height="32" viewBox="0 0 36 36">
                            <circle class="progress-ring__bg" stroke="rgba(var(--app-color-rgb), 0.2)" stroke-width="3" fill="transparent" r="14" cx="18" cy="18"/>
                            <circle class="progress-ring__circle" stroke="var(--app-color)" stroke-width="3" stroke-dasharray="88" stroke-dashoffset="88" fill="transparent" r="14" cx="18" cy="18"/>
                        </svg>
                    </div>
                </div>

                <?php if ($type === 'app'): ?>
                <div class="card-expanded-details">
                    <p class="card-full-desc"><?= e($desc) ?></p>
                    <div class="card-meta-grid">
                        <div class="meta-item">
                            <span class="meta-label">Statut</span>
                            <span class="meta-value status-badge"><i class="bi bi-circle-fill"></i> <span class="status-text">...</span></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Première co.</span>
                            <span class="meta-value first-login">...</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Dernière co.</span>
                            <span class="meta-value last-login">...</span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card-expanded-details">
                    <p class="card-full-desc"><?= e($desc) ?></p>
                    <div style="margin-top: 1rem; font-size: 0.75rem; color: var(--krono-text-3); display: flex; align-items: center; gap: 0.4rem;">
                        <i class="bi bi-link-45deg"></i>
                        <?= e(parse_url($link, PHP_URL_HOST) ?: $link) ?>
                    </div>
                </div>
                <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    
    <div class="edit-mode-container" style="display: flex; justify-content: center; margin-top: 3rem; margin-bottom: 1rem;">
        <button id="toggle-edit-mode" style="background: transparent; border: none; color: var(--krono-text-3); font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem; opacity: 0.7; transition: all 0.3s;" onmouseover="this.style.opacity='1'; this.style.color='var(--krono-text-1)';" onmouseout="this.style.opacity='0.7'; this.style.color='var(--krono-text-3)';">
            <i class="bi bi-pencil"></i> Réorganiser l'affichage
        </button>
        <div id="edit-mode-actions" style="display: none; gap: 0.5rem;">
            <button id="cancel-edit-mode" class="btn-krono btn-krono--ghost" style="font-size: 0.85rem; padding: 0.4rem 1rem; color: var(--krono-text-2);">Annuler</button>
            <button id="save-edit-mode" class="btn-krono btn-krono--primary" style="font-size: 0.85rem; padding: 0.4rem 1rem;">
                <i class="bi bi-check-lg"></i> Enregistrer
            </button>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let isDragging = false;
    let editMode = false;
    let sortableInstance = null;

    // UI Elements
    const grid = document.getElementById('portal-sortable');
    const toggleEditBtn = document.getElementById('toggle-edit-mode');
    const editActions = document.getElementById('edit-mode-actions');
    const cancelEditBtn = document.getElementById('cancel-edit-mode');
    const saveEditBtn = document.getElementById('save-edit-mode');

    // 1. Initialisation de Sortable (Draggable)
    if (grid) {
        try {
            sortableInstance = new Sortable(grid, {
                animation: 150,
                disabled: true,
                ghostClass: 'organic-card--ghost',
                onStart: function() {
                    isDragging = true;
                    // Fermer toutes les cartes ouvertes au début du drag
                    document.querySelectorAll('.organic-card.expanded').forEach(c => c.classList.remove('expanded'));
                },
                onEnd: function() {
                    isDragging = false;
                }
            });
        } catch (e) {
            console.error("Sortable initialization failed:", e);
        }

        if (toggleEditBtn) {
            toggleEditBtn.addEventListener('click', () => {
                editMode = true;
                if (sortableInstance) sortableInstance.option('disabled', false);
                toggleEditBtn.style.display = 'none';
                editActions.style.display = 'flex';
            });

            cancelEditBtn.addEventListener('click', () => {
                window.location.reload();
            });

            saveEditBtn.addEventListener('click', () => {
                const order = [];
                grid.querySelectorAll('.organic-card').forEach((el, index) => {
                    order.push({
                        type: el.dataset.type,
                        id: el.dataset.id,
                        position: index
                    });
                });
                
                saveEditBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>...';
                saveEditBtn.disabled = true;

                fetch('<?= url('/profile/portal-order') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ order: order })
                })
                .then(async r => {
                    const data = await r.json();
                    if (r.ok && data.success) {
                        if (window.KronoConnectToast) {
                            window.KronoConnectToast({ message: 'Ordre du portail mis à jour', level: 'success', duration: 3000 });
                        }
                        editMode = false;
                        if (sortableInstance) sortableInstance.option('disabled', true);
                        toggleEditBtn.style.display = 'inline-block';
                        editActions.style.display = 'none';
                        saveEditBtn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';
                        saveEditBtn.disabled = false;
                    } else {
                        throw new Error(data.error || 'Erreur lors de la sauvegarde');
                    }
                })
                .catch(err => {
                    console.error("Failed to save portal order:", err);
                    if (window.KronoConnectToast) {
                        window.KronoConnectToast({ message: 'Erreur de sauvegarde: ' + err.message, level: 'danger', duration: 5000 });
                    }
                    saveEditBtn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer';
                    saveEditBtn.disabled = false;
                });
            });
        }
    }

    // 2. Gestion de l'interactivité des cartes (Animations & Details)
    const cards = document.querySelectorAll('.organic-card');
    
    // Fermer les cartes au clic à l'extérieur (pour le mobile)
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.organic-card')) {
            document.querySelectorAll('.organic-card.expanded').forEach(c => c.classList.remove('expanded'));
        }
    });

    cards.forEach(card => {
        let hoverTimeout;
        let hasFetched = false;
        const isApp = card.dataset.type === 'app';

        // Gérer le clic pour le mobile et bloquer en mode édition
        card.addEventListener('click', (e) => {
            if (editMode) {
                e.preventDefault();
                return;
            }

            if (window.matchMedia("(hover: none), (pointer: coarse)").matches) {
                if (!card.classList.contains('expanded')) {
                    e.preventDefault();
                    
                    document.querySelectorAll('.organic-card.expanded').forEach(c => {
                        if (c !== card) c.classList.remove('expanded');
                    });

                    card.classList.add('expanded');

                    if (isApp && !hasFetched) {
                        const internalId = card.dataset.internalId;
                        fetch('<?= url('/portal/app-details') ?>?id=' + internalId)
                            .then(r => r.json())
                            .then(data => {
                                const statusBadge = card.querySelector('.status-badge');
                                const statusText = card.querySelector('.status-text');
                                if (statusBadge && statusText) {
                                    statusText.textContent = data.status === 'online' ? 'En ligne' : 'Hors ligne';
                                    statusBadge.classList.toggle('online', data.status === 'online');
                                    statusBadge.classList.toggle('offline', data.status !== 'online');
                                }
                                const firstLogin = card.querySelector('.first-login');
                                const lastLogin = card.querySelector('.last-login');
                                if (firstLogin) firstLogin.textContent = data.first_login;
                                if (lastLogin) lastLogin.textContent = data.last_login;
                                
                                if (data.description) {
                                    const fullDesc = card.querySelector('.card-full-desc');
                                    if (fullDesc) fullDesc.textContent = data.description;
                                }
                                hasFetched = true;
                            }).catch(err => console.error('Fetch error:', err));
                    }
                }
            }
        });

        card.addEventListener('mouseenter', () => {
            if (isDragging || editMode) return;
            if (window.matchMedia("(hover: none), (pointer: coarse)").matches) return;

            const arrow = card.querySelector('.arrow-circle');
            const ring = card.querySelector('.progress-ring');
            const circle = card.querySelector('.progress-ring__circle');
            
            if (ring && circle) {
                circle.style.transition = 'none';
                const radius = circle.r.baseVal.value;
                const circumference = radius * 2 * Math.PI;
                circle.style.strokeDasharray = `${circumference} ${circumference}`;
                circle.style.strokeDashoffset = circumference;
                
                arrow.classList.add('hidden');
                ring.classList.remove('hidden');
                
                void circle.offsetWidth;
                
                circle.style.transition = 'stroke-dashoffset 0.6s linear';
                circle.style.strokeDashoffset = '0';
                
                hoverTimeout = setTimeout(() => {
                    if (isDragging || editMode) return;
                    card.classList.add('expanded');
                    ring.classList.add('hidden');

                    if (isApp && !hasFetched) {
                        const internalId = card.dataset.internalId;
                        fetch('<?= url('/portal/app-details') ?>?id=' + internalId)
                            .then(r => r.json())
                            .then(data => {
                                if (!card.matches(':hover') && !card.classList.contains('expanded')) return;
                                
                                const statusBadge = card.querySelector('.status-badge');
                                const statusText = card.querySelector('.status-text');
                                if (statusBadge && statusText) {
                                    statusText.textContent = data.status === 'online' ? 'En ligne' : 'Hors ligne';
                                    statusBadge.classList.toggle('online', data.status === 'online');
                                    statusBadge.classList.toggle('offline', data.status !== 'online');
                                }
                                const firstLogin = card.querySelector('.first-login');
                                const lastLogin = card.querySelector('.last-login');
                                if (firstLogin) firstLogin.textContent = data.first_login;
                                if (lastLogin) lastLogin.textContent = data.last_login;
                                
                                if (data.description) {
                                    const fullDesc = card.querySelector('.card-full-desc');
                                    if (fullDesc) {
                                        fullDesc.textContent = data.description;
                                    }
                                }
                                hasFetched = true;
                            }).catch(err => console.error('Fetch error:', err));
                    }
                }, 600);
            }
        });
        
        card.addEventListener('mouseleave', () => {
            if (hoverTimeout) clearTimeout(hoverTimeout);
            card.classList.remove('expanded');
            
            const arrow = card.querySelector('.arrow-circle');
            const ring = card.querySelector('.progress-ring');
            const circle = card.querySelector('.progress-ring__circle');
            
            if (ring && circle) {
                ring.classList.add('hidden');
                arrow.classList.remove('hidden');
                circle.style.transition = 'none';
                const radius = circle.r.baseVal.value;
                circle.style.strokeDashoffset = radius * 2 * Math.PI;
            }
        });
    });
});
</script>

<style>
.organic-card--ghost { opacity: 0.4; transform: scale(0.95); }

/* Harmonisation de l'expansion pour TOUTES les cartes */
.organic-card .card-expanded-details {
    display: block; /* Toujours block pour permettre l'animation max-height */
}

/* Les liens custom ne doivent pas afficher les détails par défaut */
.organic-card.is-external-link:not(.expanded) .card-expanded-details {
    max-height: 0;
    opacity: 0;
    margin-top: 0;
    padding-top: 0;
    overflow: hidden;
}

.organic-card.is-external-link.expanded .card-expanded-details {
    max-height: 100px;
    opacity: 1;
    margin-top: 1rem;
}

.organic-card.is-restricted {
    opacity: 0.6;
    filter: grayscale(0.8);
    cursor: not-allowed;
}
.organic-card.is-restricted .card-icon-wrapper i {
    opacity: 0.5;
}
.organic-card.is-restricted::after {
    content: "\F47A";
    font-family: bootstrap-icons !important;
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.2rem;
    color: var(--krono-text-3);
    opacity: 0.8;
}

/* ── Hero Logo Community (Option 2 sans cadre) ── */
.organic-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}
.hero-logo-raw {
    z-index: 2;
    flex-shrink: 0;
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.hero-logo-raw img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    /* Ombre portée douce qui suit les contours transparents du logo (PNG) */
    filter: drop-shadow(0 8px 20px rgba(0, 0, 0, 0.08));
}
[data-theme="dark"] .hero-logo-raw img {
    filter: drop-shadow(0 8px 20px rgba(0, 0, 0, 0.35));
}
.hero-logo-raw:hover {
    transform: translateY(-4px) scale(1.08);
}
@media (max-width: 768px) {
    .hero-logo-raw {
        position: absolute;
        bottom: -1.5rem;
        right: -1rem;
        width: 140px;
        height: 140px;
        opacity: 0.06;
        z-index: 1;
        pointer-events: none;
        transform: rotate(-10deg);
        transition: none;
    }
    [data-theme="dark"] .hero-logo-raw {
        opacity: 0.12;
    }
    .hero-logo-raw img {
        filter: none !important;
    }
    .hero-logo-raw:hover {
        transform: rotate(-10deg) !important;
    }
}
</style>

