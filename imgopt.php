<?php
/**
 * imgopt.php — Optimisation d'images à la volée
 *
 * Usage : imgopt.php?src=images/equipe/Nom.jpg&w=400&h=400
 *
 * - Redimensionne et recadre l'image (cover, centré en haut)
 * - Convertit en WebP si le navigateur le supporte
 * - Met en cache le résultat dans /data/img-cache/
 *
 * Nécessite l'extension GD de PHP.
 */

// ── Configuration ────────────────────────────────────────────────────────────
$cacheDir   = __DIR__ . '/data/img-cache/';
$rootDir    = __DIR__ . '/';
$maxWidth   = 800;
$maxHeight  = 800;
$jpegQuality = 85;
$webpQuality = 80;

// ── Paramètres ───────────────────────────────────────────────────────────────
$src = $_GET['src'] ?? '';
$w   = min((int)($_GET['w'] ?? 400), $maxWidth);
$h   = min((int)($_GET['h'] ?? 400), $maxHeight);

// Sécurité : bloquer les tentatives de path traversal
$src = ltrim(str_replace(['..', "\0"], '', $src), '/');
if (empty($src) || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $src)) {
    http_response_code(400);
    exit('Image source invalide.');
}

$srcPath = realpath($rootDir . $src);

// Vérifier que le fichier est bien dans le répertoire racine
if (!$srcPath || strpos($srcPath, realpath($rootDir)) !== 0 || !file_exists($srcPath)) {
    http_response_code(404);
    exit('Image introuvable.');
}

// ── Détection WebP ───────────────────────────────────────────────────────────
$acceptWebp  = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
$outputExt   = $acceptWebp ? 'webp' : 'jpg';
$outputMime  = $acceptWebp ? 'image/webp' : 'image/jpeg';

// ── Cache ────────────────────────────────────────────────────────────────────
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0750, true);
}

$cacheKey  = md5($srcPath . $w . $h . $outputExt);
$cachePath = $cacheDir . $cacheKey . '.' . $outputExt;

if (file_exists($cachePath) && filemtime($cachePath) > filemtime($srcPath)) {
    // Servir depuis le cache
    header('Content-Type: ' . $outputMime);
    header('Cache-Control: public, max-age=2592000'); // 30 jours
    header('X-Cache: HIT');
    readfile($cachePath);
    exit;
}

// ── Traitement ───────────────────────────────────────────────────────────────
if (!function_exists('imagecreatefromjpeg')) {
    // GD non disponible — servir l'image originale
    header('Content-Type: ' . mime_content_type($srcPath));
    header('Cache-Control: public, max-age=86400');
    readfile($srcPath);
    exit;
}

$info = getimagesize($srcPath);
if (!$info) {
    http_response_code(500);
    exit('Erreur lecture image.');
}

[$origW, $origH, $type] = $info;

// Créer la ressource GD source
switch ($type) {
    case IMAGETYPE_JPEG: $src_img = imagecreatefromjpeg($srcPath); break;
    case IMAGETYPE_PNG:  $src_img = imagecreatefrompng($srcPath);  break;
    case IMAGETYPE_WEBP: $src_img = imagecreatefromwebp($srcPath); break;
    default:
        http_response_code(415);
        exit('Format non supporté.');
}

// Calcul des ratios pour "cover" (recadrage centré en haut)
$ratioW = $w / $origW;
$ratioH = $h / $origH;
$ratio  = max($ratioW, $ratioH);

$newW = (int)round($origW * $ratio);
$newH = (int)round($origH * $ratio);
$offsetX = (int)round(($newW - $w) / 2);
$offsetY = 0; // Aligné en haut pour les portraits

$dst_img = imagecreatetruecolor($w, $h);

// Fond blanc pour les PNG avec transparence
imagefill($dst_img, 0, 0, imagecolorallocate($dst_img, 255, 255, 255));

imagecopyresampled($dst_img, $src_img, 0, 0, (int)round($offsetX / $ratio), (int)round($offsetY / $ratio), $w, $h, $w, $h);
imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

// Recréer proprement
$final = imagecreatetruecolor($w, $h);
imagefill($final, 0, 0, imagecolorallocate($final, 255, 255, 255));
imagecopyresampled($final, $src_img,
    0, 0,
    (int)round($offsetX / $ratio), 0,
    $w, $h,
    $w, $h
);

imagedestroy($dst_img);
imagedestroy($src_img);

// ── Sauvegarde et sortie ─────────────────────────────────────────────────────
ob_start();
if ($acceptWebp && function_exists('imagewebp')) {
    imagewebp($final, null, $webpQuality);
} else {
    imagejpeg($final, null, $jpegQuality);
}
$imageData = ob_get_clean();
imagedestroy($final);

file_put_contents($cachePath, $imageData);

header('Content-Type: ' . $outputMime);
header('Content-Length: ' . strlen($imageData));
header('Cache-Control: public, max-age=2592000');
header('X-Cache: MISS');
echo $imageData;
exit;
