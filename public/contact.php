<?php
header('Content-Type: application/json; charset=utf-8');

// ─── Configuration ────────────────────────────────────────────────────────────
define('MAIL_TO',   'Mayeulg@yahoo.fr');        // destinataire
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'votre-adresse@gmail.com'); // ← votre adresse Gmail expéditrice
define('SMTP_PASS', 'xxxx xxxx xxxx xxxx');     // ← mot de passe d'application Gmail
define('ALLOWED_HOSTS', ['jetsetboat.fr', 'www.jetsetboat.fr', 'localhost', '127.0.0.1']);
// ──────────────────────────────────────────────────────────────────────────────

// CORS — autorise les requêtes depuis le serveur Astro en développement local
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    header('Access-Control-Allow-Origin: http://localhost:4321');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF — vérification de l'origine de la requête
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST) ?: '';
    if (!in_array($originHost, ALLOWED_HOSTS, true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Requête refusée.']);
        exit;
    }
}

// Honeypot anti-spam
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]); // discard silently
    exit;
}

$prenom  = trim(strip_tags($_POST['prenom']  ?? ''));
$nom     = trim(strip_tags($_POST['nom']     ?? ''));
$email   = trim($_POST['email']              ?? '');
$message = trim(strip_tags($_POST['message'] ?? ''));

if (!$prenom || !$nom || !$email || !$message) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Tous les champs sont requis.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Adresse email invalide.']);
    exit;
}

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_USER, 'JetSetBoat');
    $mail->addAddress(MAIL_TO, 'Mayeul');
    $mail->addReplyTo($email, "$prenom $nom");

    $nom_complet  = htmlspecialchars("$prenom $nom");
    $email_safe   = htmlspecialchars($email);
    $message_html = nl2br(htmlspecialchars($message));

    $mail->Subject = "Nouvelle demande — $prenom $nom";
    $mail->isHTML(true);
    $mail->Body = "
<!DOCTYPE html>
<html lang='fr'><body style='font-family:Arial,sans-serif;background:#f5f5f5;padding:20px'>
<div style='max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)'>
  <div style='background:#FF6B35;padding:24px 32px'>
    <h1 style='margin:0;color:#fff;font-size:20px'>Nouvelle demande de contact</h1>
    <p style='margin:4px 0 0;color:rgba(255,255,255,.85);font-size:14px'>JetSetBoat — Côte d'Azur</p>
  </div>
  <div style='padding:32px'>
    <table style='width:100%;border-collapse:collapse'>
      <tr><td style='padding:8px 0;color:#888;font-size:13px;width:90px'>Nom</td>
          <td style='padding:8px 0;font-weight:600'>$nom_complet</td></tr>
      <tr><td style='padding:8px 0;color:#888;font-size:13px'>Email</td>
          <td style='padding:8px 0'><a href='mailto:$email_safe' style='color:#FF6B35'>$email_safe</a></td></tr>
    </table>
    <hr style='border:none;border-top:1px solid #eee;margin:16px 0'>
    <p style='color:#888;font-size:13px;margin:0 0 8px'>Message</p>
    <p style='line-height:1.7;color:#333'>$message_html</p>
  </div>
</div>
</body></html>";
    $mail->AltBody = "Nom: $prenom $nom\nEmail: $email\n\nMessage:\n$message";

    $mail->send();
    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $mail->ErrorInfo]);
}
