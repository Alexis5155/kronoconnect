<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Core\Session;
use KronoConnect\Core\Database;
use KronoConnect\Models\ClientModel;
use KronoConnect\Models\AuthCodeModel;

class SsoController extends BaseController
{
    private ClientModel   $clients;
    private AuthCodeModel $codes;
    private Database      $db;

    public function __construct()
    {
        $this->clients = new ClientModel();
        $this->codes   = new AuthCodeModel();
        $this->db      = Database::getInstance();
    }

    // ── GET /sso/authorize ────────────────────────────────────────────────
    // Point d'entrée du flux SSO depuis les apps clientes.

    public function authorize(): void
    {
        $clientId    = $_GET['client_id']    ?? '';
        $redirectUri = $_GET['redirect_uri'] ?? '';
        $state       = $_GET['state']        ?? '';

        $client = $this->validateClient($clientId, $redirectUri);
        if ($client === null) {
            http_response_code(400);
            $this->render('errors/generic', [
                'code'    => 400,
                'title'   => 'Requête invalide',
                'message' => 'Client SSO inconnu ou redirect_uri non autorisée.',
            ]);
            return;
        }

        // Vérification des restrictions IP
        if (!\KronoConnect\Core\Security::isIpAllowed(\KronoConnect\Core\Security::getClientIp(), $client['allowed_ips'] ?? '')) {
            http_response_code(403);
            $this->render('errors/generic', [
                'code'    => 403,
                'title'   => 'Accès restreint',
                'message' => 'Application inaccessible depuis ce réseau',
            ]);
            return;
        }

        // Pas encore connecté : mémoriser le contexte SSO via un Flow ID et aller au login
        if (!Session::isLoggedIn()) {
            $flowId = bin2hex(random_bytes(4));
            Session::set('sso_flow_' . $flowId, [
                'client_id'    => $clientId,
                'redirect_uri' => $redirectUri,
                'state'        => $state,
            ]);
            redirect('/login?flow=' . $flowId);
        }

        $userId = Session::userId();
        $user = Session::get('user');
        $accessGranted = false;
        $accessMode = $client['access_mode'] ?? 'open';

        $tUserAppAccess  = $this->db->t('user_app_access');
        $tGroupAppAccess = $this->db->t('group_app_access');
        $tGroupMembers   = $this->db->t('group_members');
        $tConnLogs       = $this->db->t('sso_connection_logs');

        if (($user['role'] ?? '') === 'super_admin') {
            $accessGranted = true;
        } elseif ($accessMode === 'open') {
            $accessGranted = true;
        } elseif ($accessMode === 'manual') {
            $manualAccess = $this->db->fetchOne(
                "SELECT id FROM `{$tUserAppAccess}` WHERE user_id = ? AND client_id = ?",
                [$userId, $client['id']]
            );
            if ($manualAccess) { $accessGranted = true; }
        } elseif ($accessMode === 'group') {
            $groupAccess = $this->db->fetchOne("
                SELECT gaa.group_id
                FROM `{$tGroupAppAccess}` gaa
                JOIN `{$tGroupMembers}` gm ON gaa.group_id = gm.group_id
                WHERE gm.user_id = ? AND gaa.client_id = ?
            ", [$userId, $client['id']]);
            if ($groupAccess) { $accessGranted = true; }
        }

        if (!$accessGranted) {
            http_response_code(403);
            $this->render('errors/generic', [
                'code'    => 403,
                'title'   => 'Accès non autorisé',
                'message' => 'Vous n\'avez pas la permission d\'accéder à cette application. Veuillez contacter votre administrateur.',
            ]);
            return;
        }



        $source = $_GET['source'] ?? '';
        $fromLogin = $_GET['from_login'] ?? '';

        // La validation (consentement) est demandée UNIQUEMENT si :
        // L'utilisateur arrive d'une redirection externe en étant DÉJÀ connecté
        // (il ne vient pas du portail, et il ne vient pas de taper ses identifiants).
        // Cela évite les connexions "invisibles" par erreur via un favori KronoCore.
        $requireConsent = ($source !== 'portal' && $fromLogin !== '1');

        if (!$requireConsent) {
            // Log de connexion (indispensable pour SLO)
            $this->db->insert('sso_connection_logs', [
                'user_id'   => $userId,
                'client_id' => $clientId,
                'ip'        => \KronoConnect\Core\Security::getClientIp(),
            ]);

            $code = $this->codes->create($clientId, (int) $userId);
            $sep  = str_contains($redirectUri, '?') ? '&' : '?';
            $location = $redirectUri . $sep . 'code=' . urlencode($code);
            if ($state !== '') {
                $location .= '&state=' . urlencode($state);
            }
            header('Location: ' . $location);
            exit;
        }

        // Afficher la page de validation/consentement
        $this->render('sso/authorize', [
            'title'       => 'Autorisation',
            'client'      => $client,
            'redirectUri' => $redirectUri,
            'state'       => $state,
        ]);
    }

    // ── POST /sso/authorize ───────────────────────────────────────────────
    // Traitement du consentement utilisateur.

    public function consent(): void
    {
        $this->verifyCsrf();

        if (!Session::isLoggedIn()) {
            redirect('/login');
        }

        $clientId    = $_POST['client_id']    ?? '';
        $redirectUri = $_POST['redirect_uri'] ?? '';
        $state       = $_POST['state']        ?? '';

        $client = $this->validateClient($clientId, $redirectUri);
        if ($client === null) {
            http_response_code(400);
            $this->render('errors/generic', [
                'code'    => 400,
                'title'   => 'Requête invalide',
                'message' => 'Client SSO inconnu ou redirect_uri non autorisée.',
            ]);
            return;
        }

        // Vérification des restrictions IP
        if (!\KronoConnect\Core\Security::isIpAllowed(\KronoConnect\Core\Security::getClientIp(), $client['allowed_ips'] ?? '')) {
            http_response_code(403);
            $this->render('errors/generic', [
                'code'    => 403,
                'title'   => 'Accès restreint',
                'message' => 'Application inaccessible depuis ce réseau',
            ]);
            return;
        }

        // Refus de l'utilisateur
        if (isset($_POST['deny'])) {
            $sep = str_contains($redirectUri, '?') ? '&' : '?';
            $location = $redirectUri . $sep . 'error=access_denied';
            if ($state !== '') {
                $location .= '&state=' . urlencode($state);
            }
            header('Location: ' . $location);
            exit;
        }

        // Génération du code et redirection
        $userId = (int) Session::userId();
        $this->db->insert('sso_connection_logs', [
            'user_id'   => $userId,
            'client_id' => $clientId,
            'ip'        => \KronoConnect\Core\Security::getClientIp(),
        ]);

        $code = $this->codes->create($clientId, $userId);
        $sep  = str_contains($redirectUri, '?') ? '&' : '?';
        $location = $redirectUri . $sep . 'code=' . urlencode($code);
        if ($state !== '') {
            $location .= '&state=' . urlencode($state);
        }
        header('Location: ' . $location);
        exit;
    }

    // ── Validation du client ──────────────────────────────────────────────

    private function validateClient(string $clientId, string $redirectUri): ?array
    {
        if ($clientId === '' || $redirectUri === '') {
            return null;
        }
        $client = $this->clients->findByClientId($clientId);
        if (!$client || !$this->clients->isValidRedirectUri($client, $redirectUri)) {
            return null;
        }
        return $client;
    }
}
