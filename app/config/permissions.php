<?php
return [
    [
        'key'         => 'kc.admin.access',
        'label'       => 'Accès au panel admin',
        'description' => 'Permet d\'accéder au panel d\'administration de KronoConnect.',
    ],
    [
        'key'         => 'kc.users.manage',
        'label'       => 'Gérer les utilisateurs',
        'description' => 'Créer, modifier, activer/désactiver des comptes.',
        'parent_key'  => 'kc.admin.access',
    ],
    [
        'key'         => 'kc.users.delete',
        'label'       => 'Supprimer les utilisateurs',
        'description' => 'Supprimer définitivement des comptes utilisateurs.',
        'parent_key'  => 'kc.users.manage',
    ],
    [
        'key'         => 'kc.groups.manage',
        'label'       => 'Gérer les groupes',
        'description' => 'Créer et configurer les groupes de permissions.',
        'parent_key'  => 'kc.admin.access',
    ],
    [
        'key'         => 'kc.clients.manage',
        'label'       => 'Gérer les applications',
        'description' => 'Enregistrer et configurer les apps clientes SSO.',
        'parent_key'  => 'kc.admin.access',
    ],
    [
        'key'         => 'kc.settings.manage',
        'label'       => 'Gérer les paramètres globaux',
        'description' => 'Modifier les paramètres KronoConnect (inscription, etc.).',
        'parent_key'  => 'kc.admin.access',
    ],
    [
        'key'         => 'kc.toggle.maintenance',
        'label'       => 'Gérer la maintenance',
        'description' => 'Permet d\'activer/désactiver le mode maintenance et de conserver un accès complet.',
        'parent_key'  => 'kc.admin.access',
    ],
];
