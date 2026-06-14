# Documentation API KronoConnect

KronoConnect agit comme une passerelle centrale d'authentification et de gestion des permissions. Les applications clientes (KronoActes, KronoPlanning, etc.) communiquent avec KronoConnect de manière sécurisée en *server-to-server* (stateless).

## 1. Philosophie de Sécurité (HMAC-SHA256)

L'API de KronoConnect est conçue pour des requêtes provenant exclusivement du back-end des applications clientes (pas de requêtes AJAX depuis le navigateur de l'utilisateur final). 
La sécurité repose sur des signatures **HMAC-SHA256**. 

Avantages :
- Pas d'échange de token réseau vulnérable.
- Les requêtes sont signées avec une clé privée (`client_secret`).
- Protection contre le "Replay Attack" (attaques par rejeu) grâce à un Timestamp (tolérance maximale de 60 secondes).

### En-têtes obligatoires
Toute requête vers `/api/v1/*` ou `/api/token` doit inclure ces en-têtes :
- `X-Client-ID` : L'identifiant public du client SSO.
- `X-Timestamp` : Le timestamp Unix actuel.
- `X-Signature` : La signature HMAC-SHA256 calculée.

### Exemple de calcul de signature (PHP)
```php
$clientId = 'YOUR_CLIENT_ID';
$clientSecret = 'YOUR_CLIENT_SECRET';
$timestamp = time();

// Pour une requête avec un body (ex: POST /api/token avec JSON)
$body = json_encode(['code' => 'abcdef123456']);
// Pour une requête GET sans body (ex: GET /api/v1/user/token)
// $body = '';

// La payload à signer : "client_id:timestamp:body"
$payloadToSign = $clientId . ':' . $timestamp . ':' . $body;

// Calcul de la signature
$signature = hash_hmac('sha256', $payloadToSign, $clientSecret);

// Création du contexte HTTP
$opts = [
    'http' => [
        'method'  => 'POST',
        'header'  => [
            'Content-Type: application/json',
            'X-Client-ID: ' . $clientId,
            'X-Timestamp: ' . $timestamp,
            'X-Signature: ' . $signature
        ],
        'content' => $body
    ]
];
$context = stream_context_create($opts);
$response = file_get_contents('https://kronoconnect.local/api/token', false, $context);
```

## 2. Endpoints Exposés

### POST `/api/token`
Échange un code d'autorisation contre les informations de base de l'utilisateur. Appelé à la fin du flux SSO OAuth2-like.
* **Body** : JSON `{"code": "..."}`
* **Réponse 200** :
  ```json
  {
      "id": 42,
      "email": "jean.dupont@collectivite.fr",
      "nom": "Dupont",
      "prenom": "Jean",
      "role": "user",
      "theme": "dark"
  }
  ```

### GET `/api/v1/user/{token}`
Vérifie la validité d'une session (token) et retourne les droits d'accès. Utile pour la validation des accès API server-to-server.
* **Réponse 200** :
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
  *(Voir `permissions-model.md` pour le détail de calcul des permissions).*

## 3. Codes d'erreurs standardisés

Si l'API renvoie un code `4xx` ou `5xx`, la réponse sera toujours au format JSON :
```json
{
    "error": "code_erreur_interne"
}
```

**Codes courants :**
- `400 missing_authentication_headers` : En-têtes HMAC absents.
- `401 request_expired` : Le timestamp de la requête est trop vieux (> 60s).
- `401 invalid_signature` : La signature HMAC est incorrecte (vérifiez le secret ou le format de la payload).
- `404 user_not_found` : L'utilisateur n'existe plus ou est désactivé.
- `404 invalid_token` : Le token fourni n'existe pas.

## 4. Intégration côté Client (Manifest)

Pour qu'une application (ex: KronoActes) puisse se connecter à KronoConnect, elle doit déclarer ses intentions via un `Manifest`.

KronoConnect interrogera l'application cliente sur la route :
`GET /kronoconnect/manifest`

L'application cliente doit renvoyer ce JSON de manière publique ou accessible par le serveur KronoConnect (aucune validation HMAC n'est requise pour la lecture du manifest) :
```json
{
    "name": "KronoActes",
    "version": "1.0.0",
    "description": "Gestion des actes administratifs",
    "icon": "file-earmark-text",
    "color": "#10B981",
    "permissions": [
        { "key": "actes.read", "label": "Consulter les actes", "description": "Voir la liste des actes sans pouvoir les modifier." },
        { "key": "actes.write", "label": "Créer/Modifier", "description": "Autorise l'ajout et l'édition des actes administratifs." }
    ]
}
```
L'administrateur de KronoConnect peut ensuite synchroniser ces données via le panel d'administration.ées via le panel d'administration.