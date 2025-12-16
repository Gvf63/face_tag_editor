<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * NOUVELLE APPROCHE SIMPLE : Templates XMP
 * Au lieu de manipuler un DOM complexe, on utilise des templates texte
 * C'est plus simple, plus fiable et le résultat est prévisible !
 */
class FaceTagMetadataWriterSimple
{
  private $config;
  private $template_dir;
  
  public function __construct()
  {
    $this->template_dir = FACETAGWRITE_PATH . 'template/';
    
    $config_file = FACETAGWRITE_PATH . 'config/metadata_fields.json';
    if (file_exists($config_file)) {
      $this->config = json_decode(file_get_contents($config_file), true);
    } else {
      $this->config = array(
        'write_fields' => array(
          'iptc_keywords' => true,
          'xmp_subject' => true,
          'xmp_hierarchical' => true,
          'xmp_tagslist' => true,
          'xmp_catalogsets' => true
        ),
        'category_format' => 'Personnes|{name}',
        'tags_format' => 'Personnes/{name}'
      );
    }
  }
  
  /**
   * Écrire les métadonnées - APPROCHE SIMPLE
   */
  public function writeMetadata($image_path, $faces, $merged_data = null, $description = null)
  {
    try {
      error_log('=== WRITER SIMPLE : Début ===');

      // Use wrapper for fallback support
      $imagick = ImagickWrapper::load($image_path);

      if ($imagick->hasError()) {
        return array('success' => false, 'error' => $imagick->getError());
      }
      
      // Récupérer dimensions
      $image_width = $imagick->getImageWidth();
      $image_height = $imagick->getImageHeight();
      
      error_log('Dimensions : ' . $image_width . 'x' . $image_height);
      
      // Extraire noms des personnes
      $person_names = $this->extractPersonNames($faces);
      
      // Préparer toutes les données
      if ($merged_data) {
        $all_subjects = $merged_data['all_subjects'];
        $all_hierarchical = $merged_data['all_hierarchical'];
        $all_tagslist = $merged_data['all_tagslist'];
        $all_catalogsets = $merged_data['all_catalogsets'];
      } else {
        $all_subjects = $person_names;
        $all_hierarchical = array_map(function($n) { return 'Personnes|' . $n; }, $person_names);
        $all_tagslist = array_map(function($n) { return 'Personnes/' . $n; }, $person_names);
        $all_catalogsets = array_map(function($n) { return 'Personnes|' . $n; }, $person_names);
      }
      
      // Générer le XMP à partir du template
      $xmp = $this->buildXmpFromTemplate($faces, $image_width, $image_height, $all_subjects, $all_hierarchical, $all_tagslist, $all_catalogsets);
      
      // Injecter le XMP
      $imagick->setImageProfile('xmp', $xmp);
      
      // Écrire IPTC Keywords
      if ($this->config['write_fields']['iptc_keywords'] && count($all_subjects) > 0) {
        $this->writeIptcProfile($imagick, $all_subjects);
      }
      // ==================================================

      // Écrire la description IPTC si fournie
      //if ($description && strlen($description) > 0) {
      //  $this->writeIptcComment($imagick, $description);
      //  error_log('Description IPTC écrite: ' . strlen($description) . ' caractères');
      //}
      
      // Sauvegarder (seulement si on n'a pas déjà sauvegardé via exiftool)
      $imagick->writeImage($image_path);
      
      error_log('=== WRITER SIMPLE : Succès ===');
      
      return array('success' => true);
      
    } catch (Exception $e) {
      error_log('ERREUR : ' . $e->getMessage());
      return array('success' => false, 'error' => $e->getMessage());
    }
  }
  
  /**
   * Construire le XMP à partir des templates
   */
  private function buildXmpFromTemplate($faces, $width, $height, $subjects, $hierarchical, $tagslist, $catalogsets)
  {
    // Charger le template principal
    error_log('📁 Template dir: ' . $this->template_dir);
    error_log('📄 xmp_template.xml exists: ' . (file_exists($this->template_dir . 'xmp_template.xml') ? 'YES' : 'NO'));
    
    $template = @file_get_contents($this->template_dir . 'xmp_template.xml');
    if ($template === false) {
      error_log('❌ ERREUR CRITIQUE: Impossible de charger xmp_template.xml');
      error_log('Chemin complet: ' . realpath($this->template_dir));
      throw new Exception('Template xmp_template.xml not found in ' . $this->template_dir);
    }
    error_log('✅ Template principal chargé: ' . strlen($template) . ' bytes');
    
    // Template pour une face MPReg
    $mpreg_template = @file_get_contents($this->template_dir . 'face_mpreg_template.xml');
    if ($mpreg_template === false) {
      error_log('❌ ERREUR: face_mpreg_template.xml introuvable');
      throw new Exception('Template face_mpreg_template.xml not found');
    }
    error_log('✅ Template MPReg chargé');
    
    // Template pour une face MWG-RS
    $mwgrs_template = @file_get_contents($this->template_dir . 'face_mwgrs_template.xml');
    if ($mwgrs_template === false) {
      error_log('❌ ERREUR: face_mwgrs_template.xml introuvable');
      throw new Exception('Template face_mwgrs_template.xml not found');
    }
    error_log('✅ Template MWG-RS chargé');
    
    // Générer les faces MPReg
    $mpreg_faces = '';
    foreach ($faces as $face) {
      // MPReg utilise coin supérieur gauche + dimensions
      $topLeftX = $face['x'] - ($face['w'] / 2);
      $topLeftY = $face['y'] - ($face['h'] / 2);
      
      $mpreg_faces .= str_replace(
        array('{{NAME}}', '{{X}}', '{{Y}}', '{{W}}', '{{H}}'),
        array(
          htmlspecialchars($face['name'], ENT_XML1, 'UTF-8'),
          number_format($topLeftX, 6, '.', ''),
          number_format($topLeftY, 6, '.', ''),
          number_format($face['w'], 6, '.', ''),
          number_format($face['h'], 6, '.', '')
        ),
        $mpreg_template
      );
    }
    
    // Générer les faces MWG-RS
    $mwgrs_faces = '';
    foreach ($faces as $face) {
      $mwgrs_faces .= str_replace(
        array('{{NAME}}', '{{X}}', '{{Y}}', '{{W}}', '{{H}}'),
        array(
          htmlspecialchars($face['name'], ENT_XML1, 'UTF-8'),
          number_format($face['x'], 6, '.', ''),
          number_format($face['y'], 6, '.', ''),
          number_format($face['w'], 6, '.', ''),
          number_format($face['h'], 6, '.', '')
        ),
        $mwgrs_template
      );
    }
    
    // Générer les subjects/keywords
    $dc_subjects = '';
    foreach ($subjects as $subj) {
      $dc_subjects .= '    <rdf:li>' . htmlspecialchars($subj, ENT_XML1, 'UTF-8') . '</rdf:li>' . "\n";
    }
    
    $lr_hierarchical = '';
    foreach ($hierarchical as $hier) {
      $lr_hierarchical .= '    <rdf:li>' . htmlspecialchars($hier, ENT_XML1, 'UTF-8') . '</rdf:li>' . "\n";
    }
    
    $digikam_tags = '';
    foreach ($tagslist as $tag) {
      $digikam_tags .= '    <rdf:li>' . htmlspecialchars($tag, ENT_XML1, 'UTF-8') . '</rdf:li>' . "\n";
    }
    
    $digikam_catalog = '';
    foreach ($catalogsets as $cat) {
      $digikam_catalog .= '    <rdf:li>' . htmlspecialchars($cat, ENT_XML1, 'UTF-8') . '</rdf:li>' . "\n";
    }
    
    // Remplacer tous les placeholders
    $xmp = str_replace(
      array(
        '{{IMAGE_WIDTH}}',
        '{{IMAGE_HEIGHT}}',
        '{{MPREG_FACES}}',
        '{{MWGRS_FACES}}',
        '{{DC_SUBJECTS}}',
        '{{LR_HIERARCHICAL}}',
        '{{DIGIKAM_TAGS}}',
        '{{DIGIKAM_CATALOG}}'
      ),
      array(
        $width,
        $height,
        $mpreg_faces,
        $mwgrs_faces,
        $dc_subjects,
        $lr_hierarchical,
        $digikam_tags,
        $digikam_catalog
      ),
      $template
    );
    
    return $xmp;
  }
  
  /**
   * Extraire les noms des personnes
   */
  private function extractPersonNames($faces)
  {
    $names = array();
    foreach ($faces as $face) {
      if (!in_array($face['name'], $names)) {
        $names[] = $face['name'];
      }
    }
    return $names;
  }
  
  /**
   * Écrire le profil IPTC
   */
  private function writeIptcProfile($imagick, $keywords)
  {
    // Récupérer profil existant
    try {
      $iptc_profile = $imagick->getImageProfile('iptc');
    } catch (Exception $e) {
      $iptc_profile = false;
    }
    
    // ========== SOLUTION : CRÉER UN PROFIL IPTC MINIMAL SI INEXISTANT ==========
    if (!$iptc_profile || strlen($iptc_profile) == 0) {
      error_log('⚠ Pas de profil IPTC existant, création d\'un profil minimal');
      
      // Créer un profil IPTC minimal vide d'abord
      $minimal_profile = '';
      
      // Envelope Record: 1:000 (version = 4)
      $minimal_profile .= chr(0x1C) . chr(1) . chr(0) . pack('n', 2) . pack('n', 4);
      
      // Envelope Record: 1:090 (UTF-8 character set)
      $utf8_marker = "\x1B%G";
      $minimal_profile .= chr(0x1C) . chr(1) . chr(90) . pack('n', strlen($utf8_marker)) . $utf8_marker;
      
      // Application Record: 2:000 (version = 4)
      $minimal_profile .= chr(0x1C) . chr(2) . chr(0) . pack('n', 2) . pack('n', 4);
      
      // Injecter ce profil minimal d'abord
      try {
        $imagick->setImageProfile('iptc', $minimal_profile);
        error_log('✓ Profil IPTC minimal créé et injecté (' . strlen($minimal_profile) . ' bytes)');
      } catch (Exception $e) {
        error_log('⚠ Erreur lors de la création du profil IPTC minimal: ' . $e->getMessage());
      }
      
      // Relire le profil pour continuer normalement
      try {
        $iptc_profile = $imagick->getImageProfile('iptc');
        if ($iptc_profile) {
          error_log('✓ Profil IPTC minimal relu: ' . strlen($iptc_profile) . ' bytes');
        }
      } catch (Exception $e) {
        $iptc_profile = $minimal_profile;
      }
    }
    // =========================================================================
    
    if ($iptc_profile) {
      $iptc_data = $this->parseIptcProfile($iptc_profile);
    } else {
      $iptc_data = array();
    }
    
    // Ajouter keywords
    $iptc_data['2#025'] = $keywords;
    
    // Reconstruire
    $new_profile = $this->buildIptcProfile($iptc_data);
    
    error_log('IPTC profile size before write: ' . strlen($new_profile) . ' bytes');
    error_log('IPTC keywords to write: ' . implode(', ', $keywords));
    
    $imagick->setImageProfile('iptc', $new_profile);
    
    error_log('IPTC Keywords écrits : ' . count($keywords));
    

  }
  
  private function parseIptcProfile($binary)
  {
    $data = array();
    $pos = 0;
    $len = strlen($binary);
    
    while ($pos < $len) {
      if (ord($binary[$pos]) != 0x1C) {
        $pos++;
        continue;
      }
      
      if ($pos + 4 >= $len) break;
      
      $record = ord($binary[$pos + 1]);
      $tag = ord($binary[$pos + 2]);
      $size = (ord($binary[$pos + 3]) << 8) | ord($binary[$pos + 4]);
      
      if ($pos + 5 + $size > $len) break;
      
      $value = substr($binary, $pos + 5, $size);
      $key = sprintf('%d#%03d', $record, $tag);
      
      if ($key == '2#025') {
        if (!isset($data[$key])) {
          $data[$key] = array();
        }
        $data[$key][] = $value;
      } else {
        $data[$key] = $value;
      }
      
      $pos += 5 + $size;
    }
    
    return $data;
  }
  
  private function buildIptcProfile($data)
  {
    error_log('=== BUILD IPTC PROFILE ===');
    error_log('Input data keys: ' . implode(', ', array_keys($data)));
    if (isset($data['2#025'])) {
      error_log('Keywords (2#025): ' . print_r($data['2#025'], true));
    }
    
    $binary = '';
    
    // Envelope Record
    if (!isset($data['1#000'])) {
      $data['1#000'] = pack('n', 4);
    }
    $val = $data['1#000'];
    $binary .= chr(0x1C) . chr(1) . chr(0) . pack('n', strlen($val)) . $val;
    
    if (!isset($data['1#090'])) {
      $data['1#090'] = "\x1B%G"; // UTF-8
    }
    $val = $data['1#090'];
    $binary .= chr(0x1C) . chr(1) . chr(90) . pack('n', strlen($val)) . $val;
    
    // Application Record
    if (!isset($data['2#000'])) {
      $data['2#000'] = pack('n', 4);
    }
    $val = $data['2#000'];
    $binary .= chr(0x1C) . chr(2) . chr(0) . pack('n', strlen($val)) . $val;
    
    // Autres tags
    foreach ($data as $key => $value) {
      if ($key === '1#000' || $key === '1#090' || $key === '2#000') {
        continue;
      }
      
      list($record, $tag) = explode('#', $key);
      $record = intval($record);
      $tag = intval($tag);
      
      $values = is_array($value) ? $value : array($value);
      
      error_log('Writing IPTC tag ' . $key . ' with ' . count($values) . ' value(s)');
      
      foreach ($values as $val) {
        $size = strlen($val);
        error_log('  - Value: "' . $val . '" (' . $size . ' bytes)');
        $binary .= chr(0x1C);
        $binary .= chr($record);
        $binary .= chr($tag);
        $binary .= pack('n', $size);
        $binary .= $val;
      }
    }
    
    error_log('Total IPTC binary size: ' . strlen($binary) . ' bytes');
    
    return $binary;
  }

  /**
   * Écrire IPTC Keywords avec exiftool - LA solution qui marche vraiment !
   */
  private function writeIptcWithExiftool($image_path, $keywords)
  {
    return;
    error_log('=== ÉCRITURE IPTC avec exiftool ===');
    
    // Chemins possibles pour exiftool (priorité Synology puis Linux standard)
    $possible_paths = array(
      FACETAGWRITE_PATH . 'exiftool_wrapper.sh',  // Wrapper local (solution de contournement)
      '/bin/exiftool',                  // Synology DSM 7.x
      '/usr/bin/exiftool',              // Standard Linux
      '/usr/local/bin/exiftool',        // Installation manuelle
      '/opt/bin/exiftool',              // Synology Community
      '/volume1/@appstore/exiftool/bin/exiftool',  // Synology Package
      'exiftool'                        // PATH système
    );
    
    $exiftool = null;
    foreach ($possible_paths as $path) {
      // Test 1 : fichier existe et exécutable (check PHP)
      if (@file_exists($path) && @is_executable($path)) {
        $exiftool = $path;
        error_log('✓ exiftool trouvé (permissions OK): ' . $exiftool);
        break;
      }
      
      // Test 2 : fichier existe mais pas exécutable selon PHP → tester quand même !
      if (@file_exists($path)) {
        error_log('⚠ Test fallback pour: ' . $path);
        $test_result = @shell_exec($path . ' -ver 2>&1');
        if (!empty($test_result) && preg_match('/^\d+\.\d+/', trim($test_result))) {
          $exiftool = $path;
          error_log('✓ exiftool trouvé (via shell_exec): ' . $exiftool . ' version ' . trim($test_result));
          break;
        } else {
          error_log('  → Échec: ' . ($test_result ? trim($test_result) : 'pas de sortie'));
        }
      }
    }
    
    // Fallback: essayer avec which
    if (!$exiftool) {
      $which_result = @shell_exec('which exiftool 2>/dev/null');
      if (!empty($which_result)) {
        $exiftool = trim($which_result);
        error_log('✓ exiftool trouvé via which: ' . $exiftool);
      }
    }
    
    if (!$exiftool) {
      error_log('❌ exiftool introuvable dans les chemins suivants:');
      foreach ($possible_paths as $path) {
        error_log('   - ' . $path . ' : ' . (file_exists($path) ? 'existe mais non exécutable' : 'n\'existe pas'));
      }
      error_log('   Installation: apt-get install libimage-exiftool-perl (Debian/Ubuntu)');
      error_log('   ou: Package Center → SynoCommunity → ExifTool (Synology)');
      return false;
    }
    error_log('IPTC Keywords à écrire: ' . implode(', ', $keywords));
    
    // Construire les arguments pour chaque keyword
    $args = array();
    $args[] = '-overwrite_original';  // Pas de fichier .original
    $args[] = '-codedcharacterset=utf8';  // UTF-8 pour les accents
    
    foreach ($keywords as $kw) {
      $args[] = '-IPTC:Keywords=' . $kw;
    }
    
    $args[] = $image_path;
    
    // Échapper tous les arguments
    $escaped_args = array_map('escapeshellarg', $args);
    $cmd = $exiftool . ' ' . implode(' ', $escaped_args) . ' 2>&1';
    
    error_log('Commande: exiftool -overwrite_original -codedcharacterset=utf8 ' . count($keywords) . ' keywords');
    
    // Exécuter
    $output = array();
    $return_var = 0;
    exec($cmd, $output, $return_var);
    
    if ($return_var !== 0) {
      error_log('❌ exiftool a échoué (code retour: ' . $return_var . ')');
      if (!empty($output)) {
        error_log('Output: ' . implode("\n", $output));
      }
      return false;
    }
    
    error_log('✓ exiftool terminé avec succès');
    if (!empty($output)) {
      foreach ($output as $line) {
        error_log('  ' . $line);
      }
    }
    
    // Vérifier avec exiftool
    $verify_cmd = $exiftool . ' -IPTC:Keywords -s3 ' . escapeshellarg($image_path) . ' 2>&1';
    $verify_output = shell_exec($verify_cmd);
    
    if ($verify_output) {
      $found_keywords = array_filter(array_map('trim', explode("\n", $verify_output)));
      if (count($found_keywords) > 0) {
        error_log('✓ IPTC Keywords vérifiés: ' . implode(', ', $found_keywords));
        return true;
      }
    }
    
    error_log('⚠ Impossible de vérifier les IPTC Keywords');
    return true;  // On considère que c'est OK si exiftool n'a pas renvoyé d'erreur
  }

/**
   * Écrire IPTC Comment (2#120)
   */
  private function writeIptcComment($imagick, $comment)
  {
    try {
      $iptc_profile = $imagick->getImageProfile('iptc');
    } catch (Exception $e) {
      $iptc_profile = false;
    }

    error_log('writeIptcComment: IPTC profile size read: ' . ($iptc_profile ? strlen($iptc_profile) : 0) . ' bytes');
    
    if (!$iptc_profile || strlen($iptc_profile) == 0) {
      $minimal_profile = '';
      $minimal_profile .= chr(0x1C) . chr(1) . chr(0) . pack('n', 2) . pack('n', 4);
      $utf8_marker = "\x1B%G";
      $minimal_profile .= chr(0x1C) . chr(1) . chr(90) . pack('n', strlen($utf8_marker)) . $utf8_marker;
      $minimal_profile .= chr(0x1C) . chr(2) . chr(0) . pack('n', 2) . pack('n', 4);
      
      try {
        $imagick->setImageProfile('iptc', $minimal_profile);
      } catch (Exception $e) {
      }
      
      try {
        $iptc_profile = $imagick->getImageProfile('iptc');
      } catch (Exception $e) {
        $iptc_profile = $minimal_profile;
      }
    }
    
    if ($iptc_profile) {
      $iptc_data = $this->parseIptcProfile($iptc_profile);
    } else {
      $iptc_data = array();
    }
    
    $iptc_data['2#120'] = $comment;
    $new_profile = $this->buildIptcProfile($iptc_data);
    $imagick->setImageProfile('iptc', $new_profile);
  }


  
}
?>