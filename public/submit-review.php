<?php
header('Content-Type: application/json; charset=utf-8');

// ─── Configuration ────────────────────────────────────────────────────────────
define('MAIL_TO',       'Mayeulg@yahoo.fr');
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'votre-adresse@gmail.com'); // ← adresse Gmail expéditrice
define('SMTP_PASS',     'xxxx xxxx xxxx xxxx');     // ← mot de passe d'application Gmail
define('ALLOWED_HOSTS', ['jetsetboat.fr', 'www.jetsetboat.fr', 'localhost', '127.0.0.1']);
// ──────────────────────────────────────────────────────────────────────────────

$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    header('Access-Control-Allow-Origin: http://localhost:4321');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF — vérification de l'origine
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
    echo json_encode(['ok' => true]);
    exit;
}

$firstName = trim(strip_tags($_POST['prenom']  ?? ''));
$lastName  = trim(strip_tags($_POST['nom']     ?? ''));
$rating    = (int)($_POST['rating']            ?? 0);
$comment   = trim(strip_tags($_POST['comment'] ?? ''));

if (!$firstName || !$lastName || !$comment) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Tous les champs sont requis.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Veuillez sélectionner une note entre 1 et 5 étoiles.']);
    exit;
}

// Enregistrement dans reviews.json
$reviewsFile = __DIR__ . '/reviews.json';
$reviews = [];
if (file_exists($reviewsFile)) {
    $data = json_decode(file_get_contents($reviewsFile), true);
    if (is_array($data)) $reviews = $data;
}

$reviews[] = [
    'id'        => uniqid('rev', true),
    'firstName' => $firstName,
    'lastName'  => $lastName,
    'rating'    => $rating,
    'comment'   => $comment,
    'status'    => 'pending',
    'date'      => date('Y-m-d'),
];

if (file_put_contents(
        $reviewsFile,
        json_encode($reviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Impossible d\'enregistrer l\'avis.']);
    exit;
}

// Notification email à Mayeul
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$stars       = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
$nameDisplay = htmlspecialchars("$firstName $lastName", ENT_QUOTES, 'UTF-8');
$commentHtml = nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'));

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

    $mail->Subject = "Nouvel avis en attente — $firstName $lastName";
    $mail->isHTML(true);
    $mail->Body = "
<!DOCTYPE html>
<html lang='fr'><body style='font-family:Arial,sans-serif;background:#f5f5f5;padding:20px'>
<div style='max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)'>
  <div style='background:#FF6B35;padding:24px 32px'>
    <h1 style='margin:0;color:#fff;font-size:20px'>Nouvel avis client en attente</h1>
    <p style='margin:4px 0 0;color:rgba(255,255,255,.85);font-size:14px'>JetSetBoat — À valider dans l'espace admin</p>
  </div>
  <div style='padding:32px'>
    <table style='width:100%;border-collapse:collapse'>
      <tr><td style='padding:8px 0;color:#888;font-size:13px;width:90px'>Nom</td>
          <td style='padding:8px 0;font-weight:600'>$nameDisplay</td></tr>
      <tr><td style='padding:8px 0;color:#888;font-size:13px'>Note</td>
          <td style='padding:8px 0;color:#FF6B35;font-size:18px;letter-spacing:.1em'>$stars</td></tr>
    </table>
    <hr style='border:none;border-top:1px solid #eee;margin:16px 0'>
    <p style='color:#888;font-size:13px;margin:0 0 8px'>Commentaire</p>
    <p style='line-height:1.7;color:#333'>$commentHtml</p>
    <hr style='border:none;border-top:1px solid #eee;margin:24px 0 16px'>
    <a href='https://www.jetsetboat.fr/admin/?page=reviews'
       style='display:inline-block;background:#FF6B35;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px'>
      → Gérer les avis dans l'admin
    </a>
  </div>
</div>
</body></html>";
    $mail->AltBody = "Nouvel avis de $firstName $lastName ($stars)\n\n$comment\n\nGérer : https://www.jetsetboat.fr/admin/?page=reviews";

    $mail->send();
} catch (Exception $e) {
    // L'avis est enregistré même si l'email échoue
}

echo json_encode(['ok' => true]);
