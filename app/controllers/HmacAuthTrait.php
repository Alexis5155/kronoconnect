<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Models\ClientModel;
use KronoConnect\Core\Database;
use KronoConnect\Core\Security;

/**
 * Authentification HMAC-SHA256 pour les endpoints API stateless (server-to-server).
 *
 * Headers attendus :
 *   X-Client-ID  : identifiant public du client
 *   X-Timestamp  : timestamp Unix (tolérance ± 60 s)
 *   X-Signature  : hash_hmac('sha256', clientId:timestamp:body, client_secret_raw)
 *
 * Protection anti-replay : insertion d'un nonce unique (sha256 de la signature complète)
 * dans `api_nonces`. Même requête rejouée dans la fenêtre de 60 s = 401.
 *
 * Le contrôleur consommateur doit définir `$this->json()` (via BaseController) et
 * exposer `$this->db` (Database) ainsi que `$this->clients` (ClientModel) avant
 * d'appeler `$this->authenticateApiRequest()`.
 */
trait HmacAuthTrait
{
    private function authenticateApiRequest(): array
    {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        $clientId  = $_SERVER['HTTP_X_CLIENT_ID'] ?? $headers['X-Client-ID'] ?? '';
        $timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? $headers['X-Timestamp'] ?? '';
        $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? $headers['X-Signature'] ?? '';

        $ip       = Security::getClientIp();
        $endpoint = $_SERVER['REQUEST_URI'] ?? '';

        if (!$clientId || !$timestamp || !$signature) {
            $this->logApi($ip, (string) $clientId, $endpoint, 401);
            $this->json(['error' => 'missing_authentication_headers'], 401);
        }

        // Format strict des headers pour éviter le bypass via charge structurelle :
        // - client_id : alphanum + tiret/underscore, ≤ 100
        // - timestamp : entier décimal positif
        // - signature : sha256 hex (64 chars exactement)
        if (!preg_match('/^[A-Za-z0-9_\-]{1,100}$/', (string) $clientId)
            || !ctype_digit((string) $timestamp)
            || strlen((string) $signature) !== 64
            || !ctype_xdigit((string) $signature)) {
            $this->logApi($ip, (string) $clientId, $endpoint, 401);
            $this->json(['error' => 'malformed_authentication_headers'], 401);
        }

        if (abs(time() - (int) $timestamp) > 60) {
            $this->logApi($ip, $clientId, $endpoint, 401);
            $this->json(['error' => 'request_expired'], 401);
        }

        /** @var ClientModel $clients */
        $clients = $this->clients;
        $client = $clients->findByClientId($clientId);
        if (!$client) {
            $this->logApi($ip, $clientId, $endpoint, 401);
            $this->json(['error' => 'invalid_client'], 401);
        }

        $body = file_get_contents('php://input') ?: '';

        if (empty($client['client_secret_raw'])) {
            $this->logApi($ip, $clientId, $endpoint, 401);
            $this->json(['error' => 'client_secret_raw_missing'], 401);
        }
        $expectedSignature = hash_hmac('sha256', $clientId . ':' . $timestamp . ':' . $body, $client['client_secret_raw']);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logApi($ip, $clientId, $endpoint, 401);
            $this->json(['error' => 'invalid_signature'], 401);
        }

        // Anti-replay : nonce déterministe sur la signature → rejet si déjà vu (UNIQUE).
        /** @var Database $db */
        $db = $this->db;
        $nonce   = hash('sha256', $clientId . ':' . $timestamp . ':' . $signature);
        $tNonces = $db->t('api_nonces');
        try {
            $db->query("DELETE FROM `{$tNonces}` WHERE created_at < DATE_SUB(NOW(), INTERVAL 120 SECOND)");
            $db->query("INSERT INTO `{$tNonces}` (nonce) VALUES (?)", [$nonce]);
        } catch (\Throwable) {
            $this->logApi($ip, $clientId, $endpoint, 401);
            $this->json(['error' => 'replayed_request'], 401);
        }

        $this->logApi($ip, $clientId, $endpoint, 200);
        return $client;
    }

    private function logApi(string $ip, string $clientId, string $endpoint, int $status): void
    {
        try {
            /** @var Database $db */
            $db = $this->db;
            $db->insert('api_logs', [
                'ip_address' => $ip,
                'client_id'  => $clientId ?: null,
                'endpoint'   => $endpoint,
                'status'     => $status,
            ]);
        } catch (\Throwable) {
            // logs API best-effort
        }
    }
}
