<?php
/**
 * admin/config.php
 * Configuration de l'espace administration.
 * ⚠️  Ne jamais committer ce fichier avec de vraies clés API.
 *     Utilisez des variables d'environnement en production.
 */

// Charger .env local s'il existe (non commité)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = array_map('trim', explode('=', $line, 2));
            $val = trim($val, '"\'');
            if (!getenv($key)) putenv("{$key}={$val}");
        }
    }
}

return [
    // Mot de passe admin (bcrypt — changer en production)
    // Générer un nouveau hash : password_hash('MonMotDePasse', PASSWORD_DEFAULT)
    'admin_password_hash' => getenv('ADMIN_PASSWORD_HASH')
        ?: '$2y$12$CHANGEZ_MOI_EN_PRODUCTION_AVEC_PASSWORD_HASH',

    // Brevo — JAMAIS de clé en dur, utiliser .env ou variables d'environnement serveur
    'brevo_api_key' => getenv('BREVO_API_KEY') ?: '',
    'brevo_list_id' => (int)(getenv('BREVO_LIST_ID') ?: 2),

    // Données
    'subscriptions_file' => dirname(__DIR__) . '/data/subscriptions.csv',
    'team_file'          => dirname(__DIR__) . '/data/team.json',
    'uploads_dir'        => dirname(__DIR__) . '/images/equipe/',

    // Session
    'session_lifetime'   => 3600, // 1 heure
];
