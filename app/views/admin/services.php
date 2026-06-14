<?php
/** @var array $tree */
?>

<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Services & Structure</span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Gestion des Services</h1>
        <p class="page-header__subtitle">Organisez la structure de votre collectivité ou entreprise.</p>
    </div>
    <button class="btn-krono btn-krono--primary" onclick="document.getElementById('modal-add-service').classList.add('is-open')">
        <i class="bi bi-plus-lg"></i> Nouveau service
    </button>
</div>

<div class="fade-in-up anim-delay-1 glass-card" style="padding: 1.5rem; margin-bottom: 2rem;">
    <h3 class="krono-section-title" style="margin-top:0;"><i class="bi bi-diagram-3" style="margin-right:.5rem; color:var(--krono-accent);"></i> Arborescence des services</h3>
    <p style="font-size:0.8rem; color:var(--krono-text-3); margin-bottom:1.5rem;">
        Utilisez les poignées <i class="bi bi-grip-vertical"></i> pour réorganiser les services. 
        Vous pouvez glisser un service à l'intérieur d'un autre pour créer une hiérarchie.
    </p>

    <div id="services-tree-container">
        <?php
        if (!function_exists('renderServiceTree')) {
            function renderServiceTree(array $nodes, $parentId = null) {
                echo '<ul class="service-nestable" data-parent="' . ($parentId ?: 'root') . '">';
                foreach ($nodes as $node) {
                    ?>
                    <li class="service-node" data-id="<?= $node['id'] ?>">
                        <div class="service-item">
                            <div class="service-item__main">
                                <i class="bi bi-grip-vertical service-grip"></i>
                                <span class="service-label"><?= e($node['name']) ?></span>
                            </div>
                            <div class="service-item__actions">
                                <button type="button" class="btn-icon" 
                                        onclick="openEditModal(<?= $node['id'] ?>, '<?= e($node['name']) ?>', '<?= $node['parent_id'] ?>')"
                                        title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="<?= url('/admin/services/delete') ?>" onsubmit="return confirm('Supprimer ce service et tous ses sous-services ?')" style="margin:0;">
                                    <?= csrf() ?>
                                    <input type="hidden" name="id" value="<?= $node['id'] ?>">
                                    <button type="submit" class="btn-icon danger" title="Supprimer"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <?php renderServiceTree($node['children'] ?? [], $node['id']); ?>
                    </li>
                    <?php
                }
                echo '</ul>';
            }
        }

        if (empty($tree)) {
            echo '<div style="text-align:center; padding:3rem; color:var(--krono-text-3); font-size:0.85rem;">Aucun service défini.</div>';
        } else {
            renderServiceTree($tree);
        }
        ?>
    </div>
</div>

<!-- Modal Ajout -->
<div class="krono-modal-backdrop" id="modal-add-service">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:450px; padding:1.5rem; text-align:left;">
        <h3 style="margin-top:0; margin-bottom:1.5rem;">Nouveau service</h3>
        <form method="POST" action="<?= url('/admin/services') ?>">
            <?= csrf() ?>
            <div style="display:flex; flex-direction:column; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <label class="krono-label">Nom du service</label>
                    <input type="text" name="name" class="krono-input" placeholder="Ex: Direction du Numérique" required>
                </div>
                <div>
                    <label class="krono-label">Service parent (optionnel)</label>
                    <select name="parent_id" class="krono-input">
                        <option value="">-- Aucun (Racine) --</option>
                        <?php
                        if (!function_exists('flatServices')) {
                            function flatServices(array $nodes, $prefix = '') {
                                $res = [];
                                foreach ($nodes as $n) {
                                    $res[] = ['id' => $n['id'], 'name' => $prefix . $n['name']];
                                    if (!empty($n['children'])) {
                                        $res = array_merge($res, flatServices($n['children'], $prefix . '   '));
                                    }
                                }
                                return $res;
                            }
                        }
                        $flat = flatServices($tree);
                        foreach ($flat as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="document.getElementById('modal-add-service').classList.remove('is-open')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<style>
.service-nestable {
    list-style: none;
    padding: 0;
    margin: 0;
}
.service-nestable .service-nestable {
    padding-left: 2rem;
    border-left: 2px solid var(--krono-border-light);
    margin-top: 4px;
}
/* Par défaut, les listes vides ne prennent aucun espace */
.service-nestable .service-nestable:empty {
    margin-top: 0;
    min-height: 0;
}
.service-node {
    margin-bottom: 4px;
}
.service-node:last-child {
    margin-bottom: 0;
}

/* ── Mode Drag & Drop ── */
/* Lorsqu'on déplace un élément, on ouvre les listes vides pour pouvoir y déposer un sous-service */
body.is-dragging-service .service-nestable .service-nestable:empty {
    margin-top: 4px;
    min-height: 42px;
    background: rgba(var(--krono-accent-rgb), 0.05);
    border: 1px dashed var(--krono-accent);
    border-radius: 12px;
    position: relative;
}
body.is-dragging-service .service-nestable .service-nestable:empty::after {
    content: 'Glisser ici';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.7rem;
    color: var(--krono-accent);
    font-weight: 600;
    pointer-events: none;
}

.service-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 1rem;
    background: var(--krono-surface);
    border: 1px solid var(--krono-border);
    border-radius: 12px;
    transition: all 0.2s ease;
}
.service-item:hover {
    border-color: var(--krono-accent);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.service-item__main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.service-grip {
    cursor: grab;
    color: var(--krono-text-3);
    font-size: 1.1rem;
}
.service-grip:active { cursor: grabbing; }
.service-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--krono-text);
}
.service-item__actions {
    opacity: 0;
    transition: opacity 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.service-item:hover .service-item__actions {
    opacity: 1;
}

.btn-icon {
    background: transparent;
    border: none;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--krono-text-3);
    transition: all 0.2s ease;
}
.btn-icon:hover { background: var(--krono-surface-3); color: var(--krono-text); }
.btn-icon.danger:hover { background: var(--krono-danger-light); color: var(--krono-danger); }

/* Sortable ghost class */
.sortable-ghost {
    opacity: 0.4;
    background: var(--krono-accent-light) !important;
    border: 2px dashed var(--krono-accent) !important;
}

/* Masquer la zone de dépôt INTERNE à l'élément qu'on déplace */
.service-node.sortable-chosen > .service-nestable:empty {
    display: none !important;
}
</style>

<!-- Modal Édition -->
<div class="krono-modal-backdrop" id="modal-edit-service">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:450px; padding:1.5rem; text-align:left;">
        <h3 style="margin-top:0; margin-bottom:1.5rem;">Modifier le service</h3>
        <form method="POST" action="<?= url('/admin/services/update') ?>">
            <?= csrf() ?>
            <input type="hidden" name="id" id="edit-service-id">
            <div style="display:flex; flex-direction:column; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <label class="krono-label">Nom du service</label>
                    <input type="text" name="name" id="edit-service-name" class="krono-input" required>
                </div>
                <div>
                    <label class="krono-label">Service parent</label>
                    <select name="parent_id" id="edit-service-parent" class="krono-input">
                        <option value="">-- Aucun (Racine) --</option>
                        <?php 
                        $flat = flatServices($tree);
                        foreach ($flat as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="document.getElementById('modal-edit-service').classList.remove('is-open')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
function openEditModal(id, name, parentId) {
    document.getElementById('edit-service-id').value = id;
    document.getElementById('edit-service-name').value = name;
    
    const parentSelect = document.getElementById('edit-service-parent');
    parentSelect.value = parentId || "";
    
    // Masquer l'option du service lui-même pour éviter l'auto-parenté
    Array.from(parentSelect.options).forEach(opt => {
        opt.disabled = (opt.value == id);
    });
    
    document.getElementById('modal-edit-service').classList.add('is-open');
}

document.addEventListener('DOMContentLoaded', () => {
    const containers = document.querySelectorAll('.service-nestable');
    
    containers.forEach(container => {
        new Sortable(container, {
            group: 'services',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            handle: '.service-grip',
            ghostClass: 'sortable-ghost',
            onStart: function (evt) {
                document.body.classList.add('is-dragging-service');
            },
            onEnd: function (evt) {
                document.body.classList.remove('is-dragging-service');
                saveOrder();
            }
        });
    });

    function saveOrder() {
        const orders = [];
        
        function processList(ul, parentId = null) {
            const items = ul.querySelectorAll(':scope > .service-node');
            items.forEach((li, index) => {
                const id = li.dataset.id;
                orders.push({
                    id: id,
                    parent_id: parentId,
                    position: index
                });
                const subUl = li.querySelector(':scope > .service-nestable');
                if (subUl) {
                    processList(subUl, id);
                }
            });
        }

        const rootUl = document.querySelector('#services-tree-container > .service-nestable');
        if (rootUl) processList(rootUl);

        fetch('<?= url('/admin/services/order') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= \KronoConnect\Core\Security::csrfToken() ?>'
            },
            body: JSON.stringify({ orders: orders })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Ordre sauvegardé');
            }
        });
    }
});
</script>
