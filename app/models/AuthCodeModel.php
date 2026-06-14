<?php
declare(strict_types=1);

namespace KronoConnect\Models;

use KronoConnect\Core\Security;

class AuthCodeModel extends BaseModel
{
    protected string $table = 'sso_auth_codes';

    public function create(string $clientId, int $userId): string
    {
        $code      = Security::generateToken(32); // 64 hex chars
        $expiresAt = date('Y-m-d H:i:s', time() + 60);

        $this->db->insert('sso_auth_codes', [
            'client_id'  => $clientId,
            'user_id'    => $userId,
            'code'       => $code,
            'expires_at' => $expiresAt,
        ]);

        return $code;
    }

    public function findValid(string $code): ?array
    {
        $t = $this->db->t('sso_auth_codes');
        return $this->db->fetchOne(
            "SELECT * FROM `{$t}` WHERE `code` = ? AND `used` = 0 AND `expires_at` > NOW()",
            [$code]
        ) ?: null;
    }

    public function markUsed(int $id): void
    {
        $this->db->update('sso_auth_codes', ['used' => 1], ['id' => $id]);
    }

    public function purgeExpired(): void
    {
        $t = $this->db->t('sso_auth_codes');
        $this->db->query("DELETE FROM `{$t}` WHERE `expires_at` < NOW() OR `used` = 1");
    }
}
