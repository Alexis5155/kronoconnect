<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Core\Session;
use KronoConnect\Core\Validator;
use KronoConnect\Models\UserModel;
use ReportUri\Passkeys\WebAuthn;
use ReportUri\Passkeys\Binary\ByteBuffer;
use KronoConnect\Core\Logger;

class ProfileController extends BaseController
{
    private UserModel $userModel;
    private \KronoConnect\Core\Database $db;

    public function __construct()
    {
        if (!Session::isLoggedIn()) {
            redirect('/login');
        }
        $this->db        = \KronoConnect\Core\Database::getInstance();
        $this->userModel = new UserModel();
    }

    /**
     * Affiche la vue du profil utilisateur
     */
    public function index(): void
    {
        $userId = Session::userId();
        $user = $this->userModel->findById($userId);

        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            redirect('/login');
        }

        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
        $canChangeEmail = ((int)($settings['allow_email_change'] ?? 0)) === 1;

        $recoveryCodesCount = $this->userModel->getRemainingRecoveryCodesCount((int)$userId);
        $webAuthnKeys = $this->userModel->getWebAuthnCredentials((int)$userId);

        $this->render('profile/index', [
            'title' => 'Mon Profil',
            'user'  => $user,
            'activePage' => 'profile',
            'globalAllowEmailChange' => $canChangeEmail,
            'canChangeEmail' => $canChangeEmail,
            'useCard' => false,
            'recoveryCodesCount' => $recoveryCodesCount,
            'webAuthnKeys' => $webAuthnKeys
        ], 'main'); // On utilise le nouveau layout main (dashboard)
    }

    /**
     * Met à jour les informations de base du profil
     */
    public function update(): void
    {
        $userId = Session::userId();
        $user = $this->userModel->findById($userId);

        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
        $canChangeEmail = ((int)($settings['allow_email_change'] ?? 0)) === 1;

        $validator = new Validator();
        
        $email = $user['email'];
        if ($canChangeEmail && isset($_POST['email'])) {
            $validator->validate($_POST, [
                'email' => ['required', 'email', 'max:255']
            ]);
            $email = $_POST['email'];
            
            // Vérification si l'email est déjà utilisé par un AUTRE utilisateur
            if ($email !== $user['email']) {
                $existingUser = $this->userModel->findByEmail($email);
                if ($existingUser && $existingUser['id'] !== $userId) {
                    $validator->addError('email', 'Cet e-mail est déjà utilisé.');
                }
            }
        }

        $phone = $_POST['phone'] ?? null;
        if ($phone) {
            $validator->validate($_POST, [
                'phone' => ['max:20']
            ]);
        }

        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $errors) {
                Session::flash('error', $errors[0]);
                break;
            }
            redirect('/profile');
        }

        $changed = false;
        if ($email !== $user['email'] || $phone !== $user['phone']) {
            $this->userModel->updateProfile($userId, $user['nom'], $user['prenom'], $email, $phone);
            
            if ($email !== $user['email']) {
                Session::set('user_email', $email);
            }
            $changed = true;
        }

        if ($changed) {
            Session::flash('success', 'Votre profil a été mis à jour.');
        } else {
            Session::flash('info', 'Aucune modification à enregistrer.');
        }

        redirect('/profile');
    }

    /**
     * Met à jour le mot de passe
     */
    public function updatePassword(): void
    {
        $userId = Session::userId();
        $user = $this->userModel->findById($userId);

        $validator = new Validator();
        $validator->validate($_POST, [
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8'],
            'confirm_password' => ['required', 'string']
        ]);

        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $errors) {
                Session::flash('error', $errors[0]);
                break;
            }
            redirect('/profile');
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            Session::flash('error', 'Le mot de passe actuel est incorrect.');
            redirect('/profile');
        }

        if ($newPassword !== $confirmPassword) {
            Session::flash('error', 'Les nouveaux mots de passe ne correspondent pas.');
            redirect('/profile');
        }

        $this->userModel->updatePassword($userId, $newPassword);
        Session::flash('success', 'Votre mot de passe a été mis à jour.');
        redirect('/profile');
    }

    /**
     * Initie la configuration MFA
     */
    public function mfaSetup(): void
    {
        $userId = Session::userId();
        $user = $this->userModel->findById($userId);

        if ($user['mfa_enabled']) {
            Session::flash('error', 'Le MFA est déjà activé.');
            redirect('/profile');
        }

        $secret = \KronoConnect\Core\GoogleAuthenticator::createSecret();
        Session::set('profile_mfa_secret', $secret);

        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
        $appName = $settings['app_name'] ?? 'KronoConnect';

        $qrCodeUrl = \KronoConnect\Core\GoogleAuthenticator::getQrCodeUrl($appName, $user['email'], $secret);
        $qrCodeImage = 'https://quickchart.io/qr?text=' . rawurlencode($qrCodeUrl) . '&size=200&margin=1';

        $this->render('profile/mfa_setup', [
            'title' => 'Configuration MFA',
            'user' => $user,
            'activePage' => 'profile',
            'secret' => $secret,
            'qrCodeImage' => $qrCodeImage,
            'useCard' => false
        ], 'main');
    }

    /**
     * Valide et active le MFA
     */
    public function mfaVerifySetup(): void
    {
        $this->verifyCsrf();
        $userId = Session::userId();
        $user = $this->userModel->findById($userId);

        $secret = Session::get('profile_mfa_secret');
        if (!$secret) {
            redirect('/profile');
        }

        $code = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
        if (strlen($code) !== 6) {
            Session::flash('error', 'Veuillez saisir un code à 6 chiffres.');
            redirect('/profile/mfa-setup');
        }

        if (\KronoConnect\Core\GoogleAuthenticator::verifyCode($secret, $code)) {
            $this->userModel->enableMfa((int)$userId, $secret);
            Session::remove('profile_mfa_secret');
            
            // Générer les codes de secours
            $codes = $this->userModel->generateAndStoreRecoveryCodes((int)$userId);
            Session::set('profile_mfa_recovery_codes', $codes);

            Session::flash('success', 'L\'authentification à double facteur a été activée.');
            redirect('/profile/mfa-codes');
        } else {
            Session::flash('error', 'Code invalide. Veuillez vérifier votre application.');
            redirect('/profile/mfa-setup');
        }
    }

    /**
     * Affiche les codes de secours générés
     */
    public function mfaCodes(): void
    {
        $codes = Session::get('profile_mfa_recovery_codes');
        if (!$codes) {
            redirect('/profile');
        }
        Session::remove('profile_mfa_recovery_codes');

        $this->render('profile/mfa_codes', [
            'title' => 'Codes de secours générés',
            'codes' => $codes,
            'activePage' => 'profile',
            'useCard' => false
        ], 'main');
    }

    /**
     * Régénère les codes de secours
     */
    public function mfaRegenerateCodes(): void
    {
        $this->verifyCsrf();
        $userId = Session::userId();
        $user = $this->userModel->findById($userId);

        if (!$user || !$user['mfa_enabled']) {
            Session::flash('error', 'Le MFA doit être activé pour générer des codes de secours.');
            redirect('/profile');
        }

        $codes = $this->userModel->generateAndStoreRecoveryCodes((int)$userId);
        Session::set('profile_mfa_recovery_codes', $codes);
        Session::flash('success', 'De nouveaux codes de secours ont été générés.');
        redirect('/profile/mfa-codes');
    }

    /**
     * Désactive le MFA
     */
    public function mfaDisable(): void
    {
        $this->verifyCsrf();
        $userId = Session::userId();

        // Vérifier si le MFA est imposé par le groupe avant de laisser désactiver ?
        // Pour l'instant, on permet la désactivation, au pire il sera forcé à la prochaine connexion.
        // Ou bien on bloque s'il est imposé.
        $db = \KronoConnect\Core\Database::getInstance();
        $tGroupMembers = $db->t('group_members');
        $tGroups = $db->t('groups');
        $groupRequiresMfa = $db->fetchOne("
            SELECT 1 FROM `$tGroupMembers` gm
            JOIN `$tGroups` g ON gm.group_id = g.id
            WHERE gm.user_id = ? AND g.require_mfa = 1
            LIMIT 1
        ", [$userId]);

        if ($groupRequiresMfa) {
            Session::flash('error', 'Vous ne pouvez pas désactiver le MFA, car un de vos groupes l\'impose.');
            redirect('/profile');
        }

        $this->userModel->disableMfa((int)$userId);
        Session::flash('success', 'L\'authentification à double facteur a été désactivée.');
        redirect('/profile');
    }

    /**
     * Met à jour le thème de l'utilisateur (requête AJAX/JSON attendue)
     */
    public function updateTheme(): void
    {
        $userId = Session::userId();
        $theme = $_POST['theme'] ?? 'system';

        if (!in_array($theme, ['light', 'dark', 'system'])) {
            $theme = 'system';
        }

        $this->userModel->updateTheme($userId, $theme);

        // Mettre à jour la session
        Session::set('user_theme', $theme);

        // Répondre en JSON car appelé via Fetch
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Met à jour l'ordre de tri du portail pour l'utilisateur
     */
    public function updatePortalOrder(): void
    {
        $this->verifyCsrf();
        $userId = Session::userId();
        $input = json_decode(file_get_contents('php://input'), true);
        $order = $input['order'] ?? [];

        if (empty($order)) {
            $this->json(['error' => 'Données invalides'], 400);
        }

        $tOrder = $this->db->t('user_portal_order');

        try {
            $this->db->getRawPdo()->beginTransaction();

            // On vide l'ancien ordre pour reconstruire
            $this->db->query("DELETE FROM `$tOrder` WHERE user_id = ?", [$userId]);

            $stmt = $this->db->getRawPdo()->prepare("
                INSERT INTO `$tOrder` (user_id, item_type, item_id, position)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($order as $item) {
                $stmt->execute([
                    $userId,
                    $item['type'],
                    $item['id'],
                    $item['position']
                ]);
            }

            $this->db->getRawPdo()->commit();
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            if ($this->db->getRawPdo()->inTransaction()) {
                $this->db->getRawPdo()->rollBack();
            }
            $this->json(['error' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    public function exportData(): void
    {
        $this->verifyCsrf();
        $userId = Session::userId();
        $user = $this->userModel->findById($userId);

        if (!$user) {
            redirect('/login');
        }

        // Récupérer les accès et permissions
        $tUserAppAccess = $this->db->t('user_app_access');
        $tSsoClients = $this->db->t('sso_clients');
        $tUserPermissions = $this->db->t('user_permissions');
        $tConnLogs = $this->db->t('sso_connection_logs');
        $tGroupMembers = $this->db->t('group_members');
        $tGroups = $this->db->t('groups');

        $apps = $this->db->fetchAll("
            SELECT c.name, c.app_name, c.client_id, uaa.granted_at 
            FROM `$tUserAppAccess` uaa 
            JOIN `$tSsoClients` c ON uaa.client_id = c.id 
            WHERE uaa.user_id = ?
        ", [$userId]);

        $permissions = $this->db->fetchAll("
            SELECT client_id, perm_key, granted 
            FROM `$tUserPermissions` 
            WHERE user_id = ?
        ", [$userId]);

        $groups = $this->db->fetchAll("
            SELECT g.name, g.description 
            FROM `$tGroupMembers` gm 
            JOIN `$tGroups` g ON gm.group_id = g.id 
            WHERE gm.user_id = ?
        ", [$userId]);

        $logs = $this->db->fetchAll("
            SELECT client_id, ip, created_at 
            FROM `$tConnLogs` 
            WHERE user_id = ? 
            ORDER BY created_at DESC LIMIT 100
        ", [$userId]);

        $exportData = [
            'profil' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'prenom' => $user['prenom'],
                'nom' => $user['nom'],
                'role' => $user['role'],
                'telephone' => $user['phone'] ?? null,
                'is_active' => $user['is_active'],
                'theme' => $user['theme'],
                'created_at' => $user['created_at'],
                'last_activity_at' => $user['last_activity_at'] ?? null,
            ],
            'groupes' => $groups,
            'acces_applications' => $apps,
            'permissions_specifiques' => $permissions,
            'historique_connexions' => $logs
        ];

        \KronoConnect\Core\Logger::info('Utilisateur : export de ses données RGPD', ['user_id' => $userId]);

        $filename = 'export_kronoconnect_' . date('Y-m-d_H-i-s') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function deleteAccount(): void
    {
        $this->verifyCsrf();
        $userId = Session::userId();

        $user = $this->userModel->findById($userId);
        if (!$user) {
            redirect('/login');
        }

        if ($user['role'] === 'super_admin') {
            redirect('/profile', ['error' => 'Un super-administrateur ne peut pas supprimer son propre compte de cette manière.']);
        }

        // Vérification du mot de passe
        $password = $_POST['password'] ?? '';
        if (!\KronoConnect\Core\Security::verifyPassword($password, $user['password'])) {
            redirect('/profile', ['error' => 'Mot de passe incorrect — suppression annulée.']);
        }

        // SLO Back-channel : Déconnecter l'utilisateur des autres apps
        $email = $user['email'] ?? '';
        if ($email) {
            \KronoConnect\Services\LogoutService::notifyClients((int)$userId, (string)$email);
        }

        // Suppression de l'utilisateur.
        // La BDD devrait idéalement avoir des ON DELETE CASCADE, mais on peut forcer la suppression ou anonymisation ici.
        // Puisque KC gère des logs techniques, supprimer un utilisateur peut laisser des ID orphelins si CASCADE n'est pas défini.
        // Supposons une suppression stricte :
        $this->db->delete('users', ['id' => $userId]);

        \KronoConnect\Core\Logger::warning('Utilisateur : suppression du compte (Droit à l\'oubli)', ['user_id' => $userId, 'email' => $email]);

        Session::logout();
        redirect('/login', ['success' => 'Votre compte et toutes vos données ont été définitivement supprimés.']);
    }

    private function getWebAuthnServer(): WebAuthn
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $rpId = explode(':', $host)[0];
        return new WebAuthn('KronoConnect', $rpId, true);
    }

    public function webauthnRegisterOptions(): void
    {
        try {
            $userId = Session::userId();
            $user = $this->userModel->findById($userId);
            if (!$user) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Utilisateur introuvable.']);
                return;
            }

            $server = $this->getWebAuthnServer();

            $existingCredentials = $this->userModel->getWebAuthnCredentials((int)$userId);
            $excludeIds = [];
            foreach ($existingCredentials as $cred) {
                $excludeIds[] = base64_decode(strtr($cred['credential_id'], '-_', '+/'));
            }

            $createArgs = $server->getCreateArgs(
                (string)$userId,
                $user['email'],
                trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')),
                20,
                false,
                true, // requireUserVerification
                null,
                $excludeIds
            );

            Session::set('webauthn_challenge', $server->getChallenge()->jsonSerialize());

            header('Content-Type: application/json');
            echo json_encode($createArgs);
        } catch (\Throwable $e) {
            Logger::error('WebAuthn Registration Options failed : ' . $e->getMessage(), ['userId' => Session::userId()]);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function webauthnRegister(): void
    {
        $userId = Session::userId();
        $challenge = Session::get('webauthn_challenge');
        
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!$data || !$challenge) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Données ou challenge manquants.']);
            return;
        }

        $keyName = trim($data['name'] ?? 'Clé de sécurité');
        if (empty($keyName)) {
            $keyName = 'Clé de sécurité';
        }

        try {
            $server = $this->getWebAuthnServer();
            
            $clientDataJSON = base64_decode($data['response']['clientDataJSON']);
            $attestationObject = base64_decode($data['response']['attestationObject']);
            
            $challengeBuffer = ByteBuffer::fromBase64Url($challenge);

            $credential = $server->processCreate(
                $clientDataJSON,
                $attestationObject,
                $challengeBuffer,
                false
            );

            $credentialIdBase64Url = rtrim(strtr(base64_encode($credential->credentialId), '+/', '-_'), '=');
            
            $this->userModel->addWebAuthnCredential(
                (int)$userId,
                $credentialIdBase64Url,
                $credential->credentialPublicKey,
                $keyName
            );

            Session::remove('webauthn_challenge');

            Logger::info('Utilisateur : Clé de sécurité WebAuthn ajoutée', ['user_id' => $userId, 'key_name' => $keyName]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            Logger::error('WebAuthn Registration failed : ' . $e->getMessage(), ['userId' => $userId]);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function webauthnDelete(): void
    {
        $this->verifyCsrf();
        $userId = Session::userId();
        $keyId = isset($_POST['key_id']) ? (int)$_POST['key_id'] : 0;

        if ($keyId > 0) {
            $this->userModel->deleteWebAuthnCredential((int)$userId, $keyId);
            Logger::info('Utilisateur : Clé de sécurité WebAuthn supprimée', ['user_id' => $userId, 'key_id' => $keyId]);
            Session::flash('success', 'Clé de sécurité supprimée avec succès.');
        } else {
            Session::flash('error', 'Identifiant de clé invalide.');
        }

        redirect('/profile');
    }
}
