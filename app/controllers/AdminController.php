<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Core\Session;
use KronoConnect\Core\Database;
use KronoConnect\Core\Security;
use KronoConnect\Core\Logger;
use KronoConnect\Models\AdminModel;

class AdminController extends BaseController
{
    private Database $db;
    private AdminModel $adminModel;

    // Noms de tables préfixés
    private string $tUsers;
    private string $tSsoClients;
    private string $tGroups;
    private string $tGroupMembers;
    private string $tGroupAppAccess;
    private string $tUserAppAccess;
    private string $tGroupPermissions;
    private string $tUserPermissions;
    private string $tPermissions;
    private string $tConnLogs;
    private string $tLogs;
    private string $tCustomLinks;
    private string $tCustomLinkGroupAccess;
    private string $tCustomLinkUserAccess;
    private string $tUserPortalOrder;

    public function __construct()
    {
        $this->requireAdmin();
        $this->db         = Database::getInstance();
        $this->adminModel = new AdminModel();

        $this->tUsers            = $this->db->t('users');
        $this->tSsoClients       = $this->db->t('sso_clients');
        $this->tGroups           = $this->db->t('groups');
        $this->tGroupMembers     = $this->db->t('group_members');
        $this->tGroupAppAccess   = $this->db->t('group_app_access');
        $this->tUserAppAccess    = $this->db->t('user_app_access');
        $this->tGroupPermissions = $this->db->t('group_permissions');
        $this->tUserPermissions  = $this->db->t('user_permissions');
        $this->tPermissions      = $this->db->t('permissions');
        $this->tConnLogs         = $this->db->t('sso_connection_logs');
        $this->tLogs             = $this->db->t('logs');
        $this->tCustomLinks      = $this->db->t('custom_links');
        $this->tCustomLinkGroupAccess = $this->db->t('custom_link_group_access');
        $this->tCustomLinkUserAccess  = $this->db->t('custom_link_user_access');
        $this->tUserPortalOrder       = $this->db->t('user_portal_order');
    }

    // ── Journal des actions ──────────────────────────────────────────────────

    public function logs(): void
    {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $filters = [
            'search'    => trim($_GET['q']         ?? ''),
            'level'     => trim($_GET['level']     ?? ''),
            'date_from' => trim($_GET['date_from'] ?? ''),
            'date_to'   => trim($_GET['date_to']   ?? ''),
            'dir'       => strtolower(trim($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        $where  = ["1=1"];
        $params = [];

        if ($filters['search']) {
            $where[]  = "message LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
        }
        if ($filters['level']) {
            $where[]  = "level = ?";
            $params[] = $filters['level'];
        }
        if ($filters['date_from']) {
            $where[]  = "created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if ($filters['date_to']) {
            $where[]  = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $sortSql  = strtoupper($filters['dir']);

        $total      = (int)$this->db->fetchOne("SELECT COUNT(*) as n FROM `$this->tLogs` WHERE $whereSql", $params)['n'];
        $totalPages = (int)ceil($total / $limit);

        $rows = $this->db->fetchAll("
            SELECT * FROM `$this->tLogs`
            WHERE $whereSql
            ORDER BY created_at $sortSql
            LIMIT $limit OFFSET $offset
        ", $params);

        $this->render('admin/logs', [
            'title'   => 'Journal des actions',
            'page'    => 'logs',
            'result'  => [
                'rows'       => $rows,
                'total'      => $total,
                'totalPages' => $totalPages,
                'page'       => $page
            ],
            'filters' => $filters
        ], 'admin');
    }

    // ── Paramètres globaux ────────────────────────────────────────────────────

    public function settings(): void
    {
        $settings = $this->adminModel->getSettings();

        $dbConfig = file_exists(CONFIG_PATH . '/database.php')
            ? require CONFIG_PATH . '/database.php'
            : [];

        // --- Récupération des métriques de la base de données ---
        $dbName = $dbConfig['database'] ?? '';
        $dbMetrics = [
            'size_mb' => 0.0,
            'tables'  => 0,
            'total_migrations' => 0,
            'applied_migrations' => 0,
            'pending_migrations' => 0,
        ];
        
        if (!empty($dbName)) {
            try {
                $stat = $this->db->fetchOne("
                    SELECT 
                        COUNT(table_name) AS tables, 
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ", [$dbName]);
                if ($stat) {
                    $dbMetrics['tables'] = (int)($stat['tables'] ?? 0);
                    $dbMetrics['size_mb'] = (float)($stat['size_mb'] ?? 0.0);
                }
                
                // Migrations
                $prefix = $dbConfig['prefix'] ?? '';
                $migTable = $prefix . 'migrations';
                
                $tableExists = $this->db->fetchOne("
                    SELECT COUNT(*) as c FROM information_schema.tables 
                    WHERE table_schema = ? AND table_name = ?
                ", [$dbName, $migTable]);
                
                if ($tableExists && $tableExists['c'] > 0) {
                    $applied = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM `$migTable`")['c'];
                    $dbMetrics['applied_migrations'] = $applied;
                }
                
                $deltaDir = ROOT_PATH . '/database/migrations/delta';
                if (is_dir($deltaDir)) {
                    $files = glob($deltaDir . '/*.sql');
                    $dbMetrics['total_migrations'] = count($files ?: []);
                    $dbMetrics['pending_migrations'] = max(0, $dbMetrics['total_migrations'] - $dbMetrics['applied_migrations']);
                }
            } catch (\Throwable $e) {}
        }

        $this->render('admin/settings', [
            'title'     => 'Paramètres',
            'page'      => 'settings',
            'settings'  => $settings,
            'dbConfig'  => $dbConfig,
            'dbMetrics' => $dbMetrics,
        ], 'admin');
    }

    public function settingsUpdate(): void
    {
        $this->verifyCsrf();

        $allowRegister    = isset($_POST['allow_self_register']) ? '1' : '0';
        $allowEmailChange = isset($_POST['allow_email_change'])  ? '1' : '0';
        $manualApproval   = isset($_POST['manual_approval'])     ? '1' : '0';
        $maintenanceMode  = isset($_POST['maintenance_mode'])    ? '1' : '0';

        $this->adminModel->setSetting('allow_self_register', $allowRegister);
        $this->adminModel->setSetting('allow_email_change',  $allowEmailChange);
        $this->adminModel->setSetting('manual_approval',     $manualApproval);
        $this->adminModel->setSetting('registration',        $allowRegister);
        $this->adminModel->setSetting('maintenance_mode',     $maintenanceMode);

        $this->adminModel->setSetting('app_name',        trim(Security::sanitize($_POST['app_name'] ?? '')));
        $this->adminModel->setSetting('collectivite',    trim(Security::sanitize($_POST['collectivite'] ?? '')));
        $this->adminModel->setSetting('portal_hero_sub', trim(Security::sanitize($_POST['portal_hero_sub'] ?? '')));

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $uuid = \KronoConnect\Core\FileManager::upload($_FILES['logo'], 'kronoconnect');
                $oldSettings = $this->adminModel->getSettings();
                if (!empty($oldSettings['logo_uuid'])) {
                    \KronoConnect\Core\FileManager::delete($oldSettings['logo_uuid']);
                }
                $this->adminModel->setSetting('logo_uuid', $uuid);
            } catch (\Exception $e) {
                redirect('/admin/settings', ['error' => 'Erreur lors de l\'upload du logo : ' . $e->getMessage()]);
                return;
            }
        }

        $this->adminModel->setSetting('smtp_host',       trim($_POST['smtp_host']       ?? ''));
        $this->adminModel->setSetting('smtp_port',       trim($_POST['smtp_port']       ?? ''));
        $this->adminModel->setSetting('smtp_user',       trim($_POST['smtp_user']       ?? ''));
        
        $newSmtpPass = trim($_POST['smtp_pass'] ?? '');
        if ($newSmtpPass !== '') {
            $this->adminModel->setSetting('smtp_pass', $newSmtpPass);
        }

        $this->adminModel->setSetting('smtp_encryption', trim($_POST['smtp_encryption'] ?? ''));
        $this->adminModel->setSetting('from_email',      trim($_POST['from_email']      ?? ''));
        $this->adminModel->setSetting('from_name',       trim($_POST['from_name']       ?? ''));

        // Captcha
        $this->adminModel->setSetting('captcha_provider',   trim($_POST['captcha_provider']   ?? 'none'));
        $this->adminModel->setSetting('captcha_site_key',   trim($_POST['captcha_site_key']   ?? ''));
        $this->adminModel->setSetting('captcha_login',      isset($_POST['captcha_login'])    ? '1' : '0');
        $this->adminModel->setSetting('captcha_register',   isset($_POST['captcha_register']) ? '1' : '0');
        $this->adminModel->setSetting('captcha_reset',      isset($_POST['captcha_reset'])    ? '1' : '0');
        
        $secret = trim($_POST['captcha_secret_key'] ?? '');
        if ($secret !== '') {
            $this->adminModel->setSetting('captcha_secret_key', $secret);
        }

        // RGPD
        $this->adminModel->setSetting('gdpr_retention_accounts_months', trim($_POST['gdpr_retention_accounts_months'] ?? '36'));
        $this->adminModel->setSetting('gdpr_retention_logs_months',     trim($_POST['gdpr_retention_logs_months'] ?? '6'));
        $this->adminModel->setSetting('gdpr_privacy_url',               trim($_POST['gdpr_privacy_url'] ?? ''));
        $this->adminModel->setSetting('gdpr_legal_url',                 trim($_POST['gdpr_legal_url'] ?? ''));

        \KronoConnect\Core\Cache::forget('settings');
        \KronoConnect\Core\Cache::forget('app_config_settings');

        Logger::info('Admin : paramètres généraux modifiés', ['by' => Session::userId()]);
        redirect('/admin/settings', ['success' => 'Paramètres enregistrés.']);
    }

    public function testEmail(): void
    {
        Security::verifyCsrf();
        header('Content-Type: application/json');

        $dest = Security::sanitizeEmail($_POST['email'] ?? '');
        if (!$dest) {
            echo json_encode(['success' => false, 'message' => 'Adresse e-mail de destination invalide.']);
            return;
        }

        $host = Security::sanitize($_POST['smtp_host'] ?? '');
        if (empty($host)) {
            echo json_encode(['success' => false, 'message' => 'Le champ Serveur SMTP est vide.']);
            return;
        }

        $cfg = [
            'driver'          => 'smtp',
            'smtp_host'       => $host,
            'smtp_port'       => (int) ($_POST['smtp_port']                   ?? 587),
            'smtp_user'       => Security::sanitize($_POST['smtp_user']       ?? ''),
            'smtp_pass'       => $_POST['smtp_pass']                          ?? '', 
            'smtp_encryption' => Security::sanitize($_POST['smtp_encryption'] ?? 'tls'),
            'from_email'      => Security::sanitizeEmail($_POST['from_email']  ?? ''),
            'from_name'       => Security::sanitize($_POST['from_name']       ?? 'KronoConnect'),
        ];

        // Si le mot de passe est vide dans le POST, on tente de récupérer celui déjà en BDD ou config
        if (empty($cfg['smtp_pass'])) {
            $settings = $this->adminModel->getSettings();
            $cfg['smtp_pass'] = $settings['smtp_pass'] ?? '';
        }
        if (empty($cfg['smtp_pass'])) {
            $appCfg = require CONFIG_PATH . '/app.php';
            $cfg['smtp_pass'] = $appCfg['mail']['password'] ?? '';
        }

        // Libère la session pour éviter de bloquer l'application si la connexion SMTP fige
        \KronoConnect\Core\Session::close();

        try {
            \KronoConnect\Core\Mailer::sendMail(
                $dest,
                'Test de configuration SMTP — KronoConnect',
                '<h1>Succès !</h1><p>Si vous recevez ce message, c\'est que votre configuration SMTP sur <strong>KronoConnect</strong> est correcte.</p><p>Le serveur SSO est désormais prêt pour envoyer vos invitations et réinitialisations de mots de passe.</p>',
                true, 
                true, 
                'Test Admin',
                $cfg
            );
            $response = ['success' => true, 'message' => 'E-mail de test envoyé avec succès (via PHPMailer & Modèle) !'];
        } catch (\Throwable $e) {
            $response = ['success' => false, 'message' => 'L\'envoi a échoué : ' . $e->getMessage()];
        }
        echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
    }

    // POST /admin/settings/db
    public function saveDatabase(): void
    {
        Security::verifyCsrf();

        // Vérification du mot de passe administrateur
        $confirmPwd  = $_POST['confirm_password'] ?? '';
        $currentUser = Database::getInstance()->fetchOne(
            "SELECT password FROM `$this->tUsers` WHERE id = ?",
            [Session::userId()]
        );

        if (!$currentUser || !Security::verifyPassword($confirmPwd, $currentUser['password'])) {
            redirect('/admin/settings', ['error' => 'Mot de passe incorrect — modifications annulées.']);
        }

        // Lecture des nouveaux paramètres
        $host     = Security::sanitize($_POST['db_host']     ?? 'localhost');
        $port     = max(1, min(65535, (int) ($_POST['db_port'] ?? 3306)));
        $dbname   = Security::sanitize($_POST['db_name']     ?? '');
        $username = Security::sanitize($_POST['db_username'] ?? '');
        $newPwd   = $_POST['db_password'] ?? '';

        // Mot de passe vide → conserver l'actuel
        if ($newPwd === '') {
            $current = require CONFIG_PATH . '/database.php';
            $newPwd  = $current['password'] ?? '';
        }

        // Test de la connexion avant toute écriture
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $newPwd, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            unset($pdo);
        } catch (\PDOException $e) {
            redirect('/admin/settings', [
                'error' => 'Connexion impossible : ' . $e->getMessage(),
            ]);
        }

        // Écriture du nouveau database.php
        $content = sprintf(
            "<?php\ndeclare(strict_types=1);\n\nreturn [\n" .
            "    'driver'   => 'mysql',\n" .
            "    'host'     => %s,\n" .
            "    'port'     => %d,\n" .
            "    'database' => %s,\n" .
            "    'username' => %s,\n" .
            "    'password' => %s,\n" .
            "    'charset'  => 'utf8mb4',\n" .
            "    'options'  => [\n" .
            "        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n" .
            "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n" .
            "        PDO::ATTR_EMULATE_PREPARES   => false,\n" .
            "        PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci\",\n" .
            "    ],\n" .
            "];\n",
            var_export($host,     true),
            $port,
            var_export($dbname,   true),
            var_export($username, true),
            var_export($newPwd,   true)
        );

        if (file_put_contents(CONFIG_PATH . '/database.php', $content) === false) {
            redirect('/admin/settings', ['error' => 'Impossible d\'écrire le fichier de configuration.']);
        }

        Logger::warning('Admin : configuration BDD modifiée', ['by' => Session::userId(), 'host' => $host, 'db' => $dbname]);
        redirect('/admin/settings', ['success' => 'Configuration de la base de données mise à jour.']);
    }

    public function exportDatabase(): void
    {
        $this->requireAdmin();
        $dbConfig = file_exists(CONFIG_PATH . '/database.php') ? require CONFIG_PATH . '/database.php' : [];
        $dbName = $dbConfig['database'] ?? '';
        
        if (!$dbName) {
            redirect('/admin/settings', ['error' => 'Base de données non configurée.']);
            return;
        }

        $tablesRaw = $this->db->fetchAll("SHOW TABLES");
        $tables = array_map(fn($t) => array_values($t)[0], $tablesRaw);

        $dump = "-- KronoConnect SQL Dump\n";
        $dump .= "-- Généré le: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- Base de données: $dbName\n\n";

        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $dump .= "-- --------------------------------------------------------\n";
            $dump .= "-- Structure de la table `$table`\n";
            $dump .= "-- --------------------------------------------------------\n\n";
            
            $create = $this->db->fetchOne("SHOW CREATE TABLE `$table`");
            if ($create) {
                $dump .= "DROP TABLE IF EXISTS `$table`;\n";
                $dump .= array_values($create)[1] . ";\n\n";
            }

            $rows = $this->db->fetchAll("SELECT * FROM `$table`");
            if (count($rows) > 0) {
                $dump .= "-- Données de la table `$table`\n";
                foreach ($rows as $row) {
                    $values = array_map(function($val) {
                        if (is_null($val)) return 'NULL';
                        return "'" . addslashes((string)$val) . "'";
                    }, array_values($row));
                    $dump .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                }
                $dump .= "\n";
            }
        }

        $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="kronoconnect_backup_' . date('Y-m-d_H-i') . '.sql"');
        echo $dump;
        exit;
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(): void
    {
        $totalUsers   = (int)($this->db->fetchOne("SELECT COUNT(*) AS n FROM `$this->tUsers`")['n']                           ?? 0);
        $activeUsers  = (int)($this->db->fetchOne("SELECT COUNT(*) AS n FROM `$this->tUsers` WHERE is_active = 1")['n']       ?? 0);
        $totalClients = (int)($this->db->fetchOne("SELECT COUNT(*) AS n FROM `$this->tSsoClients`")['n']                      ?? 0);
        
        $pendingUsers = (int)($this->db->fetchOne("SELECT COUNT(*) AS n FROM `$this->tUsers` WHERE status = 'attente_validation'")['n'] ?? 0);
        $totalGroups  = (int)($this->db->fetchOne("SELECT COUNT(*) AS n FROM `$this->tGroups`")['n'] ?? 0);

        $todayLogs = 0;
        try {
            $todayLogs = (int)($this->db->fetchOne(
                "SELECT COUNT(*) AS n FROM `$this->tConnLogs` WHERE DATE(created_at) = CURDATE()"
            )['n'] ?? 0);
        } catch (\Throwable) {}

        // --- Data for Chart (last 7 days connections) ---
        $connectionHistory = [];
        try {
            $history = $this->db->fetchAll("
                SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM `$this->tConnLogs` 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(created_at)
                ORDER BY DATE(created_at) ASC
            ");
            // Fill missing days with 0
            $historyDict = [];
            foreach ($history as $row) {
                $historyDict[$row['date']] = (int)$row['count'];
            }
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $connectionHistory[] = [
                    'date' => date('d/m', strtotime("-$i days")),
                    'count' => $historyDict[$d] ?? 0
                ];
            }
        } catch (\Throwable) {
            // Provide empty fallback
            for ($i = 6; $i >= 0; $i--) {
                $connectionHistory[] = ['date' => date('d/m', strtotime("-$i days")), 'count' => 0];
            }
        }

        // --- Recent Logs ---
        $recentLogs = [];
        try {
            $recentLogs = $this->db->fetchAll("SELECT * FROM `$this->tLogs` ORDER BY id DESC LIMIT 5");
        } catch (\Throwable) {}

        // --- System Info ---
        $serverInfo = [
            'php'   => phpversion(),
            'mysql' => $this->db->fetchOne("SELECT VERSION() as v")['v'] ?? 'Inconnu',
            'os'    => PHP_OS,
            'limit' => ini_get('memory_limit')
        ];

        $appConfig = require CONFIG_PATH . '/app.php';

        $this->render('admin/dashboard', [
            'title'             => 'Tableau de bord',
            'page'              => 'dashboard',
            'totalUsers'        => $totalUsers,
            'activeUsers'       => $activeUsers,
            'pendingUsers'      => $pendingUsers,
            'totalClients'      => $totalClients,
            'totalGroups'       => $totalGroups,
            'todayLogs'         => $todayLogs,
            'connectionHistory' => $connectionHistory,
            'recentLogs'        => $recentLogs,
            'serverInfo'        => $serverInfo,
            'appConfig'         => $appConfig,
        ], 'admin');
    }

    // ── Clients SSO ───────────────────────────────────────────────────────────

    public function clients(): void
    {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        $filters = [
            'search' => trim($_GET['q']    ?? ''),
            'mode'   => trim($_GET['mode'] ?? ''),
        ];

        $where  = ['1=1'];
        $params = [];

        if ($filters['search']) {
            $where[]  = '(name LIKE ? OR app_name LIKE ? OR client_id LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s; $params[] = $s; $params[] = $s;
        }
        if ($filters['mode']) {
            $where[]  = 'access_mode = ?';
            $params[] = $filters['mode'];
        }

        $whereSql   = implode(' AND ', $where);
        $total      = (int)$this->db->fetchOne("SELECT COUNT(*) as n FROM `$this->tSsoClients` WHERE $whereSql", $params)['n'];
        $totalPages = (int)ceil($total / $limit);

        $clients = $this->db->fetchAll(
            "SELECT id, client_id, name, app_name, app_icon, app_color, access_mode, redirect_uris, created_at
             FROM `$this->tSsoClients`
             WHERE $whereSql
             ORDER BY created_at DESC
             LIMIT $limit OFFSET $offset",
            $params
        );

        $this->render('admin/clients', [
            'title'     => 'Clients SSO',
            'page'      => 'clients',
            'result'    => [
                'rows'       => $clients,
                'total'      => $total,
                'totalPages' => $totalPages,
                'page'       => $page,
            ],
            'filters'   => $filters,
        ], 'admin');
    }

    public function clientCreated(): void
    {
        $newClient = Session::get('new_client_data');
        if (!$newClient) {
            redirect('/admin/clients');
        }
        Session::remove('new_client_data');

        $this->render('admin/client_created', [
            'title'     => 'Identifiants du client',
            'page'      => 'clients',
            'newClient' => $newClient,
        ], 'admin');
    }

    public function clientDetail(string $idStr): void
    {
        $id     = (int)$idStr;
        $client = $this->db->fetchOne("SELECT * FROM `$this->tSsoClients` WHERE id = ?", [$id]);

        if (!$client) {
            redirect('/admin/clients', ['error' => 'Client introuvable.']);
        }

        $permissions = $this->db->fetchAll(
            "SELECT * FROM `$this->tPermissions` WHERE client_id = ? ORDER BY perm_key ASC",
            [$id]
        );

        $accessMode  = $client['access_mode'] ?? 'open';
        $manualUsers = [];
        $groupAccess = [];
        $allGroups   = [];
        $allUsers    = [];

        if ($accessMode === 'manual') {
            $manualUsers = $this->db->fetchAll("
                SELECT u.id, u.nom, u.prenom, u.email, uaa.granted_at
                FROM `$this->tUserAppAccess` uaa
                JOIN `$this->tUsers` u ON uaa.user_id = u.id
                WHERE uaa.client_id = ?
            ", [$id]);
            $allUsers = $this->db->fetchAll(
                "SELECT id, nom, prenom, email FROM `$this->tUsers` ORDER BY nom, prenom"
            );
        } elseif ($accessMode === 'group') {
            $groupAccess = $this->db->fetchAll("
                SELECT g.id, g.name, g.description
                FROM `$this->tGroupAppAccess` gaa
                JOIN `$this->tGroups` g ON gaa.group_id = g.id
                WHERE gaa.client_id = ?
            ", [$id]);
            $allGroups = $this->db->fetchAll(
                "SELECT id, name FROM `$this->tGroups` ORDER BY name"
            );
        }

        $pingStatus = false;
        $uris = json_decode($client['redirect_uris'], true);
        if (!empty($uris)) {
            $parsed  = parse_url($uris[0]);
            $baseUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
            if (!empty($parsed['port'])) { $baseUrl .= ':' . $parsed['port']; }
            
            // Si le chemin contient un sous-dossier, on tente de le conserver pour la racine de l'app
            $path = $parsed['path'] ?? '';
            $pathParts = explode('/', trim($path, '/'));
            if (count($pathParts) > 1) {
                $baseUrl .= '/' . $pathParts[0];
            }
            
            $manifestUrl = $baseUrl . '/kronoconnect/manifest';
            $context     = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 2, 'ignore_errors' => true]]);
            if (@file_get_contents($manifestUrl, false, $context) !== false) {
                $pingStatus = true;
            }
        }

        $this->render('admin/client_detail', [
            'title'       => 'Client : ' . ($client['app_name'] ?: $client['name']),
            'page'        => 'clients',
            'client'      => $client,
            'permissions' => $permissions,
            'accessMode'  => $accessMode,
            'manualUsers' => $manualUsers,
            'groupAccess' => $groupAccess,
            'allUsers'    => $allUsers,
            'allGroups'   => $allGroups,
            'pingStatus'  => $pingStatus,
        ], 'admin');
    }

    public function clientCreate(): void
    {
        $appUrl = $_GET['app_url'] ?? '';
        $setupUrl = $_GET['setup_url'] ?? '';
        $setupToken = $_GET['setup_token'] ?? '';

        if ($appUrl && $setupUrl && $setupToken) {
            // C'est une requête d'association automatique
            $manifest = $this->fetchManifest($appUrl);
            
            $this->render('admin/client_setup_approve', [
                'title'      => 'Association d\'application',
                'page'       => 'clients',
                'appUrl'     => rtrim($appUrl, '/'),
                'setupUrl'   => $setupUrl,
                'setupToken' => $setupToken,
                'manifest'   => $manifest,
            ], 'admin');
            return;
        }

        $this->render('admin/client_create', [
            'title' => 'Nouveau client SSO',
            'page'  => 'clients',
        ], 'admin');
    }

    private function fetchManifest(string $url): ?array
    {
        $manifestUrl = rtrim($url, '/') . '/kronoconnect/manifest';
        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 3, 'ignore_errors' => true]]);
        $response = @file_get_contents($manifestUrl, false, $context);
        
        if ($response !== false) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }
        return null;
    }

    public function clientTestManifest(): void
    {
        $this->verifyCsrf();
        $url = rtrim($_POST['url'] ?? '', '/');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->json(['success' => false, 'error' => 'URL invalide.']);
        }

        \KronoConnect\Core\Session::close();
        $context  = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true]]);
        $response = @file_get_contents($url . '/kronoconnect/manifest', false, $context);

        if ($response === false) {
            $this->json(['success' => false, 'error' => 'Impossible de joindre le serveur. L\'application n\'est pas accessible ou ne répond pas.']);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $this->json(['success' => false, 'error' => 'La réponse n\'est pas un JSON valide. L\'application n\'expose pas de manifest correct.']);
        }

        $rawIcon = $data['icon'] ?? 'app-indicator';
        $cleanIcon = preg_replace('/^bi-/i', '', trim($rawIcon)) ?: 'app-indicator';

        $this->json([
            'success'     => true,
            'name'        => $data['name']        ?? '',
            'version'     => $data['version']     ?? '',
            'color'       => $data['color']       ?? '#3B82F6',
            'icon'        => $cleanIcon,
            'permissions' => $data['permissions'] ?? [],
        ]);
    }

    public function clientStore(): void
    {
        $this->verifyCsrf();

        $name        = trim(Security::sanitize($_POST['name']         ?? ''));
        $redirectUri = trim($_POST['redirect_uri']                    ?? '');
        $accessMode  = $_POST['access_mode']                          ?? 'open';
        $appColor    = $_POST['app_color']                            ?? '#3B82F6';
        $appIcon     = $_POST['app_icon']                             ?? 'app-indicator';
        $logoutUrl   = trim($_POST['logout_url']                      ?? '');
        $allowedIps  = trim($_POST['allowed_ips']                     ?? '');
        $permissions = json_decode($_POST['permissions_json']         ?? '[]', true);

        if (!$name) {
            redirect('/admin/clients/create', ['error' => 'Le nom de l\'application est requis.']);
        }
        if (!$redirectUri || !filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            redirect('/admin/clients/create', ['error' => 'L\'URI de redirection doit être une URL valide.']);
        }
        if ($logoutUrl && !filter_var($logoutUrl, FILTER_VALIDATE_URL)) {
            redirect('/admin/clients/create', ['error' => 'L\'URL de logout doit être une URL valide.']);
        }
        if (!in_array($accessMode, ['open', 'group', 'manual'])) {
            $accessMode = 'open';
        }

        // Vérification des doublons
        $existing = $this->db->fetchOne(
            "SELECT id FROM `{$this->tSsoClients}` WHERE redirect_uris LIKE ? OR (logout_url IS NOT NULL AND logout_url != '' AND logout_url = ?)",
            ['%'.trim($redirectUri).'%', $logoutUrl]
        );
        if ($existing) {
            redirect('/admin/clients/create', ['error' => 'Une application utilisant cette URL de redirection ou de déconnexion existe déjà.']);
        }

        $clientId     = $this->generateUuid();
        $plainSecret  = bin2hex(random_bytes(32));
        $hashedSecret = Security::hashPassword($plainSecret);

        $this->db->insert('sso_clients', [
            'client_id'          => $clientId,
            'client_secret'      => $hashedSecret,
            'client_secret_raw'  => $plainSecret,
            'name'               => $name,
            'app_name'           => $name,
            'app_color'          => $appColor,
            'app_icon'           => $appIcon,
            'access_mode'        => $accessMode,
            'redirect_uris'      => json_encode([$redirectUri]),
            'logout_url'         => $logoutUrl ?: null,
            'allowed_ips'        => $allowedIps ?: null,
            'manifest_synced_at' => date('Y-m-d H:i:s'),
        ]);

        Logger::info('Admin : application SSO créée', ['client_id' => $clientId, 'name' => $name, 'by' => Session::userId()]);
        $internalId = (int)$this->db->lastInsertId();

        if (is_array($permissions)) {
            foreach ($permissions as $perm) {
                if (empty($perm['key']) || empty($perm['label'])) continue;
                $this->db->insert('permissions', [
                    'client_id'   => $internalId,
                    'perm_key'    => $perm['key'],
                    'label'       => $perm['label'],
                    'description' => $perm['description'] ?? '',
                    'synced_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        Session::set('new_client_data', [
            'name'          => $name,
            'client_id'     => $clientId,
            'client_secret' => $plainSecret,
        ]);

        $setupUrl   = $_POST['setup_url']   ?? '';
        $setupToken = $_POST['setup_token'] ?? '';

        if ($setupUrl && $setupToken) {
            $kcUrl = get_base_url();
            
            $this->render('admin/client_setup_redirect', [
                'title'        => 'Association en cours',
                'page'         => 'clients',
                'setupUrl'     => $setupUrl,
                'setupToken'   => $setupToken,
                'clientId'     => $clientId,
                'clientSecret' => $plainSecret,
                'kcUrl'        => $kcUrl,
            ], 'admin');
            return;
        }

        redirect('/admin/clients/created', ['success' => 'Application créée avec succès.']);
    }

    public function clientSetupRefuse(): void
    {
        $this->verifyCsrf();

        $setupUrl   = $_POST['setup_url']   ?? '';
        $setupToken = $_POST['setup_token'] ?? '';

        if ($setupUrl && $setupToken) {
            $this->render('admin/client_setup_refuse_redirect', [
                'title'        => 'Association refusée',
                'page'         => 'clients',
                'setupUrl'     => $setupUrl,
                'setupToken'   => $setupToken,
            ], 'admin');
            return;
        }

        redirect('/admin/clients', ['error' => 'Données de refus invalides.']);
    }

    public function clientRegenerateSecret(string $idStr): void
    {
        $this->verifyCsrf();

        $id     = (int)$idStr;
        $client = $this->db->fetchOne("SELECT * FROM `$this->tSsoClients` WHERE id = ?", [$id]);

        if (!$client) {
            redirect('/admin/clients', ['error' => 'Client introuvable.']);
        }

        $plainSecret  = bin2hex(random_bytes(32));
        $hashedSecret = Security::hashPassword($plainSecret);

        $this->db->update('sso_clients', [
            'client_secret'     => $hashedSecret,
            'client_secret_raw' => $plainSecret,
        ], ['id' => $id]);

        Logger::warning('Admin : secret application SSO régénéré', ['client_id' => $client['client_id'], 'by' => Session::userId()]);

        Session::set('new_client_data', [
            'name'          => $client['app_name'] ?: $client['name'],
            'client_id'     => $client['client_id'],
            'client_secret' => $plainSecret,
        ]);

        redirect('/admin/clients/created', ['success' => 'Le secret du client a été régénéré avec succès.']);
    }

    public function clientDelete(): void
    {
        $this->verifyCsrf();

        $clientId = $_POST['client_id'] ?? '';
        if (!$clientId) {
            redirect('/admin/clients', ['error' => 'Identifiant client manquant.']);
        }

        $client = $this->db->fetchOne(
            "SELECT * FROM `$this->tSsoClients` WHERE client_id = ?",
            [$clientId]
        );
        if (!$client) {
            redirect('/admin/clients', ['error' => 'Client introuvable.']);
        }

        $uris = json_decode($client['redirect_uris'] ?? '[]', true) ?: [];
        $webhookSuccess = false;
        $attemptedWebhook = false;

        if (!empty($uris)) {
            $redirectUri = $uris[0];
            $baseUrl = rtrim(str_ireplace('/auth/callback', '', $redirectUri), '/');
            
            $targetUrl = $baseUrl . '/kronoconnect/disconnect';
            $secretRaw = $client['client_secret_raw'] ?? '';

            if (!empty($secretRaw)) {
                $attemptedWebhook = true;
                $payload = json_encode([
                    'action'    => 'disconnect',
                    'client_id' => $clientId
                ]);
                $timestamp = (string)time();
                $signature = hash_hmac('sha256', $clientId . ':' . $timestamp . ':' . $payload, $secretRaw);

                $ch = curl_init($targetUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 2,
                    CURLOPT_CONNECTTIMEOUT => 1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'X-Client-ID: ' . $clientId,
                        'X-Timestamp: ' . $timestamp,
                        'X-Signature: ' . $signature
                    ]
                ]);

                $response = curl_exec($ch);
                $info     = curl_getinfo($ch);
                curl_close($ch);

                if ($response !== false) {
                    $status = $info['http_code'] ?? 0;
                    if ($status >= 200 && $status < 300) {
                        $data = json_decode($response, true);
                        if (($data['status'] ?? '') === 'ok') {
                            $webhookSuccess = true;
                        }
                    }
                }
            }
        }

        $this->db->delete('sso_clients', ['client_id' => $clientId]);

        Logger::warning('Admin : application SSO supprimée', ['client_id' => $clientId, 'by' => Session::userId()]);

        if ($attemptedWebhook) {
            if ($webhookSuccess) {
                redirect('/admin/clients', ['success' => 'Client supprimé. Le SSO a été automatiquement désactivé sur l\'instance distante.']);
            } else {
                redirect('/admin/clients', ['warning' => 'Client supprimé de KronoConnect. Attention : l\'instance distante n\'a pas pu être contactée (hors ligne). Pensez à y désactiver le SSO manuellement pour éviter tout blocage.']);
            }
        } else {
            redirect('/admin/clients', ['success' => 'Client supprimé.']);
        }
    }

    public function clientSyncManifest(): void
    {
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            redirect('/admin/clients', ['error' => 'Client invalide.']);
        }

        // Libère le verrou de session avant l'appel réseau qui peut prendre jusqu'à 10s
        \KronoConnect\Core\Session::close();

        $service = new \KronoConnect\Services\ManifestService();
        $success = $service->sync($id);

        if ($success) {
            redirect('/admin/clients/' . $id, ['success' => 'Manifest synchronisé avec succès.']);
        } else {
            redirect('/admin/clients/' . $id, ['error' => 'Erreur lors de la synchronisation. Vérifiez l\'URI et le serveur distant.']);
        }
    }

    public function clientAccess(string $idStr): void
    {
        $id     = (int)$idStr;
        $client = $this->db->fetchOne("SELECT * FROM `$this->tSsoClients` WHERE id = ?", [$id]);

        if (!$client) {
            redirect('/admin/clients', ['error' => 'Client introuvable.']);
        }

        $accessMode  = $client['access_mode'] ?? 'open';
        $manualUsers = [];
        $groupAccess = [];
        $allGroups   = [];
        $allUsers    = [];

        if ($accessMode === 'manual') {
            $manualUsers = $this->db->fetchAll("
                SELECT u.id, u.nom, u.prenom, u.email, uaa.granted_at
                FROM `$this->tUserAppAccess` uaa
                JOIN `$this->tUsers` u ON uaa.user_id = u.id
                WHERE uaa.client_id = ?
            ", [$id]);
            $allUsers = $this->db->fetchAll(
                "SELECT id, nom, prenom, email FROM `$this->tUsers` ORDER BY nom, prenom"
            );
        } elseif ($accessMode === 'group') {
            $groupAccess = $this->db->fetchAll("
                SELECT g.id, g.name, g.description
                FROM `$this->tGroupAppAccess` gaa
                JOIN `$this->tGroups` g ON gaa.group_id = g.id
                WHERE gaa.client_id = ?
            ", [$id]);
            $allGroups = $this->db->fetchAll(
                "SELECT id, name FROM `$this->tGroups` ORDER BY name"
            );
        }

        $this->render('admin/clients_access', [
            'title'       => 'Accès : ' . ($client['app_name'] ?: $client['name']),
            'page'        => 'clients',
            'client'      => $client,
            'accessMode'  => $accessMode,
            'manualUsers' => $manualUsers,
            'groupAccess' => $groupAccess,
            'allUsers'    => $allUsers,
            'allGroups'   => $allGroups,
        ], 'admin');
    }

    public function clientAccessMode(string $idStr): void
    {
        $this->verifyCsrf();
        $id   = (int)$idStr;
        $mode = $_POST['access_mode'] ?? 'open';

        if (in_array($mode, ['open', 'group', 'manual'])) {
            $this->db->update('sso_clients', ['access_mode' => $mode], ['id' => $id]);
            Logger::info("Admin : mode d'accès application SSO modifié", ['client_id' => $id, 'access_mode' => $mode, 'by' => Session::userId()]);
            redirect("/admin/clients/$id", ['success' => 'Mode d\'accès mis à jour.']);
        }
        redirect("/admin/clients/$id", ['error' => 'Mode invalide.']);
    }

    public function clientAllowedIps(string $idStr): void
    {
        $this->verifyCsrf();
        $id = (int)$idStr;
        $allowedIps = trim($_POST['allowed_ips'] ?? '');

        $this->db->update('sso_clients', ['allowed_ips' => $allowedIps ?: null], ['id' => $id]);
        Logger::info("Admin : IPs autorisées modifiées", ['client_id' => $id, 'allowed_ips' => $allowedIps, 'by' => Session::userId()]);
        redirect("/admin/clients/$id", ['success' => 'Restrictions IP mises à jour.']);
    }

    public function clientAccessGrant(string $idStr): void
    {
        $this->verifyCsrf();
        $clientId = (int)$idStr;
        $type     = $_POST['type']      ?? '';
        $targetId = (int)($_POST['target_id'] ?? 0);

        if ($targetId > 0) {
            if ($type === 'user') {
                $this->db->query(
                    "INSERT IGNORE INTO `$this->tUserAppAccess` (user_id, client_id, granted_by) VALUES (?, ?, ?)",
                    [$targetId, $clientId, Session::userId()]
                );
                Logger::info('Admin : accès application SSO accordé', ['client_id' => $clientId, 'type' => $type, 'target_id' => $targetId, 'by' => Session::userId()]);
                redirect("/admin/clients/$clientId", ['success' => 'Accès accordé à l\'utilisateur.']);
            } elseif ($type === 'group') {
                $this->db->query(
                    "INSERT IGNORE INTO `$this->tGroupAppAccess` (group_id, client_id) VALUES (?, ?)",
                    [$targetId, $clientId]
                );
                Logger::info('Admin : accès application SSO accordé', ['client_id' => $clientId, 'type' => $type, 'target_id' => $targetId, 'by' => Session::userId()]);
                redirect("/admin/clients/$clientId", ['success' => 'Accès accordé au groupe.']);
            }
        }
        redirect("/admin/clients/$clientId", ['error' => 'Erreur lors de l\'ajout.']);
    }

    public function clientAccessRevoke(string $idStr): void
    {
        $this->verifyCsrf();
        $clientId = (int)$idStr;
        $type     = $_POST['type']      ?? '';
        $targetId = (int)($_POST['target_id'] ?? 0);

        if ($targetId > 0) {
            if ($type === 'user') {
                $this->db->delete('user_app_access', ['user_id' => $targetId, 'client_id' => $clientId]);
                Logger::warning('Admin : accès application SSO révoqué', ['client_id' => $clientId, 'type' => $type, 'target_id' => $targetId, 'by' => Session::userId()]);
                redirect("/admin/clients/$clientId", ['success' => 'Accès révoqué pour l\'utilisateur.']);
            } elseif ($type === 'group') {
                $this->db->delete('group_app_access', ['group_id' => $targetId, 'client_id' => $clientId]);
                Logger::warning('Admin : accès application SSO révoqué', ['client_id' => $clientId, 'type' => $type, 'target_id' => $targetId, 'by' => Session::userId()]);
                redirect("/admin/clients/$clientId", ['success' => 'Accès révoqué pour le groupe.']);
            }
        }
        redirect("/admin/clients/$clientId", ['error' => 'Erreur lors de la révocation.']);
    }

    // ── Liens personnalisés ───────────────────────────────────────────────────

    public function links(): void
    {
        $links = $this->db->fetchAll("SELECT * FROM `$this->tCustomLinks` ORDER BY title ASC");

        $this->render('admin/links', [
            'title' => 'Liens personnalisés',
            'page'  => 'links',
            'links' => $links,
        ], 'admin');
    }

    public function linkCreate(): void
    {
        $this->render('admin/link_form', [
            'title'       => 'Nouveau lien personnalisé',
            'page'        => 'links',
            'link'        => null,
            'accessMode'  => 'open',
            'manualUsers' => [],
            'groupAccess' => [],
            'allUsers'    => [],
            'allGroups'   => [],
        ], 'admin');
    }

    public function linkDetail(string $idStr): void
    {
        $id   = (int)$idStr;
        $link = $this->db->fetchOne("SELECT * FROM `$this->tCustomLinks` WHERE id = ?", [$id]);

        if (!$link) {
            redirect('/admin/links', ['error' => 'Lien introuvable.']);
        }

        $accessMode  = $link['access_mode'] ?? 'open';
        $manualUsers = [];
        $groupAccess = [];
        $allGroups   = [];
        $allUsers    = [];

        if ($accessMode === 'manual') {
            $manualUsers = $this->db->fetchAll("
                SELECT u.id, u.nom, u.prenom, u.email
                FROM `$this->tCustomLinkUserAccess` clua
                JOIN `$this->tUsers` u ON clua.user_id = u.id
                WHERE clua.link_id = ?
            ", [$id]);
            $allUsers = $this->db->fetchAll(
                "SELECT id, nom, prenom, email FROM `$this->tUsers` ORDER BY nom, prenom"
            );
        } elseif ($accessMode === 'group') {
            $groupAccess = $this->db->fetchAll("
                SELECT g.id, g.name, g.description
                FROM `$this->tCustomLinkGroupAccess` clga
                JOIN `$this->tGroups` g ON clga.group_id = g.id
                WHERE clga.link_id = ?
            ", [$id]);
            $allGroups = $this->db->fetchAll(
                "SELECT id, name FROM `$this->tGroups` ORDER BY name"
            );
        }

        $this->render('admin/link_form', [
            'title'       => 'Modifier le lien : ' . $link['title'],
            'page'        => 'links',
            'link'        => $link,
            'accessMode'  => $accessMode,
            'manualUsers' => $manualUsers,
            'groupAccess' => $groupAccess,
            'allUsers'    => $allUsers,
            'allGroups'   => $allGroups,
        ], 'admin');
    }

    public function linkStore(): void
    {
        $this->verifyCsrf();

        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $url         = trim($_POST['url'] ?? '');
        $icon        = trim($_POST['icon'] ?? 'link-45deg');
        $color       = trim($_POST['color'] ?? '#3b5fc0');
        $description = trim($_POST['description'] ?? '');
        $accessMode  = $_POST['access_mode'] ?? 'open';

        if (!$title || !$url) {
            $ref = $id > 0 ? "/admin/links/$id" : "/admin/links/create";
            redirect($ref, ['error' => 'Veuillez remplir le titre et l\'URL.']);
        }

        $data = [
            'title'       => $title,
            'url'         => $url,
            'icon'        => $icon,
            'color'       => $color,
            'description' => $description,
            'access_mode' => $accessMode,
        ];

        if ($id > 0) {
            $this->db->update('custom_links', $data, ['id' => $id]);
            Logger::info('Admin : lien personnalisé modifié', ['link_id' => $id, 'by' => Session::userId()]);
            redirect("/admin/links/$id", ['success' => 'Lien mis à jour.']);
        } else {
            $this->db->insert('custom_links', $data);
            Logger::info('Admin : lien personnalisé créé', ['by' => Session::userId()]);
            redirect('/admin/links', ['success' => 'Lien créé.']);
        }
    }

    public function linkDelete(): void
    {
        $this->verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->db->delete('custom_links', ['id' => $id]);
            Logger::warning('Admin : lien personnalisé supprimé', ['link_id' => $id, 'by' => Session::userId()]);
            redirect('/admin/links', ['success' => 'Lien supprimé.']);
        }
        redirect('/admin/links', ['error' => 'ID invalide.']);
    }

    public function linkAccessMode(string $idStr): void
    {
        $this->verifyCsrf();
        $id   = (int)$idStr;
        $mode = $_POST['access_mode'] ?? 'open';

        if (in_array($mode, ['open', 'group', 'manual'])) {
            $this->db->update('custom_links', ['access_mode' => $mode], ['id' => $id]);
            Logger::info("Admin : mode d'accès lien personnalisé modifié", ['link_id' => $id, 'access_mode' => $mode, 'by' => Session::userId()]);
            redirect("/admin/links/$id", ['success' => 'Mode d\'accès mis à jour.']);
        }
        redirect("/admin/links/$id", ['error' => 'Mode invalide.']);
    }

    public function linkAccessGrant(string $idStr): void
    {
        $this->verifyCsrf();
        $linkId   = (int)$idStr;
        $type     = $_POST['type']      ?? '';
        $targetId = (int)($_POST['target_id'] ?? 0);

        if ($targetId > 0) {
            if ($type === 'user') {
                $this->db->query(
                    "INSERT IGNORE INTO `$this->tCustomLinkUserAccess` (user_id, link_id) VALUES (?, ?)",
                    [$targetId, $linkId]
                );
                Logger::info('Admin : accès lien personnalisé accordé', ['link_id' => $linkId, 'type' => $type, 'target_id' => $targetId, 'by' => Session::userId()]);
                redirect("/admin/links/$linkId", ['success' => 'Accès accordé à l\'utilisateur.']);
            } elseif ($type === 'group') {
                $this->db->query(
                    "INSERT IGNORE INTO `$this->tCustomLinkGroupAccess` (group_id, link_id) VALUES (?, ?)",
                    [$targetId, $linkId]
                );
                Logger::info('Admin : accès lien personnalisé accordé', ['link_id' => $linkId, 'type' => $type, 'target_id' => $targetId, 'by' => Session::userId()]);
                redirect("/admin/links/$linkId", ['success' => 'Accès accordé au groupe.']);
            }
        }
        redirect("/admin/links/$linkId", ['error' => 'Erreur lors de l\'ajout.']);
    }

    public function linkAccessRevoke(string $idStr): void
    {
        $this->verifyCsrf();
        $linkId   = (int)$idStr;
        $type     = $_POST['type']      ?? '';
        $targetId = (int)($_POST['target_id'] ?? 0);

        if ($targetId > 0) {
            if ($type === 'user') {
                $this->db->delete('custom_link_user_access', ['user_id' => $targetId, 'link_id' => $linkId]);
                Logger::warning('Admin : accès lien personnalisé révoqué', ['link_id' => $linkId, 'type' => $type, 'target_id' => $targetId, 'by' => Session::userId()]);
                redirect("/admin/links/$linkId", ['success' => 'Accès révoqué pour l\'utilisateur.']);
            } elseif ($type === 'group') {
                $this->db->delete('custom_link_group_access', ['group_id' => $targetId, 'link_id' => $linkId]);
                Logger::warning('Admin : accès lien personnalisé révoqué', ['link_id' => $linkId, 'type' => $type, 'target_id' => $targetId, 'by' => Session::userId()]);
                redirect("/admin/links/$linkId", ['success' => 'Accès révoqué pour le groupe.']);
            }
        }
        redirect("/admin/links/$linkId", ['error' => 'Erreur lors de la révocation.']);
    }

    // ── Groupes ───────────────────────────────────────────────────────────────

    public function groups(): void
    {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        $total      = (int)$this->db->fetchOne("SELECT COUNT(*) as n FROM `$this->tGroups`")['n'];
        $totalPages = (int)ceil($total / $limit);

        $groups = $this->db->fetchAll("
            SELECT g.*,
                   (SELECT COUNT(*) FROM `$this->tGroupMembers`   WHERE group_id = g.id) as members_count,
                   (SELECT COUNT(DISTINCT c.id) 
                    FROM `$this->tSsoClients` c 
                    LEFT JOIN `$this->tGroupAppAccess` gaa ON gaa.client_id = c.id AND gaa.group_id = g.id 
                    WHERE c.access_mode = 'open' OR (c.access_mode = 'group' AND gaa.group_id = g.id)) as apps_count
            FROM `$this->tGroups` g
            ORDER BY g.name ASC
            LIMIT $limit OFFSET $offset
        ");

        $this->render('admin/groups', [
            'title'   => 'Groupes',
            'page'    => 'groups',
            'result'  => [
                'rows'       => $groups,
                'total'      => $total,
                'totalPages' => $totalPages,
                'page'       => $page,
            ],
            'filters' => ['page' => $page],
        ], 'admin');
    }

    public function groupNew(): void
    {
        $kcPermissions = file_exists(CONFIG_PATH . '/permissions.php')
            ? require CONFIG_PATH . '/permissions.php'
            : [];

        $this->render('admin/group_form', [
            'title'         => 'Nouveau groupe',
            'page'          => 'groups',
            'kcPermissions' => $kcPermissions,
        ], 'admin');
    }

    public function groupStore(): void
    {
        $this->verifyCsrf();
        $name = trim($_POST['name']        ?? '');
        $tech = trim($_POST['tech_name']   ?? '');
        $desc = trim($_POST['description'] ?? '');
        $perms = $_POST['kc_permissions'] ?? [];
        $requireMfa = isset($_POST['require_mfa']) ? 1 : 0;

        if (!$name) {
            redirect('/admin/groups/new', ['error' => 'Le nom est requis.']);
        }

        $tech = $tech ? strtolower(preg_replace('/[^a-z0-9_]/', '_', $tech)) : null;

        try {
            $groupId = (int)$this->db->insert('groups', [
                'name' => $name,
                'tech_name' => $tech,
                'description' => $desc,
                'require_mfa' => $requireMfa
            ]);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'tech_name') !== false) {
                redirect('/admin/groups/new', ['error' => 'Ce nom technique est déjà utilisé par un autre groupe.']);
            }
            throw $e;
        }

        if ($groupId > 0 && is_array($perms)) {
            foreach ($perms as $permKey) {
                $permKey = trim((string)$permKey);
                if ($permKey === '') continue;
                $this->db->query(
                    "INSERT IGNORE INTO `$this->tGroupPermissions` (group_id, client_id, perm_key) VALUES (?, NULL, ?)",
                    [$groupId, $permKey]
                );
            }
        }

        Logger::info('Admin : groupe créé', ['group_id' => $groupId, 'name' => $name, 'by' => Session::userId()]);
        redirect('/admin/groups/' . $groupId, ['success' => 'Groupe créé.']);
    }

    public function groupDelete(): void
    {
        $this->verifyCsrf();
        $id = (int)($_POST['group_id'] ?? 0);

        if ($id > 0) {
            $group = $this->db->fetchOne("SELECT is_system FROM `$this->tGroups` WHERE id = ?", [$id]);
            if ($group && $group['is_system'] == 1) {
                redirect('/admin/groups', ['error' => 'Ce groupe système ne peut pas être supprimé.']);
            }
            $this->db->delete('groups', ['id' => $id]);
            Logger::warning('Admin : groupe supprimé', ['group_id' => $id, 'by' => Session::userId()]);
            redirect('/admin/groups', ['success' => 'Groupe supprimé.']);
        }
        redirect('/admin/groups', ['error' => 'Erreur de suppression.']);
    }

    public function groupDetail(string $idStr): void
    {
        $id    = (int)$idStr;
        $group = $this->db->fetchOne("SELECT * FROM `$this->tGroups` WHERE id = ?", [$id]);

        if (!$group) {
            redirect('/admin/groups', ['error' => 'Groupe introuvable.']);
        }

        $members = $this->db->fetchAll("
            SELECT u.id, u.nom, u.prenom, u.email
            FROM `$this->tGroupMembers` gm
            JOIN `$this->tUsers` u ON gm.user_id = u.id
            WHERE gm.group_id = ?
            ORDER BY u.nom, u.prenom
        ", [$id]);

        $accessibleApps = $this->db->fetchAll("
            SELECT DISTINCT c.id, c.client_id, c.app_name, c.name
            FROM `$this->tSsoClients` c
            LEFT JOIN `$this->tGroupAppAccess` gaa ON gaa.client_id = c.id AND gaa.group_id = ?
            WHERE c.access_mode = 'open' OR (c.access_mode = 'group' AND gaa.group_id = ?)
        ", [$id, $id]);

        foreach ($accessibleApps as &$app) {
            $app['permissions_list'] = $this->db->fetchAll(
                "SELECT * FROM `$this->tPermissions` WHERE client_id = ?",
                [$app['id']]
            );
            $granted               = $this->db->fetchAll(
                "SELECT perm_key FROM `$this->tGroupPermissions` WHERE group_id = ? AND client_id = ?",
                [$id, $app['id']]
            );
            $app['granted_perms']  = array_column($granted, 'perm_key');
        }

        $kcPermissions = file_exists(CONFIG_PATH . '/permissions.php')
            ? require CONFIG_PATH . '/permissions.php'
            : [];

        $grantedKc     = $this->db->fetchAll(
            "SELECT perm_key FROM `$this->tGroupPermissions` WHERE group_id = ? AND client_id IS NULL",
            [$id]
        );
        $grantedKcKeys = array_column($grantedKc, 'perm_key');

        $this->render('admin/group_detail', [
            'title'          => 'Groupe : ' . $group['name'],
            'page'           => 'groups',
            'group'          => $group,
            'members'        => $members,
            'accessibleApps' => $accessibleApps,
            'kcPermissions'  => $kcPermissions,
            'grantedKcKeys'  => $grantedKcKeys,
        ], 'admin');
    }

    public function groupUpdateInfo(string $idStr): void
    {
        $this->verifyCsrf();
        $id = (int)$idStr;

        $group = $this->db->fetchOne("SELECT * FROM `$this->tGroups` WHERE id = ?", [$id]);
        if (!$group) {
            redirect('/admin/groups', ['error' => 'Groupe introuvable.']);
        }

        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $requireMfa = isset($_POST['require_mfa']) ? 1 : 0;

        if ($name === '') {
            redirect("/admin/groups/$id", ['error' => 'Le nom est requis.']);
        }

        $data = [
            'description' => $desc,
            'require_mfa' => $requireMfa
        ];
        // Le nom d'un groupe système est verrouillé
        $techName = trim($_POST['tech_name'] ?? '');
        if (!($group['is_system'] ?? false)) {
            $data['name'] = $name;
            $data['tech_name'] = $techName !== '' ? $techName : null;
        }

        $this->db->update('groups', $data, ['id' => $id]);

        // ── Enregistrement des permissions ──
        // 1. Suppression des anciennes permissions
        $this->db->query("DELETE FROM `$this->tGroupPermissions` WHERE group_id = ?", [$id]);

        // 2. Insertion des nouvelles permissions KronoConnect
        $kcPerms = $_POST['kc_perms'] ?? [];
        if (is_array($kcPerms)) {
            $kcPermissionsConfig = file_exists(CONFIG_PATH . '/permissions.php')
                ? require CONFIG_PATH . '/permissions.php'
                : [];
            $validKcKeys = array_column($kcPermissionsConfig, 'key');
            
            foreach ($kcPerms as $key => $val) {
                if (in_array($key, $validKcKeys)) {
                    $this->db->query(
                        "INSERT INTO `$this->tGroupPermissions` (group_id, client_id, perm_key) VALUES (?, NULL, ?)",
                        [$id, $key]
                    );
                }
            }
        }

        // 3. Insertion des nouvelles permissions des clients SSO
        $appPerms = $_POST['app_perms'] ?? [];
        if (is_array($appPerms)) {
            foreach ($appPerms as $clientId => $perms) {
                $clientId = (int)$clientId;
                if ($clientId <= 0 || !is_array($perms)) {
                    continue;
                }
                $validAppKeys = array_column($this->db->fetchAll(
                    "SELECT perm_key FROM `$this->tPermissions` WHERE client_id = ?",
                    [$clientId]
                ), 'perm_key');

                foreach ($perms as $key => $val) {
                    if (in_array($key, $validAppKeys)) {
                        $this->db->query(
                            "INSERT INTO `$this->tGroupPermissions` (group_id, client_id, perm_key) VALUES (?, ?, ?)",
                            [$id, $clientId, $key]
                        );
                    }
                }
            }
        }

        // ── Enregistrement des membres ──
        $userIds = $_POST['user_ids'] ?? [];
        if (!is_array($userIds)) {
            $userIds = [];
        }
        $userIds = array_map('intval', $userIds);
        $userIds = array_filter($userIds, fn($uid) => $uid > 0);

        $group = $this->db->fetchOne("SELECT tech_name FROM `$this->tGroups` WHERE id = ?", [$id]);
        $techName = $group ? $group['tech_name'] : 'user';

        $defGroup = $this->db->fetchOne("SELECT id FROM `$this->tGroups` WHERE tech_name = 'user' LIMIT 1");
        $defGroupId = $defGroup ? (int)$defGroup['id'] : null;

        // Récupérer les membres avant suppression
        $oldMembers = $this->db->fetchAll("SELECT user_id FROM `$this->tGroupMembers` WHERE group_id = ?", [$id]);
        $oldUserIds = array_map('intval', array_column($oldMembers, 'user_id'));

        // 1. Supprimer tous les membres actuels de ce groupe
        $this->db->delete('group_members', ['group_id' => $id]);

        // 2. Pour chaque nouvel utilisateur, le retirer de tout autre groupe puis l'ajouter à celui-ci et synchroniser son rôle
        foreach ($userIds as $uid) {
            $this->db->delete('group_members', ['user_id' => $uid]);
            $this->db->insert('group_members', ['group_id' => $id, 'user_id' => $uid]);
        }

        // 3. Pour chaque utilisateur retiré du groupe, le réaffecter au groupe par défaut et mettre à jour son rôle à 'user'
        $removedUserIds = array_diff($oldUserIds, $userIds);
        foreach ($removedUserIds as $uid) {
            if ($defGroupId !== null && $defGroupId !== $id) {
                $this->db->delete('group_members', ['user_id' => $uid]);
                $this->db->insert('group_members', ['group_id' => $defGroupId, 'user_id' => $uid]);
            }
        }

        Logger::info('Admin : groupe, membres et permissions modifiés', ['group_id' => $id, 'by' => Session::userId()]);
        redirect("/admin/groups/$id", ['success' => 'Groupe, membres et permissions mis à jour.']);
    }

    public function groupUpdateMembers(string $idStr): void
    {
        $this->verifyCsrf();
        $groupId = (int)$idStr;
        $action  = $_POST['action']  ?? '';

        if ($action === 'add') {
            $userIds = $_POST['user_ids'] ?? [];
            if (!is_array($userIds)) {
                $singleId = (int)($_POST['user_id'] ?? 0);
                $userIds = $singleId > 0 ? [$singleId] : [];
            }

            foreach ($userIds as $uid) {
                $uid = (int)$uid;
                if ($uid > 0) {
                    // Supprimer l'utilisateur de tous les autres groupes (1 utilisateur = 1 groupe maximum)
                    $this->db->delete('group_members', ['user_id' => $uid]);
                    // L'ajouter au nouveau groupe
                    $this->db->insert('group_members', ['group_id' => $groupId, 'user_id' => $uid]);
                }
            }
        } elseif ($action === 'remove') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                $this->db->delete('group_members', ['group_id' => $groupId, 'user_id' => $userId]);
                
                $defGroup = $this->db->fetchOne("SELECT id FROM `$this->tGroups` WHERE tech_name = 'user' LIMIT 1");
                if ($defGroup && (int)$defGroup['id'] !== $groupId) {
                    $this->db->insert('group_members', ['group_id' => $defGroup['id'], 'user_id' => $userId]);
                }
            }
        }
        Logger::info('Admin : membres du groupe mis à jour', ['group_id' => $groupId, 'action' => $action, 'by' => Session::userId()]);
        redirect("/admin/groups/$groupId", ['success' => 'Membres mis à jour.']);
    }

    public function userSearch(): void
    {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $this->json([]);
        }
        
        $s = '%' . $q . '%';
        $users = $this->db->fetchAll("
            SELECT id, nom, prenom, email 
            FROM `$this->tUsers` 
            WHERE email LIKE ? OR nom LIKE ? OR prenom LIKE ? OR CONCAT(prenom, ' ', nom) LIKE ?
            ORDER BY nom, prenom
            LIMIT 20
        ", [$s, $s, $s, $s]);
        
        $this->json($users);
    }

    public function groupUpdatePermissions(string $idStr): void
    {
        $this->verifyCsrf();
        $groupId = (int)$idStr;

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $this->json(['error' => 'Invalid JSON'], 400);
        }

        $clientId = (isset($input['client_id']) && $input['client_id'] !== null && $input['client_id'] !== '')
            ? (int)$input['client_id']
            : null;
        $permKey = $input['perm_key'] ?? '';
        $granted = (bool)($input['granted'] ?? false);

        if (!$permKey) {
            $this->json(['error' => 'perm_key missing'], 400);
        }

        if (!$granted) {
            if ($clientId === null) {
                $this->db->query(
                    "DELETE FROM `$this->tGroupPermissions` WHERE group_id = ? AND client_id IS NULL AND perm_key = ?",
                    [$groupId, $permKey]
                );
            } else {
                $this->db->query(
                    "DELETE FROM `$this->tGroupPermissions` WHERE group_id = ? AND client_id = ? AND perm_key = ?",
                    [$groupId, $clientId, $permKey]
                );
            }
        } else {
            if ($clientId === null) {
                $this->db->query(
                    "DELETE FROM `$this->tGroupPermissions` WHERE group_id = ? AND client_id IS NULL AND perm_key = ?",
                    [$groupId, $permKey]
                );
                $this->db->query(
                    "INSERT INTO `$this->tGroupPermissions` (group_id, client_id, perm_key) VALUES (?, NULL, ?)",
                    [$groupId, $permKey]
                );
            } else {
                $this->db->query(
                    "INSERT IGNORE INTO `$this->tGroupPermissions` (group_id, client_id, perm_key) VALUES (?, ?, ?)",
                    [$groupId, $clientId, $permKey]
                );
            }
        }

        Logger::info('Admin : permissions groupe mises à jour', ['group_id' => $groupId, 'client_id' => $clientId, 'perm_key' => $permKey, 'granted' => $granted, 'by' => Session::userId()]);
        $this->json(['success' => true]);
    }

    // ── Utilisateurs ──────────────────────────────────────────────────────────

    public function users(): void
    {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        $filters = [
            'search' => trim((string)($_GET['q']      ?? '')),
            'status' => trim((string)($_GET['status'] ?? '')),
            'role'   => trim((string)($_GET['role']   ?? '')),
        ];

        $where  = [];
        $params = [];

        if ($filters['search'] !== '') {
            $where[]  = "(u.email LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR CONCAT(u.prenom, ' ', u.nom) LIKE ?)";
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
        }
        if ($filters['status'] !== '') {
            $where[]  = "u.status = ?";
            $params[] = $filters['status'];
        }
        if ($filters['role'] !== '') {
            $where[]  = "g.tech_name = ?";
            $params[] = $filters['role'];
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total      = (int)$this->db->fetchOne("
            SELECT COUNT(*) as n 
            FROM `$this->tUsers` u 
            LEFT JOIN `$this->tGroupMembers` gm ON u.id = gm.user_id
            LEFT JOIN `$this->tGroups` g ON gm.group_id = g.id
            $whereSql
        ", $params)['n'];
        $totalPages = (int)ceil($total / $limit);

        $users = $this->db->fetchAll("
            SELECT u.id, u.email, u.nom, u.prenom, g.tech_name AS role, u.is_active, u.status, u.can_change_email, u.created_at,
                   g.name AS group_name, g.id AS group_id
            FROM `$this->tUsers` u
            LEFT JOIN `$this->tGroupMembers` gm ON u.id = gm.user_id
            LEFT JOIN `$this->tGroups` g ON gm.group_id = g.id
            $whereSql
            ORDER BY u.created_at DESC
            LIMIT $limit OFFSET $offset
        ", $params);

        // Comptes en attente d'approbation
        $pending = $this->db->fetchAll("
            SELECT id, email, nom, prenom, created_at
            FROM `$this->tUsers`
            WHERE status = 'attente_validation'
            ORDER BY created_at ASC
        ");

        $allGroups = $this->db->fetchAll("SELECT id, name, tech_name FROM `$this->tGroups` ORDER BY name ASC");

        $this->render('admin/users', [
            'title'   => 'Utilisateurs',
            'page'    => 'users',
            'result'  => [
                'rows'       => $users,
                'total'      => $total,
                'totalPages' => $totalPages,
                'page'       => $page,
            ],
            'filters' => $filters,
            'pending' => $pending,
            'allGroups' => $allGroups,
        ], 'admin');
    }

    // POST /admin/users/{id}/approve
    public function approveUser(string $idStr): void
    {
        $this->verifyCsrf();
        $id = (int)$idStr;

        $user = $this->db->fetchOne("SELECT id FROM `$this->tUsers` WHERE id = ?", [$id]);
        if (!$user) {
            redirect('/admin/users', ['error' => 'Utilisateur introuvable.']);
        }
        $this->db->update('users', ['status' => 'actif', 'is_active' => 1], ['id' => $id]);
        Logger::info('Admin : inscription approuvée', ['user_id' => $id, 'by' => Session::userId()]);
        redirect('/admin/users', ['success' => 'Le compte a été approuvé et activé.']);
    }

    // POST /admin/users/{id}/reject
    public function rejectUser(string $idStr): void
    {
        $this->verifyCsrf();
        $id = (int)$idStr;

        $user = $this->db->fetchOne("SELECT id FROM `$this->tUsers` WHERE id = ? AND status = 'attente_validation'", [$id]);
        if (!$user) {
            redirect('/admin/users', ['error' => 'Aucun compte en attente trouvé.']);
        }
        $this->db->delete('users', ['id' => $id]);
        Logger::warning('Admin : inscription rejetée (compte supprimé)', ['user_id' => $id, 'by' => Session::userId()]);
        redirect('/admin/users', ['success' => 'L\'inscription a été rejetée et le compte supprimé.']);
    }

    public function userDetail(string $idStr): void
    {
        $id   = (int)$idStr;
        $userModel = new \KronoConnect\Models\UserModel();
        $user = $userModel->findById($id);

        if (!$user) {
            redirect('/admin/users', ['error' => 'Utilisateur introuvable.']);
        }

        $groups = $this->db->fetchAll("
            SELECT g.id, g.name
            FROM `$this->tGroupMembers` gm
            JOIN `$this->tGroups` g ON gm.group_id = g.id
            WHERE gm.user_id = ?
        ", [$id]);

        $manualApps = $this->db->fetchAll("
            SELECT c.id, c.client_id, c.name, c.app_name, uaa.granted_at
            FROM `$this->tUserAppAccess` uaa
            JOIN `$this->tSsoClients` c ON uaa.client_id = c.id
            WHERE uaa.user_id = ?
        ", [$id]);

        $isSuperAdmin = $user['role'] === 'super_admin';

        if ($isSuperAdmin) {
            $accessibleApps = $this->db->fetchAll("SELECT id, client_id, app_name, name FROM `$this->tSsoClients` ORDER BY name");
        } else {
            $accessibleApps = $this->db->fetchAll("
                SELECT DISTINCT c.id, c.client_id, c.app_name, c.name
                FROM `$this->tSsoClients` c
                LEFT JOIN `$this->tGroupAppAccess` gaa ON c.id = gaa.client_id
                LEFT JOIN `$this->tGroupMembers` gm ON gaa.group_id = gm.group_id AND gm.user_id = ?
                LEFT JOIN `$this->tUserAppAccess` uaa ON c.id = uaa.client_id AND uaa.user_id = ?
                WHERE c.access_mode = 'open'
                OR gm.user_id IS NOT NULL
                OR uaa.user_id IS NOT NULL
            ", [$id, $id]);
        }

        foreach ($accessibleApps as &$app) {
            $app['permissions_list'] = $this->db->fetchAll(
                "SELECT * FROM `$this->tPermissions` WHERE client_id = ?",
                [$app['id']]
            );
            
            if ($isSuperAdmin) {
                $app['group_perms'] = array_column($app['permissions_list'], 'perm_key');
            } else {
                $groupPerms           = $this->db->fetchAll("
                    SELECT gp.perm_key
                    FROM `$this->tGroupPermissions` gp
                    JOIN `$this->tGroupMembers` gm ON gp.group_id = gm.group_id
                    WHERE gm.user_id = ? AND gp.client_id = ?
                ", [$id, $app['id']]);
                $app['group_perms']   = array_column($groupPerms, 'perm_key');
            }

            $userPerms            = $this->db->fetchAll("
                SELECT perm_key, granted
                FROM `$this->tUserPermissions`
                WHERE user_id = ? AND client_id = ?
            ", [$id, $app['id']]);
            $app['user_perms']    = [];
            foreach ($userPerms as $up) {
                $app['user_perms'][$up['perm_key']] = (int)$up['granted'];
            }
        }

        $kcPermissions = file_exists(CONFIG_PATH . '/permissions.php')
            ? require CONFIG_PATH . '/permissions.php'
            : [];

        if ($isSuperAdmin) {
            $kcGroupPermKeys = array_column($kcPermissions, 'key');
        } else {
            $kcGroupPerms = $this->db->fetchAll("
                SELECT gp.perm_key
                FROM `$this->tGroupPermissions` gp
                JOIN `$this->tGroupMembers` gm ON gp.group_id = gm.group_id
                WHERE gm.user_id = ? AND gp.client_id IS NULL
            ", [$id]);
            $kcGroupPermKeys = array_column($kcGroupPerms, 'perm_key');
        }

        $kcUserPerms = $this->db->fetchAll("
            SELECT perm_key, granted
            FROM `$this->tUserPermissions`
            WHERE user_id = ? AND client_id IS NULL
        ", [$id]);
        $kcUserOverrides = [];
        foreach ($kcUserPerms as $up) {
            $kcUserOverrides[$up['perm_key']] = (int)$up['granted'];
        }

        $serviceModel = new \KronoConnect\Models\ServiceModel();
        $allGroups = $this->db->fetchAll("SELECT id, name, tech_name FROM `$this->tGroups` ORDER BY name ASC");

        $this->render('admin/user_detail', [
            'title'           => 'Utilisateur : ' . $user['prenom'] . ' ' . $user['nom'],
            'page'            => 'users',
            'user'            => $user,
            'groups'          => $groups,
            'allGroups'       => $allGroups,
            'manualApps'      => $manualApps,
            'accessibleApps'  => $accessibleApps,
            'kcPermissions'   => $kcPermissions,
            'kcGroupPermKeys' => $kcGroupPermKeys,
            'kcUserOverrides' => $kcUserOverrides,
            'services'        => $serviceModel->getTree()
        ], 'admin');
    }

    public function userUpdatePermissions(string $idStr): void
    {
        $this->verifyCsrf();
        $userId = (int)$idStr;

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $this->json(['error' => 'Invalid JSON'], 400);
        }

        $clientId = (isset($input['client_id']) && $input['client_id'] !== null && $input['client_id'] !== '')
            ? (int)$input['client_id']
            : null;
        $permKey = $input['perm_key'] ?? '';

        if (!$permKey) {
            $this->json(['error' => 'perm_key missing'], 400);
        }

        if (!array_key_exists('granted', $input) || $input['granted'] === null) {
            if ($clientId === null) {
                $this->db->query(
                    "DELETE FROM `$this->tUserPermissions` WHERE user_id = ? AND client_id IS NULL AND perm_key = ?",
                    [$userId, $permKey]
                );
            } else {
                $this->db->query(
                    "DELETE FROM `$this->tUserPermissions` WHERE user_id = ? AND client_id = ? AND perm_key = ?",
                    [$userId, $clientId, $permKey]
                );
            }
        } else {
            $grantedVal = $input['granted'] ? 1 : 0;
            if ($clientId === null) {
                $this->db->query(
                    "DELETE FROM `$this->tUserPermissions` WHERE user_id = ? AND client_id IS NULL AND perm_key = ?",
                    [$userId, $permKey]
                );
                $this->db->query(
                    "INSERT INTO `$this->tUserPermissions` (user_id, client_id, perm_key, granted) VALUES (?, NULL, ?, ?)",
                    [$userId, $permKey, $grantedVal]
                );
            } else {
                $this->db->query(
                    "INSERT INTO `$this->tUserPermissions` (user_id, client_id, perm_key, granted) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE granted = VALUES(granted)",
                    [$userId, $clientId, $permKey, $grantedVal]
                );
            }
        }

        Logger::info('Admin : permissions utilisateur mises à jour', ['user_id' => $userId, 'client_id' => $clientId, 'perm_key' => $permKey, 'granted' => $input['granted'] ?? null, 'by' => Session::userId()]);
        $this->json(['success' => true]);
    }

    public function userToggle(): void
    {
        $this->verifyCsrf();

        $id = (int)($_POST['user_id'] ?? 0);
        if ($id && $id !== Session::userId()) {
            $user = $this->db->fetchOne("SELECT is_active, email FROM `$this->tUsers` WHERE id = ?", [$id]);
            if ($user) {
                $newStatus = $user['is_active'] ? 0 : 1;
                $this->db->update('users', ['is_active' => $newStatus], ['id' => $id]);
                Logger::info('Admin : statut utilisateur modifié', ['user_id' => $id, 'active' => $newStatus, 'by' => Session::userId()]);
                
                // Si l'utilisateur est désactivé (active passe à 0), on propage la déconnexion
                if ($newStatus === 0) {
                    try {
                        \KronoConnect\Services\LogoutService::notifyClients($id, $user['email']);
                    } catch (\Throwable $e) {
                        Logger::error('Erreur SLO lors de la désactivation de l\'utilisateur', ['user_id' => $id, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        redirect('/admin/users', ['success' => 'Statut mis à jour.']);
    }

    public function userSave(): void
    {
        $this->verifyCsrf();

        $id      = (int)($_POST['user_id'] ?? 0);
        $email   = trim($_POST['email']    ?? '');
        $nom     = trim($_POST['nom']      ?? '');
        $prenom  = trim($_POST['prenom']   ?? '');
        $phone   = trim($_POST['phone']    ?? '');
        $serviceId = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
        
        $groupId = (int)($_POST['group_id'] ?? 0);
        $group   = $groupId > 0 ? $this->db->fetchOne("SELECT id, tech_name FROM `$this->tGroups` WHERE id = ?", [$groupId]) : null;
        $role    = $group ? $group['tech_name'] : 'user';
        
        $password = $_POST['password']     ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '/admin/users';

        if (!$email || !$nom || !$prenom) {
            redirect($referer, ['error' => 'Veuillez remplir tous les champs obligatoires.']);
        }

        if ($id > 0) {
            $exists = $this->db->fetchOne(
                "SELECT id FROM `$this->tUsers` WHERE email = ? AND id != ?",
                [$email, $id]
            );
            if ($exists) {
                redirect($referer, ['error' => 'Cette adresse email est déjà utilisée.']);
            }

            $updateData = [
                'email' => $email, 
                'nom' => $nom, 
                'prenom' => $prenom, 
                'phone' => $phone,
                'service_id' => $serviceId
            ];
            if ($password !== '') {
                $updateData['password'] = Security::hashPassword($password);
            }

            $this->db->update('users', $updateData, ['id' => $id]);
            
            if ($group) {
                $this->db->delete('group_members', ['user_id' => $id]);
                $this->db->insert('group_members', ['group_id' => $group['id'], 'user_id' => $id]);
            }
            
            Logger::info('Admin : utilisateur modifié', ['user_id' => $id, 'role' => $role, 'by' => Session::userId()]);
            redirect($referer, ['success' => 'Utilisateur mis à jour avec succès.']);
        } else {
            if ($password === '') {
                redirect($referer, ['error' => 'Le mot de passe est obligatoire pour un nouvel utilisateur.']);
            }

            $exists = $this->db->fetchOne(
                "SELECT id FROM `$this->tUsers` WHERE email = ?",
                [$email]
            );
            if ($exists) {
                redirect($referer, ['error' => 'Cette adresse email est déjà utilisée.']);
            }

            $this->db->insert('users', [
                'email'      => $email,
                'password'   => Security::hashPassword($password),
                'nom'        => $nom,
                'prenom'     => $prenom,
                'phone'      => $phone,
                'service_id' => $serviceId,
                'is_active'  => 1,
            ]);
            $newUserId = (int)$this->db->lastInsertId();
            
            if ($group) {
                $this->db->insert('group_members', ['group_id' => $group['id'], 'user_id' => $newUserId]);
            } else {
                $defGroup = $this->db->fetchOne("SELECT id FROM `$this->tGroups` WHERE tech_name = 'user' LIMIT 1");
                if ($defGroup) {
                    $this->db->insert('group_members', ['group_id' => $defGroup['id'], 'user_id' => $newUserId]);
                }
            }
            
            Logger::info('Admin : utilisateur créé', ['new_user_id' => $newUserId, 'role' => $role, 'by' => Session::userId()]);
            redirect('/admin/users', ['success' => 'Utilisateur créé avec succès.']);
        }
    }

    // POST /admin/users/{id}/delete
    public function userDelete(string $idStr): void
    {
        $this->verifyCsrf();
        $id = (int)$idStr;

        if (!Session::hasPermission('kc.users.delete') && !Session::hasRole('super_admin')) {
            redirect('/admin/users', ['error' => 'Vous n\'avez pas la permission de supprimer des utilisateurs.']);
        }

        if ($id === Session::userId()) {
            redirect('/admin/users', ['error' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        $user = $this->db->fetchOne("
            SELECT u.id, u.email, g.tech_name AS role 
            FROM `$this->tUsers` u 
            LEFT JOIN `$this->tGroupMembers` gm ON u.id = gm.user_id
            LEFT JOIN `$this->tGroups` g ON gm.group_id = g.id
            WHERE u.id = ?
        ", [$id]);
        if (!$user) {
            redirect('/admin/users', ['error' => 'Utilisateur introuvable.']);
        }

        // Empêcher un admin de supprimer un super-admin
        if ($user['role'] === 'super_admin' && !Session::hasRole('super_admin')) {
            redirect('/admin/users', ['error' => 'Seul un super-administrateur peut supprimer un compte super-administrateur.']);
        }

        // 1. Notifier les applications clientes de la déconnexion (SLO)
        try {
            \KronoConnect\Services\LogoutService::notifyClients($id, $user['email']);
        } catch (\Throwable $e) {
            Logger::error('Erreur SLO lors de la suppression de l\'utilisateur', ['user_id' => $id, 'error' => $e->getMessage()]);
        }

        // 2. Supprimer manuellement les logs de connexion (pas de clé étrangère automatique)
        $this->db->query("DELETE FROM `$this->tConnLogs` WHERE user_id = ?", [$id]);

        // 3. Supprimer le compte utilisateur de la base
        $this->db->delete('users', ['id' => $id]);

        Logger::warning('Admin : utilisateur supprimé définitivement', [
            'deleted_user_id' => $id,
            'email'           => $user['email'],
            'by'              => Session::userId()
        ]);

        redirect('/admin/users', ['success' => 'L\'utilisateur a été supprimé définitivement.']);
    }

    // ── Services ──────────────────────────────────────────────────────────────

    public function services(): void
    {
        $this->requireAdmin();
        $serviceModel = new \KronoConnect\Models\ServiceModel();
        
        $this->render('admin/services', [
            'title' => 'Gestion des Services',
            'page'  => 'services',
            'tree'  => $serviceModel->getTree()
        ], 'admin');
    }

    public function serviceStore(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $name = Security::sanitize($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $description = Security::sanitize($_POST['description'] ?? '');

        if (!$name) {
            redirect('/admin/services', ['error' => 'Le nom du service est obligatoire.']);
        }

        $serviceModel = new \KronoConnect\Models\ServiceModel();
        $serviceModel->create($name, $parentId, $description);

        Logger::info('Admin : service créé', ['name' => $name, 'by' => Session::userId()]);
        redirect('/admin/services', ['success' => 'Service créé avec succès.']);
    }

    public function serviceUpdate(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $name = Security::sanitize($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if ($id <= 0 || !$name) {
            redirect('/admin/services', ['error' => 'Veuillez remplir tous les champs obligatoires.']);
        }

        // Empêcher un service d'être son propre parent
        if ($parentId === $id) {
            redirect('/admin/services', ['error' => 'Un service ne peut pas être son propre parent.']);
        }

        $serviceModel = new \KronoConnect\Models\ServiceModel();
        $serviceModel->updateInfo($id, $name, $parentId);

        Logger::info('Admin : service modifié', ['service_id' => $id, 'name' => $name, 'by' => Session::userId()]);
        redirect('/admin/services', ['success' => 'Service mis à jour avec succès.']);
    }

    public function serviceOrder(): void
    {
        $this->requireAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $orders = $input['orders'] ?? [];

        $serviceModel = new \KronoConnect\Models\ServiceModel();
        foreach ($orders as $order) {
            $serviceModel->updateOrder(
                (int)$order['id'],
                !empty($order['parent_id']) ? (int)$order['parent_id'] : null,
                (int)$order['position']
            );
        }

        Logger::info('Admin : ordonnancement des services mis à jour', ['by' => Session::userId()]);
        $this->json(['success' => true]);
    }

    public function serviceDelete(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            redirect('/admin/services', ['error' => 'ID invalide.']);
        }

        $serviceModel = new \KronoConnect\Models\ServiceModel();
        $serviceModel->deleteService($id);

        Logger::warning('Admin : service supprimé', ['service_id' => $id, 'by' => Session::userId()]);
        redirect('/admin/services', ['success' => 'Service supprimé avec succès.']);
    }

    public function userMfaDisable(string $idStr): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $userId = (int)$idStr;
        
        $userModel = new \KronoConnect\Models\UserModel();
        $user = $userModel->findById($userId);
        if (!$user) {
            redirect('/admin/users', ['error' => 'Utilisateur introuvable.']);
        }

        $userModel->disableMfa($userId);

        Logger::warning('Admin : MFA réinitialisé pour l\'utilisateur', [
            'user_id' => $userId,
            'email' => $user['email'],
            'by' => Session::userId()
        ]);

        redirect('/admin/users/' . $userId, ['success' => 'L\'authentification à double facteur a été désactivée pour cet utilisateur.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        if (!Session::isLoggedIn()) {
            redirect('/login');
        }
        if (!Session::hasRole('admin', 'super_admin')) {
            redirect('/login', ['error' => 'Accès réservé aux administrateurs.']);
        }
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
