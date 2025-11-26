<?php
/*
Plugin Name: face_tag_editor
Version: 1.2C
Description: Créer et enregistrer les tags de visages dans les métadonnées XMP (mode modal) - Version simplifiée avec URLs
Plugin URI: https://fr.piwigo.org/ext/
Author: Charles69
Has Settings: webmaster
*/

//============= VERSIONS ============================================
/*
version 1.2C - 26/11/2025
    aspect visuel css svg
    ajouté logs

version 1.2B - 26/11/2025
    ajouté logs

version 1.2A 25/11/2025
    suppression de .jpg.original après une restauration
    corrigé rotation type 6 = 90 CW
    bouton taguer noir & blanc

version 1.2 - 24/11/2025 
    changement de nom du plugin face_tag_editor
    corrigé suppression tous les visages
    corrigé photo sans XMP
    message en clair quand les fichiers sont verrouillés
    décommenter @unlink effact fichiers temporaires

version 1.1 - 23/11/2025
    ok avec les liens symboliques et upload
    ajout d'une fonction de restauration du fichier original
    ajout de conditions sur les utilisateurs autorisés

version 1.0A - 23/11/2025
    sur les fichiers jpg qui se trouvent dans ./galleries
    prise en compte des tags visage existants
    deplacement modification des cadres
    ajout suppression de visage

*/
//====================================================================

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

if (basename(dirname(__FILE__)) != 'face_tag_editor')
{
  add_event_handler('init', 'face_tag_editor_error');
  function face_tag_editor_error()
  {
    global $page;
    $page['errors'][] = 'Désactiver le plugin et renommer le répertoire  "face_tag_editor"';
  }
  return;
}

// changt de nom du plugin de face_tag_write à face_tag_editor , mais les noms des variables sont restées tag_face_write

// Plugin constants
define('FACETAGWRITE_ID', basename(dirname(__FILE__)));
define('FACETAGWRITE_PATH', PHPWG_PLUGINS_PATH . FACETAGWRITE_ID . '/');
define('FACETAGWRITE_ADMIN', get_root_url() . 'admin.php?page=plugin-' . FACETAGWRITE_ID); // admin.php?page=plugin-face_tag_editor

// Logs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', './plugins/face_tag_editor/face_tag_editor_debug.log');

  // Charger les classes
require_once(FACETAGWRITE_PATH . 'lib/metadata_writer.php');
require_once(FACETAGWRITE_PATH . 'lib/metadata_merger.php');
require_once(FACETAGWRITE_PATH . 'lib/file_resolver.php');
require_once(FACETAGWRITE_PATH . 'lib/restore_original.php');
require_once(FACETAGWRITE_PATH . 'img/icon_svg.php'); // image du bouton taguer

// TEST : Vérifier que la fonction existe
error_log('TEST: fonction restore existe ? ' . (function_exists('face_tag_write_restore_original') ? 'OUI' : 'NON'));


// Initialisation
add_event_handler('init', 'face_tag_write_init');

function face_tag_write_init()
{
  load_language('plugin.lang', FACETAGWRITE_PATH);
}

// ==================== CHARGER JQUERY ====================
add_event_handler('loc_begin_page_header', 'face_tag_write_load_jquery');
function face_tag_write_load_jquery()
{
  global $template;
  
  $template->append('head_elements', '
  <script type="text/javascript">
    if (typeof jQuery === "undefined") {
      document.write(\'<script type="text/javascript" src="' . get_root_url() . 'themes/default/js/jquery.min.js"><\/script>\');
    }
  </script>
  ');
}

// ==================== CHARGER LE CSS DU BOUTON ====================
add_event_handler('loc_begin_page_header', 'face_tag_write_load_css');
function face_tag_write_load_css()
{
  global $template;
  
  $template->append('head_elements', '
  <link rel="stylesheet" href="' . FACETAGWRITE_PATH . 'css/face_tag_button.css">
  <link rel="stylesheet" href="' . FACETAGWRITE_PATH . 'css/face_tag_modal.css">
  ');
}

// ==================== CHARGER NOTRE SCRIPT ====================
add_event_handler('loc_end_page_tail', 'face_tag_write_load_scripts');
function face_tag_write_load_scripts()
{
  global $template, $page;
  
  if (!isset($page['image_id'])) {
    return;
  }
  
  $template->append('footer_elements', '
  <script src="' . FACETAGWRITE_PATH . 'template/draw_faces.js"></script>
  ');
}

// ==================== AJOUTER LE BOUTON ====================
add_event_handler('loc_end_picture', 'face_tag_write_add_button');

// ==================== VÉRIFICATION DES DROITS D'ACCÈS ====================
function face_tag_write_check_access()
{
  global $user;
  
  // Webmaster : accès total
  if (is_webmaster())
  {
    return true;
  }
  
  // Administrateur : accès total
  if (is_admin())
  {
    return true;
  }
  
  // Vérifier si l'utilisateur appartient au groupe 'FaceTag'
  if (!empty($user['id']))
  {
    $query = '
    SELECT g.name
    FROM ' . USER_GROUP_TABLE . ' AS ug
    INNER JOIN ' . GROUPS_TABLE . ' AS g ON ug.group_id = g.id
    WHERE ug.user_id = ' . intval($user['id']) . '
    AND g.name = "FaceTag"';
    
    $result = pwg_query($query);
    
    if (pwg_db_num_rows($result) > 0)
    {
      return true;
    }
  }
  
  // Accès refusé
  return false;
}


function face_tag_write_add_button()
{
  global $template, $picture, $user;
  
  // Vérifier les droits d'accès
  if (!face_tag_write_check_access())
  {
    return;
  }
  
  $query = '
  SELECT path, file
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . intval($picture['current']['id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row) return;
  
  $original_path = $row['path'];
  $image_url = embellish_url(get_root_url() . $original_path);
  $save_url = get_root_url() . 'ws.php?format=json&method=facetagwrite.saveXMP';
  
// Vérifier si le fichier .original existe
  $real_local_path = face_tag_write_resolve_path($row['path']);
  $has_original = file_exists($real_local_path . '.original') ? 'true' : 'false';


// VERSION 1 : Thème par défaut - SANS styles inline
$button_html = '
  <a href="#" 
     id="facetag-open-editor"
     data-image-id="' . $picture['current']['id'] . '"
     data-image-src="' . $image_url . '"
     data-save-url="' . $save_url . '"
     data-has-original="' . $has_original . '"
     class="pwg-state-default pwg-button" 
     title="Taguer les visages" 
     rel="nofollow">
    <span class="pwg-icon">' . FACETAGWRITE_ICON . '</span>
    <span class="pwg-button-text">Taguer</span>
  </a>';

// VERSION 2 : Thèmes Bootstrap - SANS styles inline
if ($user['theme'] == 'bootstrapdefault' || $user['theme'] == 'bootstrap_darkroom') {
  $button_html = '
    <a href="#" 
       id="facetag-open-editor"
       data-image-id="' . $picture['current']['id'] . '"
       data-image-src="' . $image_url . '"
       data-save-url="' . $save_url . '"
       data-has-original="' . $has_original . '"
       class="btn btn-primary" 
       title="Taguer les visages" 
       rel="nofollow">
      ' . FACETAGWRITE_ICON . ' Taguer
    </a>';
}
  
  $template->concat('PLUGIN_PICTURE_ACTIONS', $button_html);
}

// ==================== WEB SERVICE ====================
add_event_handler('ws_add_methods', 'face_tag_write_ws_methods');

function face_tag_write_ws_methods($arr)
{
  $service = &$arr[0];
  
  $service->addMethod(
    'facetagwrite.getXMP',
    'face_tag_write_get_xmp',
    array(
      'image_id' => array('default' => null, 'type' => WS_TYPE_INT),
    ),
    'Récupère les données XMP des visages (face_tag_write)',
    null,
    array('admin_status' => ACCESS_ADMINISTRATOR)
  );
  
  $service->addMethod(
    'facetagwrite.saveXMP',
    'face_tag_write_save_xmp',
    array(
      'image_id' => array('default' => null, 'type' => WS_TYPE_INT),
      'faces' => array('default' => null, 'type' => WS_TYPE_NOTNULL),
    ),
    'Sauvegarde les données XMP des visages (face_tag_write)',
    null,
    array('admin_status' => ACCESS_ADMINISTRATOR)
  );

  $service->addMethod(
    'facetagwrite.restoreOriginal',
    'face_tag_write_restore_original',
    array(
      'image_id' => array('default' => null, 'type' => WS_TYPE_INT),
    ),
    'Restaure le fichier .original (supprime tous les tags)',
    null,
    array('admin_status' => ACCESS_ADMINISTRATOR)
  );
}

// ==================== WEB SERVICE POUR LIRE LES XMP ====================
function face_tag_write_get_xmp($params, &$service)
{
  $old_error_reporting = error_reporting(E_ERROR | E_PARSE);
  $old_display_errors = ini_get('display_errors');
  ini_set('display_errors', '0');

  error_log('=== GET XMP START ===');
  error_log('288 - Image ID: ' . $params['image_id']);
  
  if (empty($params['image_id']))
  {
    error_log('ERROR: Missing image_id');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  $query = '
  SELECT path
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);

  error_log('STEP1: Query executed');
  
  if (!$row)
  {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'Image not found');
  }
  
  // ========== MÉTHODE SIMPLE : LIRE VIA URL ==========
  $url_base = get_absolute_root_url();
  $url_picture = $row['path'];
  $url_picture2 = substr($url_picture, 2); // enlève ./
  //$url_original = $url_base . $url_picture2;
// ✅ ENCODER chaque segment du chemin pour gérer les espaces et caractères spéciaux
$path_parts = explode('/', $url_picture2);
$encoded_parts = array_map('rawurlencode', $path_parts);
$url_original = $url_base . implode('/', $encoded_parts) . '?t=' . time();

  
  error_log('=== GET XMP ===');
  error_log('327 - URL originale: ' . $url_original);
  
// Télécharger dans un fichier temporaire
$temp_dir = '/volume1/web/photodev/_data/tmp';
if (!is_dir($temp_dir)) {
  mkdir($temp_dir, 0755, true);
}
$temp_file = $temp_dir . '/facetag_read_' . uniqid() . '.jpg';
  
  $image_content = @file_get_contents($url_original);
  if ($image_content === false) {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot download image from URL');
  }
  
  @file_put_contents($temp_file, $image_content);
  error_log('STEP3 : Temp file size image téléchargée: ' . filesize($temp_file) . ' octets');

  if (!extension_loaded('imagick'))
  {
    error_log('ERROR: Imagick not loaded'); 
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return array(
      'stat' => 'ok',
      'result' => array(
        'xmp' => array('_raw_xmp' => '', 'error' => 'Imagick not loaded'),
        'orientation' => 1
      )
    );
  }
  
  if (function_exists('facetag_extract_xmp')) {
    error_log('STEP4: Using facetag_extract_xmp');
    $xmp_data = facetag_extract_xmp($temp_file);
  } else {
    error_log('STEP4: Using face_tag_write_extract_xmp'); 
    $xmp_data = face_tag_write_extract_xmp($temp_file);
  }
  
  // Récupérer l'orientation EXIF
  $orientation = 1;
  if (function_exists('exif_read_data')) {
    error_log('STEP5: Reading EXIF');
    $exif = @exif_read_data($temp_file);
    if ($exif && isset($exif['Orientation'])) {
      $orientation = $exif['Orientation'];
      error_log('STEP5: Orientation = ' . $orientation); 
    }else{
      error_log('STEP5: No orientation found, using 1'); 
    }
  }else{
    error_log('ERROR: exif_read_data not available'); 
  }
  
  // Nettoyer
  @unlink($temp_file);
  
  error_reporting($old_error_reporting);
  ini_set('display_errors', $old_display_errors);
  
  error_log('SUCCESS: Returning data');

  return array(
    'stat' => 'ok',
    'result' => array(
      'xmp' => $xmp_data,
      'image_id' => $params['image_id'],
      'orientation' => $orientation
    )
  );
}

// ==================== FONCTION D'EXTRACTION XMP ====================
function face_tag_write_extract_xmp($image_path)
{
  if (!extension_loaded('imagick'))
  {
    return array('error' => 'Imagick extension not loaded');
  }
  
  try
  {
    $imagick = new Imagick($image_path);
    
    $properties = $imagick->getImageProperties();
    
    $xmp_data = array();
    
    foreach ($properties as $key => $value)
    {
      if (strpos($key, 'xmp:') === 0 || 
          strpos($key, 'dc:') === 0 ||
          strpos($key, 'Iptc4xmpCore:') === 0 ||
          strpos($key, 'mwg-rs:') === 0 ||
          strpos($key, 'MP:') === 0)
      {
        $xmp_data[$key] = $value;
      }
    }
    
    $xmp_profile = $imagick->getImageProfile('xmp');
    if ($xmp_profile)
    {
      $xmp_data['_raw_xmp'] = $xmp_profile;
    }
    
    $imagick->clear();
    $imagick->destroy();
    
    return $xmp_data;
  }
  catch (Exception $e)
  {
    return array('error' => $e->getMessage());
  }
}

// ==================== WEB SERVICE POUR SAUVEGARDER ====================
function face_tag_write_save_xmp($params, &$service)
{
  $old_error_reporting = error_reporting(E_ERROR | E_PARSE);
  $old_display_errors = ini_get('display_errors');
  ini_set('display_errors', '0');
  
  error_log('=== SAVE XMP REQUEST ===');
  
  if (empty($params['image_id']))
  {
    error_log('Missing image_id');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  if (empty($params['faces']))
  {
    error_log('âŒ Missing faces data');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing faces data');
  }
  
  $faces_json = $params['faces'];
  
  if (strpos($faces_json, '\\"') !== false) {
    $faces_json = stripslashes($faces_json);
  }
  
  $faces = json_decode($faces_json, true);
  
  if (!is_array($faces))
  {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid faces data');
  }
  
  // Permettre tableau vide pour supprimer tous les tags
  if (count($faces) === 0) {
    error_log('-> Suppression de tous les visages (tableau vide)');
  }
  
  error_log('-> ' . count($faces) . ' visages à  enregistrer');
  
  $query = '
  SELECT path
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row)
  {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'Image not found');
  }
  
  // Construire l'URL pour télécharger
  $url_base = get_absolute_root_url();
  $url_picture = $row['path'];
  $url_picture2 = substr($url_picture, 2); // enlève ./
  //$url_original = $url_base . $url_picture2;

  // ✅ ENCODER chaque segment du chemin
$path_parts = explode('/', $url_picture2);
$encoded_parts = array_map('rawurlencode', $path_parts);
$url_original = $url_base . implode('/', $encoded_parts) . '?t=' . time();
  
  error_log('URL: ' . $url_original);
  
// Télécharger dans un fichier temporaire
$temp_dir = '/volume1/web/photodev/_data/tmp';
if (!is_dir($temp_dir)) {
  mkdir($temp_dir, 0755, true);
}
$temp_file = $temp_dir . '/facetag_write_' . uniqid() . '.jpg';
  
  $image_content = @file_get_contents($url_original);
  if ($image_content === false) {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot download image from URL');
  }
  
  @file_put_contents($temp_file, $image_content);
  error_log('Image téléchargée: ' . filesize($temp_file) . ' octets');
  
// Construire le chemin local et le résoudre (gère les liens symboliques)
  $real_local_path = face_tag_write_resolve_path($row['path']);
  
  if ($real_local_path === false) {
    error_log('❌ Impossible de résoudre le chemin du fichier');
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot resolve file path');
  }
  
  error_log('Chemin local résolu: ' . $real_local_path);
  
  // Vérifier que le fichier existe et est accessible
  if (!file_exists($real_local_path)) {
    error_log('❌ Le fichier n\'existe pas: ' . $real_local_path);
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'File not found at resolved path');
  }
  
  // Vérifier les permissions en lecture
  if (!is_readable($real_local_path)) {
    error_log('❌ Le fichier n\'est pas lisible');
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(403, 'File is not readable');
  }
  
  // Créer backup
  // Créer un fichier temporaire pour lire les métadonnées originales
  $temp_for_reading = $temp_dir . '/facetag_read_' . uniqid() . '.jpg';
  @file_put_contents($temp_for_reading, $image_content);
  
  error_log('Fichier pour lecture métadonnées: ' . $temp_for_reading);
  
  // Créer backup
  $backup_path = $real_local_path . '.original';
  $backup_created = false;
  
  if (!file_exists($backup_path)) {
    if (@copy($real_local_path, $backup_path)) {
      @chmod($backup_path, 0444);
      $backup_created = true;
      error_log('Backup créé');
    }
  } else {
    error_log('Backup existe déjà');
  }
  
  // IMPORTANT : Toujours rendre le fichier writable avant modification
  // (car il peut avoir été mis en 0644 lors d'une précédente sauvegarde)
  @chmod($real_local_path, 0666);
  error_log('Permissions du fichier mises à 0666 pour permettre l\'écriture');
  
// ✅ AJOUTER CE LOG
$perms = fileperms($real_local_path);
error_log('Permissions actuelles: ' . decoct($perms & 0777));


  
  $writer = new FaceTagMetadataWriter();
  $merger = new FaceTagMetadataMerger();
  
  // Fusionner métadonnées (on passe le fichier de lecture pour lire les métadonnées existantes)
  $merged_data = $merger->merge($temp_for_reading, $faces);
  error_log('Métadonnées fusionnées');
  
  // Nettoyer le fichier de lecture
  @unlink($temp_for_reading);
  
  // Écrire métadonnées sur le fichier temporaire
  try {
    $result = $writer->writeMetadata($temp_file, $faces, $merged_data);
  } catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Exception: ' . $e->getMessage());
  }
  
  if ($result['success'])
  {
    error_log(' XMP écrit sur fichier temporaire');
    
    // Essayer d'écrire sur le chemin résolu
    error_log('>> main.inc.php');
    error_log('Tentative copie vers: ' . $real_local_path);
    error_log('Fichier existe: ' . (file_exists($real_local_path) ? 'OUI' : 'NON'));
    error_log('Writable: ' . (is_writable($real_local_path) ? 'OUI' : 'NON'));
    
    if (@copy($temp_file, $real_local_path)) {
      error_log('Fichier copié vers: ' . $real_local_path);
      @chmod($real_local_path, 0644);
    } else {
      error_log('Échec copie - Dernière tentative: écriture directe');
      
      // Dernière tentative : lire le temp et écrire directement
      $content = file_get_contents($temp_file);
      if (@file_put_contents($real_local_path, $content) !== false) {
        error_log('Écriture directe réussie');
        @chmod($real_local_path, 0644);
      } else {
        error_log('Toutes les méthodes ont échoué');
        @unlink($temp_file);
        error_reporting($old_error_reporting);
        ini_set('display_errors', $old_display_errors);
        return new PwgError(500, 'Cannot write to file - check permissions on: ' . $real_local_path);
      }
    }
    
    // Nettoyer le fichier temporaire
    @unlink($temp_file);
    
    // Régénérer les miniatures
    try {
      face_tag_write_regenerate_derivatives($params['image_id']);
      error_log(' Miniatures régénérées');
    } catch (Exception $e) {
      error_log('Erreur miniatures: ' . $e->getMessage());
    }
    
    
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    
    return array(
      'stat' => 'ok',
      'message' => 'XMP saved successfully',
      'faces_count' => count($faces),
      'backup_created' => $backup_created
    );
  }
  else
  {
    error_log(' Échec écriture XMP: ' . $result['error']);
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Failed to write XMP: ' . $result['error']);
  }
}

// ==================== RÉGÉNÉRER LES MINIATURES ET SYNCHRONISER ====================
function face_tag_write_regenerate_derivatives($image_id)
{
  // ==================== RÉGÉNÉRATION DES MINIATURES ====================
  if (defined('IMAGE_DERIVATIVES_TABLE') && defined('PWG_DERIVATIVE_DIR')) {
    $query = '
    SELECT id, path
    FROM ' . IMAGES_TABLE . '
    WHERE id = ' . intval($image_id);
    
    $result = pwg_query($query);
    $image = pwg_db_fetch_assoc($result);
    
    if ($image) {
      // Supprimer les entrées de la base de données
      $query = '
      DELETE FROM ' . IMAGE_DERIVATIVES_TABLE . '
      WHERE image_id = ' . intval($image_id);
      pwg_query($query);
      
      // Supprimer les fichiers physiques
      $path_info = pathinfo($image['path']);
      
      $derivative_dirs = array(
        PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . 'square/',
        PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . 'thumb/',
        PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . 'small/',
        PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . 'medium/',
        PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . 'large/',
        PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . 'xlarge/',
        PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . 'xxlarge/',
      );
      
      foreach ($derivative_dirs as $dir) {
        if (is_dir($dir)) {
          $pattern = $dir . '*/' . $path_info['filename'] . '*';
          $files = glob($pattern);
          if ($files) {
            foreach ($files as $file) {
              @unlink($file);
            }
          }
        }
      }
      
      error_log('✓ Miniatures régénérées');
    }
  } else {
    error_log('⚠ Constantes miniatures non définies - Synchronisation uniquement');
  }
  
  // ==================== SYNCHRONISATION DES MÉTADONNÉES PIWIGO ====================
  // Charger TOUS les fichiers nécessaires
  if (!function_exists('sync_metadata')) {
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions_metadata.php');
  }
  
  if (!function_exists('tag_id_from_tag_name')) {
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
  }
  
  try {
    sync_metadata(array($image_id));
    error_log('✓ Métadonnées Piwigo synchronisées pour image ' . $image_id);
  } catch (Exception $e) {
    error_log('⚠ Erreur synchronisation des métadonnées: ' . $e->getMessage());
  }
  
 return true;
}




?>