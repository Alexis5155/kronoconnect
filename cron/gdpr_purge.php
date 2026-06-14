<?php
declare(strict_types=1);

/**
 * Script de purge automatique RGPD pour KronoConnect
 * A exécuter via une tâche Cron, ex: 0 3 * * * php /chemin/vers/kronoconnect/cron/gdpr_purge.php
 */

if (php_sapi_name() !== 'cli') {
    die("Ce script ne peut être exécuté qu'en ligne de commande (CLI).\n");
}

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/core/helpers.php';
require ROOT_PATH . '/app/core/Autoloader.php';

use KronoConnect\Core\Autoloader;
use KronoConnect\Core\Database;
use KronoConnect\Core\Logger;
use KronoConnect\Models\AdminModel;

$autoloader = new Autoloader();
$autoloader->register();

echo "=================================================\n";
echo "  KronoConnect - Purge RGPD (" . date('Y-m-d H:i:s') . ")\n";
echo "=================================================\n\n";

try {
    $db = Database::getInstance();
    $adminModel = new AdminModel();
    $settings = $adminModel->getSettings();

    // 1. Purge des journaux de connexion
    $retentionLogs = (int)($settings['gdpr_retention_logs_months'] ?? 6);
    if ($retentionLogs < 1) $retentionLogs = 6;

    echo "[*] Purge des journaux de connexion et logs (>$retentionLogs mois)...\n";
    $dateLimitLogs = date('Y-m-d H:i:s', strtotime("-{$retentionLogs} months"));

    $tConnLogs = $db->t('sso_connection_logs');
    $tApiLogs = $db->t('api_logs');
    $tLogs = $db->t('logs');

    // On supprime par lots pour éviter de bloquer la base de données
    $countConnLogs = 0;
    $countApiLogs = 0;
    $countSysLogs = 0;

    while (true) {
        $stmt = $db->getRawPdo()->prepare("DELETE FROM `$tConnLogs` WHERE created_at < ? LIMIT 1000");
        $stmt->execute([$dateLimitLogs]);
        $deleted = $stmt->rowCount();
        $countConnLogs += $deleted;
        if ($deleted < 1000) break;
    }
    
    while (true) {
        $stmt = $db->getRawPdo()->prepare("DELETE FROM `$tApiLogs` WHERE created_at < ? LIMIT 1000");
        $stmt->execute([$dateLimitLogs]);
        $deleted = $stmt->rowCount();
        $countApiLogs += $deleted;
        if ($deleted < 1000) break;
    }

    while (true) {
        $stmt = $db->getRawPdo()->prepare("DELETE FROM `$tLogs` WHERE created_at < ? LIMIT 1000");
        $stmt->execute([$dateLimitLogs]);
        $deleted = $stmt->rowCount();
        $countSysLogs += $deleted;
        if ($deleted < 1000) break;
    }

    echo "    -> Sso Connection Logs supprimés : $countConnLogs\n";
    echo "    -> API Logs supprimés : $countApiLogs\n";
    echo "    -> System Logs supprimés : $countSysLogs\n\n";

    // 2. Purge des comptes inactifs
    $retentionAccounts = (int)($settings['gdpr_retention_accounts_months'] ?? 36);
    if ($retentionAccounts < 1) $retentionAccounts = 36;

    echo "[*] Recherche des comptes inactifs (>$retentionAccounts mois)...\n";
    $dateLimitAccounts = date('Y-m-d H:i:s', strtotime("-{$retentionAccounts} months"));
    
    $tUsers = $db->t('users');

    // On cherche les utilisateurs qui n'ont pas eu d'activité récente (pas de sso_connection_logs récent, et compte ancien)
    // Pour simplifier et garantir les performances, on se base sur `last_activity_at` s'il existe (non natif),
    // ou on joint avec les logs. Comme KC n'avait pas nativement `last_activity_at`, on regarde la date la plus récente dans `sso_connection_logs`
    // ou la date de création du compte si aucun log.

    $inactiveUsers = $db->fetchAll("
        SELECT u.id, u.email, u.role
        FROM `$tUsers` u
        LEFT JOIN (
            SELECT user_id, MAX(created_at) as last_conn
            FROM `$tConnLogs`
            GROUP BY user_id
        ) conn ON conn.user_id = u.id
        WHERE u.role != 'super_admin'
        AND (
            (conn.last_conn IS NOT NULL AND conn.last_conn < ?)
            OR
            (conn.last_conn IS NULL AND u.created_at < ?)
        )
    ", [$dateLimitAccounts, $dateLimitAccounts]);

    $countInactive = count($inactiveUsers);
    echo "    -> Utilisateurs identifiés pour suppression : $countInactive\n";

    $countDeletedUsers = 0;
    foreach ($inactiveUsers as $user) {
        // En vrai production, un avertissement email (30 jours avant par ex) serait envoyé ici 
        // ou via un autre script s'exécutant sur le délai (-35 mois).

        $db->delete('users', ['id' => $user['id']]);
        $countDeletedUsers++;
        Logger::info('Utilisateur : purge automatique pour inactivité RGPD', ['user_id' => $user['id'], 'email' => $user['email']]);
    }

    echo "    -> Utilisateurs supprimés avec succès : $countDeletedUsers\n\n";

    echo "=================================================\n";
    echo "  Terminé avec succès.\n";
    echo "=================================================\n";

} catch (\Throwable $e) {
    echo "\n[ERREUR FATALE] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
