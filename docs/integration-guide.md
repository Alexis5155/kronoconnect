# Guide d'Intégration KronoConnect

Ce guide explique pas à pas comment intégrer une nouvelle application PHP (comme KronoCore, KronoActes, etc.) avec la passerelle d'authentification KronoConnect.

Le flux utilisé s'inspire d'OAuth 2.0 (Authorization Code Grant), mais simplifié et sécurisé via des signatures HMAC-SHA256 pour les échanges entre serveurs.

---

## 1. Déclarer l'application dans KronoConnect

Avant de coder, l'application doit être déclarée dans le panel d'administration de KronoConnect.

1. Allez dans **Clients SSO** > **Nouveau client**.
2. Remplissez le nom et l'URL de base (ex: `https://kronoactes.local`).
3. L'URI de redirection sera générée automatiquement (ex: `https://kronoactes.local/auth/callback`).
4. Choisissez le **Mode d'accès** (Ouvert, par Groupe ou Manuel).
5. Poursuivez l'assistant. À la dernière étape, un **Client ID** et un **Client Secret** seront générés. **Notez-les précieusement dans le fichier `.env` de votre application cliente.**

Exemple dans votre application cliente (`.env`) :
```env
KRONOCONNECT_URL=https://kronoconnect.local
KRONOCONNECT_CLIENT_ID=votre-client-id-ici
KRONOCONNECT_CLIENT_SECRET=votre-secret-complexe-ici
```

---

## 2. Exposer le Manifest (Côté App Cliente)

KronoConnect a besoin de connaître les permissions que votre application gère. Votre application doit exposer un point de terminaison public en `GET` sur `/kronoconnect/manifest`.

**Exemple de contrôleur (Côté App Cliente) :**
```php
public function manifest() {
    $manifest = [
        "name" => "KronoActes",
        "version" => "1.0.0",
        "description" => "Gestion des actes administratifs",
        "icon" => "file-earmark-text",
        "color" => "#10B981",
        "permissions" => [
            [
                "key" => "actes.read",
                "label" => "Consulter",
                "description" => "Voir les actes."
            ],
            [
                "key" => "actes.write",
                "label" => "Modifier",
                "description" => "Créer ou éditer des actes."
            ]
        ]
    ];
    
    // Le manifest n'a pas besoin d'être signé lors de sa récupération initiale par le Wizard de KronoConnect, 
    // mais il est de bonne pratique de le renvoyer en JSON propre.
    header('Content-Type: application/json');
    echo json_encode($manifest);
    exit;
}
```

---

## 3. Le flux de Connexion (Côté App Cliente)

### Étape 3.1 : Rediriger l'utilisateur vers KronoConnect
Quand un utilisateur clique sur "Se connecter", vous devez le rediriger vers la page d'autorisation de KronoConnect.

**Code de redirection (Côté App Cliente) :**
```php
$kronoconnectUrl = $_ENV['KRONOCONNECT_URL'];
$clientId = $_ENV['KRONOCONNECT_CLIENT_ID'];
$redirectUri = urlencode('https://kronoactes.local/auth/callback');

$authUrl = "{$kronoconnectUrl}/sso/authorize?client_id={$clientId}&redirect_uri={$redirectUri}";

header("Location: $authUrl");
exit;
```

### Étape 3.2 : Traiter le retour (Le Callback)
Après s'être identifié (et avoir potentiellement accepté l'accès si l'app n'est pas en accès libre), KronoConnect redirige l'utilisateur vers votre `redirect_uri` en ajoutant un `?code=xyz123`.

**Code de réception (Côté App Cliente) :**
```php
public function callback() {
    $code = $_GET['code'] ?? null;
    
    if (!$code) {
        die("Erreur : Aucun code d'autorisation reçu.");
    }
    
    // Étape 3.3 : Échanger le code contre les infos de l'utilisateur
    $userInfo = $this->exchangeCodeForUser($code);
    
    // Connecter l'utilisateur localement
    $_SESSION['user'] = $userInfo;
    
    // Rediriger vers l'accueil de l'application
    header("Location: /dashboard");
    exit;
}
```

### Étape 3.3 : Échanger le code (Server-to-Server HMAC)
C'est ici qu'intervient la sécurité. Votre serveur doit contacter le serveur KronoConnect pour échanger le code `xyz123` contre l'identité réelle de l'utilisateur. Cet appel POST est signé avec le `Client Secret`.

**Fonction d'échange (Côté App Cliente) :**
```php
private function exchangeCodeForUser(string $code) {
    $kronoconnectUrl = $_ENV['KRONOCONNECT_URL'];
    $clientId = $_ENV['KRONOCONNECT_CLIENT_ID'];
    $clientSecret = $_ENV['KRONOCONNECT_CLIENT_SECRET'];
    
    $endpoint = $kronoconnectUrl . '/api/token';
    $timestamp = time();
    $body = json_encode(['code' => $code]);
    
    // Calcul de la signature HMAC-SHA256
    $payloadToSign = $clientId . ':' . $timestamp . ':' . $body;
    $signature = hash_hmac('sha256', $payloadToSign, $clientSecret);
    
    // Préparation de la requête HTTP
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => [
                'Content-Type: application/json',
                'X-Client-ID: ' . $clientId,
                'X-Timestamp: ' . $timestamp,
                'X-Signature: ' . $signature
            ],
            'content' => $body,
            'ignore_errors' => true // Pour récupérer le JSON en cas de 40x
        ]
    ];
    
    $context = stream_context_create($opts);
    $response = file_get_contents($endpoint, false, $context);
    
    // Vérification du statut HTTP
    $statusLine = $http_response_header[0] ?? '';
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
    $statusCode = (int)($match[1] ?? 500);
    
    if ($statusCode !== 200) {
        die("Erreur de communication avec KronoConnect (Code {$statusCode}) : " . $response);
    }
    
    return json_decode($response, true);
}
```

---

## 4. Vérifier l'accès et les permissions

L'objet utilisateur renvoyé par la route `/api/token` contient les informations de base. 

Si vous avez besoin de récupérer l'état des permissions d'un utilisateur de manière asynchrone (ex: vérifier s'il a toujours le droit d'être sur l'application), vous pouvez utiliser l'endpoint `/api/v1/user/{token}` de la même manière (requête GET signée par HMAC).

Cet endpoint vous renverra :
```json
{
    "id": 42,
    "email": "jean.dupont@collectivite.fr",
    "firstname": "Jean",
    "lastname": "Dupont",
    "theme": "dark",
    "access_granted": true,
    "permissions": ["actes.read", "actes.write"]
}
```

*Remarque : Les permissions sont fusionnées (Appartient au groupe + Surcharges individuelles - Révocations individuelles). Le super-administrateur reçoit toujours `access_granted: true` et la liste complète des permissions.*