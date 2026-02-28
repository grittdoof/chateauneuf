<?php
/**
 * submit-idea.php
 * Gestion des soumissions d'idées citoyennes :
 *  - Validation des données
 *  - Enregistrement CSV local
 *  - Notification par email à l'équipe
 *  - Email de confirmation à l'utilisateur via Brevo SMTP
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
    'from_email'     => 'contact@entreprendre-chateauneuf.fr',
    'from_name'      => 'Entreprendre Ensemble pour Châteauneuf',
    'team_email'     => 'contact@entreprendre-chateauneuf.fr',
    'data_file'      => __DIR__ . '/data/ideas.csv',
];

// ── Lecture du body JSON ──────────────────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data) {
    $data = $_POST;
}

// ── Validation ────────────────────────────────────────────────────────────────
$prenom    = trim($data['prenom']    ?? '');
$nom       = trim($data['nom']       ?? '');
$email     = trim($data['email']     ?? '');
$telephone = trim($data['telephone'] ?? '');
$sujet     = trim($data['sujet']     ?? '');
$message   = trim($data['message']   ?? '');

$errors = [];
if (empty($prenom))                                                     $errors[] = 'Le prénom est requis.';
if (empty($nom))                                                        $errors[] = 'Le nom est requis.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = 'Un email valide est requis.';
if (empty($telephone))                                                  $errors[] = 'Le téléphone est requis.';
if (empty($sujet))                                                      $errors[] = 'Veuillez choisir un sujet.';
if (empty($message))                                                    $errors[] = 'Merci de décrire votre idée.';

if ($errors) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Nettoyage
$prenom    = htmlspecialchars($prenom,    ENT_QUOTES, 'UTF-8');
$nom       = htmlspecialchars($nom,       ENT_QUOTES, 'UTF-8');
$sujet     = htmlspecialchars($sujet,     ENT_QUOTES, 'UTF-8');
$message   = htmlspecialchars($message,   ENT_QUOTES, 'UTF-8');
$telephone = preg_replace('/[^0-9+\s\-\(\)]/', '', $telephone);

// ── Enregistrement CSV ────────────────────────────────────────────────────────
$dataDir = dirname($config['data_file']);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0750, true);
}

$csvFile    = $config['data_file'];
$isNew      = !file_exists($csvFile);
$ideaId     = uniqid('idea_');
$fileHandle = fopen($csvFile, 'a');

if ($fileHandle) {
    if ($isNew) {
        fputcsv($fileHandle, ['id', 'prenom', 'nom', 'email', 'telephone', 'sujet', 'message', 'date', 'ip', 'lu']);
    }
    fputcsv($fileHandle, [
        $ideaId,
        $prenom,
        $nom,
        $email,
        $telephone,
        $sujet,
        $message,
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        '0',
    ]);
    fclose($fileHandle);
}

// ── Notification email à l'équipe ────────────────────────────────────────────
sendTeamNotification($prenom, $nom, $email, $telephone, $sujet, $message, $config);

// ── Confirmation email à l'utilisateur ──────────────────────────────────────
sendUserConfirmation($email, $prenom, $nom, $sujet, $config);

// ── Réponse ───────────────────────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'message' => 'Votre idée a bien été envoyée ! Vous recevrez une confirmation par email.',
]);
exit;

// ═══════════════════════════════════════════════════════════════════════════════
// FONCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

function sendTeamNotification(string $prenom, string $nom, string $email, string $telephone, string $sujet, string $message, array $config): bool
{
    $subject = "Nouvelle idée citoyenne : {$sujet}";
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Nouvelle idée citoyenne</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#D35E2D,#A94B24);padding:28px 40px;text-align:center;">
            <h1 style="color:#fff;font-size:20px;margin:0;">Nouvelle idée citoyenne</h1>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 40px;">
            <div style="background:#FEF3F0;border-left:4px solid #D35E2D;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:24px;">
              <p style="margin:0;color:#A94B24;font-weight:700;font-size:16px;">{$sujet}</p>
            </div>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
              <tr><td style="color:#6b7280;font-size:13px;padding:4px 0;width:100px;"><strong>Nom :</strong></td><td style="color:#1f2937;font-size:14px;">{$prenom} {$nom}</td></tr>
              <tr><td style="color:#6b7280;font-size:13px;padding:4px 0;"><strong>Email :</strong></td><td style="color:#1f2937;font-size:14px;"><a href="mailto:{$email}" style="color:#0098D8;">{$email}</a></td></tr>
              <tr><td style="color:#6b7280;font-size:13px;padding:4px 0;"><strong>Tél :</strong></td><td style="color:#1f2937;font-size:14px;">{$telephone}</td></tr>
            </table>
            <div style="background:#f9fafb;border-radius:12px;padding:20px;margin-bottom:16px;">
              <p style="margin:0 0 8px;color:#6b7280;font-size:12px;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Message</p>
              <p style="margin:0;color:#374151;line-height:1.7;font-size:14px;white-space:pre-wrap;">{$message}</p>
            </div>
          </td>
        </tr>
        <tr>
          <td style="background:#f9fafb;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af;font-size:12px;margin:0;">
              Idée soumise depuis le site entreprendre-chateauneuf.fr
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    return sendViaBrevo($config['team_email'], $config['from_name'], $subject, $htmlBody, $config);
}

function sendUserConfirmation(string $toEmail, string $prenom, string $nom, string $sujet, array $config): bool
{
    $subject  = 'Votre idée a bien été reçue — Entreprendre Ensemble';
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Confirmation</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#0098D8,#007AAD);padding:36px 40px;text-align:center;">
            <h1 style="color:#fff;font-size:22px;margin:0 0 8px;">Entreprendre Ensemble</h1>
            <p style="color:rgba(255,255,255,.85);margin:0;font-size:14px;">pour Châteauneuf</p>
          </td>
        </tr>
        <tr>
          <td style="padding:40px;">
            <h2 style="color:#1f2937;font-size:20px;margin:0 0 16px;">Merci {$prenom} !</h2>
            <p style="color:#374151;line-height:1.7;margin:0 0 16px;">
              Votre idée sur le thème <strong style="color:#0098D8;">« {$sujet} »</strong> a bien été reçue par notre équipe.
            </p>
            <p style="color:#374151;line-height:1.7;margin:0 0 16px;">
              Nous la lirons attentivement et pourrons revenir vers vous si nécessaire. Chaque contribution compte pour bâtir ensemble le projet pour Châteauneuf.
            </p>
            <div style="background:#E6F7FC;border-left:4px solid #0098D8;border-radius:0 8px 8px 0;padding:16px 20px;margin:24px 0;">
              <p style="margin:0;color:#005B82;font-weight:600;font-size:15px;">Réunion publique — Mardi 11 mars 2026</p>
              <p style="margin:6px 0 0;color:#005B82;font-size:14px;">19h00 · Salle communale de Châteauneuf</p>
              <p style="margin:6px 0 0;color:#374151;font-size:14px;">Venez nous rencontrer et échanger en direct !</p>
            </div>
            <p style="color:#374151;line-height:1.7;margin:0;">
              À très bientôt,<br>
              <strong>L'équipe Entreprendre Ensemble pour Châteauneuf</strong>
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#f9fafb;padding:24px 40px;text-align:center;border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af;font-size:12px;margin:0;">
              Vous recevez cet email suite à votre soumission d'idée sur notre site.<br>
              <a href="mailto:{$config['from_email']}" style="color:#0098D8;">{$config['from_email']}</a>
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    return sendViaBrevo($toEmail, $prenom . ' ' . $nom, $subject, $htmlBody, $config);
}

function sendViaBrevo(string $toEmail, string $toName, string $subject, string $htmlContent, array $config): bool
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

    if ($httpCode >= 200 && $httpCode < 300) return true;

    // Fallback mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$config['from_name']} <{$config['from_email']}>\r\n";
    $headers .= "Reply-To: {$config['from_email']}\r\n";

    return @mail($toEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlContent, $headers);
}
