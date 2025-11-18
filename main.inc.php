<?php
/*
Plugin Name: face_tag_write
Version: 1.0
Description: Créer et enregistrer les tags de visages dans les métadonnées XMP
Plugin URI: https://fr.piwigo.org/ext/
Author: Charles69
Author URI:
Has Settings: webmaster
*/

// ============  VERSIONS =========================================================
 // historique des versions
 /*
 version 1.0 - 17/11/2025
   - Création du plugin pour taguer les visages
   - Interface de dessin avec Fabric.js
   - Sauvegarde dans XMP avec exiftool
   - Compatible avec face_tag (lecture)
*/
//=================================================================================

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

if (basename(dirname(__FILE__)) != 'face_tag_write')
{
  add_event_handler('init', 'face_tag_write_error');
  function face_tag_write_error()
  {
    global $page;
    $page['errors'][] = 'Uninstall the plugin and rename it to "face_tag_write"';
  }
  return;
}

// Plugin constants
global $prefixeTable;
define('FACETAGWRITE_ID', basename(dirname(__FILE__)));
define('FACETAGWRITE_PATH', PHPWG_PLUGINS_PATH . FACETAGWRITE_ID . '/');
define('FACETAGWRITE_ADMIN', get_root_url() . 'admin.php?page=plugin-' . FACETAGWRITE_ID);

// Initialisation du plugin
add_event_handler('init', 'face_tag_write_init');

function face_tag_write_init()
{
  // Charger les traductions
  load_language('plugin.lang', FACETAGWRITE_PATH);
}

// ==================== CHARGER JQUERY ====================
add_event_handler('loc_begin_page_header', 'face_tag_write_load_jquery');
function face_tag_write_load_jquery()
{
  global $template;
  
  // Charger jQuery seulement s'il n'est pas déjà chargé
  $template->append('head_elements', '
  <script type="text/javascript">
    if (typeof jQuery === "undefined") {
      document.write(\'<script type="text/javascript" src="' . get_root_url() . 'themes/default/js/jquery.min.js"><\/script>\');
    }
  </script>
  ');
}

// ==================== AJOUTER LE BOUTON DANS LA PAGE PHOTO ====================
add_event_handler('loc_end_picture', 'face_tag_write_picture_toolbar');

function face_tag_write_picture_toolbar()
{
  global $template, $user, $picture;
  
  // Vérifier les droits (seulement webmaster/admin)
  if (!is_admin())
  {
    return;
  }
  
  $template->set_template_dir(FACETAGWRITE_PATH.'template/');
  
  // URL pour le mode édition
  $edit_url = get_root_url() . 'index.php?/picture/' . $picture['current']['id'] . '&facetag_edit=1';
  
  // Construire l'URL AJAX pour la sauvegarde
  $save_url = get_root_url() . 'ws.php?format=json&method=facetag.saveXMP';
  
  // Icône
  $icon = get_root_url() . 'plugins/' . FACETAGWRITE_ID . '/images/edit.png';
  
  // Assigner les variables au template
  $template->assign(array(
    'FACETAGWRITE_EDIT_URL' => $edit_url,
    'FACETAGWRITE_SAVE_URL' => $save_url,
    'FACETAGWRITE_ICON' => $icon,
    'FACETAGWRITE_PATH' => FACETAGWRITE_PATH,
  ));
  
  // Choix du template selon le thème
  if ($user['theme'] == 'bootstrapdefault' || $user['theme'] == 'bootstrap_darkroom') {
    $tpl_file = 'picture_button_bootstrap.tpl';
  } else {
    $tpl_file = 'picture_button.tpl';
  }
  
  // Assigner le template au bloc d'actions
  $template->set_filename('face_tag_write_button', $tpl_file);
  $template->concat('PLUGIN_PICTURE_ACTIONS', $template->parse('face_tag_write_button', true));
}

// ==================== MODE ÉDITION ====================
add_event_handler('loc_begin_picture', 'face_tag_write_edit_mode');

function face_tag_write_edit_mode()
{
  global $template, $picture, $page;
  
  // Vérifier si on est en mode édition
  if (!isset($_GET['facetag_edit']) || !is_admin())
  {
    return;
  }
  
  // Charger Fabric.js depuis CDN
  $template->append('head_elements', '
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
  ');
  
  // URL pour la sauvegarde
  $save_url = get_root_url() . 'ws.php?format=json&method=facetag.saveXMP';
  
  // Assigner les variables
  $template->assign(array(
    'FACETAG_EDIT_MODE' => true,
    'FACETAG_SAVE_URL' => $save_url,
    'FACETAG_IMAGE_ID' => $picture['current']['id'],
    'FACETAGWRITE_PATH' => FACETAGWRITE_PATH,
  ));
}

// Charger les CSS et JS pour le mode édition
add_event_handler('loc_end_page_tail', 'face_tag_write_load_assets');

function face_tag_write_load_assets()
{
  global $template;
  
  // Vérifier si on est en mode édition
  if (!isset($_GET['facetag_edit']) || !is_admin())
  {
    return;
  }
  
  // Charger nos fichiers CSS et JS
  $template->append('footer_elements', '
  <link rel="stylesheet" href="' . FACETAGWRITE_PATH . 'template/draw_faces.css">
  <script src="' . FACETAGWRITE_PATH . 'template/draw_faces.js"></script>
  ');
}

// Ajouter l'interface d'édition dans la page
add_event_handler('loc_end_picture', 'face_tag_write_display_editor', EVENT_HANDLER_PRIORITY_NEUTRAL + 10);

function face_tag_write_display_editor()
{
  global $template, $picture;
  
  // Vérifier si on est en mode édition
  if (!isset($_GET['facetag_edit']) || !is_admin())
  {
    return;
  }
  
  // Ajouter l'interface d'édition APRÈS l'image
  $template->set_template_dir(FACETAGWRITE_PATH.'template/');
  $template->set_filename('face_tag_write_editor', 'draw_faces.tpl');
  $template->concat('PLUGIN_PICTURE_AFTER', $template->parse('face_tag_write_editor', true));
}

// ==================== WEB SERVICES ====================
add_event_handler('ws_add_methods', 'face_tag_write_ws_add_methods');

function face_tag_write_ws_add_methods($arr)
{
  $service = &$arr[0];
  
  $service->addMethod(
    'facetag.saveXMP',
    'face_tag_write_ws_save_xmp',
    array(
      'image_id' => array('default' => null),
      'faces' => array('default' => null),
    ),
    'Sauvegarde les données XMP des visages',
    null,
    array('admin_status' => ACCESS_ADMINISTRATOR)
  );
}

function face_tag_write_ws_save_xmp($params, &$service)
{
  if (empty($params['image_id']))
  {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  if (empty($params['faces']))
  {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing faces data');
  }
  
  // Récupérer le chemin de l'image
  $query = '
SELECT path
FROM ' . IMAGES_TABLE . '
WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row)
  {
    return new PwgError(404, 'Image not found');
  }
  
  $image_path = PHPWG_ROOT_PATH . $row['path'];
  
  if (!file_exists($image_path))
  {
    return new PwgError(404, 'Image file not found: ' . $image_path);
  }
  
  // Décoder les données JSON
  $faces = json_decode($params['faces'], true);
  
  if (!is_array($faces))
  {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid faces data format');
  }
  
  // Écrire les XMP avec exiftool
  $result = face_tag_write_xmp_with_exiftool($image_path, $faces);
  
  if ($result['success'])
  {
    return array(
      'stat' => 'ok',
      'message' => 'XMP data saved successfully',
      'faces_count' => count($faces)
    );
  }
  else
  {
    return new PwgError(500, 'Failed to write XMP: ' . $result['error']);
  }
}

// ==================== FONCTION D'ÉCRITURE XMP ====================
function face_tag_write_xmp_with_exiftool($image_path, $faces)
{
  // Vérifier que exiftool est disponible
  exec('exiftool -ver 2>&1', $output, $return_code);
  
  if ($return_code !== 0)
  {
    return array(
      'success' => false,
      'error' => 'exiftool not available. Please install exiftool on your server.'
    );
  }
  
  // Préparer les arguments exiftool
  $args = array();
  
  // Supprimer toutes les anciennes régions
  $args[] = '-RegionInfo=';
  
  // Ajouter chaque visage
  foreach ($faces as $index => $face)
  {
    $name = $face['name'];
    $x = $face['x'];
    $y = $face['y'];
    $w = $face['w'];
    $h = $face['h'];
    
    // Format mwg-rs (Metadata Working Group - Regions Schema)
    // Compatible avec digiKam, Lightroom, Windows Photos, etc.
    
    // Ajouter une région
    $args[] = '-RegionName=' . escapeshellarg($name);
    $args[] = '-RegionType=Face';
    $args[] = '-RegionAreaX=' . $x;
    $args[] = '-RegionAreaY=' . $y;
    $args[] = '-RegionAreaW=' . $w;
    $args[] = '-RegionAreaH=' . $h;
    $args[] = '-RegionAreaUnit=normalized';
  }
  
  // Options de préservation
  $args[] = '-overwrite_original'; // Ne pas créer de backup
  $args[] = '-P'; // Préserver la date de modification
  
  // Ajouter le chemin de l'image
  $args[] = escapeshellarg($image_path);
  
  // Construire la commande complète
  $cmd = 'exiftool ' . implode(' ', $args) . ' 2>&1';
  
  // Exécuter
  exec($cmd, $output, $return_code);
  
  if ($return_code === 0)
  {
    return array(
      'success' => true,
      'output' => implode("\n", $output),
      'command' => $cmd
    );
  }
  else
  {
    return array(
      'success' => false,
      'error' => implode("\n", $output),
      'command' => $cmd
    );
  }
}

?>