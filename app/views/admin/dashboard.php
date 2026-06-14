<?php
/**
 * Admin — Tableau de bord
 * Variables : $totalUsers, $activeUsers, $pendingUsers, $totalClients, $totalGroups, $todayLogs, $connectionHistory, $recentLogs, $serverInfo, $appConfig
 */
$version = $appConfig['version'] ?? '1.0.0';
$isDebug = (bool) ($appConfig['debug'] ?? false);
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Administration</h1>
        <p class="page-header__subtitle">Vue d'ensemble de KronoConnect</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/admin/users') ?>" class="btn-krono btn-krono--primary">
            <i class="bi bi-people-fill"></i> Gérer les utilisateurs
        </a>
    </div>
</div>

<!-- ══ STAT CARDS ═══════════════════════════════════════════════ -->
<div class="adm-stats">

    <div class="adm-stat-card glass-card fade-in-up anim-delay-1">
        <div class="adm-stat-card__icon" style="background:var(--krono-accent-gradient);">
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="adm-stat-card__body">
            <div class="adm-stat-card__value"><?= number_format((int)$totalUsers) ?></div>
            <div class="adm-stat-card__label">Utilisateurs au total</div>
        </div>
    </div>

    <div class="adm-stat-card glass-card fade-in-up anim-delay-2">
        <div class="adm-stat-card__icon" style="background:linear-gradient(135deg,#16a34a,#22c55e);">
            <i class="bi bi-person-check-fill"></i>
        </div>
        <div class="adm-stat-card__body">
            <div class="adm-stat-card__value"><?= number_format((int)$activeUsers) ?></div>
            <div class="adm-stat-card__label">Comptes actifs</div>
        </div>
    </div>

    <div class="adm-stat-card glass-card fade-in-up anim-delay-3">
        <div class="adm-stat-card__icon" style="background:linear-gradient(135deg,#d97706,#f59e0b);">
            <i class="bi bi-app-indicator"></i>
        </div>
        <div class="adm-stat-card__body">
            <div class="adm-stat-card__value"><?= number_format((int)$totalClients) ?></div>
            <div class="adm-stat-card__label">Clients SSO</div>
        </div>
    </div>

    <div class="adm-stat-card glass-card fade-in-up anim-delay-4">
        <div class="adm-stat-card__icon" style="background:linear-gradient(135deg,#64748b,#94a3b8);">
            <i class="bi bi-activity"></i>
        </div>
        <div class="adm-stat-card__body">
            <div class="adm-stat-card__value"><?= number_format((int)$todayLogs) ?></div>
            <div class="adm-stat-card__label">Connexions aujourd'hui</div>
        </div>
    </div>

</div>

<!-- ══ GRILLE PRINCIPALE ═════════════════════════════════════════ -->
<div class="adm-grid">

    <!-- Colonne principale -->
    <div class="adm-col-main">

        <!-- Graphique des connexions -->
        <div class="glass-card fade-in-up anim-delay-5">
            <div class="adm-section-title" style="margin-top:0;">
                <i class="bi bi-graph-up-arrow" style="color:var(--krono-accent);"></i>
                Activité SSO (7 derniers jours)
            </div>
            <div style="position: relative; height: 250px; width: 100%; padding: 0 1.5rem 1.5rem 1.5rem; box-sizing: border-box;">
                <canvas id="connectionsChart"></canvas>
            </div>
        </div>

        <!-- Informations Système -->
        <div class="glass-card fade-in-up anim-delay-6">
            <div class="adm-section-title">
                <i class="bi bi-cpu-fill" style="color:var(--krono-accent);"></i>
                Informations Système
            </div>
            <div class="adm-sysinfo-grid">
                <div class="adm-sysinfo-item">
                    <span class="adm-sysinfo-item__key">PHP</span>
                    <span class="adm-sysinfo-item__val"><?= e($serverInfo['php'] ?? '') ?></span>
                </div>
                <div class="adm-sysinfo-item">
                    <span class="adm-sysinfo-item__key">MySQL</span>
                    <span class="adm-sysinfo-item__val"><?= e($serverInfo['mysql'] ?? '') ?></span>
                </div>
                <div class="adm-sysinfo-item">
                    <span class="adm-sysinfo-item__key">Système</span>
                    <span class="adm-sysinfo-item__val"><?= e($serverInfo['os'] ?? '') ?></span>
                </div>
                <div class="adm-sysinfo-item">
                    <span class="adm-sysinfo-item__key">Memory Limit</span>
                    <span class="adm-sysinfo-item__val"><?= e($serverInfo['limit'] ?? '') ?></span>
                </div>
                <div class="adm-sysinfo-item">
                    <span class="adm-sysinfo-item__key">Environnement</span>
                    <span class="adm-sysinfo-item__val">
                        <?php if ($isDebug): ?>
                            <span class="badge-krono badge-krono--warning badge-no-dot">
                                <i class="bi bi-bug-fill"></i> Développement
                            </span>
                        <?php else: ?>
                            <span class="badge-krono badge-krono--success badge-no-dot">
                                <i class="bi bi-shield-check"></i> Production
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="adm-sysinfo-item">
                    <span class="adm-sysinfo-item__key">Version KronoConnect</span>
                    <span class="adm-sysinfo-item__val">
                        <i class="bi bi-tag-fill" style="color:var(--krono-accent); margin-right:.25rem;"></i>
                        v<?= e($version) ?>
                    </span>
                </div>
            </div>
        </div>

    </div><!-- /adm-col-main -->

    <!-- Colonne secondaire -->
    <div class="adm-col-side">

        <!-- Alertes & KPI secondaires -->
        <div class="adm-alerts-stack">
            <?php if ($pendingUsers > 0): ?>
                <a href="<?= url('/admin/users?status=attente_validation') ?>" class="adm-alert-card glass-card fade-in-up anim-delay-7">
                    <div class="adm-alert-icon" style="color:#d97706; background:rgba(245,158,11,0.15);">
                        <i class="bi bi-person-exclamation"></i>
                    </div>
                    <div class="adm-alert-body">
                        <div class="adm-alert-title">Comptes en attente</div>
                        <div class="adm-alert-desc"><strong><?= $pendingUsers ?></strong> demande(s) d'approbation</div>
                    </div>
                    <div class="adm-alert-arrow"><i class="bi bi-chevron-right"></i></div>
                </a>
            <?php endif; ?>

            <a href="<?= url('/admin/groups') ?>" class="adm-alert-card glass-card fade-in-up anim-delay-8">
                <div class="adm-alert-icon" style="color:var(--krono-accent); background:var(--krono-accent-light);">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div class="adm-alert-body">
                    <div class="adm-alert-title">Groupes & RBAC</div>
                    <div class="adm-alert-desc"><strong><?= $totalGroups ?></strong> groupe(s) paramétré(s)</div>
                </div>
                <div class="adm-alert-arrow"><i class="bi bi-chevron-right"></i></div>
            </a>
        </div>

        <!-- Activité récente -->
        <div class="glass-card fade-in-up adm-card-flush anim-delay-9">
            <div class="adm-card-header">
                <span class="adm-card-header__title">
                    <i class="bi bi-journal-text" style="color:var(--krono-accent);"></i>
                    Activité récente
                </span>
                <a href="<?= url('/admin/logs') ?>" class="adm-card-header__link">Tout voir</a>
            </div>

            <?php if (empty($recentLogs)): ?>
                <div class="adm-empty">Aucune activité enregistrée.</div>
            <?php else: ?>
                <?php
                $levelColors = [
                    'info'     => 'info',
                    'warning'  => 'warning',
                    'error'    => 'danger',
                    'critical' => 'danger',
                    'debug'    => 'neutral',
                ];
                $levelIcons = [
                    'info'     => 'bi-info-circle-fill',
                    'warning'  => 'bi-exclamation-triangle-fill',
                    'error'    => 'bi-x-circle-fill',
                    'critical' => 'bi-radioactive',
                    'debug'    => 'bi-bug-fill',
                ];
                foreach ($recentLogs as $log):
                    $lvl   = $log['level'] ?? 'info';
                    $color = $levelColors[$lvl] ?? 'neutral';
                    $icon  = $levelIcons[$lvl]  ?? 'bi-dot';
                ?>
                    <div class="adm-log-row">
                        <div class="adm-log-row__badge">
                            <span class="badge-krono badge-krono--<?= $color ?> badge-no-dot" style="font-size:0.65rem; padding: 0.2rem 0.4rem;">
                                <i class="bi <?= $icon ?>" style="font-size:.65rem; margin-right:3px;"></i>
                                <?= ucfirst($lvl) ?>
                            </span>
                        </div>
                        <div class="adm-log-row__body">
                            <div class="adm-log-row__msg" style="font-size:0.8rem;"><?= e($log['message'] ?? '') ?></div>
                            <div class="adm-log-row__time" style="font-size:0.65rem;"><?= date('d/m H:i', strtotime($log['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /adm-col-side -->

</div><!-- /adm-grid -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Data from PHP
    const historyData = <?= json_encode($connectionHistory ?? []) ?>;
    
    if (historyData.length > 0) {
        const labels = historyData.map(d => d.date);
        const data = historyData.map(d => d.count);

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDark ? 'rgba(255,255,255,0.7)' : 'rgba(0,0,0,0.6)';
        const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
        
        const rootStyles = getComputedStyle(document.documentElement);
        const accentColor = rootStyles.getPropertyValue('--krono-accent').trim() || '#3B82F6';

        const ctx = document.getElementById('connectionsChart').getContext('2d');
        
        // Gradient for chart area
        const gradient = ctx.createLinearGradient(0, 0, 0, 250);
        // We use hex to rgba conversion
        let r=59, g=130, b=246; // default blue
        if (accentColor.startsWith('#')) {
            const hex = accentColor.replace('#', '');
            if(hex.length === 6) {
                r = parseInt(hex.substring(0,2), 16);
                g = parseInt(hex.substring(2,4), 16);
                b = parseInt(hex.substring(4,6), 16);
            }
        }
        gradient.addColorStop(0, `rgba(${r}, ${g}, ${b}, 0.5)`);
        gradient.addColorStop(1, `rgba(${r}, ${g}, ${b}, 0.0)`);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Connexions',
                    data: data,
                    borderColor: accentColor,
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointBackgroundColor: accentColor,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4 // smoothed curves
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(0,0,0,0.8)' : 'rgba(255,255,255,0.9)',
                        titleColor: isDark ? '#fff' : '#000',
                        bodyColor: isDark ? '#ddd' : '#333',
                        borderColor: gridColor,
                        borderWidth: 1,
                        padding: 10,
                        boxPadding: 4,
                        usePointStyle: true
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { size: 11, family: "'Geist', sans-serif" } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            font: { size: 11, family: "'Geist', sans-serif" },
                            stepSize: 1
                        },
                        border: { display: false }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    }
});
</script>

<style>
/* ── Stat cards ────────────────────────────────────────────── */
.adm-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.adm-stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    padding: 1.25rem;
}
.adm-stat-card__icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.adm-stat-card__value {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--krono-text);
    line-height: 1;
}
.adm-stat-card__label {
    font-size: .75rem;
    color: var(--krono-text-3);
    margin-top: .15rem;
}

/* ── Grille principale ────────────────────────────────────── */
.adm-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 992px) {
    .adm-grid { grid-template-columns: 1fr; }
}
.adm-col-main,
.adm-col-side {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* ── Cards avec flush (padding zéro + header interne) ─────── */
.adm-card-flush { padding: 0; }
.adm-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid var(--krono-border);
}
.adm-card-header__title {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .7px;
    color: var(--krono-text-2);
    display: flex;
    align-items: center;
    gap: .5rem;
}
.adm-card-header__link {
    font-size: .75rem;
    font-weight: 600;
    color: var(--krono-accent);
    text-decoration: none;
}
.adm-card-header__link:hover { text-decoration: underline; }
.adm-empty {
    padding: 2rem;
    text-align: center;
    color: var(--krono-text-3);
    font-size: .85rem;
}

/* ── Section title (pour les cards normales) ──────────────── */
.adm-section-title {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .7px;
    color: var(--krono-text-2);
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: 1.1rem;
    padding: 1.5rem 1.5rem 0 1.5rem;
}

/* ── Lignes de log ────────────────────────────────────────── */
.adm-log-row {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: .7rem 1.5rem;
    border-bottom: 1px solid var(--krono-border-light);
}
.adm-log-row:last-child { border-bottom: none; }
.adm-log-row__badge { width: 70px; flex-shrink: 0; display: flex; justify-content: center; }
.adm-log-row__msg { font-size: .85rem; color: var(--krono-text); line-height: 1.4; }
.adm-log-row__time { font-size: .7rem; color: var(--krono-text-3); margin-top: .15rem; }

/* ── Grille système ───────────────────────────────────────── */
.adm-sysinfo-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: .75rem;
    padding: 0 1.5rem 1.5rem 1.5rem;
}
@media (max-width: 768px) {
    .adm-sysinfo-grid { grid-template-columns: 1fr; }
}
.adm-sysinfo-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .55rem .75rem;
    background: var(--krono-surface-3);
    border-radius: 8px;
    border: 1px solid var(--krono-border-light);
    gap: .5rem;
}
.adm-sysinfo-item__key {
    font-size: .75rem;
    color: var(--krono-text-3);
    white-space: nowrap;
}
.adm-sysinfo-item__val {
    font-size: .75rem;
    font-weight: 700;
    color: var(--krono-text);
    text-align: right;
    word-break: break-all;
}

/* ── Stack Alerts ─────────────────────────────────────────── */
.adm-alerts-stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.adm-alert-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    text-decoration: none;
    gap: 1rem;
    transition: transform 0.2s, box-shadow 0.2s;
}
.adm-alert-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--krono-shadow-md);
}
.adm-alert-icon {
    width: 40px; height: 40px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.adm-alert-body { flex: 1; min-width: 0; }
.adm-alert-title { font-weight: 700; font-size: 0.9rem; color: var(--krono-text); }
.adm-alert-desc { font-size: 0.75rem; color: var(--krono-text-3); margin-top: 0.15rem; }
.adm-alert-arrow { color: var(--krono-text-3); font-size: 1.2rem; transition: transform 0.2s; }
.adm-alert-card:hover .adm-alert-arrow { transform: translateX(3px); color: var(--krono-accent); }
</style>
