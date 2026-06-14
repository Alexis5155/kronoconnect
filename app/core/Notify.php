<?php
declare(strict_types=1);

namespace KronoConnect\Core;

use KronoConnect\Models\NotificationModel;
use KronoConnect\Models\UserModel;

/**
 * Façade d'émission de notifications côté KronoConnect lui-même.
 *
 * Toutes les notifications créées via cette classe sont enregistrées avec
 * `client_id = NULL`, ce qui signifie : émises par le hub KronoConnect
 * (alertes système, changements de compte, etc.). Le centre de notifications
 * et la cloche les affichent avec le branding KC.
 *
 * Pour les notifications émises *par* une application cliente, c'est
 * NotificationController (HMAC) qui s'en charge — pas cette classe.
 */
class Notify
{
    /**
     * Émet une notification interne à destination d'un utilisateur (par email).
     */
    public static function email(
        string $email,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null
    ): bool {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('Notify (KC) : email invalide', ['email' => $email]);
            return false;
        }

        $user = (new UserModel())->findByEmail($email);
        if (!$user) {
            Logger::warning('Notify (KC) : utilisateur introuvable', ['email' => $email]);
            return false;
        }

        return self::byUserId((int) $user['id'], $title, $message, $type, $url);
    }

    /**
     * Émet une notification interne à destination d'un user_id KronoConnect.
     */
    public static function byUserId(
        int $userId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null
    ): bool {
        $type    = self::normalizeType($type);
        $title   = trim($title);
        $message = trim($message);

        if ($title === '' || $message === '') {
            Logger::warning('Notify (KC) : titre ou message vide', ['user_id' => $userId]);
            return false;
        }

        // Caps anti-DoS + validation URL (XSS via href).
        if (mb_strlen($title)   > NotificationModel::MAX_TITLE_LEN)   { $title   = mb_substr($title,   0, NotificationModel::MAX_TITLE_LEN); }
        if (mb_strlen($message) > NotificationModel::MAX_MESSAGE_LEN) { $message = mb_substr($message, 0, NotificationModel::MAX_MESSAGE_LEN); }

        $safeUrl = NotificationModel::sanitizeUrl($url);
        if ($safeUrl === false) {
            Logger::warning('Notify (KC) : URL rejetée', ['user_id' => $userId, 'url' => $url]);
            return false;
        }

        try {
            (new NotificationModel())->create(
                $userId,
                null,            // ⇒ émetteur = KronoConnect lui-même
                $type,
                $title,
                $message,
                $safeUrl
            );
            return true;
        } catch (\Throwable $e) {
            Logger::error('Notify (KC) : insertion échouée', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Diffuse une notification à tous les utilisateurs actifs.
     * À manier avec précaution — un INSERT par utilisateur.
     *
     * @return int  Nombre d'utilisateurs effectivement notifiés.
     */
    public static function broadcast(
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null
    ): int {
        $type   = self::normalizeType($type);
        $db     = Database::getInstance();
        $tUsers = $db->t('users');
        $users  = $db->fetchAll("SELECT id FROM `{$tUsers}` WHERE is_active = 1");

        $model = new NotificationModel();
        $count = 0;

        foreach ($users as $u) {
            try {
                $model->create((int) $u['id'], null, $type, $title, $message, $url);
                $count++;
            } catch (\Throwable $e) {
                Logger::warning('Notify::broadcast : insertion échouée pour un user', [
                    'user_id' => $u['id'],
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Logger::info('Notify::broadcast effectué', [
            'count' => $count,
            'title' => $title,
            'type'  => $type,
        ]);

        return $count;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === 'alert' || $type === 'danger') {
            $type = 'error';
        }
        return in_array($type, ['info', 'success', 'warning', 'error'], true) ? $type : 'info';
    }
}
