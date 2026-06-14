# Modèle de Permissions RBAC de KronoConnect

KronoConnect implémente un système de contrôle d'accès basé sur les rôles et les groupes (RBAC), enrichi de surcharges individuelles. Ce système garantit à la fois une gestion de masse facile (via les groupes) et une granularité fine pour les cas particuliers.

## 1. Concepts de base

- **Application (Client SSO)** : Une application tierce (ex: KronoPlanning, KronoActes).
- **Mode d'accès d'une App** :
  - `open` : Tous les utilisateurs actifs peuvent y accéder.
  - `group` : L'accès est réservé aux membres de Groupes explicitement autorisés.
  - `manual` : L'accès est défini utilisateur par utilisateur.
- **Permission (`perm_key`)** : Une capacité d'action spécifique déclarée par l'application (ex: `actes.write`). Les clés ne sont pas transformées par KronoConnect.

## 1.b. Permissions Internes (KronoConnect)
KronoConnect possède son propre jeu de permissions pour gérer l'accès à son panel d'administration (ex: `kc.admin.access`, `kc.users.manage`). 
Dans la base de données, ces permissions internes sont rattachées virtuellement avec la valeur `client_id = NULL`, permettant de les gérer avec le même moteur RBAC que les applications tierces.

## 1.c. Synchronisation du Manifest
Pour que KronoConnect connaisse les permissions d'une application tierce, l'administrateur doit "Synchroniser le manifest". KronoConnect effectue alors une requête GET (publique) sur le point de terminaison `/kronoconnect/manifest` de l'application cliente et met à jour son dictionnaire interne (`table permissions`). Les anciennes permissions absentes du nouveau manifest sont automatiquement supprimées.

## 2. Calcul des Accès (`access_granted`)

Lorsqu'un utilisateur tente de se connecter à une application via SSO, ou que l'API est interrogée, KronoConnect calcule d'abord si l'accès global à l'application lui est accordé :

1. L'app est en mode `open` ? -> **OUI**
2. L'app est en mode `manual` ? 
   - L'utilisateur est-il dans la table `user_app_access` ? -> **OUI / NON**
3. L'app est en mode `group` ?
   - L'utilisateur appartient-il à un groupe qui possède l'accès à l'app (`group_app_access`) ? -> **OUI / NON**

*Si le résultat est NON, le flux SSO est interrompu avec un message d'erreur.*

## 3. Calcul des Permissions Effectives

Une fois l'accès accordé, KronoConnect calcule le tableau des permissions effectives que l'utilisateur possède sur cette application spécifique. Le calcul s'effectue en deux couches (Union puis Différence).

```text
[ Permissions Effectives ] = ( [Permissions Groupes] ∪ [Surcharges Accordées] ) - [Surcharges Révoquées]
```

### Schéma Explicatif

```ascii
                      +-------------------+
                      | Utilisateur (U1)  |
                      +-------------------+
                               |
                   Appartient à Groupes (G1, G2)
                               |
               +-------------------------------+
               | G1: [ 'actes.read' ]          |
               | G2: [ 'actes.write' ]         |
               +-------------------------------+
                               |
                     = UNION (Permissions par défaut)
                     [ 'actes.read', 'actes.write' ]
                               |
                               v
               +-------------------------------+
               | Exceptions Individuelles (U1) |
               | - 'admin.access' = FORCER OUI |
               | - 'actes.write'  = FORCER NON |
               +-------------------------------+
                               |
                               v
                     [ Permissions Finales ]
                  [ 'actes.read', 'admin.access' ]
```

## 4. Cas d'Usage Concrets

### A. Le Directeur Général des Services (DGS)
Le DGS a besoin de voir tout (`actes.read`, `planning.read`), mais ne doit pas modifier la saisie quotidienne des agents. 
* **Mise en place** : Il est placé dans le groupe "Direction" qui donne accès en lecture à toutes les applications en mode `group`. 

### B. Le RH qui remplace le DGS pendant 1 semaine
Le DGS est en congé, le RH doit temporairement valider les actes. 
* **Mise en place** : On ne change pas le groupe "Direction". On va sur le profil de l'utilisateur RH, onglet "Permissions", et on force la permission `actes.validate` à **OUI**. 
* **Après 1 semaine** : On remet l'exception sur "Hérité".

### C. Le Stagiaire
Un stagiaire RH fait partie du groupe "Ressources Humaines" qui donne accès à `planning.write`. Cependant, ce stagiaire spécifique ne doit faire que de l'observation.
* **Mise en place** : Il reste dans le groupe RH pour accéder à l'application. On va sur son profil, onglet "Permissions", et on force la permission `planning.write` à **NON**. La permission du groupe est révoquée uniquement pour lui.