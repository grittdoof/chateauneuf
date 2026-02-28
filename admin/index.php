<?php
require __DIR__ . '/auth.php';
$config = require __DIR__ . '/config.php';

// ── Gestion de la déconnexion ──────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Lecture des inscriptions ───────────────────────────────────────────────
$subscriptions = [];
$csvFile = $config['subscriptions_file'];
if (file_exists($csvFile)) {
    $handle = fopen($csvFile, 'r');
    $headers = fgetcsv($handle); // ignore header row
    while (($row = fgetcsv($handle)) !== false) {
        if ($headers && count($row) === count($headers)) {
            $subscriptions[] = array_combine($headers, $row);
        }
    }
    fclose($handle);
}

$count = count($subscriptions);
// Inverser pour avoir les plus récentes en premier
$subscriptions = array_reverse($subscriptions);

// ── Lecture des idées citoyennes ─────────────────────────────────────────────
$ideas = [];
$ideasFile = __DIR__ . '/../data/ideas.csv';
if (file_exists($ideasFile)) {
    $handle = fopen($ideasFile, 'r');
    $headers = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        if ($headers && count($row) === count($headers)) {
            $ideas[] = array_combine($headers, $row);
        }
    }
    fclose($handle);
}
$ideasCount = count($ideas);
$ideasUnread = count(array_filter($ideas, fn($i) => ($i['lu'] ?? '0') === '0'));
$ideas = array_reverse($ideas);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Entreprendre Ensemble</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .tab-btn.active { background: white; box-shadow: 0 1px 4px rgba(0,0,0,0.1); color: #1d4ed8; font-weight: 700; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

  <!-- Header -->
  <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img src="../images/logo.png" alt="Logo" class="h-10">
        <div>
          <p class="font-bold text-gray-800 leading-none">Administration</p>
          <p class="text-xs text-gray-500">Entreprendre Ensemble pour Châteauneuf</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a href="../index.html" target="_blank" class="text-sm text-blue-600 hover:underline">Voir le site →</a>
        <a href="?logout=1" class="text-sm text-red-600 hover:underline">Déconnexion</a>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500 mb-1">Inscriptions totales</p>
        <p class="text-3xl font-bold text-blue-600"><?= $count ?></p>
      </div>
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500 mb-1">Cette semaine</p>
        <p class="text-3xl font-bold text-green-600"><?= count(array_filter($subscriptions, fn($s) => isset($s['date']) && strtotime($s['date']) > strtotime('-7 days'))) ?></p>
      </div>
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500 mb-1">Idées citoyennes</p>
        <p class="text-3xl font-bold text-orange-600"><?= $ideasCount ?></p>
        <?php if ($ideasUnread > 0): ?>
        <p class="text-xs text-orange-500 mt-1 font-semibold"><?= $ideasUnread ?> non lue<?= $ideasUnread > 1 ? 's' : '' ?></p>
        <?php endif; ?>
      </div>
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <p class="text-sm text-gray-500 mb-1">Membres de l'équipe</p>
        <p class="text-3xl font-bold text-purple-600">17</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-gray-100 rounded-2xl p-1.5 mb-6 flex gap-1 max-w-lg">
      <button onclick="showTab('inscriptions')" id="tab-inscriptions" class="tab-btn active flex-1 py-2 px-4 rounded-xl text-sm transition-all">Inscriptions</button>
      <button onclick="showTab('idees')" id="tab-idees" class="tab-btn flex-1 py-2 px-4 rounded-xl text-sm text-gray-600 transition-all">
        Idées<?php if ($ideasUnread > 0): ?> <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-orange-500 text-white text-[10px] font-bold ml-1"><?= $ideasUnread ?></span><?php endif; ?>
      </button>
      <button onclick="showTab('equipe')" id="tab-equipe" class="tab-btn flex-1 py-2 px-4 rounded-xl text-sm text-gray-600 transition-all">L'équipe</button>
    </div>

    <!-- Tab: Inscriptions -->
    <div id="panel-inscriptions">
      <div class="flex flex-col sm:flex-row gap-3 justify-between items-start sm:items-center mb-5">
        <h2 class="text-xl font-bold text-gray-800">Liste des inscrits (<?= $count ?>)</h2>
        <div class="flex gap-2">
          <a href="export.php?format=csv" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export CSV
          </a>
          <a href="export.php?format=excel" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export Excel
          </a>
        </div>
      </div>

      <?php if (empty($subscriptions)): ?>
      <div class="bg-white rounded-2xl border border-gray-100 p-16 text-center">
        <p class="text-gray-400 text-lg">Aucune inscription pour le moment.</p>
      </div>
      <?php else: ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Prénom</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Nom</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Téléphone</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($subscriptions as $sub): ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($sub['prenom'] ?? '') ?></td>
                <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($sub['nom'] ?? '') ?></td>
                <td class="px-4 py-3 text-blue-600">
                  <a href="mailto:<?= htmlspecialchars($sub['email'] ?? '') ?>" class="hover:underline">
                    <?= htmlspecialchars($sub['email'] ?? '') ?>
                  </a>
                </td>
                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($sub['telephone'] ?? '') ?></td>
                <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($sub['date'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Tab: Idées citoyennes -->
    <div id="panel-idees" class="hidden">
      <div class="flex flex-col sm:flex-row gap-3 justify-between items-start sm:items-center mb-5">
        <h2 class="text-xl font-bold text-gray-800">Idées citoyennes (<?= $ideasCount ?>)</h2>
        <a href="export.php?format=csv&type=ideas" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-xl text-sm font-semibold hover:bg-orange-700 transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Export CSV
        </a>
      </div>

      <?php if (empty($ideas)): ?>
      <div class="bg-white rounded-2xl border border-gray-100 p-16 text-center">
        <p class="text-gray-400 text-lg">Aucune idée soumise pour le moment.</p>
      </div>
      <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($ideas as $idea): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow <?= ($idea['lu'] ?? '0') === '0' ? 'border-l-4 border-l-orange-400' : '' ?>">
          <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span class="inline-block px-2.5 py-0.5 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold"><?= htmlspecialchars($idea['sujet'] ?? '') ?></span>
                <?php if (($idea['lu'] ?? '0') === '0'): ?>
                <span class="inline-block px-2 py-0.5 bg-red-100 text-red-600 rounded-full text-[10px] font-bold uppercase">Nouveau</span>
                <?php endif; ?>
                <span class="text-xs text-gray-400"><?= htmlspecialchars($idea['date'] ?? '') ?></span>
              </div>
              <p class="text-gray-800 text-sm leading-relaxed mb-3" style="white-space:pre-wrap;"><?= htmlspecialchars($idea['message'] ?? '') ?></p>
              <div class="flex flex-wrap gap-4 text-xs text-gray-500">
                <span><strong class="text-gray-700"><?= htmlspecialchars(($idea['prenom'] ?? '') . ' ' . ($idea['nom'] ?? '')) ?></strong></span>
                <a href="mailto:<?= htmlspecialchars($idea['email'] ?? '') ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($idea['email'] ?? '') ?></a>
                <span><?= htmlspecialchars($idea['telephone'] ?? '') ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Tab: Équipe -->
    <div id="panel-equipe" class="hidden">
      <div class="flex justify-between items-center mb-5">
        <h2 class="text-xl font-bold text-gray-800">Membres de l'équipe</h2>
        <a href="edit-team.php" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-xl text-sm font-semibold hover:bg-purple-700 transition-colors shadow-sm">
          Gérer les membres
        </a>
      </div>

      <?php
      $teamFile = $config['team_file'];
      $teamData = file_exists($teamFile) ? json_decode(file_get_contents($teamFile), true) : [];
      $members = $teamData['members'] ?? [];
      ?>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($members as $m): ?>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
          <div class="h-32 bg-gray-100 overflow-hidden">
            <img src="../<?= htmlspecialchars($m['photo'] ?? '') ?>" alt="<?= htmlspecialchars(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')) ?>" class="w-full h-full object-cover object-top">
          </div>
          <div class="p-3">
            <div class="flex items-center gap-2 mb-1">
              <span class="w-5 h-5 rounded-full bg-orange-100 text-orange-700 text-xs font-bold flex items-center justify-center flex-shrink-0"><?= $m['id'] ?></span>
              <p class="font-semibold text-gray-800 text-sm leading-tight"><?= htmlspecialchars(($m['prenom'] ?? '') . ' ' . ($m['nom'] ?? '')) ?></p>
            </div>
            <p class="text-xs text-gray-500"><?= $m['age'] ? $m['age'] . ' ans' : '' ?></p>
            <p class="text-xs text-blue-600 font-medium truncate mt-0.5"><?= htmlspecialchars($m['profession'] ?? '') ?></p>
            <a href="edit-team.php?id=<?= $m['id'] ?>" class="mt-2 block text-center text-xs py-1.5 bg-gray-100 hover:bg-blue-100 hover:text-blue-700 rounded-lg transition-colors font-medium">
              Modifier
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </main>

  <script>
    function showTab(tab) {
      ['inscriptions', 'idees', 'equipe'].forEach(t => {
        document.getElementById('panel-' + t).classList.toggle('hidden', tab !== t);
        document.getElementById('tab-' + t).classList.toggle('active', tab === t);
        document.getElementById('tab-' + t).classList.toggle('text-gray-600', tab !== t);
      });
    }
  </script>
</body>
</html>
