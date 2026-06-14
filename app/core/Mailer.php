<?php
declare(strict_types=1);

namespace KronoConnect\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Mailer — envoi d'e-mails via PHPMailer avec support de modèles.
 */
class Mailer
{
    /**
     * Méthode principale pour envoyer un e-mail avec ou sans modèle.
     *
     * @param string $toEmail      Email du destinataire
     * @param string $subject      Objet du mail
     * @param string $content      Contenu (corps du message)
     * @param bool   $useTemplate  Si true, enveloppe le contenu dans le layout standard
     * @param bool   $isHtml       Si true, traite le contenu comme du HTML, sinon convertit les retours à la ligne
     * @param string $toName       Nom optionnel du destinataire
     * @param array  $customCfg    Configuration SMTP personnalisée (pour tests)
     */
    public static function sendMail(
        string $toEmail,
        string $subject,
        string $content,
        bool   $useTemplate = true,
        bool   $isHtml = true,
        string $toName = '',
        ?array $customCfg = null
    ): void {
        
        $finalBody = $content;

        // 1. Gestion du format
        if (!$isHtml) {
            $finalBody = nl2br(htmlspecialchars($content));
        }

        // 2. Gestion du modèle (Layout)
        if ($useTemplate) {
            $finalBody = self::wrapInTemplate($finalBody);
        }

        // 3. Envoi via la méthode de base
        self::send($toEmail, $toName ?: $toEmail, $subject, $finalBody, $customCfg);
    }

    /**
     * Envoie un e-mail HTML via PHPMailer (Méthode de base).
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?array $customCfg = null
    ): void {
        $cfg = $customCfg ?? self::loadConfig();
        $mail = new PHPMailer(true);

        try {
            $driver = $cfg['driver'] ?? 'smtp';

            if ($driver === 'smtp') {
                $mail->isSMTP();
                $mail->Host       = $cfg['smtp_host'];
                $mail->SMTPAuth   = !empty($cfg['smtp_user']);
                $mail->Username   = $cfg['smtp_user'] ?? '';
                $mail->Password   = $cfg['smtp_pass'] ?? '';
                $mail->Port       = (int) ($cfg['smtp_port'] ?? 587);
                $mail->Timeout    = 5; // Eviter que la requête tourne en rond indéfiniment
                $mail->SMTPDebug  = 0;

                $encryption = $cfg['smtp_encryption'] ?? 'tls';
                if ($encryption === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($encryption === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
            } else {
                $mail->isMail(); // Driver 'mail' natif de PHP
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);

            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\n", $htmlBody));

            $mail->send();
        } catch (Exception $e) {
            throw new \RuntimeException("Erreur Mailer : {$mail->ErrorInfo}");
        }
    }

    /**
     * Enveloppe le contenu dans le layout HTML défini dans app/views/emails/layout.php
     */
    private static function wrapInTemplate(string $content): string
    {
        $settings = self::loadConfig();

        // Données pour le template
        $appName      = $settings['from_name'] ?? 'KronoConnectCore';
        $collectivite = 'Votre Collectivité'; // Par défaut

        // Tentative de récupération du nom de la collectivité en BDD
        try {
            $adminModel = new \KronoConnect\Models\AdminModel();
            $dbSettings = \KronoConnect\Core\Cache::remember('settings', 300, fn() => $adminModel->getSettings());
            if (isset($dbSettings['collectivite'])) {
                $collectivite = $dbSettings['collectivite'];
            }
        } catch (\Throwable) {}

        // Capture de l'output du fichier layout
        ob_start();
        $viewFile = VIEW_PATH . '/emails/layout.php';
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            return $content; // Failover
        }
        return ob_get_clean();
    }

    /**
     * Charge la config e-mail depuis app.php et la BDD.
     */
    private static function loadConfig(): array
    {
        $appCfg = require CONFIG_PATH . '/app.php';
        $mail   = $appCfg['mail'] ?? [];

        $dbSettings = [];
        try {
            $adminModel = new \KronoConnect\Models\AdminModel();
            $dbSettings = \KronoConnect\Core\Cache::remember('settings', 300, fn() => $adminModel->getSettings());
        } catch (\Throwable) {}

        return [
            'driver'         => $dbSettings['driver'] ?? $mail['driver'] ?? 'smtp',
            'smtp_host'      => $dbSettings['smtp_host'] ?? $mail['host'] ?? '',
            'smtp_port'      => (int) ($dbSettings['smtp_port'] ?? $mail['port'] ?? 587),
            'smtp_user'      => $dbSettings['smtp_user'] ?? $mail['username'] ?? '',
            'smtp_pass'      => $dbSettings['smtp_pass'] ?? $mail['password'] ?? '',
            'smtp_encryption'=> $dbSettings['smtp_encryption'] ?? $mail['encryption'] ?? 'tls',
            'from_email'     => $dbSettings['from_email'] ?? $mail['from_email'] ?? '',
            'from_name'      => $dbSettings['from_name'] ?? $mail['from_name'] ?? ($appCfg['name'] ?? 'KronoConnectCore'),
        ];
    }
    }
