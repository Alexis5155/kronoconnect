<?php
declare(strict_types=1);

define('KRONO_APP_NAME',  'KronoConnect');
define('KRONO_VERSION',   '1.0.0');
define('ROOT_PATH',       dirname(__DIR__));
define('MIGRATIONS_PATH', ROOT_PATH . '/database/migrations');
define('CONFIG_PATH',     ROOT_PATH . '/app/config');
define('LOCK_FILE',       __DIR__ . '/install.lock');

// ── Protection install.lock ──────────────────────────────────────────────────
if (file_exists(LOCK_FILE)) {
    $isInstallAction = (($_GET['action'] ?? '') === 'install');
    if (!$isInstallAction) {
        if (isset($_GET['action'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Application déjà installée.']);
        } else {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Verrouillé</title>
            <style>body{font-family:system-ui;display:flex;align-items:center;justify-content:center;
            min-height:100vh;margin:0;background:#0a0e1a;color:#e8eeff;}
            .b{text-align:center;}.b h1{color:#f87171;}.b a{color:#818cf8;font-weight:700;text-decoration:none;}</style></head>
            <body><div class="b"><h1>🔒 Déjà installé</h1>
            <p style="color:rgba(255,255,255,.5);margin-bottom:1.5rem;">Supprimez <code>install/install.lock</code> pour réinstaller.</p>
            <a href="/">← Retour</a></div></body></html>';
        }
        exit;
    }
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    match ($_GET['action']) {
        'check_requirements' => ajaxCheckRequirements(),
        'check_db'           => ajaxCheckDb($body),
        'check_smtp'         => ajaxCheckSmtp($body),
        'install'            => ajaxInstall($body),
        default              => ajaxError('Action inconnue.'),
    };
    exit;
}

function ajaxCheckRequirements(): void {
    [$ok, $items] = checkRequirements();
    echo json_encode(['ok' => $ok, 'items' => $items]);
}

function ajaxCheckDb(array $b): void {
    $host = trim($b['db_host'] ?? ''); $port = (int)($b['db_port'] ?? 3306);
    $name = trim($b['db_name'] ?? ''); $user = trim($b['db_user'] ?? ''); $pass = $b['db_pass'] ?? '';
    if (!$host || !$name || !$user) ajaxError('Hôte, nom de la base et utilisateur sont obligatoires.');
    try { makePdo($host, $port, $name, $user, $pass); echo json_encode(['ok' => true, 'message' => 'Connexion réussie.']); }
    catch (\PDOException $e) { ajaxError('Connexion impossible : ' . $e->getMessage()); }
}

function ajaxCheckSmtp(array $b): void {
    $host = trim($b['smtp_host'] ?? ''); $port = (int)($b['smtp_port'] ?? 587);
    if (!$host) ajaxError('Hôte SMTP requis.');
    $conn = @fsockopen($host, $port, $errno, $errstr, 5);
    if (!$conn) ajaxError("Impossible de joindre {$host}:{$port} — {$errstr}");
    fclose($conn);
    echo json_encode(['ok' => true, 'message' => "Connexion TCP à {$host}:{$port} réussie."]);
}

function ajaxInstall(array $b): void {
    @ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Accel-Buffering: no'); header('Cache-Control: no-cache');

    function tlog(string $type, string $msg): void { echo json_encode(['type' => $type, 'msg' => $msg]) . "\n"; flush(); }

    if (file_exists(LOCK_FILE)) { tlog('error', 'Déjà installé.'); return; }

    $db     = $b['db']    ?? [];
    $smtp   = $b['smtp']  ?? [];
    $app    = $b['app']   ?? [];
    $admin  = $b['admin'] ?? [];

    if (empty($db['db_host']) || empty($db['db_name']) || empty($db['db_user'])) { tlog('error', 'Paramètres BDD manquants.'); return; }
    if (empty($admin['email']) || empty($admin['password'])) { tlog('error', 'E-mail ou mot de passe admin manquant.'); return; }
    if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) { tlog('error', 'Adresse e-mail admin invalide.'); return; }
    if (strlen($admin['password']) < 8) { tlog('error', 'Le mot de passe admin doit comporter au moins 8 caractères.'); return; }

    try {
        // ── Répertoires de stockage ───────────────────────────────────────
        tlog('info', 'Création des répertoires de stockage…');
        foreach ([ROOT_PATH . '/storage/logs', ROOT_PATH . '/storage/cache'] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Impossible de créer : {$dir}");
            }
        }
        tlog('success', 'Répertoires créés.');

        // ── Connexion BDD ─────────────────────────────────────────────────
        tlog('info', 'Connexion à la base de données…');
        $pdo = makePdo($db['db_host'], (int)($db['db_port'] ?? 3306), $db['db_name'], $db['db_user'], $db['db_pass'] ?? '');
        tlog('success', 'Connexion établie.');

        // ── Migrations SQL ────────────────────────────────────────────────
        $prefix = $db['db_prefix'] ?? '';
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}migrations` (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration_name VARCHAR(255) NOT NULL UNIQUE, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        $schemaPath = MIGRATIONS_PATH . '/schema.sql';
        if (is_file($schemaPath)) {
            tlog('info', "Importation de schema.sql…");
            $sql = str_replace('{PREFIX}', $prefix, file_get_contents($schemaPath));
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt) $pdo->exec($stmt);
            }
            tlog('success', "schema.sql appliqué.");
        }

        $jsonPath = MIGRATIONS_PATH . '/migrations.json';
        if (is_file($jsonPath)) {
            $data = json_decode(file_get_contents($jsonPath), true);
            if (is_array($data) && !empty($data['migrations'])) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO `{$prefix}migrations` (migration_name, applied_at) VALUES (?, NOW())");
                foreach ($data['migrations'] as $mName) {
                    $stmt->execute([$mName]);
                }
                tlog('info', count($data['migrations']) . " migration(s) marquée(s) comme appliquée(s).");
            }
        }

        // ── Compte super-administrateur ───────────────────────────────────
        tlog('info', 'Création du super-administrateur…');
        $hash = password_hash($admin['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO `{$prefix}users` (nom, prenom, email, password, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$admin['nom'] ?? '', $admin['prenom'] ?? '', $admin['email'], $hash]);
        $adminUserId = (int) $pdo->lastInsertId();
        tlog('success', 'Compte admin créé.');

        // ── Affectation au groupe super_admin ─────────────────────────────
        $group = $pdo->query("SELECT id FROM `{$prefix}groups` WHERE tech_name = 'super_admin' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        if ($group && $adminUserId) {
            $pdo->prepare("INSERT IGNORE INTO `{$prefix}group_members` (group_id, user_id) VALUES (?, ?)")
                ->execute([$group['id'], $adminUserId]);
            tlog('success', 'Super-administrateur affecté au groupe super_admin.');
        }

        // ── Fichiers de config ────────────────────────────────────────────
        tlog('info', 'Génération de database.php…'); writeDatabaseConfig($db); tlog('success', 'database.php généré.');
        tlog('info', 'Mise à jour de app.php…'); writeAppConfig($app['app_name'] ?? KRONO_APP_NAME, $app['base_url'] ?? ''); tlog('success', 'app.php mis à jour.');

        // ── Verrouillage ──────────────────────────────────────────────────
        tlog('info', 'Verrouillage du wizard…');
        file_put_contents(LOCK_FILE, json_encode([
            'installed_at' => date('c'),
            'version'      => KRONO_VERSION,
            'installed_by' => $admin['email'],
            'app_name'     => $app['app_name'] ?? KRONO_APP_NAME,
        ]));
        tlog('success', 'install.lock créé.');
        tlog('done', ($app['base_url'] ?? '') . '/login');

    } catch (\PDOException $e)    { tlog('error', 'Erreur BDD : '    . $e->getMessage()); }
      catch (\RuntimeException $e) { tlog('error', 'Erreur système : ' . $e->getMessage()); }
}

function ajaxError(string $msg): never { echo json_encode(['ok' => false, 'error' => $msg]); exit; }

function makePdo(string $host, int $port, string $db, string $user, string $pass): \PDO {
    return new \PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user, $pass,
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
}

function checkRequirements(): array {
    $items = []; $allOk = true;
    $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
    if (!$phpOk) $allOk = false;
    $items[] = ['label' => 'PHP ≥ 8.2', 'value' => PHP_VERSION, 'status' => $phpOk ? 'ok' : 'ko', 'required' => true];

    foreach (['pdo' => ['PDO', true], 'pdo_mysql' => ['PDO MySQL', true], 'mbstring' => ['mbstring', true],
              'openssl' => ['OpenSSL', true], 'json' => ['JSON', true], 'fileinfo' => ['fileinfo', false]] as $ext => [$label, $req]) {
        $loaded = extension_loaded($ext); if ($req && !$loaded) $allOk = false;
        $items[] = ['label' => "Ext. {$label}", 'value' => $loaded ? 'OK' : 'Manquante', 'status' => $loaded ? 'ok' : ($req ? 'ko' : 'warning'), 'required' => $req];
    }

    // Pour les dirs de stockage, on vérifie que le parent est accessible s'ils n'existent pas encore
    $dirs = [
        ROOT_PATH . '/storage' => 'storage/ (à créer)',
        ROOT_PATH . '/app/config' => 'app/config/',
        __DIR__ => 'install/',
    ];
    foreach ($dirs as $path => $label) {
        if (is_dir($path)) {
            $ok = is_writable($path);
        } else {
            // Dir n'existe pas encore — sera créé par le wizard si le parent est accessible
            $ok = is_writable(dirname($path));
        }
        if (!$ok) $allOk = false;
        $items[] = ['label' => "Dossier {$label}", 'value' => $ok ? 'OK' : 'Non accessible', 'status' => $ok ? 'ok' : 'ko', 'required' => true];
    }
    return [$allOk, $items];
}

function writeDatabaseConfig(array $db): void {
    $h = addslashes($db['db_host']); $p = (int)($db['db_port'] ?? 3306);
    $n = addslashes($db['db_name']); $u = addslashes($db['db_user']);
    $w = addslashes($db['db_pass'] ?? ''); $x = addslashes($db['db_prefix'] ?? '');
    $c = "<?php\n// Généré par le wizard — NE PAS VERSIONNER\nreturn ['host'=>'{$h}','port'=>{$p},'database'=>'{$n}','username'=>'{$u}','password'=>'{$w}','prefix'=>'{$x}','charset'=>'utf8mb4','options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]];\n";
    if (file_put_contents(CONFIG_PATH . '/database.php', $c) === false) throw new \RuntimeException("Impossible d'écrire database.php");
}

function writeAppConfig(string $appName, string $baseUrl): void {
    $path = CONFIG_PATH . '/app.php';
    if (!file_exists($path)) {
        $n = addslashes($appName);
        $u = addslashes($baseUrl);
        $c = "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'name' => '{$n}',\n    'version' => '0.0.1',\n    'debug'    => true,\n    'timezone' => 'Europe/Paris',\n    \n    'update' => [\n        'github_repo' => 'Alexis5155/kronoconnect',\n    ],\n\n    'base_url' => '{$u}',\n\n    'session' => [\n        'name'     => 'KRONOCONNECT_SESS',\n        'lifetime' => 0,\n        'secure'   => false,\n        'httponly' => true,\n        'samesite' => 'Lax',\n    ],\n\n    'remember_me' => [\n        'cookie_name' => 'KronoConnect_remember',\n        'lifetime'    => 30 * 24 * 3600,\n    ],\n\n    'features' => [\n        'registration' => true,\n    ],\n];\n";
        if (file_put_contents($path, $c) === false) {
            throw new \RuntimeException("Impossible d'écrire app.php");
        }
        return;
    }
    $c = file_get_contents($path);
    // Limite le remplacement à 1 occurrence pour ne pas écraser le 'name' de la session
    $c = preg_replace("/'name'\s*=>\s*'[^']*'/",    "'name' => '"    . addslashes($appName) . "'", $c, 1);
    $c = preg_replace("/'base_url'\s*=>\s*'[^']*'/", "'base_url' => '" . addslashes($baseUrl) . "'", $c, 1);
    file_put_contents($path, $c);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation — <?= KRONO_APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* ══ RESET ══════════════════════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --accent:#3b5fc0;--accent-rgb:59,95,192;
  --accent-l:#eef1fb;--accent-g:linear-gradient(135deg,#3b5fc0,#6175d8);
  --success:#16a34a;--success-bg:#f0fdf4;--success-border:#bbf7d0;
  --danger:#dc2626;--danger-bg:#fef2f2;--danger-border:#fecaca;
  --warning:#d97706;--warning-bg:#fffbeb;--warning-border:#fde68a;
  --t1:#1a1f36;--t2:#4a5176;--t3:#8890b0;
  --border:rgba(0,0,0,.08);--border-s:rgba(0,0,0,.12);
  --r:12px;--r-lg:20px;
  --kc-accent:#818cf8;--kc-purple:#a78bfa;
  --hh:70px;
}
html{height:100%;}
body{min-height:100%;font-family:'Segoe UI',system-ui,-apple-system,sans-serif;color:var(--t1);overflow-x:hidden;}

/* ══ BG LAYERS ══════════════════════════════════════════════════════════ */
#bg-light{position:fixed;inset:0;z-index:0;background:linear-gradient(135deg,#dde4f5,#eef1fb,#f5f6ff,#e8ecf8);background-size:400% 400%;animation:bg-shift 22s ease infinite;transition:opacity 1.8s cubic-bezier(.4,0,.2,1);}
#bg-dark{position:fixed;inset:0;z-index:0;background:#080b14;opacity:0;transition:opacity 1.8s cubic-bezier(.4,0,.2,1);pointer-events:none;}
#bg-ink{position:fixed;inset:0;z-index:1;pointer-events:none;background:radial-gradient(circle at 50% 50%, #080b14 0%, transparent 0%);opacity:0;transition:none;}
@keyframes bg-shift{0%,100%{background-position:0 50%}50%{background-position:100% 50%}}

#halos{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;}
.hl{position:absolute;border-radius:50%;transition:opacity 1.5s ease;}
.hl-a{width:700px;height:700px;top:-250px;left:-200px;background:radial-gradient(circle,rgba(59,95,192,.1),transparent 70%);animation:hfloat 14s ease-in-out infinite;}
.hl-b{width:600px;height:600px;bottom:-200px;right:-150px;background:radial-gradient(circle,rgba(99,112,200,.07),transparent 70%);animation:hfloat 14s ease-in-out infinite;animation-delay:-7s;}
.hd-a{width:900px;height:900px;top:50%;left:50%;transform:translate(-50%,-50%);background:radial-gradient(circle,rgba(59,95,255,.09),transparent 65%);animation:kc-pulse 9s ease-in-out infinite;opacity:0;transition:opacity 1.5s;}
.hd-b{width:500px;height:500px;top:5%;right:-80px;background:radial-gradient(circle,rgba(139,92,246,.07),transparent 65%);animation:kc-pulse 9s ease-in-out infinite;animation-delay:-4.5s;opacity:0;transition:opacity 1.5s;}
body.dark .hd-a,body.dark .hd-b{opacity:1;}
body.dark .hl-a,body.dark .hl-b{opacity:0;}
@keyframes hfloat{0%,100%{transform:translateY(0)}50%{transform:translateY(18px)}}
@keyframes kc-pulse{0%,100%{transform:translate(-50%,-50%) scale(1)}50%{transform:translate(-50%,-50%) scale(1.18)}}

#kc-particles{position:fixed;inset:0;pointer-events:none;z-index:2;overflow:hidden;opacity:0;transition:opacity 1.5s 0.5s;}
body.dark #kc-particles{opacity:1;}
.kc-p{position:absolute;border-radius:50%;background:radial-gradient(circle,rgba(99,120,255,.65),transparent);animation:kc-drift linear infinite;}
@keyframes kc-drift{0%{transform:translateY(110vh) scale(0);opacity:0;}10%{opacity:1;}90%{opacity:.45;}100%{transform:translateY(-15vh) scale(1.4);opacity:0;}}

/* ══ HEADER ════════════════════════════════════════════════════════════ */
#header{position:fixed;top:0;left:0;right:0;z-index:200;height:var(--hh);display:flex;align-items:center;padding:0 1.5rem;gap:1.25rem;background:rgba(255,255,255,.58);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.8);transition:background 1.8s,border-color 1.8s;overflow:visible;}
body.dark #header{background:rgba(8,11,20,.78);border-bottom-color:rgba(99,120,255,.22);}
.hd-brand{display:flex;align-items:center;gap:.65rem;font-size:1.08rem;font-weight:900;letter-spacing:-.3px;color:var(--t1);white-space:nowrap;flex-shrink:0;transition:color 1.8s;}
body.dark .hd-brand{color:#e8eeff;}
.hd-logo{width:32px;height:32px;background:var(--accent-g);border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.1rem;flex-shrink:0;box-shadow:0 2px 8px rgba(var(--accent-rgb),.4);transition:background 1.8s,box-shadow 1.8s;}
body.dark .hd-logo{background:linear-gradient(135deg,#4f63d2,#818cf8);box-shadow:0 2px 8px rgba(129,140,248,.45);}

#stepper{flex:1;min-width:0;display:flex;align-items:center;padding:3px 0;gap:0.5rem;}
.s-line{flex:1;height:2px;min-width:12px;background:var(--border-s);border-radius:2px;transition:background .45s;}
.s-line.done{background:var(--success);}
body.dark .s-line{background:rgba(99,120,255,.18);}
body.dark .s-line.done{background:rgba(74,222,128,.42);}
.s-item{display:flex;align-items:center;flex-shrink:0;}
.s-dot{display:flex;align-items:center;justify-content:center;gap:.42rem;padding:.38rem .72rem;border-radius:24px;border:1.5px solid rgba(255,255,255,.7);background:rgba(255,255,255,.52);color:var(--t3);white-space:nowrap;box-shadow:0 1px 3px rgba(0,0,0,.06),inset 0 1px 0 rgba(255,255,255,.75);transition:background .4s,border-color .4s,color .4s,box-shadow .4s,padding .4s,border-radius .4s;}
body.dark .s-dot{background:rgba(255,255,255,.06);border-color:rgba(99,120,255,.22);color:rgba(180,190,255,.35);box-shadow:none;}
.s-num{font-size:.82rem;font-weight:800;flex-shrink:0;line-height:1;}
.s-lbl{font-size:.76rem;font-weight:700;line-height:1;}
.s-item.done .s-dot{padding:.38rem .62rem;background:rgba(22,163,74,.1);border-color:rgba(22,163,74,.35);color:var(--success);box-shadow:none;}
body.dark .s-item.done .s-dot{background:rgba(74,222,128,.07);border-color:rgba(74,222,128,.3);color:#4ade80;}
.s-item.active .s-dot{padding:.38rem .92rem;background:var(--accent-g);border-color:transparent;color:white;box-shadow:0 2px 12px rgba(var(--accent-rgb),.38);}
body.dark .s-item.active .s-dot{background:linear-gradient(135deg,#4f63d2,#818cf8);box-shadow:0 2px 12px rgba(129,140,248,.42);}
@media(max-width:640px){.hd-brand span{display:none;}#header{padding:0 1rem;gap:.75rem;}}
@media(max-width:1150px){
  #stepper{gap:0.25rem;}
  .s-line{min-width:6px;height:1.5px;}
  .s-lbl{display:none!important;}
  .s-dot{width:34px;height:34px;padding:0;gap:0;border-radius:50%;}
  .s-item.done .s-dot{width:34px;height:34px;padding:0;border-radius:50%;}
  .s-item.active .s-dot{width:34px;height:34px;padding:0;gap:0;border-radius:50%;}
  .s-num{font-size:.78rem;}
}
body.resizing *{transition:none!important;animation:none!important;}

/* ══ SCÈNE ══════════════════════════════════════════════════════════════ */
#scene{position:relative;z-index:10;min-height:100vh;padding-top:calc(var(--hh) + 2.5rem);padding-bottom:3rem;display:flex;align-items:flex-start;justify-content:center;padding-left:1rem;padding-right:1rem;}

/* ══ CARD ═══════════════════════════════════════════════════════════════ */
#card{position:relative;background:rgba(255,255,255,.72);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.85);border-radius:var(--r-lg);box-shadow:0 8px 40px rgba(0,0,0,.09),0 1px 0 rgba(255,255,255,.9) inset;overflow:hidden;transition:width .55s cubic-bezier(.65,0,.35,1),height .55s cubic-bezier(.65,0,.35,1),max-width .55s cubic-bezier(.65,0,.35,1),background 1.8s,border-color 1.8s,box-shadow 1.8s;width:100%;}
body.dark #card{background:rgba(15,20,40,.83);border-color:rgba(99,120,255,.22);box-shadow:0 8px 40px rgba(0,0,0,.5),0 0 80px rgba(59,95,255,.06);}
.panel{position:absolute;top:0;left:0;width:100%;padding:2rem 2.25rem 1.85rem;opacity:0;pointer-events:none;transition:opacity .3s ease, transform .4s cubic-bezier(.4,0,.2,1);transform:translateX(40px);}
.panel.active{opacity:1;pointer-events:all;transform:translateX(0);position:relative;}
.panel.exit-left {opacity:0;transform:translateX(-40px);position:absolute;}
.panel.exit-right{opacity:0;transform:translateX(40px); position:absolute;}

/* ══ ÉTAPE 1 ════════════════════════════════════════════════════════════ */
.welcome-grid{display:grid;grid-template-columns:1fr 1px 1fr;gap:0 1.75rem;align-items:start;}
@media(max-width:680px){.welcome-grid{grid-template-columns:1fr;gap:1.5rem 0;}.welcome-div{display:none;}}
.welcome-div{width:1px;background:var(--border);align-self:stretch;}
.welcome-logo-wrap{position:relative;width:64px;height:64px;margin-bottom:1.4rem;}
.welcome-logo-bg{position:absolute;inset:0;border-radius:16px;background:linear-gradient(135deg,#3b5fc0,#818cf8,#a78bfa);background-size:200% 200%;animation:logo-shift 6s ease infinite;box-shadow:0 8px 28px rgba(59,95,192,.38),0 0 0 1px rgba(255,255,255,.15) inset;}
@keyframes logo-shift{0%,100%{background-position:0 0}50%{background-position:100% 100%}}
.welcome-logo-ic{position:relative;z-index:1;width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.9rem;color:white;filter:drop-shadow(0 2px 6px rgba(0,0,0,.25));animation:logo-pulse 3s ease-in-out infinite;}
@keyframes logo-pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}
.welcome-title{font-size:1.6rem;font-weight:900;letter-spacing:-.5px;line-height:1.15;color:var(--t1);margin-bottom:.5rem;}
.welcome-title span{background:linear-gradient(135deg,var(--accent),#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.welcome-sub{font-size:.86rem;color:var(--t2);line-height:1.7;margin-bottom:1.5rem;}
.feat-list{display:flex;flex-direction:column;gap:.7rem;}
.feat-item{display:flex;align-items:flex-start;gap:.75rem;padding:.7rem .85rem;background:rgba(59,95,192,.05);border:1px solid rgba(59,95,192,.1);border-radius:var(--r);opacity:0;transform:translateX(-16px);transition:opacity .4s ease,transform .4s ease,background .2s,border-color .2s;}
.feat-item.visible{opacity:1;transform:translateX(0);}
.feat-item:hover{background:rgba(59,95,192,.09);border-color:rgba(59,95,192,.2);}
.feat-ic{width:34px;height:34px;border-radius:9px;flex-shrink:0;background:var(--accent-g);display:flex;align-items:center;justify-content:center;font-size:.95rem;color:white;box-shadow:0 2px 8px rgba(var(--accent-rgb),.3);}
.feat-t{font-size:.83rem;font-weight:700;color:var(--t1);}
.feat-d{font-size:.74rem;color:var(--t2);margin-top:.12rem;line-height:1.5;}
.req-side{display:flex;flex-direction:column;gap:0;}
.req-side-title{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--t3);margin-bottom:.65rem;display:flex;align-items:center;gap:.4rem;}
.req-row{display:flex;align-items:center;gap:.5rem;padding:.4rem .5rem;border-radius:8px;font-size:.78rem;font-weight:500;color:var(--t2);opacity:0;transform:translateX(10px);transition:opacity .35s ease,transform .35s ease,background .15s;}
.req-row.visible{opacity:1;transform:translateX(0);}
.req-row:hover{background:rgba(0,0,0,.03);}
.rr-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
.rr-dot.ok  {background:var(--success);}
.rr-dot.ko  {background:var(--danger);}
.rr-dot.warn{background:var(--warning);}
.rr-label{flex:1;}
.rr-val{font-size:.7rem;font-weight:600;color:var(--t3);}
.rr-badge{font-size:.58rem;font-weight:800;padding:.08rem .38rem;border-radius:5px;}
.rr-badge.req{background:rgba(220,38,38,.1);color:var(--danger);}
.rr-badge.opt{background:rgba(217,119,6,.1);color:var(--warning);}
#req-summary{margin-top:.75rem;padding:.55rem .7rem;border-radius:var(--r);font-size:.78rem;font-weight:600;display:flex;align-items:center;gap:.5rem;}
#req-summary.ok  {background:var(--success-bg);color:var(--success);border:1px solid var(--success-border);}
#req-summary.ko  {background:var(--danger-bg); color:var(--danger); border:1px solid var(--danger-border);}

/* ══ STEP HERO ══════════════════════════════════════════════════════════ */
.step-hero{display:flex;align-items:center;gap:.88rem;margin-bottom:.5rem;}
.step-icon{width:44px;height:44px;border-radius:13px;flex-shrink:0;background:var(--accent-g);display:flex;align-items:center;justify-content:center;font-size:1.15rem;color:white;box-shadow:0 4px 14px rgba(var(--accent-rgb),.26);transition:background 1.8s,box-shadow 1.8s;}
body.dark .step-icon{background:linear-gradient(135deg,#4f63d2,#818cf8);box-shadow:0 4px 14px rgba(129,140,248,.3);}
.step-icon.kc{background:linear-gradient(135deg,#4f63d2,#818cf8);box-shadow:0 4px 14px rgba(99,120,255,.35);}
.step-hero .p-eyebrow{margin-bottom:.05rem;}
.step-hero .p-title{margin-bottom:0;}

/* ══ FORM COMMUN ════════════════════════════════════════════════════════ */
.p-eyebrow{font-size:.78rem;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:var(--accent);margin-bottom:.5rem;transition:color 1.8s;}
body.dark .p-eyebrow{color:var(--kc-accent);}
.p-title{font-size:1.68rem;font-weight:900;letter-spacing:-.4px;line-height:1.2;color:var(--t1);margin-bottom:.4rem;transition:color 1.8s;}
body.dark .p-title{color:#e8eeff;}
.p-sub{font-size:.98rem;color:var(--t2);line-height:1.7;margin-bottom:1.5rem;transition:color 1.8s;}
body.dark .p-sub{color:rgba(180,190,255,.6);}
@media(max-width:480px){
  .p-eyebrow{font-size:.7rem;margin-bottom:.4rem;}
  .p-title{font-size:1.4rem;margin-bottom:.3rem;}
  .p-sub{font-size:.9rem;margin-bottom:1.2rem;}
}
.field{margin-bottom:1rem;}
@media(max-width:480px){.field{margin-bottom:.9rem;}}
.label{display:block;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--t2);margin-bottom:.35rem;transition:color 1.8s;}
@media(max-width:480px){.label{font-size:.72rem;margin-bottom:.28rem;}}
body.dark .label{color:rgba(180,190,255,.6);}
.input{width:100%;padding:.78rem .95rem;background:rgba(255,255,255,.9);border:1.5px solid var(--border-s);border-radius:var(--r);color:var(--t1);font-size:1rem;font-family:inherit;outline:none;transition:all .22s;}
@media(max-width:480px){.input{padding:.68rem .85rem;font-size:.95rem;}}
body.dark .input{background:rgba(255,255,255,.05);border-color:rgba(99,120,255,.25);color:#e8eeff;}
body.dark .input::placeholder{color:rgba(180,190,255,.28);}
.input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(var(--accent-rgb),.13);}
body.dark .input:focus{border-color:var(--kc-accent);box-shadow:0 0 0 3px rgba(129,140,248,.18);}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;}
@media(max-width:480px){.row-2{grid-template-columns:1fr;}}
.field-help{font-size:.8rem;color:var(--t3);margin-top:.28rem;}
@media(max-width:480px){.field-help{font-size:.75rem;margin-top:.2rem;}}
body.dark .field-help{color:rgba(180,190,255,.33);}
.sep{height:1px;background:var(--border);margin:1.15rem 0;}
body.dark .sep{background:rgba(99,120,255,.15);}
.section-label{font-size:.78rem;font-weight:800;color:var(--t3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:.7rem;display:flex;align-items:center;gap:.5rem;}
@media(max-width:480px){.section-label{font-size:.7rem;margin-bottom:.6rem;}}
.section-label i{color:var(--accent);}
body.dark .section-label{color:rgba(180,190,255,.42);}
body.dark .section-label i{color:var(--kc-accent);}
.opt-tag{font-size:.6rem;font-weight:700;color:var(--t3);background:rgba(0,0,0,.06);padding:.08rem .38rem;border-radius:5px;vertical-align:middle;margin-left:.3rem;}
body.dark .opt-tag{background:rgba(255,255,255,.07);color:rgba(180,190,255,.38);}

/* ══ BOUTONS ════════════════════════════════════════════════════════════ */
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.78rem 1.55rem;border:none;border-radius:var(--r);font-family:inherit;font-size:.95rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .22s;white-space:nowrap;}
@media(max-width:480px){.btn{padding:.68rem 1.3rem;font-size:.9rem;gap:.4rem;}}
.btn:disabled{opacity:.45;cursor:not-allowed;transform:none!important;}
.btn-primary{background:var(--accent-g);color:white;box-shadow:0 4px 14px rgba(var(--accent-rgb),.28);}
.btn-primary:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 7px 20px rgba(var(--accent-rgb),.4);}
.btn-ghost{background:rgba(255,255,255,.7);border:1.5px solid var(--border-s);color:var(--t2);}
.btn-ghost:hover{background:var(--accent-l);border-color:rgba(var(--accent-rgb),.28);color:var(--accent);}
body.dark .btn-ghost{background:rgba(255,255,255,.05);border-color:rgba(99,120,255,.22);color:rgba(180,190,255,.6);}
body.dark .btn-ghost:hover{background:rgba(99,120,255,.1);border-color:rgba(99,120,255,.4);color:#c7d0ff;}
.btn-kc{background:linear-gradient(135deg,#4f63d2,#818cf8);color:white;box-shadow:0 4px 18px rgba(99,120,255,.38),0 0 32px rgba(99,120,255,.1);}
.btn-kc:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 7px 26px rgba(99,120,255,.52),0 0 48px rgba(99,120,255,.14);}
.btn-success{background:linear-gradient(135deg,#16a34a,#22c55e);color:white;box-shadow:0 4px 14px rgba(22,163,74,.26);}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 7px 20px rgba(22,163,74,.38);}
.btn-row{display:flex;align-items:center;justify-content:space-between;gap:.7rem;margin-top:1.4rem;flex-wrap:wrap;}
.btn-row-r{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;}

/* ══ ALERTES ════════════════════════════════════════════════════════════ */
.alert{display:flex;align-items:flex-start;gap:.65rem;padding:.9rem 1.05rem;border-radius:var(--r);border:1px solid transparent;font-size:.95rem;font-weight:500;margin-bottom:1.2rem;}
@media(max-width:480px){.alert{padding:.8rem .9rem;font-size:.9rem;margin-bottom:1rem;}}
.alert i{flex-shrink:0;font-size:.9rem;margin-top:.06rem;}
.alert-danger {background:var(--danger-bg); border-color:var(--danger-border); color:var(--danger);}
.alert-success{background:var(--success-bg);border-color:var(--success-border);color:var(--success);}
.alert-warning{background:var(--warning-bg);border-color:var(--warning-border);color:var(--warning);}
.alert-info   {background:var(--accent-l);  border-color:rgba(var(--accent-rgb),.18);color:var(--accent);}

/* ══ TEST SMTP BOX ══════════════════════════════════════════════════════ */
.test-box{background:var(--accent-l);border:1px solid rgba(var(--accent-rgb),.16);border-radius:var(--r);padding:.9rem 1.05rem;margin-bottom:1rem;}
@media(max-width:480px){.test-box{padding:.8rem .9rem;margin-bottom:.9rem;}}
body.dark .test-box{background:rgba(99,120,255,.07);border-color:rgba(99,120,255,.2);}
.test-box-ttl{font-size:.82rem;font-weight:800;color:var(--accent);margin-bottom:.55rem;}
@media(max-width:480px){.test-box-ttl{font-size:.75rem;margin-bottom:.4rem;}}
body.dark .test-box-ttl{color:var(--kc-accent);}
.test-row{display:flex;gap:.45rem;align-items:center;flex-wrap:wrap;}
.test-row .input{flex:1;min-width:150px;}

/* ══ ÉTAPE 4 — CLIENT SSO ═══════════════════════════════════════════════ */
.kc-badge{display:inline-flex;align-items:center;gap:.45rem;padding:.32rem .92rem;border-radius:22px;background:linear-gradient(135deg,rgba(99,120,255,.13),rgba(139,92,246,.13));border:1px solid rgba(99,120,255,.28);font-size:.75rem;font-weight:800;color:var(--kc-accent);letter-spacing:.8px;text-transform:uppercase;margin-bottom:1rem;}
@media(max-width:480px){.kc-badge{padding:.28rem .8rem;font-size:.68rem;margin-bottom:.9rem;}}
.kc-features{display:flex;flex-direction:column;gap:.42rem;margin-bottom:1.4rem;}
.kc-feat{display:flex;align-items:flex-start;gap:.7rem;padding:.7rem .85rem;background:rgba(255,255,255,.03);border:1px solid rgba(99,120,255,.12);border-radius:var(--r);transition:all .22s;}
.kc-feat:hover{background:rgba(99,120,255,.07);border-color:rgba(99,120,255,.25);}
.kc-feat-ic{width:33px;height:33px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,rgba(99,120,255,.2),rgba(139,92,246,.2));border:1px solid rgba(99,120,255,.28);display:flex;align-items:center;justify-content:center;font-size:.9rem;color:var(--kc-accent);}
.kc-feat-t{font-size:.81rem;font-weight:700;color:#c7d0ff;}
.kc-feat-d{font-size:.72rem;color:rgba(180,190,255,.5);margin-top:.1rem;}
.kc-sep{height:1px;background:linear-gradient(90deg,transparent,rgba(99,120,255,.22),transparent);margin:1.15rem 0;}
.kc-lbl{color:rgba(180,190,255,.58)!important;}

/* ══ RÉCAP ══════════════════════════════════════════════════════════════ */
.recap-grid{display:flex;flex-direction:column;gap:.4rem;margin-bottom:1.1rem;}
.recap-sec{background:rgba(255,255,255,.75);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;}
body.dark .recap-sec{background:rgba(255,255,255,.03);border-color:rgba(99,120,255,.14);}
.recap-head{padding:.46rem .85rem;background:rgba(0,0,0,.022);border-bottom:1px solid var(--border);font-size:.66rem;font-weight:800;color:var(--t2);text-transform:uppercase;letter-spacing:.7px;display:flex;align-items:center;gap:.38rem;}
.recap-head i{color:var(--accent);}
body.dark .recap-head{background:rgba(255,255,255,.02);border-color:rgba(99,120,255,.1);color:rgba(180,190,255,.5);}
.recap-row{display:flex;justify-content:space-between;align-items:center;padding:.4rem .85rem;border-bottom:1px solid var(--border);font-size:.81rem;}
.recap-row:last-child{border-bottom:none;}
body.dark .recap-row{border-color:rgba(99,120,255,.08);}
.rk{color:var(--t2);font-weight:500;}
body.dark .rk{color:rgba(180,190,255,.5);}
.rv{color:var(--t1);font-weight:700;max-width:58%;text-align:right;word-break:break-all;font-size:.79rem;}
body.dark .rv{color:#c7d0ff;}
.rv.dim{color:var(--t3);font-weight:500;font-style:italic;}

/* ══ TERMINAL ═══════════════════════════════════════════════════════════ */
.terminal{background:#0d1117;border:1px solid rgba(255,255,255,.07);border-radius:var(--r-lg);overflow:hidden;font-family:'Cascadia Code','Fira Code',Consolas,monospace;box-shadow:0 14px 48px rgba(0,0,0,.32),0 0 0 1px rgba(255,255,255,.03);}
.term-bar{display:flex;align-items:center;gap:.42rem;padding:.58rem .88rem;background:rgba(255,255,255,.03);border-bottom:1px solid rgba(255,255,255,.05);}
.td{width:11px;height:11px;border-radius:50%;}
.td-r{background:#ff5f56;}.td-y{background:#febc2e;}.td-g{background:#28c840;}
.term-ttl{flex:1;text-align:center;font-size:.68rem;color:rgba(255,255,255,.22);font-family:inherit;}
.term-body{padding:1.05rem 1.3rem;min-height:190px;max-height:290px;overflow-y:auto;font-size:.79rem;line-height:1.75;}
.term-body::-webkit-scrollbar{width:3px;}
.term-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:2px;}
.tl{display:flex;align-items:flex-start;gap:.5rem;margin-bottom:.08rem;animation:tl-in .18s ease both;}
@keyframes tl-in{from{opacity:0;transform:translateY(3px)}to{opacity:1;transform:none}}
.tl-pre{color:rgba(255,255,255,.16);flex-shrink:0;min-width:11px;user-select:none;}
.tl-info{color:#7dd3fc;}.tl-success{color:#4ade80;}.tl-error{color:#f87171;}.tl-done{color:#c084fc;}
.term-cursor{display:inline-block;width:7px;height:12px;background:#7dd3fc;margin-left:2px;vertical-align:middle;animation:blink .85s step-end infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}

/* ══ SUCCÈS ══════════════════════════════════════════════════════════════ */
.success-wrap{text-align:center;padding:.75rem 0 .25rem;}
.s-ring-wrap{position:relative;width:86px;height:86px;margin:0 auto 1.3rem;display:flex;align-items:center;justify-content:center;}
.s-ring{position:absolute;inset:0;border-radius:50%;border:2px solid rgba(22,163,74,.3);animation:s-ring 2.5s ease-in-out infinite;}
@keyframes s-ring{0%,100%{transform:scale(1);opacity:.5}50%{transform:scale(1.24);opacity:1}}
.s-ic{width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#16a34a,#22c55e);display:flex;align-items:center;justify-content:center;font-size:1.75rem;color:white;box-shadow:0 6px 26px rgba(22,163,74,.36);animation:ic-pop .55s cubic-bezier(.34,1.56,.64,1) both;}
@keyframes ic-pop{from{transform:scale(.2);opacity:0}to{transform:scale(1);opacity:1}}
.spin{width:15px;height:15px;border-radius:50%;flex-shrink:0;border:2.5px solid rgba(255,255,255,.3);border-top-color:white;animation:spin .65s linear infinite;}
.spin-d{border-color:rgba(var(--accent-rgb),.2);border-top-color:var(--accent);}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>

<div id="bg-light"></div>
<div id="bg-dark"></div>
<div id="bg-ink"></div>
<div id="halos">
  <div class="hl hl-a"></div><div class="hl hl-b"></div>
  <div class="hl hd-a"></div><div class="hl hd-b"></div>
</div>
<div id="kc-particles"></div>

<!-- ══ HEADER ══════════════════════════════════════════════════════════ -->
<div id="header">
  <div class="hd-brand">
    <div class="hd-logo"><i class="bi bi-key-fill"></i></div>
    <span><?= KRONO_APP_NAME ?></span>
  </div>

  <div id="stepper">
    <?php
    $steps = [1=>'Prérequis', 2=>'Base de données', 3=>'E-mail', 4=>'Config.', 5=>'Récap.', 6=>'Installation'];
    foreach ($steps as $n => $label): ?>
      <div class="s-item" id="si-<?= $n ?>">
        <div class="s-dot" id="sd-<?= $n ?>">
          <span class="s-num"><?= $n ?></span>
          <span class="s-lbl"><?= $label ?></span>
        </div>
      </div>
      <?php if ($n < count($steps)): ?>
      <div class="s-line" id="sl-<?= $n ?>"></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

</div>

<!-- ══ SCÈNE ════════════════════════════════════════════════════════════ -->
<div id="scene">
<div id="card">

  <!-- ─── ÉTAPE 1 : ACCUEIL + PRÉREQUIS ─────────────────────────────── -->
  <div class="panel active" id="panel-1">
    <div class="welcome-grid">
      <div>
        <div class="welcome-logo-wrap">
          <div class="welcome-logo-bg"></div>
          <div class="welcome-logo-ic"><i class="bi bi-key-fill"></i></div>
        </div>
        <div class="welcome-title">Bienvenue sur<br><span><?= KRONO_APP_NAME ?></span></div>
        <div class="welcome-sub">Le serveur d'authentification centrale de l'écosystème Krono. Ce wizard configure votre instance SSO en quelques étapes.</div>
        <div class="feat-list" id="feat-list">
          <div class="feat-item">
            <div class="feat-ic"><i class="bi bi-shield-lock-fill"></i></div>
            <div>
              <div class="feat-t">SSO OAuth2 simplifié</div>
              <div class="feat-d">Un seul compte pour accéder à toutes les applications de l'écosystème Krono.</div>
            </div>
          </div>
          <div class="feat-item">
            <div class="feat-ic"><i class="bi bi-arrow-left-right"></i></div>
            <div>
              <div class="feat-t">Flux sécurisé</div>
              <div class="feat-d">Codes d'autorisation éphémères (60s), secrets hashés en bcrypt, API stateless.</div>
            </div>
          </div>
          <div class="feat-item">
            <div class="feat-ic"><i class="bi bi-plug-fill"></i></div>
            <div>
              <div class="feat-t">Multi-clients</div>
              <div class="feat-d">Connectez KronoCore et toute autre application grâce aux clients SSO enregistrés.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="welcome-div"></div>

      <div>
        <div class="req-side-title"><i class="bi bi-cpu"></i> Prérequis serveur</div>
        <div id="req-list" class="req-side">
          <div style="display:flex;align-items:center;gap:.5rem;padding:.4rem .5rem;font-size:.8rem;color:var(--t3);">
            <div class="spin spin-d"></div> Analyse…
          </div>
        </div>
        <div id="req-summary" style="display:none;"></div>
      </div>
    </div>

    <div class="btn-row" style="margin-top:1.75rem;">
      <div style="font-size:.78rem;color:var(--t3);">Version <?= KRONO_VERSION ?></div>
      <button class="btn btn-primary" id="btn-req" disabled onclick="goToStep(2)">
        Démarrer l'installation <i class="bi bi-arrow-right-short"></i>
      </button>
    </div>
  </div>

  <!-- ─── ÉTAPE 2 : BASE DE DONNÉES ─────────────────────────────────── -->
  <div class="panel" id="panel-2">
    <div class="step-hero">
      <div class="step-icon"><i class="bi bi-database"></i></div>
      <div>
        <div class="p-eyebrow">Étape 2 / 6</div>
        <div class="p-title">Base de données</div>
      </div>
    </div>
    <div class="p-sub">Connexion MySQL / MariaDB. L'utilisateur doit avoir les droits <code>CREATE</code>, <code>INSERT</code>, <code>ALTER</code>.</div>
    <div id="db-alert"></div>
    <div class="row-2">
      <div class="field"><label class="label">Hôte *</label><input class="input" type="text" id="db_host" value="localhost"></div>
      <div class="field"><label class="label">Port</label><input class="input" type="number" id="db_port" value="3306"></div>
    </div>
    <div class="field"><label class="label">Nom de la base *</label><input class="input" type="text" id="db_name" placeholder="kronoconnect"></div>
    <div class="row-2">
      <div class="field"><label class="label">Utilisateur *</label><input class="input" type="text" id="db_user" placeholder="root" autocomplete="off"></div>
      <div class="field"><label class="label">Mot de passe</label><input class="input" type="password" id="db_pass" placeholder="••••••••"></div>
    </div>
    <div class="field">
      <label class="label">Préfixe <span class="opt-tag">optionnel</span></label>
      <input class="input" type="text" id="db_prefix" placeholder="ex : krono_">
      <div class="field-help">Utile si la base est partagée avec d'autres applications.</div>
    </div>
    <div class="btn-row">
      <button class="btn btn-ghost" onclick="goToStep(1)"><i class="bi bi-arrow-left-short"></i> Retour</button>
      <button class="btn btn-primary" id="btn-db" onclick="testDb()"><i class="bi bi-plug-fill"></i> Tester et continuer</button>
    </div>
  </div>

  <!-- ─── ÉTAPE 3 : E-MAIL ───────────────────────────────────────────── -->
  <div class="panel" id="panel-3">
    <div class="step-hero">
      <div class="step-icon"><i class="bi bi-envelope"></i></div>
      <div>
        <div class="p-eyebrow">Étape 3 / 6</div>
        <div class="p-title">E-mail <span class="opt-tag" style="font-size:.75rem;vertical-align:middle;">optionnel</span></div>
      </div>
    </div>
    <div class="p-sub">Serveur SMTP pour les notifications et la réinitialisation de mot de passe. Configurable depuis le panel admin.</div>
    <div id="smtp-alert"></div>
    <div class="row-2">
      <div class="field"><label class="label">Hôte SMTP</label><input class="input" type="text" id="smtp_host" placeholder="smtp.gmail.com"></div>
      <div class="field"><label class="label">Port</label><input class="input" type="number" id="smtp_port" value="587"></div>
    </div>
    <div class="row-2">
      <div class="field"><label class="label">Identifiant</label><input class="input" type="text" id="smtp_user" placeholder="user@domaine.fr" autocomplete="off"></div>
      <div class="field"><label class="label">Mot de passe</label><input class="input" type="password" id="smtp_pass" placeholder="••••••••"></div>
    </div>
    <div class="row-2">
      <div class="field">
        <label class="label">Chiffrement</label>
        <select class="input" id="smtp_encryption">
          <option value="tls">TLS (587)</option><option value="ssl">SSL (465)</option><option value="none">Aucun</option>
        </select>
      </div>
      <div class="field"><label class="label">Adresse expéditeur</label><input class="input" type="email" id="mail_from" placeholder="noreply@domaine.fr"></div>
    </div>
    <div class="field"><label class="label">Nom expéditeur</label><input class="input" type="text" id="mail_from_name" value="<?= KRONO_APP_NAME ?> Notifications"></div>
    <div class="test-box">
      <div class="test-box-ttl"><i class="bi bi-send-fill"></i> Test de connexion TCP</div>
      <div class="test-row">
        <input class="input" type="email" id="smtp_test_to" placeholder="Adresse de réception (optionnel)">
        <button class="btn btn-ghost" id="btn-smtp-test" onclick="testSmtp()"><i class="bi bi-send"></i> Tester</button>
      </div>
      <div id="smtp-test-res" style="margin-top:.42rem;font-size:.75rem;"></div>
    </div>
    <div class="btn-row">
      <button class="btn btn-ghost" onclick="goToStep(2)"><i class="bi bi-arrow-left-short"></i> Retour</button>
      <div class="btn-row-r">
        <button class="btn btn-ghost" onclick="skipSmtp()">Ignorer <i class="bi bi-skip-forward-fill"></i></button>
        <button class="btn btn-primary" onclick="saveSmtp()">Enregistrer <i class="bi bi-arrow-right-short"></i></button>
      </div>
    </div>
  </div>

  <!-- ─── ÉTAPE 4 : CONFIGURATION ─────────────────────────────────── -->
  <div class="panel" id="panel-4">
    <div class="step-hero">
      <div class="step-icon"><i class="bi bi-gear-fill"></i></div>
      <div>
        <div class="p-eyebrow">Étape 4 / 6</div>
        <div class="p-title">Configuration</div>
      </div>
    </div>
    <div class="p-sub">Nom de l'instance et premier compte super-administrateur.</div>
    <div id="config-alert"></div>
    <div class="section-label"><i class="bi bi-app"></i> Application</div>
    <div class="row-2">
      <div class="field">
        <label class="label">Nom *</label>
        <input class="input" type="text" id="app_name" value="<?= KRONO_APP_NAME ?>">
        <div class="field-help">Affiché dans l'interface de connexion.</div>
      </div>
      <div class="field">
        <label class="label">URL de base *</label>
        <?php
        $detectedBasePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
        $detectedUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $detectedBasePath;
        ?>
        <input class="input" type="url" id="base_url" value="<?= $detectedUrl ?>">
        <div class="field-help">Sans slash final.</div>
      </div>
    </div>
    <div class="sep"></div>
    <div class="section-label"><i class="bi bi-shield-fill"></i> Super-administrateur</div>
    <div class="row-2">
      <div class="field"><label class="label">Prénom *</label><input class="input" type="text" id="admin_prenom" placeholder="Jean"></div>
      <div class="field"><label class="label">Nom *</label><input class="input" type="text" id="admin_nom" placeholder="Dupont"></div>
    </div>
    <div class="field"><label class="label">Adresse e-mail *</label><input class="input" type="email" id="admin_email" placeholder="admin@domaine.fr"></div>
    <div class="row-2">
      <div class="field"><label class="label">Mot de passe *</label><input class="input" type="password" id="admin_password" placeholder="8 caractères min."></div>
      <div class="field"><label class="label">Confirmer *</label><input class="input" type="password" id="admin_password2" placeholder="Confirmer"></div>
    </div>
    <div class="btn-row">
      <button class="btn btn-ghost" onclick="goToStep(3)"><i class="bi bi-arrow-left-short"></i> Retour</button>
      <button class="btn btn-primary" onclick="saveConfig()">Continuer <i class="bi bi-arrow-right-short"></i></button>
    </div>
  </div>

  <!-- ─── ÉTAPE 5 : RÉCAPITULATIF ───────────────────────────────────── -->
  <div class="panel" id="panel-5">
    <div class="step-hero">
      <div class="step-icon"><i class="bi bi-list-check"></i></div>
      <div>
        <div class="p-eyebrow">Étape 5 / 6</div>
        <div class="p-title">Récapitulatif</div>
      </div>
    </div>
    <div class="p-sub">Vérifiez tout avant de lancer l'installation.</div>
    <div class="recap-grid" id="recap-grid"></div>
    <div class="alert alert-warning">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <div>Cette opération créera les tables et les fichiers de config. <strong>Elle ne peut pas être annulée.</strong></div>
    </div>
    <div class="btn-row">
      <button class="btn btn-ghost" onclick="goToStep(4)"><i class="bi bi-arrow-left-short"></i> Modifier</button>
      <button class="btn btn-success" onclick="goToStep(6)"><i class="bi bi-rocket-takeoff-fill"></i> Lancer l'installation</button>
    </div>
  </div>

  <!-- ─── ÉTAPE 6 : INSTALLATION ────────────────────────────────────── -->
  <div class="panel" id="panel-6">
    <div class="step-hero">
      <div class="step-icon"><i class="bi bi-terminal-fill"></i></div>
      <div>
        <div class="p-eyebrow">Étape 6 / 6</div>
        <div class="p-title" id="install-title">Installation en cours…</div>
      </div>
    </div>
    <div class="terminal">
      <div class="term-bar">
        <div class="td td-r"></div><div class="td td-y"></div><div class="td td-g"></div>
        <div class="term-ttl"><?= KRONO_APP_NAME ?> — Install</div>
      </div>
      <div class="term-body" id="term-body">
        <div class="tl"><span class="tl-pre">$</span><span class="tl-info">Initialisation…</span><span class="term-cursor"></span></div>
      </div>
    </div>
    <div id="install-success" style="display:none;">
      <div class="success-wrap" style="margin-top:1.6rem;">
        <div class="s-ring-wrap"><div class="s-ring"></div><div class="s-ic"><i class="bi bi-check-lg"></i></div></div>
        <div style="font-size:1.2rem;font-weight:800;color:var(--t1);margin-bottom:.3rem;">Installation réussie !</div>
        <div style="color:var(--t2);font-size:.85rem;margin-bottom:1.4rem;">Connectez-vous avec <strong id="install-email"></strong></div>
        <a id="install-link" href="#" class="btn btn-success" style="font-size:.93rem;padding:.75rem 1.7rem;">
          <i class="bi bi-box-arrow-in-right"></i> Accéder à KronoConnect
        </a>
      </div>
    </div>
  </div>

</div><!-- /card -->
</div><!-- /scene -->

<script>
// ══════════════════════════════════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════════════════════════════════
const APP_NAME    = <?= json_encode(KRONO_APP_NAME) ?>;
const TOTAL       = 6;
const STEP_LABELS = {1:'Prérequis',2:'Base de données',3:'E-mail',4:'Configuration',5:'Récapitulatif',6:'Installation'};

const state = {
  db:    {db_host:'localhost',db_port:'3306',db_name:'',db_user:'',db_pass:'',db_prefix:''},
  smtp:  {skip:false,smtp_host:'',smtp_port:'587',smtp_user:'',smtp_pass:'',smtp_encryption:'tls',mail_from:'',mail_from_name:APP_NAME+' Notifications'},
  app:   {app_name:APP_NAME,base_url:''},
  admin: {prenom:'',nom:'',email:'',password:''},
};

let current  = 1;
let isDark   = false;
let inkTimer = null;

// ══════════════════════════════════════════════════════════════════════════
// CARD RESIZE
// ══════════════════════════════════════════════════════════════════════════
const card = document.getElementById('card');
const CARD_WIDTHS = {1:'780px',2:'520px',3:'560px',4:'560px',5:'660px',6:'640px'};

function resizeCard(toStep, callback) {
  const targetPanel = document.getElementById(`panel-${toStep}`);
  const targetWidth = CARD_WIDTHS[toStep] || '560px';
  targetPanel.style.visibility = 'hidden';
  targetPanel.style.position   = 'absolute';
  targetPanel.style.display    = 'block';
  targetPanel.style.opacity    = '0';
  targetPanel.style.width      = targetWidth;
  const targetH = targetPanel.scrollHeight;
  targetPanel.style.display    = '';
  targetPanel.style.visibility = '';
  targetPanel.style.position   = '';
  targetPanel.style.opacity    = '';
  targetPanel.style.width      = '';
  const currentH = card.offsetHeight;
  card.style.height   = currentH + 'px';
  card.style.maxWidth = card.offsetWidth + 'px';
  void card.offsetHeight;
  card.style.height   = targetH + 'px';
  card.style.maxWidth = targetWidth;
  setTimeout(callback, 220);
  setTimeout(() => { card.style.height = ''; }, 580);
}

// ══════════════════════════════════════════════════════════════════════════
// NAVIGATION
// ══════════════════════════════════════════════════════════════════════════
function goToStep(n) {
  if (n === current) return;
  const from = current, forward = n > from;
  resizeCard(n, () => {
    const oldPanel = document.getElementById(`panel-${from}`);
    oldPanel.classList.remove('active');
    oldPanel.classList.add(forward ? 'exit-left' : 'exit-right');
    setTimeout(() => oldPanel.classList.remove('exit-left','exit-right'), 420);
    const newPanel = document.getElementById(`panel-${n}`);
    newPanel.style.transform = forward ? 'translateX(40px)' : 'translateX(-40px)';
    newPanel.style.opacity   = '0';
    newPanel.classList.add('active');
    void newPanel.offsetWidth;
    newPanel.style.transform = '';
    newPanel.style.opacity   = '';
  });
  current = n;
  updateStepper(n);
  restoreFields(n);
  if (n === 5) buildRecap();
  if (n === 6) runInstall();
}

// ══════════════════════════════════════════════════════════════════════════
// RESTAURATION DES CHAMPS
// ══════════════════════════════════════════════════════════════════════════
function restoreFields(n) {
  const set = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
  if (n===2){ set('db_host',state.db.db_host); set('db_port',state.db.db_port); set('db_name',state.db.db_name); set('db_user',state.db.db_user); set('db_prefix',state.db.db_prefix); }
  if (n===3){ set('smtp_host',state.smtp.smtp_host); set('smtp_port',state.smtp.smtp_port); set('smtp_user',state.smtp.smtp_user); set('smtp_encryption',state.smtp.smtp_encryption); set('mail_from',state.smtp.mail_from); set('mail_from_name',state.smtp.mail_from_name); }
  if (n===4){ set('app_name',state.app.app_name); set('base_url',state.app.base_url); set('admin_prenom',state.admin.prenom); set('admin_nom',state.admin.nom); set('admin_email',state.admin.email); }
}

// ══════════════════════════════════════════════════════════════════════════
// STEPPER
// ══════════════════════════════════════════════════════════════════════════
function updateStepper(n) {
  for (let i=1;i<=TOTAL;i++) {
    const item=document.getElementById(`si-${i}`), dot=document.getElementById(`sd-${i}`);
    item.classList.remove('done','active');
    if (i < n) {
      item.classList.add('done');
      dot.innerHTML='<span class="s-num"><i class="bi bi-check-lg"></i></span>';
    } else {
      dot.innerHTML=`<span class="s-num">${i}</span><span class="s-lbl">${STEP_LABELS[i]}</span>`;
      if (i===n) item.classList.add('active');
    }
    if(i<TOTAL) document.getElementById(`sl-${i}`).classList.toggle('done',i<n);
  }
}

// ══════════════════════════════════════════════════════════════════════════
// DARK MODE — ENCRE ORGANIQUE (step 4)
// ══════════════════════════════════════════════════════════════════════════
function handleDark(from, to) {
  if (to===4 && !isDark) { isDark=true; inkTransition(true); }
  else if (from===4 && to!==4 && isDark) { isDark=false; inkTransition(false); }
}

function inkTransition(toDark) {
  const ink=document.getElementById('bg-ink'), bgDark=document.getElementById('bg-dark'), bgLight=document.getElementById('bg-light');
  if (toDark) {
    bgDark.style.opacity='1'; ink.style.opacity='1';
    let start=null; const duration=1800;
    function animateInk(ts) {
      if(!start) start=ts;
      const p=Math.min((ts-start)/duration,1), eased=1-Math.pow(1-p,3), pct=eased*165;
      ink.style.background=`radial-gradient(circle at 50% 50%, #080b14 ${pct}%, transparent ${pct+8}%)`;
      if(p<1){ inkTimer=requestAnimationFrame(animateInk); }
      else { bgLight.style.opacity='0'; ink.style.opacity='0'; ink.style.background='radial-gradient(circle at 50% 50%, #080b14 0%, transparent 0%)'; document.body.classList.add('dark'); spawnParticles(); }
    }
    cancelAnimationFrame(inkTimer); inkTimer=requestAnimationFrame(animateInk);
  } else {
    cancelAnimationFrame(inkTimer); document.body.classList.remove('dark'); bgLight.style.opacity='1';
    bgDark.style.transition='opacity 1.6s cubic-bezier(.4,0,.2,1)'; bgDark.style.opacity='0';
    setTimeout(()=>{ bgDark.style.transition=''; },1700);
    ink.style.opacity='0'; ink.style.background='radial-gradient(circle at 50% 50%, #080b14 0%, transparent 0%)';
  }
}

function spawnParticles() {
  const c=document.getElementById('kc-particles');
  if(c.childElementCount>0) return;
  for(let i=0;i<16;i++){
    const p=document.createElement('div'); p.className='kc-p';
    const sz=Math.random()*5+3;
    p.style.cssText=`width:${sz}px;height:${sz}px;left:${Math.random()*100}%;animation-duration:${11+Math.random()*14}s;animation-delay:-${Math.random()*12}s;opacity:${.28+Math.random()*.32};`;
    c.appendChild(p);
  }
}

// ══════════════════════════════════════════════════════════════════════════
// ÉTAPE 1 — PRÉREQUIS
// ══════════════════════════════════════════════════════════════════════════
async function checkRequirements() {
  const res=await fetch('?action=check_requirements'), data=await res.json();
  const list=document.getElementById('req-list'); list.innerHTML='';
  data.items.forEach((item,i) => {
    const el=document.createElement('div'); el.className='req-row'; el.style.transitionDelay=`${i*0.055}s`;
    const dotCls=item.status==='ok'?'ok':item.status==='ko'?'ko':'warn';
    el.innerHTML=`<div class="rr-dot ${dotCls}"></div><span class="rr-label">${item.label}</span><span class="rr-val">${item.value}</span>${item.required&&item.status==='ko'?'<span class="rr-badge req">REQUIS</span>':''}${!item.required&&item.status==='warning'?'<span class="rr-badge opt">OPT.</span>':''}`;
    list.appendChild(el);
    requestAnimationFrame(()=>requestAnimationFrame(()=>el.classList.add('visible')));
  });
  const sum=document.getElementById('req-summary'); sum.style.display='flex';
  if(data.ok){ sum.className=''; sum.classList.add('ok'); sum.innerHTML='<i class="bi bi-check-circle-fill"></i> Serveur compatible'; document.getElementById('btn-req').disabled=false; }
  else { sum.className=''; sum.classList.add('ko'); sum.innerHTML='<i class="bi bi-x-circle-fill"></i> Prérequis manquants'; }
  document.querySelectorAll('.feat-item').forEach((el,i)=>{ setTimeout(()=>el.classList.add('visible'),150+i*120); });
}

// ══════════════════════════════════════════════════════════════════════════
// ÉTAPE 2 — BDD
// ══════════════════════════════════════════════════════════════════════════
async function testDb() {
  const btn=document.getElementById('btn-db'), alrt=document.getElementById('db-alert');
  btn.disabled=true; btn.innerHTML='<div class="spin"></div> Connexion…';
  state.db={db_host:v('db_host'),db_port:v('db_port'),db_name:v('db_name'),db_user:v('db_user'),db_pass:v('db_pass'),db_prefix:v('db_prefix')};
  try {
    const res=await fetch('?action=check_db',{method:'POST',headers:jh(),body:JSON.stringify(state.db)}), data=await res.json();
    if(data.ok){ alrt.innerHTML=`<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> ${data.message}</div>`; setTimeout(()=>goToStep(3),550); }
    else alrt.innerHTML=`<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> ${data.error}</div>`;
  } catch(e){ alrt.innerHTML=`<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> Erreur réseau.</div>`; }
  btn.disabled=false; btn.innerHTML='<i class="bi bi-plug-fill"></i> Tester et continuer';
}

// ══════════════════════════════════════════════════════════════════════════
// ÉTAPE 3 — SMTP
// ══════════════════════════════════════════════════════════════════════════
async function testSmtp() {
  const btn=document.getElementById('btn-smtp-test'), res=document.getElementById('smtp-test-res');
  btn.disabled=true; btn.innerHTML='<div class="spin spin-d"></div>';
  try {
    const r=await fetch('?action=check_smtp',{method:'POST',headers:jh(),body:JSON.stringify({smtp_host:v('smtp_host'),smtp_port:v('smtp_port')})}), d=await r.json();
    res.innerHTML=d.ok?`<span style="color:var(--success);font-weight:600;"><i class="bi bi-check-circle-fill"></i> ${d.message}</span>`
                      :`<span style="color:var(--danger);font-weight:600;"><i class="bi bi-x-circle-fill"></i> ${d.error}</span>`;
  } catch(e){ res.innerHTML=`<span style="color:var(--danger);">Erreur réseau.</span>`; }
  btn.disabled=false; btn.innerHTML='<i class="bi bi-send"></i> Tester';
}
function saveSmtp() {
  const alrt=document.getElementById('smtp-alert'), from=v('mail_from');
  if(from&&!from.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) { alrt.innerHTML=`<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> Adresse expéditeur invalide.</div>`; return; }
  state.smtp={skip:false,smtp_host:v('smtp_host'),smtp_port:v('smtp_port'),smtp_user:v('smtp_user'),smtp_pass:v('smtp_pass'),smtp_encryption:v('smtp_encryption'),mail_from:from,mail_from_name:v('mail_from_name')};
  alrt.innerHTML=''; goToStep(4);
}
function skipSmtp(){ state.smtp={skip:true}; document.getElementById('smtp-alert').innerHTML=''; goToStep(4); }

// ══════════════════════════════════════════════════════════════════════════
// ÉTAPE 4 — CONFIG
// ══════════════════════════════════════════════════════════════════════════
function saveConfig() {
  const alrt=document.getElementById('config-alert'), errors=[];
  const [appName,baseUrl,prenom,nom,email,password,password2]=['app_name','base_url','admin_prenom','admin_nom','admin_email','admin_password','admin_password2'].map(v);
  if(!appName) errors.push('Nom de l\'application obligatoire.');
  if(!baseUrl) errors.push('URL de base obligatoire.');
  if(!prenom)  errors.push('Prénom obligatoire.');
  if(!nom)     errors.push('Nom obligatoire.');
  if(!email||!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errors.push('Adresse e-mail invalide.');
  if(password.length<8)   errors.push('Mot de passe : 8 caractères minimum.');
  if(password!==password2) errors.push('Les mots de passe ne correspondent pas.');
  if(errors.length){ alrt.innerHTML=`<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><div><ul style="margin:.22rem 0 0 1rem;">${errors.map(e=>`<li style="font-size:.82rem;">${e}</li>`).join('')}</ul></div></div>`; return; }
  state.app   = {app_name:appName, base_url:baseUrl.replace(/\/$/,'')};
  state.admin = {prenom, nom, email, password};
  alrt.innerHTML=''; goToStep(5);
}

// ══════════════════════════════════════════════════════════════════════════
// ÉTAPE 6 — RÉCAP
// ══════════════════════════════════════════════════════════════════════════
function buildRecap() {
  const sections=[
    {icon:'bi-database',    title:'Base de données', rows:[['Hôte',`${state.db.db_host}:${state.db.db_port}`],['Base',state.db.db_name],['Préfixe',state.db.db_prefix||'—']]},
    {icon:'bi-envelope',    title:'E-mail',           rows:state.smtp.skip?[['Statut','<span class="rv dim">Non configuré</span>']]:    [['Hôte SMTP',`${state.smtp.smtp_host}:${state.smtp.smtp_port}`],['Expéditeur',state.smtp.mail_from||'—']]},
    {icon:'bi-app',         title:'Application',      rows:[['Nom',state.app.app_name],['URL',state.app.base_url]]},
    {icon:'bi-shield-fill', title:'Super-admin',      rows:[['Identité',`${state.admin.prenom} ${state.admin.nom}`],['E-mail',state.admin.email]]},
  ];
  document.getElementById('recap-grid').innerHTML=sections.map(s=>`
    <div class="recap-sec">
      <div class="recap-head"><i class="bi ${s.icon}"></i>${s.title}</div>
      ${s.rows.map(([k,val])=>`<div class="recap-row"><span class="rk">${k}</span><span class="rv">${val}</span></div>`).join('')}
    </div>`).join('');
}

// ══════════════════════════════════════════════════════════════════════════
// ÉTAPE 7 — INSTALLATION
// ══════════════════════════════════════════════════════════════════════════
async function runInstall() {
  const body=document.getElementById('term-body'); body.innerHTML='';
  let cursorLine=null;
  function addLine(type,msg) {
    if(cursorLine) cursorLine.remove();
    const line=document.createElement('div'); line.className='tl';
    const pre={'info':'$','success':'✓','error':'✗','done':'✓'}[type]??'$';
    line.innerHTML=`<span class="tl-pre">${pre}</span><span class="tl-${type}">${msg}</span>`;
    body.appendChild(line); body.scrollTop=body.scrollHeight;
  }
  function addCursor() {
    cursorLine=document.createElement('div'); cursorLine.className='tl';
    cursorLine.innerHTML=`<span class="tl-pre"> </span><span class="term-cursor"></span>`;
    body.appendChild(cursorLine); body.scrollTop=body.scrollHeight;
  }
  addLine('info','Démarrage de l\'installation…'); addCursor();
  const payload={db:state.db, smtp:state.smtp.skip?{}:state.smtp, app:state.app, admin:state.admin};
  try {
    const response=await fetch('?action=install',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const reader=response.body.getReader(), decoder=new TextDecoder(); let buffer='';
    while(true) {
      const {done,value}=await reader.read(); if(done)break;
      buffer+=decoder.decode(value,{stream:true});
      const lines=buffer.split('\n'); buffer=lines.pop();
      for(const raw of lines) {
        const t=raw.trim(); if(!t)continue;
        try {
          const obj=JSON.parse(t);
          if(obj.type==='done') {
            addLine('done','Installation terminée avec succès.');
            document.getElementById('install-title').textContent='Installation terminée !';
            document.getElementById('install-email').textContent=state.admin.email;
            document.getElementById('install-link').href=obj.msg;
            document.getElementById('install-success').style.display='block';
          } else { addLine(obj.type,obj.msg); addCursor(); }
        } catch(e) {}
      }
    }
  } catch(e) { addLine('error','Erreur réseau : '+e.message); }
}

// ══════════════════════════════════════════════════════════════════════════
// UTILS
// ══════════════════════════════════════════════════════════════════════════
const v  = id => { const el=document.getElementById(id); return el?el.value.trim():''; };
const jh = () => ({'Content-Type':'application/json'});

// ══════════════════════════════════════════════════════════════════════════
// INITIALISATION
// ══════════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  updateStepper(1);
  card.style.maxWidth = CARD_WIDTHS[1];
  requestAnimationFrame(() => {
    card.style.height = document.getElementById('panel-1').scrollHeight + 'px';
    setTimeout(() => { card.style.height = ''; }, 620);
  });
  state.app.base_url = v('base_url');
  checkRequirements();
});

let _resizeTimer;
window.addEventListener('resize', () => {
  document.body.classList.add('resizing');
  clearTimeout(_resizeTimer);
  _resizeTimer = setTimeout(() => document.body.classList.remove('resizing'), 120);
});
</script>
</body>
</html>
