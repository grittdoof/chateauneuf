<?php
require __DIR__ . '/auth.php';
$config = require __DIR__ . '/config.php';

$teamFile = $config['team_file'];
$uploadsDir = $config['uploads_dir'];
$teamData = file_exists($teamFile) ? json_decode(file_get_contents($teamFile), true) : ['members' => []];
$members = &$teamData['members'];

$memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$member = null;
$memberIndex = -1;

foreach ($members as $i => $m) {
    if ($m['id'] === $memberId) {
        $member = $m;
        $memberIndex = $i;
        break;
    }
}

$success = '';
$error = '';

// ── Traitement du formulaire ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $member !== null) {
    // Upload photo
    if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);

        if (in_array($mimeType, $allowedTypes)) {
            $ext = $mimeType === 'image/png' ? 'png' : ($mimeType === 'image/webp' ? 'webp' : 'jpg');
            $newFilename = $member['prenom'] . ' ' . $member['nom'] . '.' . $ext;
            $destPath = rtrim($uploadsDir, '/') . '/' . $newFilename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                $members[$memberIndex]['photo'] = 'images/equipe/' . $newFilename;
            }
        } else {
            $error = 'Format de fichier non autorisé (JPEG, PNG ou WebP uniquement).';
        }
    }

    if (!$error) {
        // Mise à jour des champs texte
        $fields = ['prenom', 'nom', 'role', 'profession', 'depuis', 'q1', 'q2', 'q3'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $members[$memberIndex][$f] = trim($_POST[$f]);
            }
        }
        if (isset($_POST['age'])) {
            $members[$memberIndex]['age'] = $_POST['age'] !== '' ? (int)$_POST['age'] : null;
        }

        // Sauvegarde
        file_put_contents($teamFile, json_encode($teamData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $member = $members[$memberIndex];
        $success = 'Modifications enregistrées avec succès.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Modifier l'équipe — Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

  <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="index.php" class="text-gray-500 hover:text-gray-800 transition-colors">← Tableau de bord</a>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-800">Modifier un membre</span>
      </div>
      <a href="?logout=1" class="text-sm text-red-600 hover:underline">Déconnexion</a>
    </div>
  </header>

  <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8">

    <?php if (!$member): ?>
    <!-- Liste des membres -->
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Sélectionner un membre à modifier</h1>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      <?php foreach ($members as $m): ?>
      <a href="?id=<?= $m['id'] ?>" class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all">
        <div class="h-28 bg-gray-100 overflow-hidden">
          <img src="../<?= htmlspecialchars($m['photo'] ?? '') ?>" alt="" class="w-full h-full object-cover object-top">
        </div>
        <div class="p-3">
          <p class="font-semibold text-gray-800 text-sm"><?= $m['id'] ?>. <?= htmlspecialchars(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')) ?></p>
          <p class="text-xs text-blue-600 truncate mt-0.5"><?= htmlspecialchars($m['profession'] ?? 'Bio à compléter') ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- Formulaire de modification -->
    <div class="flex items-center gap-4 mb-8">
      <div class="w-16 h-16 rounded-full overflow-hidden shadow">
        <img src="../<?= htmlspecialchars($member['photo'] ?? '') ?>" alt="" class="w-full h-full object-cover object-top">
      </div>
      <div>
        <h1 class="text-2xl font-bold text-gray-800">
          <?= htmlspecialchars(($member['prenom'] ?? '') . ' ' . ($member['nom'] ?? '')) ?>
        </h1>
        <p class="text-gray-500">Candidat n°<?= $member['id'] ?></p>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4 mb-6"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-6"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">

      <!-- Informations de base -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <h2 class="text-lg font-bold text-gray-800 mb-5 pb-3 border-b border-gray-100">Informations principales</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Prénom</label>
            <input type="text" name="prenom" value="<?= htmlspecialchars($member['prenom'] ?? '') ?>" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nom</label>
            <input type="text" name="nom" value="<?= htmlspecialchars($member['nom'] ?? '') ?>" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Âge</label>
            <input type="number" name="age" value="<?= htmlspecialchars((string)($member['age'] ?? '')) ?>" min="18" max="99" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm" placeholder="ex : 36">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Rôle / Titre</label>
            <input type="text" name="role" value="<?= htmlspecialchars($member['role'] ?? '') ?>" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm" placeholder="ex : Tête de liste">
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Profession</label>
            <input type="text" name="profession" value="<?= htmlspecialchars($member['profession'] ?? '') ?>" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Présence sur la commune</label>
            <input type="text" name="depuis" value="<?= htmlspecialchars($member['depuis'] ?? '') ?>" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm" placeholder="ex : Castelneuvien de toujours">
          </div>
        </div>
      </div>

      <!-- Photo -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <h2 class="text-lg font-bold text-gray-800 mb-5 pb-3 border-b border-gray-100">Photo</h2>
        <div class="flex items-start gap-5">
          <img src="../<?= htmlspecialchars($member['photo'] ?? '') ?>" alt="Photo actuelle" class="w-24 h-24 rounded-xl object-cover object-top shadow border border-gray-200" id="photo-preview">
          <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Nouvelle photo (JPEG, PNG ou WebP)</label>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" onchange="previewPhoto(event)">
            <p class="text-xs text-gray-400 mt-1.5">Taille recommandée : 400×400 px minimum. La photo sera automatiquement optimisée.</p>
          </div>
        </div>
      </div>

      <!-- Biographie -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <h2 class="text-lg font-bold text-gray-800 mb-5 pb-3 border-b border-gray-100">Biographie</h2>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
              <span class="inline-block w-4 h-4 bg-blue-100 text-blue-700 rounded text-xs text-center leading-4 mr-1">1</span>
              Ce que j'apporte à la commune
            </label>
            <textarea name="q1" rows="4" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm resize-none"><?= htmlspecialchars($member['q1'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
              <span class="inline-block w-4 h-4 bg-orange-100 text-orange-700 rounded text-xs text-center leading-4 mr-1">2</span>
              Mon parcours (âge, métier)
            </label>
            <textarea name="q2" rows="4" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm resize-none"><?= htmlspecialchars($member['q2'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
              <span class="inline-block w-4 h-4 bg-green-100 text-green-700 rounded text-xs text-center leading-4 mr-1">3</span>
              Pourquoi cet engagement ?
            </label>
            <textarea name="q3" rows="4" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm resize-none"><?= htmlspecialchars($member['q3'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 sm:flex-none px-8 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-md">
          Enregistrer les modifications
        </button>
        <a href="edit-team.php" class="px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-xl hover:bg-gray-200 transition-colors">
          Annuler
        </a>
      </div>
    </form>
    <?php endif; ?>
  </main>

  <script>
    function previewPhoto(event) {
      const file = event.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
          document.getElementById('photo-preview').src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    }
  </script>
</body>
</html>
