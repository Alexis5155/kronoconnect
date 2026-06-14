<nav class="krono-breadcrumb" style="margin-bottom:1rem;" aria-label="Fil d'Ariane">
    <a href="<?= url('/admin') ?>"><i class="bi bi-house-fill"></i></a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <a href="<?= url('/admin/clients') ?>">Clients SSO</a>
    <span class="krono-breadcrumb__sep"><i class="bi bi-chevron-right"></i></span>
    <span class="krono-breadcrumb__current">Gérer les accès</span>
</nav>

<div class="page-header" style="margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem;">
    <?php if (!empty($client['app_icon'])): ?>
        <div style="width:48px; height:48px; border-radius:8px; background:var(--krono-surface-2); display:flex; align-items:center; justify-content:center; color:var(--krono-accent); font-size:1.5rem;">
            <i class="bi bi-<?= e($client['app_icon']) ?>"></i>
        </div>
    <?php else: ?>
        <div style="width:48px; height:48px; border-radius:8px; background:var(--krono-surface-2); display:flex; align-items:center; justify-content:center; color:var(--krono-text-3); font-size:1.5rem;">
            <i class="bi bi-app-indicator"></i>
        </div>
    <?php endif; ?>
    
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;margin:0;">Accès : <?= e($client['app_name'] ?: $client['name']) ?></h1>
        <p style="color:var(--krono-text-3);margin:.25rem 0 0;font-size:.9rem;">
            Définissez qui peut se connecter à cette application.
        </p>
    </div>
</div>

<div class="krono-grid-2">
    <!-- Colonne de gauche : Mode d'accès -->
    <div>
        <div class="fade-in-up anim-delay-1 glass-card" style="padding:1.5rem;">
            <h2 class="krono-section-title" style="margin-top:0;"><i class="bi bi-shield-lock"></i> Mode d'accès</h2>
            
            <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-mode') ?>">
                <?= csrf() ?>
                
                <div style="display:flex; flex-direction:column; gap:1rem; margin-top:1rem;">
                    
                    <label class="krono-radio-card <?= $accessMode === 'open' ? 'active' : '' ?>">
                        <input type="radio" name="access_mode" value="open" <?= $accessMode === 'open' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <div class="krono-radio-card__icon"><i class="bi bi-globe"></i></div>
                        <div class="krono-radio-card__text">
                            <strong>Ouvert (Implicite)</strong>
                            <span>Tous les utilisateurs actifs peuvent se connecter. Pas d'écran de consentement.</span>
                        </div>
                    </label>

                    <label class="krono-radio-card <?= $accessMode === 'group' ? 'active' : '' ?>">
                        <input type="radio" name="access_mode" value="group" <?= $accessMode === 'group' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <div class="krono-radio-card__icon"><i class="bi bi-collection"></i></div>
                        <div class="krono-radio-card__text">
                            <strong>Par Groupe</strong>
                            <span>L'accès est restreint aux membres des groupes autorisés.</span>
                        </div>
                    </label>

                    <label class="krono-radio-card <?= $accessMode === 'manual' ? 'active' : '' ?>">
                        <input type="radio" name="access_mode" value="manual" <?= $accessMode === 'manual' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <div class="krono-radio-card__icon"><i class="bi bi-person-lock"></i></div>
                        <div class="krono-radio-card__text">
                            <strong>Manuel (Individuel)</strong>
                            <span>Accès strictement contrôlé. Il faut autoriser chaque utilisateur un par un.</span>
                        </div>
                    </label>

                </div>
            </form>
        </div>
    </div>

    <!-- Colonne de droite : Liste (Groupes ou Utilisateurs) -->
    <div>
        <?php if ($accessMode === 'open'): ?>
            <div class="fade-in-up anim-delay-2 glass-card" style="padding:2rem; text-align:center; color:var(--krono-text-3);">
                <i class="bi bi-unlock" style="font-size:2.5rem; color:var(--krono-success); margin-bottom:1rem; display:block;"></i>
                <h3 style="color:var(--krono-text); margin-bottom:0.5rem;">Accès public ouvert</h3>
                <p style="font-size:0.9rem; max-width:300px; margin:0 auto;">Aucune restriction n'est appliquée.<br>Tout utilisateur avec un compte KronoConnect valide peut accéder à cette application.</p>
            </div>
        <?php elseif ($accessMode === 'manual'): ?>
            <div class="fade-in-up anim-delay-3 glass-card" style="padding:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <h2 class="krono-section-title" style="margin:0;"><i class="bi bi-people"></i> Utilisateurs autorisés</h2>
                    <button type="button" class="btn-krono btn-krono--primary btn-krono--sm" onclick="document.getElementById('modalAddUser').classList.add('is-open')">
                        <i class="bi bi-plus-lg"></i> Ajouter
                    </button>
                </div>

                <?php if (empty($manualUsers)): ?>
                    <p style="text-align:center; color:var(--krono-text-3); font-size:0.9rem; padding:1rem 0;">Aucun utilisateur n'est autorisé pour le moment.</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <?php foreach ($manualUsers as $u): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; background:var(--krono-surface-2); border-radius:var(--krono-radius);">
                                <div>
                                    <div style="font-weight:600;"><?= e($u['prenom'] . ' ' . $u['nom']) ?></div>
                                    <div style="font-size:0.75rem; color:var(--krono-text-3);"><?= e($u['email']) ?></div>
                                </div>
                                <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-revoke') ?>" style="margin:0;">
                                    <?= csrf() ?>
                                    <input type="hidden" name="type" value="user">
                                    <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-krono btn-krono--danger btn-krono--sm" title="Révoquer l'accès">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($accessMode === 'group'): ?>
            <div class="fade-in-up anim-delay-4 glass-card" style="padding:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <h2 class="krono-section-title" style="margin:0;"><i class="bi bi-collection"></i> Groupes autorisés</h2>
                    <button type="button" class="btn-krono btn-krono--primary btn-krono--sm" onclick="document.getElementById('modalAddGroup').classList.add('active')">
                        <i class="bi bi-plus-lg"></i> Ajouter
                    </button>
                </div>

                <?php if (empty($groupAccess)): ?>
                    <p style="text-align:center; color:var(--krono-text-3); font-size:0.9rem; padding:1rem 0;">Aucun groupe n'est autorisé pour le moment.</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <?php foreach ($groupAccess as $g): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; background:var(--krono-surface-2); border-radius:var(--krono-radius);">
                                <div>
                                    <div style="font-weight:600;"><i class="bi bi-folder2-open" style="margin-right:.4rem; color:var(--krono-text-3);"></i><?= e($g['name']) ?></div>
                                </div>
                                <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-revoke') ?>" style="margin:0;">
                                    <?= csrf() ?>
                                    <input type="hidden" name="type" value="group">
                                    <input type="hidden" name="target_id" value="<?= $g['id'] ?>">
                                    <button type="submit" class="btn-krono btn-krono--danger btn-krono--sm" title="Révoquer l'accès au groupe">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.krono-radio-card {
    display: flex; align-items: center; gap: 1rem;
    padding: 1rem; border: 1.5px solid var(--krono-border-strong);
    border-radius: var(--krono-radius);
    background: var(--krono-surface-2);
    cursor: pointer; transition: all 0.2s ease;
}
.krono-radio-card:hover { border-color: var(--krono-accent-light); }
.krono-radio-card.active { border-color: var(--krono-accent); background: var(--krono-accent-light); }
.krono-radio-card input { display: none; }
.krono-radio-card__icon { font-size: 1.5rem; color: var(--krono-text-2); }
.krono-radio-card.active .krono-radio-card__icon { color: var(--krono-accent); }
.krono-radio-card__text { display: flex; flex-direction: column; }
.krono-radio-card__text strong { color: var(--krono-text); font-size: 0.95rem; margin-bottom: 0.2rem; }
.krono-radio-card.active .krono-radio-card__text strong { color: var(--krono-accent); }
.krono-radio-card__text span { color: var(--krono-text-3); font-size: 0.8rem; line-height: 1.4; }

/* Modales basiques (on réutilise le style de kConfirmModal) */
.krono-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.krono-modal-backdrop.active { display:flex; animation: fadeInModal 0.2s ease; }
@keyframes fadeInModal { from { opacity:0; } to { opacity:1; } }
</style>

<!-- Modale Ajout Utilisateur -->
<div class="krono-modal-backdrop" id="modalAddUser">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:400px; padding:1.5rem; text-align:left;">
        <h3 style="margin-top:0; margin-bottom:1rem;">Accorder l'accès à un utilisateur</h3>
        <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-grant') ?>">
            <?= csrf() ?>
            <input type="hidden" name="type" value="user">
            
            <div style="margin-bottom:1.5rem;">
                <label class="krono-label">Sélectionner l'utilisateur</label>
                <select name="target_id" class="krono-input" required>
                    <option value="">-- Choisir un utilisateur --</option>
                    <?php foreach ($allUsers as $u): ?>
                        <?php 
                        // Vérifier si déjà accordé pour le désactiver dans la liste
                        $alreadyGranted = false;
                        foreach ($manualUsers as $mu) {
                            if ($mu['id'] == $u['id']) { $alreadyGranted = true; break; }
                        }
                        ?>
                        <option value="<?= $u['id'] ?>" <?= $alreadyGranted ? 'disabled' : '' ?>>
                            <?= e($u['prenom'] . ' ' . $u['nom'] . ' (' . $u['email'] . ')') ?>
                            <?= $alreadyGranted ? ' — Déjà autorisé' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="document.getElementById('modalAddGroup').classList.remove('is-open')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--primary">Accorder</button>
            </div>
        </form>
    </div>
</div>

<!-- Modale Ajout Groupe -->
<div class="krono-modal-backdrop" id="modalAddGroup">
    <div class="glass-card krono-modal-content" style="width:100%; max-width:400px; padding:1.5rem; text-align:left;">
        <h3 style="margin-top:0; margin-bottom:1rem;">Accorder l'accès à un groupe</h3>
        <form method="POST" action="<?= url('/admin/clients/' . $client['id'] . '/access-grant') ?>">
            <?= csrf() ?>
            <input type="hidden" name="type" value="group">
            
            <div style="margin-bottom:1.5rem;">
                <label class="krono-label">Sélectionner le groupe</label>
                <select name="target_id" class="krono-input" required>
                    <option value="">-- Choisir un groupe --</option>
                    <?php foreach ($allGroups as $g): ?>
                        <?php 
                        $alreadyGranted = false;
                        foreach ($groupAccess as $mg) {
                            if ($mg['id'] == $g['id']) { $alreadyGranted = true; break; }
                        }
                        ?>
                        <option value="<?= $g['id'] ?>" <?= $alreadyGranted ? 'disabled' : '' ?>>
                            <?= e($g['name']) ?>
                            <?= $alreadyGranted ? ' — Déjà autorisé' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:.5rem;">
                <button type="button" class="btn-krono btn-krono--ghost" onclick="document.getElementById('modalAddGroup').classList.remove('is-open')">Annuler</button>
                <button type="submit" class="btn-krono btn-krono--primary">Accorder</button>
            </div>
        </form>
    </div>
</div>
