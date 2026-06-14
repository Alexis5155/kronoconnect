<?php
declare(strict_types=1);

namespace KronoConnect\Services;

use KronoConnect\Core\Database;
use KronoConnect\Core\Logger;

class LogoutService
{
    /**
     * Notifie les applications clientes d'une déconnexion (Single Logout)
     *
     * @param int    $userId
     * @param string $email
     */
    public static function notifyClients(int $userId, string $email): void
    {
        try {
            $db = Database::getInstance();
            
            // Récupérer les clients uniques sur lesquels l'utilisateur s'est connecté
            // au cours des 30 derniers jours, en s'assurant qu'on a bien l'URL et le secret
            $tConnLogs  = $db->t('sso_connection_logs');
            $tSsoClients = $db->t('sso_clients');
            $query = "
                SELECT DISTINCT c.client_id, c.logout_url, c.client_secret_raw
                FROM `{$tConnLogs}` scl
                JOIN `{$tSsoClients}` c ON scl.client_id = c.client_id
                WHERE scl.user_id = ?
                  AND scl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND c.logout_url IS NOT NULL
                  AND c.logout_url != ''
            ";
            
            $clients = $db->fetchAll($query, [$userId]);
            
            Logger::info("LogoutService: " . count($clients) . " clients trouvés pour SLO.", ['userId' => $userId]);

            if (empty($clients)) {
                return; // Rien à notifier
            }
            
            $payload = json_encode([
                'action' => 'logout',
                'email'  => $email
            ]);
            
            if ($payload === false) {
                Logger::error("LogoutService: Echec de l'encodage JSON du payload.", ['userId' => $userId]);
                return;
            }
            
            foreach ($clients as $client) {
                $clientUrl = rtrim($client['logout_url'], '/');
                $targetUrl = $clientUrl . '/kronoconnect/logout';
                $clientId  = $client['client_id'];
                $secretRaw = $client['client_secret_raw'] ?? '';
                
                if (empty($secretRaw)) {
                    Logger::warning("LogoutService: Secret brut manquant pour le client {$clientId}. Notification ignorée.");
                    continue;
                }
                
                $timestamp = (string)time();
                
                // Signature: HMAC-SHA256(client_secret_raw, "client_id:timestamp:body")
                $signature = hash_hmac('sha256', $clientId . ':' . $timestamp . ':' . $payload, $secretRaw);
                
                // Initialiser cURL
                $ch = curl_init($targetUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    // Timeout ultra court (2s) pour ne pas bloquer la déconnexion
                    CURLOPT_TIMEOUT        => 2,
                    CURLOPT_CONNECTTIMEOUT => 1,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'X-Client-ID: ' . $clientId,
                        'X-Timestamp: ' . $timestamp,
                        'X-Signature: ' . $signature
                    ]
                ]);
                
                $response = curl_exec($ch);
                $error    = curl_error($ch);
                $info     = curl_getinfo($ch);
                
                curl_close($ch);
                
                if ($response === false) {
                    Logger::warning("LogoutService: Echec notification SLO vers {$clientId} ({$targetUrl})", ['error' => $error]);
                } else {
                    $status = $info['http_code'] ?? 0;
                    Logger::info("LogoutService: SLO notifié à {$clientId} ({$targetUrl})", ['status' => $status]);
                }
            }
            
        } catch (\Throwable $e) {
            Logger::error("LogoutService: Exception lors de la notification SLO.", [
                'userId'  => $userId,
                'message' => $e->getMessage()
            ]);
        }
    }
}
