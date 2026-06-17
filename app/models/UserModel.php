<?php
declare(strict_types=1);

namespace KronoConnect\Models;

use KronoConnect\Core\Security;

class UserModel extends BaseModel
{
    protected string $table = 'users';

    protected function findBy(string $column, mixed $value): ?array
    {
        $tUsers = $this->db->t('users');
        $tServices = $this->db->t('services');
        $tGroupMembers = $this->db->t('group_members');
        $tGroups = $this->db->t('groups');
        
        return $this->db->fetchOne("
            SELECT u.*, s.name as service_name, g.tech_name AS role, g.name AS group_name, g.id AS group_id
            FROM `{$tUsers}` u 
            LEFT JOIN `{$tServices}` s ON u.service_id = s.id 
            LEFT JOIN `{$tGroupMembers}` gm ON u.id = gm.user_id
            LEFT JOIN `{$tGroups}` g ON gm.group_id = g.id
            WHERE u.`{$column}` = ?
        ", [$value]) ?: null;
    }

    public function findById(int $id): ?array
    {
        $user = $this->findBy('id', $id);
        if ($user) {
            $user['permissions'] = $this->getCompiledSystemPermissions((int)$user['id']);
        }
        return $user;
    }

    public function findByEmail(string $email): ?array
    {
        $user = $this->findBy('email', strtolower($email));
        if ($user) {
            $user['permissions'] = $this->getCompiledSystemPermissions((int)$user['id']);
        }
        return $user;
    }

    public function findByRememberToken(string $token): ?array
    {
        $user = $this->findBy('remember_token', $token);
        if ($user) {
            $user['permissions'] = $this->getCompiledSystemPermissions((int)$user['id']);
        }
        return $user;
    }

    public function create(string $email, string $password, string $nom, string $prenom, string $status = 'actif', ?string $verificationToken = null): int
    {
        $userId = $this->db->insert('users', [
            'email'              => strtolower($email),
            'password'           => Security::hashPassword($password),
            'nom'                => $nom,
            'prenom'             => $prenom,
            'status'             => $status,
            'verification_token' => $verificationToken,
        ]);

        $tGroups = $this->db->t('groups');
        $tGroupMembers = $this->db->t('group_members');
        $group = $this->db->fetchOne("SELECT id FROM `{$tGroups}` WHERE tech_name = 'user' LIMIT 1");
        if ($group) {
            $this->db->query(
                "INSERT IGNORE INTO `{$tGroupMembers}` (group_id, user_id) VALUES (?, ?)",
                [$group['id'], $userId]
            );
        }

        return $userId;
    }

    public function findByVerificationToken(string $email, string $token): ?array
    {
        $tUsers = $this->db->t('users');
        $tGroupMembers = $this->db->t('group_members');
        $tGroups = $this->db->t('groups');
        
        $user = $this->db->fetchOne("
            SELECT u.*, g.tech_name AS role, g.name AS group_name, g.id AS group_id
            FROM `{$tUsers}` u
            LEFT JOIN `{$tGroupMembers}` gm ON u.id = gm.user_id
            LEFT JOIN `{$tGroups}` g ON gm.group_id = g.id
            WHERE u.email = ? AND u.verification_token = ?
        ", [strtolower($email), $token]);
        
        if ($user) {
            $user['permissions'] = $this->getCompiledSystemPermissions((int)$user['id']);
        }
        return $user;
    }

    public function clearVerificationToken(int $id, string $newStatus = 'actif'): void
    {
        $this->db->update('users', [
            'verification_token' => null,
            'status'             => $newStatus
        ], ['id' => $id]);
    }

    public function setRememberToken(int $id, string $token): void
    {
        $this->db->update('users', ['remember_token' => $token], ['id' => $id]);
    }

    public function setSsoToken(int $id, string $token): void
    {
        $this->db->update('users', ['sso_token' => $token], ['id' => $id]);
    }

    public function clearRememberToken(int $id): void
    {
        $this->db->update('users', ['remember_token' => null], ['id' => $id]);
    }

    public function updateProfile(int $id, string $nom, string $prenom, string $email, ?string $phone = null): void
    {
        $this->db->update('users', [
            'nom'    => $nom,
            'prenom' => $prenom,
            'email'  => strtolower($email),
            'phone'  => $phone
        ], ['id' => $id]);
    }

    public function updateLastActivity(int $id): void
    {
        $this->db->update('users', ['last_activity_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    public function updatePassword(int $id, string $newPassword): void
    {
        $this->db->update('users', [
            'password' => Security::hashPassword($newPassword)
        ], ['id' => $id]);
    }

    public function updateTheme(int $id, string $theme): void
    {
        $this->db->update('users', ['theme' => $theme], ['id' => $id]);
    }

    public function toggleEmail(int $id, int $canChangeEmail): void
    {
        $this->db->update('users', ['can_change_email' => $canChangeEmail], ['id' => $id]);
    }

    public function setResetToken(int $id, string $token, string $expiresAt): void
    {
        $this->db->update('users', [
            'reset_token' => $token,
            'reset_token_expires_at' => $expiresAt
        ], ['id' => $id]);
    }

    public function findByResetToken(string $token): ?array
    {
        $t = $this->db->t('users');
        return $this->db->fetchOne(
            "SELECT * FROM `{$t}` WHERE reset_token = ? AND reset_token_expires_at > NOW()",
            [$token]
        );
    }

    public function clearResetToken(int $id): void
    {
        $this->db->update('users', [
            'reset_token' => null,
            'reset_token_expires_at' => null
        ], ['id' => $id]);
    }

    public function enableMfa(int $id, string $secret): void
    {
        $this->db->update('users', [
            'mfa_secret' => $secret,
            'mfa_enabled' => 1
        ], ['id' => $id]);
    }

    public function disableMfa(int $id): void
    {
        $this->db->update('users', [
            'mfa_secret' => null,
            'mfa_enabled' => 0
        ], ['id' => $id]);
        $this->deleteRecoveryCodes($id);
        $this->deleteWebAuthnCredentials($id);
    }

    public function generateAndStoreRecoveryCodes(int $userId): array
    {
        $tCodes = $this->db->t('user_mfa_recovery_codes');
        // Supprimer les anciens codes de secours
        $this->db->query("DELETE FROM `{$tCodes}` WHERE user_id = ?", [$userId]);

        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $bytes = random_bytes(4);
            $hex = bin2hex($bytes);
            $code = strtoupper(substr($hex, 0, 4) . '-' . substr($hex, 4, 4));
            $codes[] = $code;

            $this->db->insert('user_mfa_recovery_codes', [
                'user_id' => $userId,
                'code_hash' => password_hash($code, PASSWORD_BCRYPT)
            ]);
        }

        return $codes;
    }

    public function verifyRecoveryCode(int $userId, string $code): bool
    {
        $tCodes = $this->db->t('user_mfa_recovery_codes');
        $records = $this->db->fetchAll(
            "SELECT id, code_hash FROM `{$tCodes}` WHERE user_id = ? AND used_at IS NULL",
            [$userId]
        );

        foreach ($records as $record) {
            if (password_verify(strtoupper(trim($code)), $record['code_hash'])) {
                // Marquer comme utilisé
                $this->db->query(
                    "UPDATE `{$tCodes}` SET used_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$record['id']]
                );
                return true;
            }
        }

        return false;
    }

    public function getRemainingRecoveryCodesCount(int $userId): int
    {
        $tCodes = $this->db->t('user_mfa_recovery_codes');
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM `{$tCodes}` WHERE user_id = ? AND used_at IS NULL",
            [$userId]
        );
        return $row ? (int)$row['count'] : 0;
    }

    public function deleteRecoveryCodes(int $userId): void
    {
        $tCodes = $this->db->t('user_mfa_recovery_codes');
        $this->db->query("DELETE FROM `{$tCodes}` WHERE user_id = ?", [$userId]);
    }

    public function getWebAuthnCredentials(int $userId): array
    {
        $tWebAuthn = $this->db->t('user_webauthn_credentials');
        return $this->db->fetchAll(
            "SELECT id, credential_id, name, created_at, counter FROM `{$tWebAuthn}` WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );
    }

    public function addWebAuthnCredential(int $userId, string $credentialId, string $publicKey, string $name): void
    {
        $this->db->insert('user_webauthn_credentials', [
            'user_id' => $userId,
            'credential_id' => $credentialId,
            'public_key' => $publicKey,
            'name' => $name
        ]);

        $this->db->update('users', ['mfa_enabled' => 1], ['id' => $userId]);
    }

    public function deleteWebAuthnCredential(int $userId, int $keyId): void
    {
        $tWebAuthn = $this->db->t('user_webauthn_credentials');
        $this->db->query("DELETE FROM `{$tWebAuthn}` WHERE user_id = ? AND id = ?", [$userId, $keyId]);

        // Si l'utilisateur n'a plus de clé WebAuthn ET n'a pas de secret TOTP, on désactive le MFA
        $user = $this->findById($userId);
        $hasKeys = $this->hasWebAuthnCredentials($userId);
        if (!$hasKeys && empty($user['mfa_secret'])) {
            $this->db->update('users', ['mfa_enabled' => 0], ['id' => $userId]);
            $this->deleteRecoveryCodes($userId);
        }
    }

    public function updateWebAuthnCounter(string $credentialId, int $counter): void
    {
        $tWebAuthn = $this->db->t('user_webauthn_credentials');
        $this->db->query("UPDATE `{$tWebAuthn}` SET counter = ? WHERE credential_id = ?", [$counter, $credentialId]);
    }

    public function findWebAuthnCredential(string $credentialId): ?array
    {
        $tWebAuthn = $this->db->t('user_webauthn_credentials');
        return $this->db->fetchOne("SELECT * FROM `{$tWebAuthn}` WHERE credential_id = ? LIMIT 1", [$credentialId]);
    }

    public function hasWebAuthnCredentials(int $userId): bool
    {
        $tWebAuthn = $this->db->t('user_webauthn_credentials');
        $row = $this->db->fetchOne("SELECT COUNT(*) as count FROM `{$tWebAuthn}` WHERE user_id = ?", [$userId]);
        return $row ? (int)$row['count'] > 0 : false;
    }

    public function deleteWebAuthnCredentials(int $userId): void
    {
        $tWebAuthn = $this->db->t('user_webauthn_credentials');
        $this->db->query("DELETE FROM `{$tWebAuthn}` WHERE user_id = ?", [$userId]);
    }

    /**
     * Calcule et consolide les permissions système (KronoConnect) d'un utilisateur
     * en combinant les droits de ses groupes et ses surcharges individuelles (client_id IS NULL).
     */
    public function getCompiledSystemPermissions(int $userId): array
    {
        $tGroupMembers = $this->db->t('group_members');
        $tGroupPermissions = $this->db->t('group_permissions');
        $tUserPermissions = $this->db->t('user_permissions');

        // 1. Charger les permissions système héritées des groupes
        $groupPerms = $this->db->fetchAll("
            SELECT gp.perm_key
            FROM `{$tGroupPermissions}` gp
            JOIN `{$tGroupMembers}` gm ON gp.group_id = gm.group_id
            WHERE gm.user_id = ? AND gp.client_id IS NULL
        ", [$userId]);
        $perms = array_column($groupPerms, 'perm_key');

        // 2. Charger les surcharges utilisateur système (client_id IS NULL)
        $userPerms = $this->db->fetchAll("
            SELECT perm_key, granted
            FROM `{$tUserPermissions}`
            WHERE user_id = ? AND client_id IS NULL
        ", [$userId]);

        foreach ($userPerms as $up) {
            $key = $up['perm_key'];
            if ((int)$up['granted'] === 1) {
                if (!in_array($key, $perms, true)) {
                    $perms[] = $key;
                }
            } else {
                $perms = array_diff($perms, [$key]);
            }
        }

        // Resolving parent dependencies and pruning if parent not granted
        $configPath = CONFIG_PATH . '/permissions.php';
        $parentsMap = [];
        if (file_exists($configPath)) {
            $kcPermissions = require $configPath;
            foreach ($kcPermissions as $perm) {
                if (!empty($perm['parent_key'])) {
                    $parentsMap[$perm['key']] = $perm['parent_key'];
                } elseif (!empty($perm['requires'])) {
                    $parentsMap[$perm['key']] = $perm['requires'];
                }
            }
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($perms as $i => $perm) {
                if (isset($parentsMap[$perm])) {
                    $parent = $parentsMap[$perm];
                    if (!in_array($parent, $perms, true)) {
                        unset($perms[$i]);
                        $perms = array_values($perms);
                        $changed = true;
                        break;
                    }
                }
            }
        }

        return array_values($perms);
    }
}
