<?php
session_start();
$config = require __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, $config['admin_password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        header('Location: index.php');
        exit;
    } else {
        $error = 'Mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administration — Entreprendre Ensemble</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
    <div class="text-center mb-8">
      <img src="../images/logo.png" alt="Châteauneuf" class="h-16 mx-auto mb-4">
      <h1 class="text-2xl font-bold text-gray-800">Administration</h1>
      <p class="text-gray-500 text-sm mt-1">Entreprendre Ensemble pour Châteauneuf</p>
    </div>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-6 text-sm">
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">Mot de passe</label>
        <input
          type="password"
          id="password"
          name="password"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none transition-colors"
          placeholder="••••••••"
          autofocus
          required
        >
      </div>
      <button type="submit" class="w-full py-3 px-6 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-xl hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5">
        Se connecter
      </button>
    </form>
    <p class="text-center text-gray-400 text-xs mt-6">
      <a href="../index.html" class="hover:text-blue-500 transition-colors">← Retour au site</a>
    </p>
  </div>
</body>
</html>
