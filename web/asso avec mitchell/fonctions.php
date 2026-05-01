<?php
require_once __DIR__ . '/config.php';
// =========================================================
// 🔧 Initialisation des logs
// =========================================================
date_default_timezone_set('Europe/Paris');
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFileServer = $logDir . '/serverBridge_log.txt';
function logServer($message) {
    global $logFileServer;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFileServer, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// =========================================================
// 📧 Envoi d'email via PHPMailer + SMTP alwaysdata
// =========================================================
require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envoie un email HTML.
 *
 * @param string $destinataire  Adresse email du destinataire
 * @param string $sujet         Sujet du message
 * @param string $corpsHtml     Corps HTML du message
 * @return bool                 true si envoyé, false sinon
 */
function envoyerEmail(string $destinataire, string $sujet, string $corpsHtml): bool {

    $mail = new PHPMailer(true);

    try {
        // Credentials lus depuis config.php — jamais en dur dans ce fichier
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL port 465
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('resultats-bridge@alwaysdata.net', 'Tournoi Bridge Online');
        $mail->addAddress($destinataire);

        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $corpsHtml;
        $mail->AltBody = strip_tags(str_replace(
            ['<br>', '<br/>', '<p>', '</p>'], "\n", $corpsHtml
        ));

        $mail->send();
        logServer("📧 Email envoyé à $destinataire : $sujet");
        return true;

    } catch (Exception $e) {
        logServer("❌ Échec envoi email à $destinataire : " . $mail->ErrorInfo);
        return false;
    }
}
