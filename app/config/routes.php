<?php
declare(strict_types=1);

use KronoConnect\Controllers\AuthController;
use KronoConnect\Controllers\SsoController;
use KronoConnect\Controllers\ApiController;
use KronoConnect\Controllers\AdminController;
use KronoConnect\Controllers\ProfileController;
use KronoConnect\Controllers\NotificationController;
use KronoConnect\Controllers\NotificationCenterController;
use KronoConnect\Controllers\UpdateController;

// ── Authentification ──────────────────────────────────────────────────────
$router->get('/login',    [AuthController::class, 'loginForm']);
$router->post('/login',   [AuthController::class, 'login']);
$router->get('/logout',   [AuthController::class, 'logout']);
$router->post('/logout',  [AuthController::class, 'logout']);
$router->get('/register', [AuthController::class, 'registerForm']);
$router->post('/register',[AuthController::class, 'register']);
$router->get('/login/mfa', [AuthController::class, 'mfaForm']);
$router->post('/login/mfa', [AuthController::class, 'mfaVerify']);
$router->get('/login/mfa-setup', [AuthController::class, 'mfaSetupForm']);
$router->post('/login/mfa-setup', [AuthController::class, 'mfaSetupVerify']);
$router->post('/verify-code', [AuthController::class, 'verifyCode']);
$router->get('/login/mfa-codes', [AuthController::class, 'mfaCodesForm']);
$router->post('/login/mfa-codes-confirm', [AuthController::class, 'mfaCodesConfirm']);
$router->get('/login/webauthn/assertion-options', [AuthController::class, 'webauthnAssertionOptions']);
$router->post('/login/webauthn/verify', [AuthController::class, 'webauthnVerify']);
$router->post('/reset-password', [AuthController::class, 'forgotPassword']); // Route pour le panel "mot de passe oublié"
$router->get('/reset-password/{token}', [AuthController::class, 'resetPasswordForm']);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPassword']);
$router->get('/captcha/image',   [AuthController::class, 'captchaImage']);
$router->get('/public/logo',     [AuthController::class, 'publicLogo']);
// ── Profil Utilisateur ────────────────────────────────────────────────────
$router->get('/profile',          [ProfileController::class, 'index']);
$router->post('/profile/update',  [ProfileController::class, 'update']);
$router->post('/profile/portal-order', [ProfileController::class, 'updatePortalOrder']);
$router->post('/profile/password',[ProfileController::class, 'updatePassword']);
$router->get('/profile/mfa-setup', [ProfileController::class, 'mfaSetup']);
$router->post('/profile/mfa-setup', [ProfileController::class, 'mfaVerifySetup']);
$router->post('/profile/mfa-disable', [ProfileController::class, 'mfaDisable']);
$router->get('/profile/mfa-codes', [ProfileController::class, 'mfaCodes']);
$router->post('/profile/mfa-regenerate-codes', [ProfileController::class, 'mfaRegenerateCodes']);
$router->get('/profile/webauthn/register-options', [ProfileController::class, 'webauthnRegisterOptions']);
$router->post('/profile/webauthn/register', [ProfileController::class, 'webauthnRegister']);
$router->post('/profile/webauthn/delete', [ProfileController::class, 'webauthnDelete']);
$router->post('/profile/theme',   [ProfileController::class, 'updateTheme']);
$router->post('/profile/export',  [ProfileController::class, 'exportData']);
$router->post('/profile/delete',  [ProfileController::class, 'deleteAccount']);

// ── SSO (flux OAuth2 simplifié) ───────────────────────────────────────────
$router->get('/sso/authorize',  [SsoController::class, 'authorize']);
$router->post('/sso/authorize', [SsoController::class, 'consent']);

// ── API stateless (server-to-server) ─────────────────────────────────────
$router->get('/api/v1/ping', [ApiController::class, 'ping']);
$router->get('/api/v1/services', [ApiController::class, 'getServices']);
$router->post('/api/token', [ApiController::class, 'token']);
$router->get('/api/v1/user/{token}', [ApiController::class, 'getUserInfo']);
$router->post('/api/v1/manifest', [ApiController::class, 'syncManifest']);

// Hub de notifications centralisé
$router->post('/api/v1/notifications',           [NotificationController::class, 'send']);
$router->get('/api/v1/notifications',            [NotificationController::class, 'listUnread']);
$router->post('/api/v1/notifications/unread',    [NotificationController::class, 'listUnread']);
$router->get('/api/v1/notifications/history',    [NotificationController::class, 'history']);
$router->post('/api/v1/notifications/history',   [NotificationController::class, 'history']);
$router->post('/api/v1/notifications/mark-read', [NotificationController::class, 'markRead']);

// ── Centre de notifications (utilisateur connecté) ───────────────────────
$router->get('/notifications',              [NotificationCenterController::class, 'index']);
$router->get('/notifications/unread',       [NotificationCenterController::class, 'unread']);
$router->post('/notifications/mark-read',   [NotificationCenterController::class, 'markRead']);

// ── Panel Admin ───────────────────────────────────────────────────────────
$router->get('/admin',                    [AdminController::class, 'dashboard']);
$router->get('/admin/settings',           [AdminController::class, 'settings']);
$router->post('/admin/settings',          [AdminController::class, 'settingsUpdate']);
$router->post('/admin/settings/db',       [AdminController::class, 'saveDatabase']);
$router->get('/admin/settings/db/export', [AdminController::class, 'exportDatabase']);
$router->post('/admin/settings/test-email', [AdminController::class, 'testEmail']);

$router->get('/admin/clients',            [AdminController::class, 'clients']);
$router->get('/admin/clients/create',     [AdminController::class, 'clientCreate']);
$router->get('/admin/clients/created',    [AdminController::class, 'clientCreated']);
$router->get('/admin/clients/{id}',        [AdminController::class, 'clientDetail']);
$router->post('/admin/clients/setup-refuse', [AdminController::class, 'clientSetupRefuse']);
$router->post('/admin/clients/test-manifest', [AdminController::class, 'clientTestManifest']);
$router->post('/admin/clients',           [AdminController::class, 'clientStore']);
$router->post('/admin/clients/{id}/regenerate-secret', [AdminController::class, 'clientRegenerateSecret']);
$router->post('/admin/clients/delete',    [AdminController::class, 'clientDelete']);
$router->post('/admin/clients/sync',      [AdminController::class, 'clientSyncManifest']);
$router->get('/admin/clients/{id}/access', [AdminController::class, 'clientAccess']);
$router->post('/admin/clients/{id}/access-mode', [AdminController::class, 'clientAccessMode']);
$router->post('/admin/clients/{id}/allowed-ips', [AdminController::class, 'clientAllowedIps']);
$router->post('/admin/clients/{id}/access-grant', [AdminController::class, 'clientAccessGrant']);
$router->post('/admin/clients/{id}/access-revoke', [AdminController::class, 'clientAccessRevoke']);

$router->get('/admin/links',              [AdminController::class, 'links']);
$router->get('/admin/links/create',       [AdminController::class, 'linkCreate']);
$router->get('/admin/links/{id}',         [AdminController::class, 'linkDetail']);
$router->post('/admin/links',             [AdminController::class, 'linkStore']);
$router->post('/admin/links/delete',      [AdminController::class, 'linkDelete']);
$router->post('/admin/links/{id}/access-mode', [AdminController::class, 'linkAccessMode']);
$router->post('/admin/links/{id}/access-grant', [AdminController::class, 'linkAccessGrant']);
$router->post('/admin/links/{id}/access-revoke', [AdminController::class, 'linkAccessRevoke']);

$router->get('/admin/groups',             [AdminController::class, 'groups']);
$router->get('/admin/groups/new',         [AdminController::class, 'groupNew']);
$router->post('/admin/groups',            [AdminController::class, 'groupStore']);
$router->post('/admin/groups/delete',     [AdminController::class, 'groupDelete']);
$router->get('/admin/groups/{id}',        [AdminController::class, 'groupDetail']);
$router->post('/admin/groups/{id}/info',  [AdminController::class, 'groupUpdateInfo']);
$router->post('/admin/groups/{id}/members', [AdminController::class, 'groupUpdateMembers']);
$router->post('/admin/groups/{id}/permissions', [AdminController::class, 'groupUpdatePermissions']);

$router->get('/admin/logs',              [AdminController::class, 'logs']);

// ── Gestion des Services ──────────────────────────────────────────────────
$router->get('/admin/services',            [AdminController::class, 'services']);
$router->post('/admin/services',           [AdminController::class, 'serviceStore']);
$router->post('/admin/services/update',    [AdminController::class, 'serviceUpdate']);
$router->post('/admin/services/order',     [AdminController::class, 'serviceOrder']);
$router->post('/admin/services/delete',    [AdminController::class, 'serviceDelete']);

$router->get('/admin/users',              [AdminController::class, 'users']);
$router->get('/admin/users/search',       [AdminController::class, 'userSearch']);
$router->post('/admin/users/save',        [AdminController::class, 'userSave']);
$router->get('/admin/users/{id}',         [AdminController::class, 'userDetail']);
$router->post('/admin/users/toggle',      [AdminController::class, 'userToggle']);
$router->post('/admin/users/{id}/approve', [AdminController::class, 'approveUser']);
$router->post('/admin/users/{id}/reject',  [AdminController::class, 'rejectUser']);
$router->post('/admin/users/{id}/delete',  [AdminController::class, 'userDelete']);
$router->post('/admin/users/{id}/permissions', [AdminController::class, 'userUpdatePermissions']);
$router->post('/admin/users/{id}/mfa-disable', [AdminController::class, 'userMfaDisable']);

// ── Mises à jour ──────────────────────────────────────────────────────────
$router->post('/admin/updates/check',  [UpdateController::class, 'check']);
$router->post('/admin/updates/apply',  [UpdateController::class, 'apply']);
$router->get('/admin/updates/history', [UpdateController::class, 'history']);

// ── Racine → page de connexion ────────────────────────────────────────────
$router->get('/', [AuthController::class, 'loginForm']);
$router->get('/portal/app-details', [AuthController::class, 'appDetails']);
