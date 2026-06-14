<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Core\Session;
use KronoConnect\Core\Security;
use KronoConnect\Core\Validator;
use KronoConnect\Models\UserModel;
use ReportUri\Passkeys\WebAuthn;
use ReportUri\Passkeys\Binary\ByteBuffer;
use KronoConnect\Core\Logger;

class AuthController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    // ── GET /login ────────────────────────────────────────────────────────

    public function loginForm(): void
    {
        // Un utilisateur déjà connecté qui arrive sur /login sans flux SSO
        // voit juste une page "connecté" — pas de redirect vers / (boucle infinie).
        if (Session::isLoggedIn()) {
            $userId = Session::userId();
            $userRole = Session::get('user_role') ?? Session::get('user')['role'] ?? 'user';
            
            $db = \KronoConnect\Core\Database::getInstance();
            $tClients = $db->t('sso_clients');
            $tUserAccess = $db->t('user_app_access');
            $tGroupAccess = $db->t('group_app_access');
            $tGroupMembers = $db->t('group_members');
            $tCustomLinks = $db->t('custom_links');
            $tCustomLinkUserAccess = $db->t('custom_link_user_access');
            $tCustomLinkGroupAccess = $db->t('custom_link_group_access');
            $tOrder = $db->t('user_portal_order');

            // 1. Récupération des Clients SSO
            if ($userRole === 'super_admin') {
                $clients = $db->fetchAll("SELECT *, 'app' as item_type FROM `$tClients` ORDER BY name");
            } else {
                $clients = $db->fetchAll("
                    SELECT DISTINCT c.*, 'app' as item_type
                    FROM `$tClients` c
                    LEFT JOIN `$tUserAccess` uaa ON c.id = uaa.client_id AND uaa.user_id = ?
                    LEFT JOIN `$tGroupAccess` gaa ON c.id = gaa.client_id
                    LEFT JOIN `$tGroupMembers` gm ON gaa.group_id = gm.group_id AND gm.user_id = ?
                    WHERE c.access_mode = 'open' 
                       OR (c.access_mode = 'manual' AND uaa.user_id IS NOT NULL)
                       OR (c.access_mode = 'group' AND gm.user_id IS NOT NULL)
                    ORDER BY c.name
                ", [$userId, $userId]);
            }

            // 2. Récupération des Liens Personnalisés
            if ($userRole === 'super_admin') {
                $links = $db->fetchAll("SELECT *, 'link' as item_type FROM `$tCustomLinks` ORDER BY title");
            } else {
                $links = $db->fetchAll("
                    SELECT DISTINCT l.*, 'link' as item_type
                    FROM `$tCustomLinks` l
                    LEFT JOIN `$tCustomLinkUserAccess` clua ON l.id = clua.link_id AND clua.user_id = ?
                    LEFT JOIN `$tCustomLinkGroupAccess` clga ON l.id = clga.link_id
                    LEFT JOIN `$tGroupMembers` gm ON clga.group_id = gm.group_id AND gm.user_id = ?
                    WHERE l.access_mode = 'open'
                       OR (l.access_mode = 'manual' AND clua.user_id IS NOT NULL)
                       OR (l.access_mode = 'group' AND gm.user_id IS NOT NULL)
                    ORDER BY l.title
                ", [$userId, $userId]);
            }

            // 3. Fusion et Tri
            $items = array_merge($clients, $links);
            
            // Récupération de l'ordre personnalisé
            $userOrder = $db->fetchAll("SELECT item_type, item_id, position FROM `$tOrder` WHERE user_id = ? ORDER BY position", [$userId]);
            $orderMap = [];
            foreach ($userOrder as $o) {
                $orderMap[$o['item_type'] . ':' . $o['item_id']] = (int)$o['position'];
            }

            usort($items, function($a, $b) use ($orderMap) {
                $idA = ($a['item_type'] === 'app') ? $a['client_id'] : (string)$a['id'];
                $idB = ($b['item_type'] === 'app') ? $b['client_id'] : (string)$b['id'];
                
                $keyA = $a['item_type'] . ':' . $idA;
                $keyB = $b['item_type'] . ':' . $idB;
                
                $posA = $orderMap[$keyA] ?? 99999;
                $posB = $orderMap[$keyB] ?? 99999;
                
                if ($posA !== $posB) {
                    return $posA <=> $posB;
                }
                
                // Tri alphabétique si même position (nouveaux items)
                $nameA = $a['app_name'] ?? $a['name'] ?? $a['title'] ?? '';
                $nameB = $b['app_name'] ?? $b['name'] ?? $b['title'] ?? '';
                return strcasecmp((string)$nameA, (string)$nameB);
            });

            // Vérification des restrictions par IP
            $userIp = \KronoConnect\Core\Security::getClientIp();
            foreach ($items as &$item) {
                if (($item['item_type'] ?? '') === 'app') {
                    $item['is_ip_restricted'] = !\KronoConnect\Core\Security::isIpAllowed($userIp, $item['allowed_ips'] ?? '');
                } else {
                    $item['is_ip_restricted'] = false;
                }
            }
            unset($item);

            $this->render('auth/connected', [
                'title' => 'Portail des applications',
                'activePage' => 'home',
                'items' => $items,
                'useCard' => false
            ], 'main');
            return;
        }

        $ssoClient = null;
        $flowId = $_GET['flow'] ?? '';
        $pending = $flowId ? Session::get('sso_flow_' . $flowId) : null;
        
        if ($pending && !empty($pending['client_id'])) {
            $clientModel = new \KronoConnect\Models\ClientModel();
            $ssoClient = $clientModel->findByClientId($pending['client_id']);
        }

        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
        $maintenance = ($settings['maintenance_mode'] ?? '0') === '1';

        $this->render('auth/login', [
            'title'           => 'Connexion',
            'ssoClient'       => $ssoClient,
            'sso_layout'      => false,
            'flowId'          => $flowId,
            'maintenanceMode' => $maintenance
        ]);
    }

    // ── POST /login ───────────────────────────────────────────────────────

    public function login(): void
    {
        $this->verifyCsrf();

        $flowId = $_GET['flow'] ?? '';
        $loginUrl = '/login' . ($flowId ? '?flow=' . $flowId : '');

        $email    = Security::sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!\KronoConnect\Core\Captcha::validate('login')) {
            redirect($loginUrl, ['error' => 'Validation anti-robot échouée. Veuillez réessayer.']);
        }

        if (!$email) {
            redirect($loginUrl, ['error' => 'Adresse e-mail invalide.']);
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !Security::verifyPassword($password, $user['password'])) {
            redirect($loginUrl, ['error' => 'Identifiants incorrects.']);
        }

        if (!$user['is_active']) {
            redirect($loginUrl, ['error' => 'Ce compte est désactivé.']);
        }

        // Système d'approbation : un compte en attente ne peut pas se connecter.
        $status = $user['status'] ?? 'actif';
        if ($status === 'attente_validation') {
            redirect($loginUrl, ['error' => 'Votre compte est en attente d\'approbation par un administrateur.']);
        }
        if ($status === 'verification_mail') {
            redirect($loginUrl, ['error' => 'Vous devez d\'abord vérifier votre adresse e-mail.']);
        }
        if ($status === 'desactive') {
            redirect($loginUrl, ['error' => 'Ce compte est désactivé.']);
        }

        $mfaEnabled = (bool)($user['mfa_enabled'] ?? 0);
        $mfaRequired = $mfaEnabled;
        
        if (!$mfaRequired) {
            $db = \KronoConnect\Core\Database::getInstance();
            $tGroupMembers = $db->t('group_members');
            $tGroups = $db->t('groups');
            $groupRequiresMfa = $db->fetchOne("
                SELECT 1 FROM `$tGroupMembers` gm
                JOIN `$tGroups` g ON gm.group_id = g.id
                WHERE gm.user_id = ? AND g.require_mfa = 1
                LIMIT 1
            ", [$user['id']]);
            if ($groupRequiresMfa) {
                $mfaRequired = true;
            }
        }

        if ($mfaRequired) {
            Session::set('pending_mfa_user_id', $user['id']);
            Session::set('pending_mfa_remember', !empty($_POST['remember_me']));
            Session::set('pending_mfa_flow', $flowId);
            
            if ($mfaEnabled) {
                redirect('/login/mfa' . ($flowId ? '?flow=' . $flowId : ''));
            } else {
                redirect('/login/mfa-setup' . ($flowId ? '?flow=' . $flowId : ''));
            }
        }

        $this->processFinalLogin($user, !empty($_POST['remember_me']), $flowId);
    }

    private function processFinalLogin(array $user, bool $rememberMe, string $flowId): void
    {
        Session::login($user);
        $this->users->updateLastActivity((int) $user['id']);

        if ($rememberMe) {
            $token  = Security::generateToken(32);
            $config = require CONFIG_PATH . '/app.php';
            $this->users->setRememberToken((int) $user['id'], $token);
            setcookie(
                $config['remember_me']['cookie_name'],
                $token,
                time() + $config['remember_me']['lifetime'],
                '/',
                '',
                false,
                true
            );
        }

        $pending = $flowId ? Session::get('sso_flow_' . $flowId) : null;
        
        if ($pending) {
            $pending['from_login'] = '1';
            Session::remove('sso_flow_' . $flowId);
            redirect('/sso/authorize?' . http_build_query($pending));
        }

        redirect('/');
    }

    // ── GET /login/mfa ────────────────────────────────────────────────────

    public function mfaForm(): void
    {
        if (Session::isLoggedIn()) redirect('/');
        $userId = Session::get('pending_mfa_user_id');
        if (!$userId) redirect('/login');

        $flowId = Session::get('pending_mfa_flow') ?? '';
        $hasWebAuthn = $this->users->hasWebAuthnCredentials((int)$userId);

        $this->render('auth/mfa', [
            'title' => 'Double authentification',
            'sso_layout' => false,
            'flowId' => $flowId,
            'hasWebAuthn' => $hasWebAuthn
        ]);
    }

    // ── POST /login/mfa ───────────────────────────────────────────────────

    public function mfaVerify(): void
    {
        $this->verifyCsrf();
        if (Session::isLoggedIn()) redirect('/');

        $userId = Session::get('pending_mfa_user_id');
        if (!$userId) redirect('/login');

        $flowId = Session::get('pending_mfa_flow') ?? '';
        $mfaUrl = '/login/mfa' . ($flowId ? '?flow=' . $flowId : '');

        $rawCode = trim($_POST['code'] ?? '');
        $code = preg_replace('/[^0-9]/', '', $rawCode);

        $user = $this->users->findById((int)$userId);
        if (!$user || !$user['mfa_enabled']) {
            Session::remove('pending_mfa_user_id');
            redirect('/login');
        }

        $verified = false;
        $isRecovery = false;

        if (strlen($code) === 6) {
            if (\KronoConnect\Core\GoogleAuthenticator::verifyCode((string)$user['mfa_secret'], $code)) {
                $verified = true;
            }
        } else {
            // Tentative d'utilisation d'un code de secours (8 caractères alphanumériques)
            $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawCode));
            if (strlen($cleanCode) === 8) {
                $formattedCode = substr($cleanCode, 0, 4) . '-' . substr($cleanCode, 4, 4);
                if ($this->users->verifyRecoveryCode((int)$userId, $formattedCode)) {
                    $verified = true;
                    $isRecovery = true;
                }
            }
        }

        if ($verified) {
            $rememberMe = (bool)Session::get('pending_mfa_remember');
            Session::remove('pending_mfa_user_id');
            Session::remove('pending_mfa_remember');
            Session::remove('pending_mfa_flow');

            if ($isRecovery) {
                Session::flash('warning', 'Vous vous êtes connecté avec un code de secours. Veuillez reconfigurer votre double authentification si vous n\'avez plus accès à votre appareil.');
            }

            $this->processFinalLogin($user, $rememberMe, $flowId);
        } else {
            redirect($mfaUrl, ['error' => 'Code de sécurité ou code de secours invalide.']);
        }
    }

    // ── GET /login/mfa-setup ──────────────────────────────────────────────

    public function mfaSetupForm(): void
    {
        if (Session::isLoggedIn()) redirect('/');
        
        $userId = Session::get('pending_mfa_user_id');
        if (!$userId) redirect('/login');

        $user = $this->users->findById((int)$userId);
        if (!$user) redirect('/login');

        if ($user['mfa_enabled']) {
            redirect('/login/mfa'); // Déjà configuré
        }

        $secret = Session::get('setup_mfa_secret');
        if (!$secret) {
            $secret = \KronoConnect\Core\GoogleAuthenticator::createSecret();
            Session::set('setup_mfa_secret', $secret);
        }

        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
        $appName = $settings['app_name'] ?? 'KronoConnect';

        $qrCodeUrl = \KronoConnect\Core\GoogleAuthenticator::getQrCodeUrl($appName, $user['email'], $secret);
        
        // QuickChart.io est plus fiable et ne pose pas de problème de double encodage
        $qrCodeImage = 'https://quickchart.io/qr?text=' . rawurlencode($qrCodeUrl) . '&size=200&margin=1';

        $flowId = Session::get('pending_mfa_flow') ?? '';
        $this->render('auth/mfa_setup', [
            'title' => 'Configuration MFA Obligatoire',
            'sso_layout' => false,
            'flowId' => $flowId,
            'secret' => $secret,
            'qrCodeImage' => $qrCodeImage
        ]);
    }

    // ── POST /login/mfa-setup ─────────────────────────────────────────────

    public function mfaSetupVerify(): void
    {
        $this->verifyCsrf();
        if (Session::isLoggedIn()) redirect('/');

        $userId = Session::get('pending_mfa_user_id');
        $secret = Session::get('setup_mfa_secret');
        
        if (!$userId || !$secret) redirect('/login');

        $flowId = Session::get('pending_mfa_flow') ?? '';
        $setupUrl = '/login/mfa-setup' . ($flowId ? '?flow=' . $flowId : '');

        $code = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
        if (strlen($code) !== 6) {
            redirect($setupUrl, ['error' => 'Veuillez saisir un code à 6 chiffres.']);
        }

        if (\KronoConnect\Core\GoogleAuthenticator::verifyCode($secret, $code)) {
            $user = $this->users->findById((int)$userId);
            
            // Activer le MFA
            $this->users->enableMfa((int)$userId, $secret);
            $user['mfa_enabled'] = 1;
            
            // Générer les codes de secours
            $codes = $this->users->generateAndStoreRecoveryCodes((int)$userId);
            Session::set('mfa_recovery_codes', $codes);
            Session::remove('setup_mfa_secret');

            redirect('/login/mfa-codes' . ($flowId ? '?flow=' . $flowId : ''));
        } else {
            redirect($setupUrl, ['error' => 'Code de sécurité invalide. Veuillez vérifier l\'application.']);
        }
    }

    // ── GET /login/mfa-codes ──────────────────────────────────────────────

    public function mfaCodesForm(): void
    {
        if (Session::isLoggedIn()) redirect('/');
        
        $userId = Session::get('pending_mfa_user_id');
        $codes = Session::get('mfa_recovery_codes');
        if (!$userId || !$codes) {
            redirect('/login');
        }

        $flowId = Session::get('pending_mfa_flow') ?? '';
        $this->render('auth/mfa_codes', [
            'title' => 'Codes de secours générés',
            'sso_layout' => false,
            'flowId' => $flowId,
            'codes' => $codes
        ]);
    }

    // ── POST /login/mfa-codes-confirm ─────────────────────────────────────

    public function mfaCodesConfirm(): void
    {
        $this->verifyCsrf();
        if (Session::isLoggedIn()) redirect('/');

        $userId = Session::get('pending_mfa_user_id');
        $codes = Session::get('mfa_recovery_codes');
        if (!$userId || !$codes) {
            redirect('/login');
        }

        $user = $this->users->findById((int)$userId);
        if (!$user) redirect('/login');

        $rememberMe = (bool)Session::get('pending_mfa_remember');
        $flowId = Session::get('pending_mfa_flow') ?? '';

        // Nettoyer la session MFA temporaire
        Session::remove('pending_mfa_user_id');
        Session::remove('pending_mfa_remember');
        Session::remove('pending_mfa_flow');
        Session::remove('mfa_recovery_codes');

        $this->processFinalLogin($user, $rememberMe, $flowId);
    }

    // ── GET /logout ───────────────────────────────────────────────────────

    public function logout(): void
    {
        $userId = Session::userId();
        if ($userId) {
            $this->users->clearRememberToken($userId);
            
            // Notification Single Logout (SLO) en arrière-plan
            $user = $this->users->findById((int)$userId);
            $email = $user['email'] ?? '';

            if ($email) {
                \KronoConnect\Services\LogoutService::notifyClients((int)$userId, (string)$email);
            }
        }

        $config = require CONFIG_PATH . '/app.php';
        setcookie($config['remember_me']['cookie_name'], '', time() - 3600, '/');

        Session::logout();
        redirect('/login', ['success' => 'Vous êtes déconnecté.']);
    }

    // ── GET /register ─────────────────────────────────────────────────────

    public function registerForm(): void
    {
        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
        $allowRegister = ($settings['allow_self_register'] ?? '1') === '1';
        $maintenance = ($settings['maintenance_mode'] ?? '0') === '1';

        if ($maintenance) {
            redirect('/login', ['error' => 'Les inscriptions sont temporairement fermées (maintenance).']);
        }
        if (!$allowRegister) {
            redirect('/login', ['error' => 'Les inscriptions sont fermées.']);
        }
        if (Session::isLoggedIn()) {
            redirect('/');
        }
        $this->render('auth/register', ['title' => 'Créer un compte']);
    }

    // ── POST /register ────────────────────────────────────────────────────

    public function register(): void
    {
        $this->verifyCsrf();

        $flowId = $_GET['flow'] ?? '';
        $loginUrl = '/login' . ($flowId ? '?flow=' . $flowId : '');
        $registerUrl = '/register' . ($flowId ? '?flow=' . $flowId : '');

        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
        $appConfig = require CONFIG_PATH . '/app.php';
        $allowRegister = ($settings['allow_self_register'] ?? '1') === '1';
        $maintenance = ($settings['maintenance_mode'] ?? '0') === '1';

        if ($maintenance) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Les inscriptions sont temporairement fermées (maintenance).']], 403);
            }
            redirect($loginUrl, ['error' => 'Les inscriptions sont temporairement fermées (maintenance).']);
        }

        if (!$allowRegister) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Les inscriptions sont fermées.']], 403);
            }
            redirect($loginUrl, ['error' => 'Les inscriptions sont fermées.']);
        }

        $gdprPrivacyUrl = $settings['gdpr_privacy_url'] ?? '';
        if (!empty($gdprPrivacyUrl) && empty($_POST['rgpd_consent'])) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Vous devez accepter la politique de confidentialité.']], 400);
            }
            redirect($registerUrl, ['error' => 'Vous devez accepter la politique de confidentialité.']);
        }

        // Validation du CAPTCHA
        if (!\KronoConnect\Core\Captcha::validate('register')) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Veuillez valider le CAPTCHA.']], 400);
            }
            redirect($registerUrl, ['error' => 'Veuillez valider le CAPTCHA.']);
        }

        $v = (new Validator($_POST))
            ->required('nom')
            ->required('prenom')
            ->required('email')
            ->email('email')
            ->required('password')
            ->minLength('password', 8);

        if (!$v->passes()) {
            $errors = array_values($v->errors());
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => $errors], 400);
            }
            redirect($registerUrl, ['error' => $errors[0]]);
        }

        $email = Security::sanitizeEmail($_POST['email'] ?? '');
        if (!$email) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Adresse e-mail invalide.']], 400);
            }
            redirect($registerUrl, ['error' => 'Adresse e-mail invalide.']);
        }

        if ($this->users->findByEmail($email)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Cette adresse e-mail est déjà utilisée.']], 400);
            }
            redirect($registerUrl, ['error' => 'Cette adresse e-mail est déjà utilisée.']);
        }

        // Code à 6 chiffres pour la vérification par email
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // L'utilisateur est créé avec le statut 'verification_mail'
        $this->users->create(
            $email,
            $_POST['password'],
            Security::sanitize($_POST['nom']),
            Security::sanitize($_POST['prenom']),
            'verification_mail',
            $code
        );

        // Envoi de l'e-mail de vérification
        try {
            $prenom = Security::sanitize($_POST['prenom']);
            $nom = Security::sanitize($_POST['nom']);
            $appName = $appConfig['name'] ?? 'KronoConnect';
            
            $content = "<p>Bonjour " . e($prenom) . ",</p>";
            $content .= "<p>Merci pour votre inscription sur <strong>" . e($appName) . "</strong>.</p>";
            $content .= "<p>Afin de finaliser la création de votre compte, veuillez saisir le code de validation suivant :</p>";
            $content .= "<div style=\"background:#3b5fc0;color:#fff;padding:16px 24px;border-radius:8px;font-size:24px;font-weight:bold;letter-spacing:4px;text-align:center;margin:24px 0;\">";
            $content .= $code;
            $content .= "</div>";
            $content .= "<p>Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail.</p>";
            
            // Libère la session pour éviter de bloquer l'utilisateur si l'envoi d'e-mail fige
            \KronoConnect\Core\Session::close();

            \KronoConnect\Core\Mailer::sendMail(
                $email,
                "Code de validation — " . $appName,
                $content,
                true,
                true,
                $prenom . ' ' . $nom
            );
        } catch (\Throwable $e) {
            // L'erreur d'email ne bloque pas l'inscription en local, mais devrait être logguée
            \KronoConnect\Core\Logger::error('Échec envoi email de vérification', ['email' => $email, 'error' => $e->getMessage()]);
        }

        if ($this->isAjax()) {
            $this->json(['success' => true, 'email' => $email]);
        }

        redirect($loginUrl, ['success' => 'Compte créé. Veuillez saisir le code envoyé à votre adresse e-mail pour le valider.']);
    }

    // ── POST /verify-code ─────────────────────────────────────────────────

    public function verifyCode(): void
    {
        $this->verifyCsrf();

        $email = Security::sanitizeEmail($_POST['email'] ?? '');
        $code  = Security::sanitize($_POST['code'] ?? '');

        if (!$email || !$code) {
            $this->json(['success' => false, 'message' => 'Veuillez renseigner le code.'], 400);
        }

        $user = $this->users->findByVerificationToken($email, $code);

        if (!$user) {
            $this->json(['success' => false, 'message' => 'Code invalide ou expiré.'], 400);
        }

        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings   = $adminModel->getSettings();
        $needsApproval = ($settings['manual_approval'] ?? '0') === '1';

        if (!$needsApproval) {
            // Activation directe
            $this->users->clearVerificationToken((int)$user['id'], 'actif');
            
            // Connexion automatique
            Session::login($user);
            $this->users->updateLastActivity((int)$user['id']);
            
            $this->json(['success' => true, 'needs_approval' => false, 'redirect' => url('/')]);
        } else {
            // Passage en attente de validation
            $this->users->clearVerificationToken((int)$user['id'], 'attente_validation');
            $this->json(['success' => true, 'needs_approval' => true]);
        }
    }

    // ── Mot de passe oublié ───────────────────────────────────────────────

    public function forgotPassword(): void
    {
        $this->verifyCsrf();
        
        $flowId = $_GET['flow'] ?? '';
        $loginUrl = '/login' . ($flowId ? '?flow=' . $flowId : '');

        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
        $maintenance = ($settings['maintenance_mode'] ?? '0') === '1';

        if ($maintenance) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['La réinitialisation de mot de passe est désactivée pendant la maintenance.']], 403);
            }
            redirect($loginUrl, ['error' => 'La réinitialisation de mot de passe est désactivée pendant la maintenance.']);
        }

        $email = Security::sanitizeEmail($_POST['email'] ?? '');

        if (!\KronoConnect\Core\Captcha::validate('reset')) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Validation anti-robot échouée. Veuillez réessayer.']], 400);
            }
            redirect($loginUrl, ['error' => 'Validation anti-robot échouée. Veuillez réessayer.']);
        }

        if (!$email) {
            redirect($loginUrl, ['error' => 'Adresse e-mail invalide.']);
        }

        $user = $this->users->findByEmail($email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // Valide 1 heure
            
            $this->users->setResetToken((int)$user['id'], $token, $expiresAt);

            $resetUrl = url('/reset-password/' . $token);

            try {
                $content = "<p>Bonjour " . e($user['prenom']) . ",</p>";
                $content .= "<p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le lien ci-dessous pour procéder (valide 1 heure) :</p>";
                $content .= '<p><a href="' . $resetUrl . '" class="btn">Réinitialiser mon mot de passe</a></p>';
                $content .= "<p>Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail.</p>";
                
                // Libère la session pour éviter de bloquer l'utilisateur si l'envoi d'e-mail fige
                \KronoConnect\Core\Session::close();

                \KronoConnect\Core\Mailer::sendMail(
                    $email,
                    "Réinitialisation de votre mot de passe",
                    $content,
                    true,
                    true,
                    $user['prenom'] . ' ' . $user['nom']
                );
            } catch (\Throwable $e) {
                // Log error
            }
        }

        if ($this->isAjax()) {
            $this->json(['success' => true, 'message' => 'Lien envoyé si l\'adresse existe.']);
        }
        redirect($loginUrl, ['success' => 'Si cet e-mail existe, un lien a été envoyé.']);
    }

    public function resetPasswordForm(string $token): void
    {
        if (Session::isLoggedIn()) {
            redirect('/');
        }

        $user = $this->users->findByResetToken($token);
        if (!$user) {
            redirect('/login', ['error' => 'Le lien de réinitialisation est invalide ou a expiré.']);
        }

        $this->render('auth/reset_password', ['title' => 'Nouveau mot de passe', 'token' => $token]);
    }

    public function resetPassword(string $token): void
    {
        $this->verifyCsrf();

        $user = $this->users->findByResetToken($token);
        if (!$user) {
            redirect('/login', ['error' => 'Le lien de réinitialisation est invalide ou a expiré.']);
        }

        $v = (new Validator($_POST))
            ->required('password')
            ->minLength('password', 8);

        if (!$v->passes()) {
            redirect('/reset-password/' . $token, ['error' => 'Le mot de passe doit faire au moins 8 caractères.']);
        }

        if ($_POST['password'] !== ($_POST['password_confirm'] ?? '')) {
            redirect('/reset-password/' . $token, ['error' => 'Les mots de passe ne correspondent pas.']);
        }

        $this->users->updatePassword((int)$user['id'], $_POST['password']);
        $this->users->clearResetToken((int)$user['id']);

        redirect('/login', ['success' => 'Votre mot de passe a été mis à jour avec succès.']);
    }

    public function captchaImage(): void
    {
        \KronoConnect\Core\Captcha::generateImage();
    }

    /**
     * API pour récupérer les détails d'une application (statut, logs) pour le portail
     */
    public function appDetails(): void
    {
        if (!Session::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }

        $userId = Session::userId();
        $clientId = (int)($_GET['id'] ?? 0);

        $db = \KronoConnect\Core\Database::getInstance();
        $tClients = $db->t('sso_clients');
        $client = $db->fetchOne("SELECT * FROM `$tClients` WHERE id = ?", [$clientId]);

        if (!$client) {
            http_response_code(404);
            echo json_encode(['error' => 'App introuvable']);
            exit;
        }

        // Ping de statut via le manifest et récupération de la description
        $status = 'offline';
        $description = null;
        $uris = json_decode($client['redirect_uris'], true);
        if (!empty($uris)) {
            $parsed  = parse_url($uris[0]);
            $baseUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
            if (!empty($parsed['port'])) { $baseUrl .= ':' . $parsed['port']; }
            
            // Si le chemin contient un sous-dossier, on tente de le conserver pour la racine de l'app
            $path = $parsed['path'] ?? '';
            $pathParts = explode('/', trim($path, '/'));
            if (count($pathParts) > 1) {
                $baseUrl .= '/' . $pathParts[0];
            }
            
            $manifestUrl = $baseUrl . '/kronoconnect/manifest';
            
            $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 1, 'ignore_errors' => true]]);
            $responseBody = @file_get_contents($manifestUrl, false, $context);
            if ($responseBody !== false) {
                $manifestData = json_decode($responseBody, true);
                // On n'est 'online' que si le JSON est valide et contient un nom d'app
                if (is_array($manifestData) && !empty($manifestData['name'])) {
                    $status = 'online';
                    if (!empty($manifestData['description'])) {
                        $description = (string)$manifestData['description'];
                    }
                }
            }
        }

        // Récupération des dates de connexion
        $tLogs = $db->t('sso_connection_logs');
        $logs = $db->fetchOne("
            SELECT MIN(created_at) as first_login, MAX(created_at) as last_login 
            FROM `$tLogs` 
            WHERE user_id = ? AND client_id = ?
        ", [$userId, $client['client_id']]);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'description' => $description,
            'first_login' => $logs['first_login'] ? date('d/m/Y à H:i', strtotime($logs['first_login'])) : 'Jamais',
            'last_login'  => $logs['last_login']  ? date('d/m/Y à H:i', strtotime($logs['last_login']))  : 'Jamais'
        ]);
        exit;
    }

    /**
     * Helper pour détecter AJAX
     */
    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Sert le logo de la collectivité de manière publique (inline).
     */
    public function publicLogo(): void
    {
        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
        $uuid = $settings['logo_uuid'] ?? null;

        if (!$uuid) {
            http_response_code(404);
            echo "No logo_uuid in settings";
            exit;
        }

        $db = \KronoConnect\Core\Database::getInstance();
        $tableName = $db->t('kronoconnect_files');
        // KronoConnect utilise KronoConnect_files selon FileManager
        $stmt = $db->getRawPdo()->prepare("SELECT * FROM `{$tableName}` WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $fileInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$fileInfo) {
            http_response_code(404);
            echo "File not found in DB: $uuid";
            exit;
        }

        $path = ROOT_PATH . '/storage/files/' . $uuid . '.' . $fileInfo['extension'];

        if (!file_exists($path)) {
            http_response_code(404);
            echo "File not found on disk: $path";
            exit;
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        $maxAge = 86400 * 7; // Cache navigateur 1 semaine
        header('Content-Type: ' . $fileInfo['mime_type']);
        header('Content-Disposition: inline; filename="logo.' . $fileInfo['extension'] . '"');
        header('Cache-Control: public, max-age=' . $maxAge);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        header('Content-Length: ' . filesize($path));

        readfile($path);
        exit;
    }

    private function getWebAuthnServer(): WebAuthn
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $rpId = explode(':', $host)[0];
        return new WebAuthn('KronoConnect', $rpId, true);
    }

    public function webauthnAssertionOptions(): void
    {
        $userId = Session::get('pending_mfa_user_id');
        if (!$userId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Session MFA expirée.']);
            return;
        }

        $server = $this->getWebAuthnServer();

        $credentials = $this->users->getWebAuthnCredentials((int)$userId);
        $allowedIds = [];
        foreach ($credentials as $cred) {
            $allowedIds[] = base64_decode(strtr($cred['credential_id'], '-_', '+/'));
        }

        if (empty($allowedIds)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Aucune clé enregistrée pour cet utilisateur.']);
            return;
        }

        $getArgs = $server->getGetArgs(
            $allowedIds,
            20,
            true, // allowUsb
            true, // allowNfc
            true, // allowBle
            true, // allowHybrid
            true, // allowInternal
            false // requireUserVerification
        );

        Session::set('webauthn_challenge', $server->getChallenge()->jsonSerialize());

        header('Content-Type: application/json');
        echo json_encode($getArgs);
    }

    public function webauthnVerify(): void
    {
        $userId = Session::get('pending_mfa_user_id');
        $challenge = Session::get('webauthn_challenge');
        $flowId = Session::get('pending_mfa_flow') ?? '';
        $rememberMe = (bool)Session::get('pending_mfa_remember');

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!$userId || !$data || !$challenge) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Session ou données manquantes.']);
            return;
        }

        try {
            $server = $this->getWebAuthnServer();
            
            $clientDataJSON = base64_decode($data['response']['clientDataJSON']);
            $authenticatorData = base64_decode($data['response']['authenticatorData']);
            $signature = base64_decode($data['response']['signature']);
            
            $credentialId = $data['id'];
            
            $dbCred = $this->users->findWebAuthnCredential($credentialId);
            if (!$dbCred || (int)$dbCred['user_id'] !== (int)$userId) {
                throw new \Exception('Clé de sécurité non reconnue ou invalide.');
            }

            $challengeBuffer = ByteBuffer::fromBase64Url($challenge);

            $server->processGet(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $dbCred['public_key'],
                $challengeBuffer,
                (int)$dbCred['counter'],
                false
            );

            $newCounter = $server->getSignatureCounter() ?? 0;
            $this->users->updateWebAuthnCounter($credentialId, $newCounter);

            $user = $this->users->findById((int)$userId);
            if (!$user) {
                throw new \Exception('Utilisateur introuvable.');
            }

            Session::remove('pending_mfa_user_id');
            Session::remove('pending_mfa_remember');
            Session::remove('pending_mfa_flow');
            Session::remove('webauthn_challenge');

            Logger::info('Utilisateur connecté par clé de sécurité WebAuthn', ['user_id' => $userId]);

            // Finaliser la connexion de l'utilisateur
            Session::login($user);
            $this->users->updateLastActivity((int)$user['id']);

            if ($rememberMe) {
                $token  = \KronoConnect\Core\Security::generateToken(32);
                $config = require CONFIG_PATH . '/app.php';
                $this->users->setRememberToken((int) $user['id'], $token);
                setcookie(
                    $config['remember_me']['cookie_name'],
                    $token,
                    time() + $config['remember_me']['lifetime'],
                    '/',
                    '',
                    false,
                    true
                );
            }

            $redirectUrl = '/';
            $pending = $flowId ? Session::get('sso_flow_' . $flowId) : null;
            if ($pending) {
                $pending['from_login'] = '1';
                Session::remove('sso_flow_' . $flowId);
                $redirectUrl = '/sso/authorize?' . http_build_query($pending);
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect_url' => $redirectUrl]);
        } catch (\Exception $e) {
            Logger::error('WebAuthn Authentication failed : ' . $e->getMessage(), ['userId' => $userId]);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
