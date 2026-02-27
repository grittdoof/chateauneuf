<?php
/**
 * subscribe.php
 * Gestion des inscriptions OPTIN :
 *  - Validation des données
 *  - Enregistrement CSV local
 *  - Ajout contact dans Brevo (liste)
 *  - Envoi d'un email de confirmation via Brevo SMTP
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// ── Configuration ─────────────────────────────────────────────────────────────
$config = [
    'brevo_api_key'  => getenv('BREVO_API_KEY') ?: 'VOTRE_CLE_API_BREVO',
    'brevo_list_id'  => (int)(getenv('BREVO_LIST_ID') ?: 2),       // ID de la liste Brevo
    'smtp_host'      => 'smtp-relay.brevo.com',
    'smtp_port'      => 587,
    'smtp_user'      => getenv('BREVO_SMTP_USER') ?: 'VOTRE_LOGIN_SMTP_BREVO',
    'smtp_pass'      => getenv('BREVO_SMTP_PASS') ?: 'VOTRE_MOT_DE_PASSE_SMTP_BREVO',
    'from_email'     => 'contact@entreprendre-chateauneuf.fr',
    'from_name'      => 'Entreprendre Ensemble pour Châteauneuf',
    'data_file'      => __DIR__ . '/data/subscriptions.csv',
];

// ── Lecture du body JSON ──────────────────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data) {
    // Fallback sur $_POST
    $data = $_POST;
}

// ── Validation ────────────────────────────────────────────────────────────────
$prenom    = trim($data['prenom']    ?? '');
$nom       = trim($data['nom']       ?? '');
$email     = trim($data['email']     ?? '');
$telephone = trim($data['telephone'] ?? '');

$errors = [];
if (empty($prenom))                             $errors[] = 'Le prénom est requis.';
if (empty($nom))                                $errors[] = 'Le nom est requis.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Un email valide est requis.';
if (empty($telephone))                          $errors[] = 'Le numéro de portable est requis.';

if ($errors) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Nettoyage
$prenom    = htmlspecialchars($prenom,    ENT_QUOTES, 'UTF-8');
$nom       = htmlspecialchars($nom,       ENT_QUOTES, 'UTF-8');
$telephone = preg_replace('/[^0-9+\s\-\(\)]/', '', $telephone);

// ── Enregistrement CSV ────────────────────────────────────────────────────────
$dataDir = dirname($config['data_file']);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0750, true);
}

$csvFile   = $config['data_file'];
$isNew     = !file_exists($csvFile);
$fileHandle = fopen($csvFile, 'a');

if ($fileHandle) {
    if ($isNew) {
        fputcsv($fileHandle, ['id', 'prenom', 'nom', 'email', 'telephone', 'date', 'ip']);
    }
    fputcsv($fileHandle, [
        uniqid(),
        $prenom,
        $nom,
        $email,
        $telephone,
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    fclose($fileHandle);
}

// ── Ajout dans Brevo via API ──────────────────────────────────────────────────
$brevoResult = addContactToBrevo($email, $prenom, $nom, $telephone, $config);

// ── Email de confirmation via Brevo SMTP ─────────────────────────────────────
$emailSent = sendConfirmationEmail($email, $prenom, $nom, $config);

// ── Réponse ───────────────────────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'message' => 'Inscription réussie ! Vous allez recevoir un email de confirmation.',
]);
exit;

// ═══════════════════════════════════════════════════════════════════════════════
// FONCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

function addContactToBrevo(string $email, string $prenom, string $nom, string $telephone, array $config): bool
{
    $payload = json_encode([
        'email'      => $email,
        'attributes' => [
            'PRENOM'    => $prenom,
            'NOM'       => $nom,
            'SMS'       => $telephone,
        ],
        'listIds'          => [$config['brevo_list_id']],
        'updateEnabled'    => true,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/contacts');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'api-key: ' . $config['brevo_api_key'],
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

function sendConfirmationEmail(string $toEmail, string $prenom, string $nom, array $config): bool
{
    $subject  = 'Votre inscription — Entreprendre Ensemble pour Châteauneuf';
    $fromName = $config['from_name'];
    $fromEmail = $config['from_email'];

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Confirmation d'inscription</title></head>
<body style="font-family: Arial, sans-serif; background:#f3f4f6; margin:0; padding:0;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); max-width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#0098D8,#007AAD); padding:36px 40px; text-align:center;">
            <h1 style="color:#ffffff; font-size:22px; margin:0 0 8px;">Entreprendre Ensemble</h1>
            <p style="color:rgba(255,255,255,0.85); margin:0; font-size:14px;">pour Châteauneuf</p>
          </td>
        </tr>
        <tr>
          <td style="padding:40px;">
            <h2 style="color:#1f2937; font-size:20px; margin:0 0 16px;">Bonjour {$prenom} !</h2>
            <p style="color:#374151; line-height:1.7; margin:0 0 16px;">
              Merci pour votre inscription. Vous recevrez très prochainement notre programme complet pour Châteauneuf.
            </p>
            <div style="background:#E6F7FC; border-left:4px solid #0098D8; border-radius:0 8px 8px 0; padding:16px 20px; margin:24px 0;">
              <p style="margin:0; color:#005B82; font-weight:600; font-size:15px;">📅 Réunion publique — Mardi 11 mars 2026</p>
              <p style="margin:6px 0 0; color:#005B82; font-size:14px;">19h00 · Salle communale de Châteauneuf</p>
              <p style="margin:6px 0 0; color:#374151; font-size:14px;">Venez rencontrer l'équipe et découvrir le programme en détail !</p>
            </div>
            <p style="color:#374151; line-height:1.7; margin:0 0 24px;">
              En attendant, n'hésitez pas à suivre notre actualité et à partager cette initiative autour de vous.
            </p>
            <p style="color:#374151; line-height:1.7; margin:0;">
              À très bientôt,<br>
              <strong>L'équipe Entreprendre Ensemble pour Châteauneuf</strong>
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#f9fafb; padding:24px 40px; text-align:center; border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af; font-size:12px; margin:0;">
              Vous recevez cet email car vous vous êtes inscrit sur notre site.<br>
              Conformément au RGPD, vous pouvez vous désinscrire à tout moment en nous contactant à
              <a href="mailto:{$fromEmail}" style="color:#0098D8;">{$fromEmail}</a>.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    // Envoi via Brevo SMTP avec la fonction mail() de PHP en fallback
    // Pour un vrai projet, utiliser PHPMailer ou la Brevo Transactional Email API
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // Option 1 : Brevo Transactional Email API (recommandé)
    $result = sendViaBrevoTransactional($toEmail, $prenom . ' ' . $nom, $subject, $htmlBody, $config);
    if ($result) return true;

    // Option 2 : mail() de fallback
    return @mail($toEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
}

function sendViaBrevoTransactional(string $toEmail, string $toName, string $subject, string $htmlContent, array $config): bool
{
    $payload = json_encode([
        'sender'      => ['name' => $config['from_name'], 'email' => $config['from_email']],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => $htmlContent,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'api-key: ' . $config['brevo_api_key'],
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}
