<?php
// Fichier à placer dans /volume1/web/piwigo/plugins/face_tag_editor/
// Nom : debug_version.php
// Accès via : http://ton_nas/piwigo/plugins/face_tag_editor/debug_version.php

echo "<h1>Diagnostic face_tag_editor</h1>";

echo "<h2>1. Chemin du fichier metadata_writer.php</h2>";
echo "Chemin complet : " . realpath(__DIR__ . '/lib/metadata_writer.php');
echo "<br>";
echo "Fichier existe : " . (file_exists(__DIR__ . '/lib/metadata_writer.php') ? 'OUI' : 'NON');
echo "<br>";
echo "Taille : " . filesize(__DIR__ . '/lib/metadata_writer.php') . " bytes";
echo "<br>";
echo "Modifié le : " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/lib/metadata_writer.php'));

echo "<h2>2. Version du fichier</h2>";
$content = file_get_contents(__DIR__ . '/lib/metadata_writer.php');

if (strpos($content, 'appendChild($dimW)') !== false) {
    echo "<p style='color:green; font-size:20px;'>✅ NOUVELLE VERSION détectée (avec appendChild)</p>";
} else if (strpos($content, "setAttribute('stDim:w'") !== false) {
    echo "<p style='color:red; font-size:20px;'>❌ ANCIENNE VERSION détectée (avec setAttribute)</p>";
} else {
    echo "<p style='color:orange; font-size:20px;'>⚠️ Version inconnue</p>";
}

echo "<h2>3. Extrait du code (lignes 400-410)</h2>";
$lines = explode("\n", $content);
echo "<pre>";
for ($i = 399; $i < 410 && $i < count($lines); $i++) {
    echo ($i+1) . ": " . htmlspecialchars($lines[$i]) . "\n";
}
echo "</pre>";

echo "<h2>4. Recherche de tous les metadata_writer.php</h2>";
exec('find /volume1/web/piwigo -name "metadata_writer.php" 2>/dev/null', $files);
echo "<ul>";
foreach ($files as $file) {
    echo "<li>" . $file . " (taille: " . filesize($file) . " bytes)</li>";
}
echo "</ul>";

echo "<h2>5. OPcache activé ?</h2>";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    echo "OPcache actif : " . ($status['opcache_enabled'] ? 'OUI' : 'NON');
    echo "<br>";
    echo "Nombre de fichiers en cache : " . $status['opcache_statistics']['num_cached_scripts'];
} else {
    echo "OPcache non disponible";
}
?>