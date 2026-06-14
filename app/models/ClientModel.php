<?php
declare(strict_types=1);

namespace KronoConnect\Models;

class ClientModel extends BaseModel
{
    protected string $table = 'sso_clients';

    public function findByClientId(string $clientId): ?array
    {
        return $this->findBy('client_id', $clientId);
    }

    public function verifySecret(string $plainSecret, string $hashedSecret): bool
    {
        return password_verify($plainSecret, $hashedSecret);
    }

    public function isValidRedirectUri(array $client, string $uri): bool
    {
        $allowed = json_decode($client['redirect_uris'], true) ?? [];
        return in_array($uri, $allowed, true);
    }
}
