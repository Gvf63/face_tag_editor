<?php
/**
 * Gestion des chemins de fichiers avec support des liens symboliques
 */

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

/**
 * Resout un chemin qui peut etre un lien symbolique
 * Retourne le chemin reel du fichier cible
 */
function face_tag_write_resolve_path($path)
{
  // Construire le chemin complet depuis la racine Piwigo
  $full_path = PHPWG_ROOT_PATH . $path;
  $full_path = str_replace('/./', '/', $full_path);
  
  //*error_log('Résolution du chemin: ' . $full_path);
  
  // Verifier si c'est un lien symbolique
  if (is_link($full_path)) {
    //*error_log('Lien symbolique détecté');
    $target = readlink($full_path);
    
    // Si le lien est relatif, le resoudre par rapport au repertoire contenant le lien
    if ($target[0] !== '/') {
      $target = dirname($full_path) . '/' . $target;
    }
    
    // Nettoyer le chemin (resoudre les .. et .)
    $target = realpath($target);
    
    if ($target === false) {
      //*error_log('Impossible de résoudre le lien symbolique');
      return false;
    }
    
    //*error_log('Lien résolu vers: ' . $target);
    return $target;
  }
  
  // Si ce n'est pas un lien symbolique, utiliser realpath normalement
  $real = realpath($full_path);
  
  if ($real === false) {
    //*error_log('realpath a échoué, utilisation du chemin original');
    return $full_path;
  }
  
  //*error_log('Chemin réel: ' . $real);
  return $real;
}
?>