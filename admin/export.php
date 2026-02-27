<?php
require __DIR__ . '/auth.php';
$config = require __DIR__ . '/config.php';

$format = $_GET['format'] ?? 'csv';
$csvFile = $config['subscriptions_file'];

if (!file_exists($csvFile)) {
    header('Location: index.php?error=nofile');
    exit;
}

$rows = [];
$handle = fopen($csvFile, 'r');
while (($row = fgetcsv($handle)) !== false) {
    $rows[] = $row;
}
fclose($handle);

$filename = 'inscriptions_chateauneuf_' . date('Ymd_His');

if ($format === 'excel') {
    // Export XLSX-compatible (CSV with BOM for Excel UTF-8 detection)
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    // BOM pour que Excel reconnaisse l'UTF-8
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';'); // séparateur point-virgule pour Excel FR
    }
    fclose($out);
} else {
    // Export CSV standard
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
}
exit;
