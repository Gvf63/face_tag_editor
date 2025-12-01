<?php
/**
 * Restauration du fichier original
 */

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

/**
 * Restaure le fichier .original (supprime tous les tags)
 */
function face_tag_write_restore_original($params, &$service)
{
  global $conf, $user;
  
  error_log('=== RESTORE ORIGINAL REQUEST ===');
  
  $old_error_reporting = error_reporting(E_ALL);
  $old_display_errors = ini_get('display_errors');
  ini_set('display_errors', 0);
  
  if (empty($params['image_id'])) {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(400, 'Missing image_id parameter');
  }
  
  // Récupérer les infos de l'image
  $query = '
  SELECT id, path, file
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row) {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'Image not found');
  }
  
  error_log('Image trouvée: ' . $row['file']);
  
  // Construire le chemin local et le résoudre (gère les liens symboliques)
  $real_local_path = face_tag_write_resolve_path($row['path']);
  
  if ($real_local_path === false) {
    error_log('❌ Impossible de résoudre le chemin du fichier');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot resolve file path');
  }
  
  error_log('Chemin résolu: ' . $real_local_path);
  
  // Vérifier que le fichier existe
  if (!file_exists($real_local_path)) {
    error_log('❌ Le fichier n\'existe pas');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'File not found at resolved path');
  }
  
  // Vérifier que le backup existe
  $backup_path = $real_local_path . '.original';
  
  if (!file_exists($backup_path)) {
    error_log('❌ Aucun fichier .original trouvé');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'No .original backup file found. Cannot restore.');
  }
  
  error_log('Backup trouvé: ' . $backup_path);
  
  // Rendre le fichier actuel writable
  @chmod($real_local_path, 0666);
  
// Copier le backup par-dessus le fichier actuel
  if (!@copy($backup_path, $real_local_path)) {
    error_log('❌ Échec de la copie du backup');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Failed to restore original file');
  }
  
  error_log('✅ Fichier original restauré');
  
  // Supprimer le fichier .original maintenant qu'il a été restauré
  if (@unlink($backup_path)) {
    error_log('✅ Fichier .original supprimé');
  } else {
    error_log('⚠️ Impossible de supprimer le fichier .original');
  }
  
  // Régénérer les métadonnéess
  face_tag_write_regenerate_metadata($params['image_id']);
  
  error_reporting($old_error_reporting);
  ini_set('display_errors', $old_display_errors);
  
  return array(
    'stat' => 'ok',
    'result' => array(
      'message' => 'Original file restored successfully',
      'image_id' => $params['image_id'],
      'backup_used' => $backup_path
    )
  );
}
?>