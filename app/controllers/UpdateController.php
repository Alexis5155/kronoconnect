<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Core\Database;
use KronoConnect\Core\Security;
use KronoConnect\Core\Session;
use KronoConnect\Services\UpdateService;

class UpdateController extends BaseController
{
    private array $config;
    private UpdateService $service;

    public function __construct()
    {
        if (Session::get('user_role') !== 'super_admin') {
            $this->json([
                'update_available' => false, 
                'error' => 'Accès réservé aux super-administrateurs.'
            ], 403);
        }

        $this->config = require CONFIG_PATH . '/app.php';
        $this->service = new UpdateService(Database::getInstance()->getRawPdo(), $this->config);
    }

    public function check(): void
    {
        Security::verifyCsrf();
        Session::close();
        $this->json($this->service->check());
    }

    public function download(): void
    {
        Security::verifyCsrf();

        $version = trim($_POST['version'] ?? '');

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            $this->json(['ok' => false, 'error' => 'Version invalide.'], 400);
        }

        Session::close();

        try {
            $path = $this->service->download($version);
            $this->json(['ok' => true, 'path' => basename($path)]);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apply(): void
    {
        Security::verifyCsrf();

        @ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-cache');

        $version = trim($_POST['version'] ?? '');

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            echo json_encode(['type' => 'error', 'msg' => 'Version invalide.']) . "\n";
            flush();
            return;
        }

        $log = function (string $logType, string $msg): void {
            echo json_encode(['type' => $logType, 'msg' => $msg]) . "\n";
            flush();
        };

        Session::close();

        try {
            $log('info', "Récupération de l'archive…");
            $zipPath = $this->service->download($version);
            $log('success', 'Archive prête.');

            $this->service->apply($zipPath, $version, $log);

            try {
                $this->notifyUpdateSuccess($version);
            } catch (\Throwable $t) {
                \KronoConnect\Core\Logger::error("Notification de mise à jour échouée: " . $t->getMessage());
            }

            $log('done', "v{$version}");
        } catch (\RuntimeException $e) {
            $log('error', $e->getMessage());
        }
    }

    private function notifyUpdateSuccess(string $newVersion): void
    {
        $db = Database::getInstance();
        $tUsers = $db->t('users');
        $tGroupMembers = $db->t('group_members');
        $tGroups = $db->t('groups');

        $users = $db->fetchAll("
            SELECT u.id, g.tech_name AS role
            FROM `{$tUsers}` u
            LEFT JOIN `{$tGroupMembers}` gm ON u.id = gm.user_id
            LEFT JOIN `{$tGroups}` g ON gm.group_id = g.id
            WHERE u.is_active = 1 AND u.status = 'actif'
        ");

        $userModel = new \KronoConnect\Models\UserModel();
        $notificationModel = new \KronoConnect\Models\NotificationModel();

        foreach ($users as $user) {
            $userId = (int)$user['id'];
            $role = $user['role'] ?? '';

            $hasPerm = ($role === 'super_admin');
            if (!$hasPerm) {
                $perms = $userModel->getCompiledSystemPermissions($userId);
                if (in_array('kc.settings.update', $perms, true)) {
                    $hasPerm = true;
                }
            }

            if ($hasPerm) {
                $notificationModel->create(
                    $userId,
                    null, // Central SSO
                    'success',
                    'Mise à jour système effectuée',
                    "La mise à jour de KronoConnect vers la version v{$newVersion} a été installée avec succès.",
                    url('/admin/settings#updates')
                );
            }
        }
    }

    public function history(): void
    {
        $this->json($this->service->history());
    }
}