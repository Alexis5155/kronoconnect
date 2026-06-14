# Guide d'intégration : Single Logout (SLO) en Back-Channel

Ce guide explique comment implémenter la déconnexion centralisée (Single Logout) côté application cliente.

## 1. Fonctionnement général

KronoConnect implémente un système de **Back-Channel Single Logout**. 
Lorsqu'un utilisateur se déconnecte du portail central KronoConnect, le serveur KronoConnect contacte en arrière-plan (serveur-à-serveur) toutes les applications auxquelles cet utilisateur s'est connecté récemment.

Ce mécanisme permet à chaque application cliente de recevoir l'instruction de détruire la session locale de l'utilisateur concerné, garantissant ainsi une déconnexion globale sécurisée.

## 2. Prérequis

Pour que votre application puisse recevoir les requêtes de déconnexion :
1. Vous devez disposer de votre **Client ID** et **Client Secret** (celui généré lors de la création de l'application dans KronoConnect).
2. L'URL de base de votre application doit être accessible par le serveur KronoConnect.
3. Dans l'interface d'administration de KronoConnect, vous devez avoir renseigné le champ **URL de logout back-channel** pour votre application. 
   - *Exemple d'URL configurée* : `https://mon-application.fr`

> **Note :** KronoConnect concaténera `/kronoconnect/logout` à l'URL que vous avez configurée.
> *Si vous avez configuré `https://mon-application.fr`, la requête sera envoyée à `https://mon-application.fr/kronoconnect/logout`.*

## 3. Format de la requête envoyée par KronoConnect

La requête envoyée à votre application aura le format suivant :

- **Méthode :** `POST`
- **Endpoint :** `{VOTRE_LOGOUT_URL}/kronoconnect/logout`
- **Timeout :** La requête est envoyée avec un timeout très court (2 secondes). Vous devez traiter la demande rapidement (ou la mettre en file d'attente) pour ne pas bloquer les processus du portail.

### 3.1. En-têtes HTTP (Headers)

La requête contient des en-têtes cruciaux pour la sécurité et l'authentification :

| En-tête | Description |
|---|---|
| `Content-Type` | Toujours `application/json` |
| `X-Client-ID` | Votre identifiant d'application (Client ID) |
| `X-Timestamp` | Timestamp UNIX (en secondes) de l'émission de la requête |
| `X-Signature` | Signature cryptographique garantissant l'intégrité et l'authenticité de la requête |

### 3.2. Corps de la requête (Payload JSON)

Le corps de la requête est un objet JSON contenant l'adresse e-mail de l'utilisateur à déconnecter :

```json
{
  "action": "logout",
  "email": "jean.dupont@exemple.fr"
}
```

## 4. Vérification de la signature (Sécurité)

Il est **impératif** de vérifier la signature de la requête avant de déconnecter un utilisateur pour éviter qu'un attaquant ne forge des requêtes de déconnexion.

La signature (`X-Signature`) est générée côté KronoConnect en utilisant l'algorithme **HMAC-SHA256**.

### 4.1. Algorithme de calcul

```text
chaine_a_signer = {Client_ID} + ":" + {Timestamp_Recu} + ":" + {Corps_de_la_requete_brut}
signature_attendue = HMAC_SHA256(chaine_a_signer, {Votre_Client_Secret})
```

1. Concaténez le Client ID, le Timestamp (récupéré dans les headers) et le corps brut de la requête (avant `json_decode`), séparés par des deux-points (`:`).
2. Hachez cette chaîne avec votre Client Secret en utilisant HMAC-SHA256.
3. Comparez la signature calculée avec celle reçue dans l'en-tête `X-Signature` (en utilisant une fonction de comparaison sécurisée contre les attaques temporelles, comme `hash_equals` en PHP).
4. (Optionnel mais recommandé) Vérifiez que le Timestamp n'est pas trop ancien (ex: maximum 60 secondes de délai) pour éviter les attaques par rejeu.

## 5. Exemple d'implémentation (PHP)

Voici un exemple de script PHP qui pourrait correspondre à la route `/kronoconnect/logout` de votre application cliente :

```php
<?php
// Configuration (à adapter selon votre application)
$clientId = 'VOTRE_CLIENT_ID';
$clientSecret = 'VOTRE_CLIENT_SECRET';

// Récupération des en-têtes (Attention: getallheaders() ou $_SERVER selon votre serveur web)
$receivedClientId  = $_SERVER['HTTP_X_CLIENT_ID'] ?? '';
$receivedTimestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
$receivedSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// Récupération du corps de la requête brute
$rawBody = file_get_contents('php://input');

// 1. Vérifications basiques
if ($receivedClientId !== $clientId) {
    http_response_code(400);
    die('Invalid Client ID');
}

if (abs(time() - (int)$receivedTimestamp) > 60) {
    http_response_code(400);
    die('Request expired');
}

// 2. Calcul de la signature attendue
$stringToSign = $receivedClientId . ':' . $receivedTimestamp . ':' . $rawBody;
$expectedSignature = hash_hmac('sha256', $stringToSign, $clientSecret);

// 3. Vérification de la signature
if (!hash_equals($expectedSignature, $receivedSignature)) {
    http_response_code(401);
    die('Invalid Signature');
}

// 4. Traitement de la déconnexion
$data = json_decode($rawBody, true);

if (isset($data['action']) && $data['action'] === 'logout' && !empty($data['email'])) {
    $emailToLogout = $data['email'];
    
    // --> VOTRE LOGIQUE MÉTIER ICI <--
    // Ex: Détruire la session PHP de l'utilisateur correspondant à $emailToLogout
    // Ex: Invalider les tokens d'accès locaux en base de données
    
    error_log("Single Logout réussi pour : " . $emailToLogout);
}

http_response_code(200);
echo json_encode(['status' => 'success']);
```
