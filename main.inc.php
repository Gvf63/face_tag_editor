<?php
/*
Plugin Name: face_tag_editor
Version: 1.9C
Description: Créer et enregistrer les tags de visages dans les métadonnées XMP et description 
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=1053
Author: Charles69
Has Settings: webmaster
*/

//============= VERSIONS ============================================
/*
version 1.9C en cours  16/12/2025
    suite à régression de fonctionnalités avec External Imagick seul
    fonctionnement sans exiftool 
    corrigé orientation 270CW


version 1.9 - 10/12/2025 diffusée
    changement de méthode pour la lecture des visages en php  pur
    traduction en anglais de l'interface

version 1.8 - 08/12/2025
    ajouté description ( commment )
    modifié Plugin URI

version 1.7A - 06/12/2025 
    base PHP Imagick , External Imagick, Exiftool
    corrigé problème avec item Photoshop 
    à voir 270CW ?

version 1.6 - 04/12/2025 diffusée
    à voir : 270 CW
    External Imagick & PHP IMagick
    corrigé : saisie nom annule
    corrigé : restauration fichier
    ajouté  : modification nom  
    bug enregistrement original

version 1.5 - 01/12/2025 diffusée
    contourné le problème allow_url_fopen
    bug sur restaurer l'original <- 1.4C (pas 1.4A)

version 1.4C - 30/11/2025
    pb valider qui ne fonctionne pas -> contournement
    régénération des miniatures = non nécessaire & ne fonctionne pas
    la régénération des miniatures est automatique par piwigo

version 1.4A - 29/11/2025 diffusée
    corrigé rotation 8
    corrigé caractères accentué dans les noms
    corrigé XMP nécessaires à xnview
    nouvelle façon d'écrire les XMP

version 1.3A - 27/11/2025
    ajouté logs

version 1.3 - 27/11/2025
    corrigé chemin codé en dur !!

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



// Utilisation de la méthode face_tag V2
require_once(FACETAGWRITE_PATH . 'lib/editor_xmp_ex.php');


  // Charger les classes
require_once(FACETAGWRITE_PATH . 'lib/imagick_wrapper.php');
require_once(FACETAGWRITE_PATH . 'lib/metadata_writer.php');
require_once(FACETAGWRITE_PATH . 'lib/metadata_merger.php');
require_once(FACETAGWRITE_PATH . 'lib/file_resolver.php');
require_once(FACETAGWRITE_PATH . 'lib/restore_original.php');
require_once(FACETAGWRITE_PATH . 'img/icon_svg.php'); // image du bouton taguer



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

// ==================== CHARGER SCRIPT ====================
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

//===================== TRADUCTION DE l'EDITEUR =================

add_event_handler('loc_begin_page_header', 'face_tag_editor_load_translations');

function face_tag_editor_load_translations()
{
  global $template;
  
  load_language('plugin.lang', FACETAGWRITE_PATH);
  
  // Injecter directement les traductions en JavaScript
  $js_translations = "
<script type=\"text/javascript\">
var facetagLang = {
  'Éditeur de visages': '" . l10n('Éditeur de visages') . "',
  'Image': '" . l10n('Image') . "',
  'Instructions :': '" . l10n('Instructions :') . "',
  'Cliquez et faites glisser sur l\'image pour dessiner un rectangle autour d\'un visage. Double-cliquez sur un cadre pour renommer un visage': '" . l10n('Cliquez et faites glisser sur l\'image pour dessiner un rectangle autour d\'un visage. Double-cliquez sur un cadre pour renommer un visage') . "',
  'Visages tagués': '" . l10n('Visages tagués') . "',
  'Aucun visage tagué': '" . l10n('Aucun visage tagué') . "',
  'Tout effacer': '" . l10n('Tout effacer') . "',
  'Restaurer l\'original': '" . l10n('Restaurer l\'original') . "',
  'Restaurer le fichier .original (supprime tous les tags)': '" . l10n('Restaurer le fichier .original (supprime tous les tags)') . "',
  'Description...': '" . l10n('Description...') . "',
  'Annuler': '" . l10n('Annuler') . "',
  'Enregistrer': '" . l10n('Enregistrer') . "',
  'existant': '" . l10n('existant') . "',
  'Supprimer': '" . l10n('Supprimer') . "',
  'Nommer la personne': '" . l10n('Nommer la personne') . "',
  'Nom de la personne': '" . l10n('Nom de la personne') . "',
  'Personnes existantes :': '" . l10n('Personnes existantes :') . "',
  'Valider': '" . l10n('Valider') . "',
  'Veuillez entrer un nom': '" . l10n('Veuillez entrer un nom') . "',
  'Renommer la personne': '" . l10n('Renommer la personne') . "',
  'Ancien nom :': '" . l10n('Ancien nom :') . "',
  'Nouveau nom': '" . l10n('Nouveau nom') . "',
  'Autres personnes :': '" . l10n('Autres personnes :') . "',
  'Renommer': '" . l10n('Renommer') . "',
  
'Aucun visage à effacer': '" . l10n('Aucun visage à effacer') . "',
'✅ Fichier original restauré avec succès !': '" . l10n('✅ Fichier original restauré avec succès !') . "',
'❌ Aucun fichier .original trouvé à restaurer.\\n\\nLe fichier original n\'existe que si vous avez déjà enregistré des tags.': '" . l10n('❌ Aucun fichier .original trouvé à restaurer.\\n\\nLe fichier original n\'existe que si vous avez déjà enregistré des tags.') . "',
'❌ Accès refusé. Vous n\'avez pas les permissions nécessaires.': '" . l10n('❌ Accès refusé. Vous n\'avez pas les permissions nécessaires.') . "',
'Voulez-vous vraiment supprimer tous les tags de visages de cette image ?': '" . l10n('Voulez-vous vraiment supprimer tous les tags de visages de cette image ?') . "',
'Êtes-vous sûr de vouloir effacer tous les rectangles ?': '" . l10n('Êtes-vous sûr de vouloir effacer tous les rectangles ?') . "',
'⚠️ ATTENTION ⚠️\\n\\nCette action va :\\n• Restaurer le fichier .original \\n• Régénérer les miniatures\\n\\nÊtes-vous sûr de vouloir continuer ?': '" . l10n('⚠️ ATTENTION ⚠️\\n\\nCette action va :\\n• Restaurer le fichier .original \\n• Régénérer les miniatures\\n\\nÊtes-vous sûr de vouloir continuer ?') . "',

'✅ Visages enregistrés avec succès !': '" . l10n('✅ Visages enregistrés avec succès !') . "',
'Visages: ': '" . l10n('Visages: ') . "',
'Backup créé: Oui (.original)': '" . l10n('Backup créé: Oui (.original)') . "',
'Backup: Déjà existant': '" . l10n('Backup: Déjà existant') . "',

};
</script>
";
  
  $template->append('head_elements', $js_translations);
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

//---------------------------------------------------------------------------
function face_tag_write_add_button()
{
  global $template, $picture, $user;
  
  // Vérifier les droits d'accès
  if (!face_tag_write_check_access())
  {
    return;
  }
 
  $query = '
  SELECT path, file, comment
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . intval($picture['current']['id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row) return;
  
  $original_path = $row['path'];
  $image_url = embellish_url(get_root_url() . $original_path);
  $save_url = get_root_url() . 'ws.php?format=json&method=facetagwrite.saveXMP';
  
  // Vérifier si le fichier de backup .original existe
  $real_local_path = face_tag_write_resolve_path($row['path']);
  $has_original = file_exists($real_local_path . '.original') ? 'true' : 'false';

  // Textes traduits
  $tag_text = l10n('Taguer');
  $tag_title = l10n('Taguer les visages');

  // VERSION 1 : Thème par défaut - SANS styles inline
  $button_html = '
    <a href="#" 
       id="facetag-open-editor"
       data-image-id="' . $picture['current']['id'] . '"
       data-image-src="' . $image_url . '"
       data-save-url="' . $save_url . '"
       data-has-original="' . $has_original . '"
       data-description="' . htmlspecialchars($row['comment'] ?? '', ENT_QUOTES, 'UTF-8') . '"
       class="pwg-state-default pwg-button" 
       title="' . $tag_title . '" 
       rel="nofollow">
      <span class="pwg-icon">' . FACETAGWRITE_ICON . '</span>
      <span class="pwg-button-text">' . $tag_text . '</span>
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
         data-description="' . htmlspecialchars($row['comment'] ?? '', ENT_QUOTES, 'UTF-8') . '"
         class="btn btn-primary" 
         title="' . $tag_title . '" 
         rel="nofollow">
        ' . FACETAGWRITE_ICON . ' ' . $tag_text . '
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
  'facetagwrite.clearLog',
  'face_tag_write_clear_log',
  array(),
  'Clear the debug log file',
  null,
  array('admin_only' => true)
  );

  //----------------------------
  $service->addMethod(
  'facetagwrite.getFaces',
  'facetagwrite_ws_get_faces',
  array(
    'image_id' => array('default' => null),
  ),
  'Récupère les faces déjà parsées d\'une image',
  null,
  array('admin_status' => ACCESS_GUEST)
);

  
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
  
  header('Content-Type: application/json; charset=UTF-8');
  
  $old_error_reporting = error_reporting(E_ERROR | E_PARSE);
  $old_display_errors = ini_get('display_errors');
  ini_set('display_errors', '0');

  error_log('**** DEBUT LOG ****');
  
  error_log('1 (333) Image ID -> ' . $params['image_id']);
  
  if (empty($params['image_id']))
  {
    error_log('ERROR (337) : Missing image_id');
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

  //error_log('STEP1: Query executed');
  
  if (!$row)
  {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'Image not found');
  }
  
  
  $real_local_path = face_tag_write_resolve_path($row['path']);

  if ($real_local_path === false) {
    error_log('❌ Impossible de résoudre le chemin du fichier');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot resolve file path');
  }

  if (!file_exists($real_local_path)) {
    error_log('❌ Le fichier n\'existe pas: ' . $real_local_path);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'File not found at resolved path');
  }

  error_log('=== GET XMP ===');
  error_log('2 (378) Fichier local -> ' . $real_local_path);

  // Créer un fichier temporaire
  $temp_dir = PHPWG_ROOT_PATH . '_data/tmp';
  if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
    if (!is_dir($temp_dir)) {
      error_log("Créer un repertoire tmp, ./_data/tmp avec des droits en écriture");
    }
  } else {
    error_log("3 - le rep ./_data/tmp existe");
  }


  $temp_file = $temp_dir . '/facetag_write_' . uniqid() . '.jpg';

  // Lire le fichier local directement (pas de allow_url_fopen nécessaire)
  $image_content = @file_get_contents($real_local_path);
  if ($image_content === false) {
    error_log('ERROR: file_get_contents du fichier local FAILED');
    $last_error = error_get_last();
    if ($last_error) {
      error_log('ERROR: PHP error = ' . $last_error['message']);
    }
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot read image file');
  }

  error_log('STEP2B: file_get_contents SUCCESS, size = ' . strlen($image_content) . ' bytes');

  @file_put_contents($temp_file, $image_content);
  //error_log('STEP3: Temp file size = ' . filesize($temp_file) . ' octets');
  error_log('Temp file size = ' . filesize($temp_file) . ' octets');

  // Try to extract XMP using wrapper (with fallback to external ImageMagick)
  if (function_exists('facetag_extract_xmp')) {
    //error_log('STEP4: Using facetag_extract_xmp');
    $xmp_data = facetag_extract_xmp($temp_file);
  } else {
    error_log('STEP4: Using face_tag_write_extract_xmp');
    $xmp_data = face_tag_write_extract_xmp($temp_file);
  }

  // Check if extraction had an error
  if (isset($xmp_data['error'])) {
    error_log('ERROR: XMP extraction failed - ' . $xmp_data['error']);
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return array(
      'stat' => 'ok',
      'result' => array(
        'xmp' => array('_raw_xmp' => '', 'error' => $xmp_data['error']),
        'orientation' => 1
      )
    );
  }
  
  // Récupérer l'orientation EXIF
  $orientation = 1;
  if (function_exists('exif_read_data')) {
    //error_log('STEP5: Reading EXIF');
    $exif = @exif_read_data($temp_file);
    if ($exif && isset($exif['Orientation'])) {
      $orientation = $exif['Orientation'];
      //error_log('STEP5: Orientation = ' . $orientation); 
    }else{
      //error_log('STEP5: No orientation found, using 1'); 
    }
  }else{
    error_log('ERROR: exif_read_data not available'); 
  }
  
  // Nettoyer
  @unlink($temp_file);
  
  error_reporting($old_error_reporting);
  ini_set('display_errors', $old_display_errors);
  
  error_log('SUCCESS: Returning data');

  // === AJOUT : Parser les faces côté serveur ===
  require_once(FACETAGWRITE_PATH . 'lib/metadata_reader.php');
  $reader = new FaceTagMetadataReader();
  $metadata = $reader->readAll($real_local_path);
  
  $faces = isset($metadata['xmp']['faces']) ? $metadata['xmp']['faces'] : array();
  
  error_log('Faces parsées côté serveur: ' . count($faces));
  
  // Ajouter les faces au XMP
  if (!isset($xmp_data['faces'])) {
    $xmp_data['faces'] = $faces;
  }

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
  try
  {
    // Use wrapper for fallback support
    $imagick = ImagickWrapper::load($image_path);

    if ($imagick->hasError())
    {
      return array('error' => $imagick->getError());
    }

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
  
  header('Content-Type: application/json; charset=UTF-8');
  
  $old_error_reporting = error_reporting(E_ERROR | E_PARSE);
  $old_display_errors = ini_get('display_errors');
  ini_set('display_errors', '0');
  
  error_log('=== SAVE XMP REQUEST ===');

// Nettoyer les anciens fichiers temporaires (>1h)
$temp_dir = PHPWG_ROOT_PATH . '_data/tmp';
if (is_dir($temp_dir)) {
  $files = glob($temp_dir . '/facetag_*');
  $now = time();
  foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > 3600) {
      @unlink($file);
    }
  }
}




  
  if (empty($params['image_id']))
  {
    error_log('Missing image_id');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  if (empty($params['faces']))
  {
    error_log('ERREUR Missing faces data');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing faces data');
  }
  
 // Récupérer la description - forcer null si vide    ---------------------------------------------------------        V1.9A

$description = isset($params['description']) ? stripslashes(trim($params['description'])) : null;
if ($description === '') {
  $description = null;
  error_log('Description vide - sera supprimée');
} else if ($description !== null) {
  error_log('Description reçue: ' . strlen($description) . ' caractères');
} else {
  error_log('Description non fournie (null)');
}
  
 $faces_json = $params['faces'];

 //error_log('FACES JSON reçu (raw bytes): ' . bin2hex(substr($faces_json, 0, 100)));

  
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
  
  // Construire le chemin local et le résoudre (gère les liens symboliques)
  $real_local_path = face_tag_write_resolve_path($row['path']);

  error_log('Fichier local: ' . $real_local_path);

  if ($real_local_path === false) {
    error_log('❌ Impossible de résoudre le chemin du fichier');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot resolve file path');
  }

  error_log('Chemin local résolu: ' . $real_local_path);

  // Vérifier que le fichier existe et est accessible
  if (!file_exists($real_local_path)) {
    error_log('❌ Le fichier n\'existe pas: ' . $real_local_path);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'File not found at resolved path');
  }

  // Vérifier les permissions en lecture
  if (!is_readable($real_local_path)) {
    error_log('❌ Le fichier n\'est pas lisible');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(403, 'File is not readable');
  }

  // Créer un répertoire temporaire si nécessaire
  $temp_dir = PHPWG_ROOT_PATH . '_data/tmp';
  if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
    if (!is_dir($temp_dir)) {
      error_log('⚠️ Impossible de créer le répertoire temporaire');
      error_reporting($old_error_reporting);
      ini_set('display_errors', $old_display_errors);
      return new PwgError(500, 'Cannot create temporary directory');
    }
  }

  // Créer un fichier temporaire pour lire les métadonnées originales
  $temp_file = $temp_dir . '/facetag_write_' . uniqid() . '.jpg';
  $temp_for_reading = $temp_dir . '/facetag_read_' . uniqid() . '.jpg';

  // Copier le fichier original vers le fichier temporaire (pour écriture)
  if (!@copy($real_local_path, $temp_file)) {
    error_log('❌ Impossible de copier le fichier vers le fichier temporaire');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot copy file to temporary location');
  }

  // Copier aussi pour lecture des métadonnées
  if (!@copy($real_local_path, $temp_for_reading)) {
    error_log('❌ Impossible de copier le fichier pour la lecture des métadonnées');
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot copy file for metadata reading');
  }
  
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
//$perms = fileperms($real_local_path);
//error_log('Permissions actuelles: ' . decoct($perms & 0777));


  
  $writer = new FaceTagMetadataWriterSimple();
  $merger = new FaceTagMetadataMerger();
  
  // Fusionner métadonnées (on passe le fichier de lecture pour lire les métadonnées existantes)
  $merged_data = $merger->merge($temp_for_reading, $faces);
  error_log('Métadonnées fusionnées');
  
  // Nettoyer le fichier de lecture
  @unlink($temp_for_reading);
  
  // Écrire métadonnées sur le fichier temporaire
  try {
    $result = $writer->writeMetadata($temp_file, $faces, $merged_data, $description);
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
      // Forcer le vidage du cache PHP et système
      clearstatcache(true, $real_local_path);
      // Mettre à jour la date de modification pour forcer le rechargement
      @touch($real_local_path);
      error_log('Cache vidé et date de modification mise à jour');
    } else {
      error_log('Échec copie - Dernière tentative: écriture directe');

      // Dernière tentative : lire le temp et écrire directement
      $content = file_get_contents($temp_file);
      if (@file_put_contents($real_local_path, $content) !== false) {
        error_log('Écriture directe réussie');
        @chmod($real_local_path, 0644);
        // Forcer le vidage du cache PHP et système
        clearstatcache(true, $real_local_path);
        // Mettre à jour la date de modification pour forcer le rechargement
        @touch($real_local_path);
        error_log('Cache vidé et date de modification mise à jour');
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
      face_tag_write_regenerate_metadata($params['image_id'], count($faces) > 0, strlen($description) > 0);
      error_log(' Métadata synchronisées');
    } catch (Exception $e) {
      error_log('Erreur synchro metadonnées: ' . $e->getMessage());
    }
    
    
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    
    $response = array(
      'stat' => 'ok',
      'message' => 'XMP saved successfully',
      'faces_count' => count($faces),
      'backup_created' => $backup_created
    );
    
    // Propager le warning si exiftool manque
    if (isset($result['warning'])) {
      $response['warning'] = $result['warning'];
      error_log('⚠ Warning propagé au client: ' . $result['warning']);
    }
    
    return $response;

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

// ==================== SYNCHRONISATION DES METADONNEES ====================
function face_tag_write_regenerate_metadata($image_id, $has_faces = true, $has_description = true)
{
  
  // Charge les fichiers nécessaires
  if (!function_exists('sync_metadata')) {
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions_metadata.php');
  }
  
  if (!function_exists('tag_id_from_tag_name')) {
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
  }

  // Si plus de visages, supprimer tous les tags de l'image
  if (!$has_faces) {
    error_log('Suppression des tags Piwigo (plus de visages)');
    $query = 'DELETE FROM ' . IMAGE_TAG_TABLE . ' WHERE image_id = ' . intval($image_id);
    pwg_query($query);
  }

  // Si plus de description, la supprimer
  if (!$has_description) {
    error_log('Suppression de la description Piwigo');
    $query = 'UPDATE ' . IMAGES_TABLE . ' SET comment = NULL WHERE id = ' . intval($image_id);
    pwg_query($query);
  }

    sync_metadata(array($image_id));
    invalidate_user_cache();
    error_log('✓ Métadonnées Piwigo synchronisées pour image ' . $image_id);
  
 return true;
}

//------------------------------------------------------------------------------
// Nouvelle fonction Web Service
function facetagwrite_ws_get_faces($params, &$service)
{
  if (empty($params['image_id']))
  {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  // Récupérer le chemin de l'image
  $query = '
SELECT path
FROM ' . IMAGES_TABLE . '
WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row) {
    return new PwgError(404, 'Image not found');
  }
  
  $image_path = PHPWG_ROOT_PATH . $row['path'];
  $image_path = face_tag_write_resolve_path($row['path']);
  
  if (!file_exists($image_path)) {
    return new PwgError(404, 'Image file not found');
  }
  
  // Utiliser metadata_reader pour lire les faces
  require_once(FACETAGWRITE_PATH . 'lib/metadata_reader.php');
  $reader = new FaceTagMetadataReader();
  $metadata = $reader->readAll($image_path);
  
  error_log('=== getFaces Web Service ===');
  error_log('Faces trouvées: ' . (isset($metadata['xmp']['faces']) ? count($metadata['xmp']['faces']) : 0));
  
  // Retourner les faces au format JSON
  return array(
    'image_id' => $params['image_id'],
    'orientation' => isset($metadata['orientation']) ? $metadata['orientation'] : 1,
    'faces' => isset($metadata['xmp']['faces']) ? $metadata['xmp']['faces'] : array()
  );
}


//-------------------------------------------------------------------------------
/// Effacement du fichier de log quand on clique sur taguer

function face_tag_write_clear_log($params, &$service)
{
  // Vérifier les droits d'accès
  if (!face_tag_write_check_access())
  {
    return new PwgError(403, 'Access denied');
  }
  
  $log_file = FACETAGWRITE_PATH . 'face_tag_editor_debug.log';
  if (file_exists($log_file)) {
    @unlink($log_file);
  }
  
  error_log('=== NOUVEAU TRAITEMENT  ===');
  
  return array('stat' => 'ok', 'message' => 'Log cleared');
}


?>