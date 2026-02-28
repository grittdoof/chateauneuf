<?php
/**
 * admin/config.php
 * Configuration de l'espace administration.
 * ⚠️  Ne jamais committer ce fichier avec de vraies clés API.
 *     Utilisez des variables d'environnement en production.
 */

return [
    // Mot de passe admin (bcrypt — changer en production)
    // Générer un nouveau hash : password_hash('MonMotDePasse', PASSWORD_DEFAULT)
    'admin_password_hash' => getenv('ADMIN_PASSWORD_HASH')
        ?: '$2y$12$jrLJ9j2XpkCIcbIEKqA9juE0OAP8Yq7zUIjJle0ThcoJOf2jax0eK',

    // Brevo
    'brevo_api_key' => getenv('BREVO_API_KEY') ?: 'REMOVED_SECRET_KEY',
    'brevo_list_id' => (int)(getenv('BREVO_LIST_ID') ?: 2),

    // Données
    'subscriptions_file' => dirname(__DIR__) . '/data/subscriptions.csv',
    'team_file'          => dirname(__DIR__) . '/data/team.json',
    'uploads_dir'        => dirname(__DIR__) . '/images/equipe/',

    // Session
    'session_lifetime'   => 3600, // 1 heure
];
