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
      //*error_log('=== WRITER SIMPLE : Début ===');

      // Use wrapper for fallback support
      $imagick = ImagickWrapper::load($image_path);

      if ($imagick->hasError()) {
        return array('success' => false, 'error' => $imagick->getError());
      }
      
      // Récupérer dimensions
      $image_width = $imagick->getImageWidth();
      $image_height = $imagick->getImageHeight();
      
      //*error_log('Dimensions : ' . $image_width . 'x' . $image_height);
      
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
      
      // Écrire IPTC Keywords + Description en une seule fois
      //if ($this->config['write_fields']['iptc_keywords'] && count($all_subjects) > 0) {
      if ($this->config['write_fields']['iptc_keywords'] ) {
        $this->writeIptcData($imagick, $all_subjects, $description);
      }
      
      // Sauvegarder
      $imagick->writeImage($image_path);
      
      $imagick->clear();
      $imagick->destroy();
      
      //*error_log('=== WRITER SIMPLE : Succès ===');
      
      return array('success' => true);
      
    } catch (Exception $e) {
      //*error_log('ERREUR : ' . $e->getMessage());
      return array('success' => false, 'error' => $e->getMessage());
    }
  }
  
  /**
   * Construire le XMP à partir des templates
   */
  private function buildXmpFromTemplate($faces, $width, $height, $subjects, $hierarchical, $tagslist, $catalogsets)
  {
    // Charger le template principal
    //*error_log('📁 Template dir: ' . $this->template_dir);
    //*error_log('📄 xmp_template.xml exists: ' . (file_exists($this->template_dir . 'xmp_template.xml') ? 'YES' : 'NO'));
    
    $template = @file_get_contents($this->template_dir . 'xmp_template.xml');
    if ($template === false) {
      //*error_log('❌ ERREUR CRITIQUE: Impossible de charger xmp_template.xml');
      //*error_log('Chemin complet: ' . realpath($this->template_dir));
      throw new Exception('Template xmp_template.xml not found in ' . $this->template_dir);
    }
    //*error_log('✅ Template principal chargé: ' . strlen($template) . ' bytes');
    
    // Template pour une face MPReg
    $mpreg_template = @file_get_contents($this->template_dir . 'face_mpreg_template.xml');
    if ($mpreg_template === false) {
      //*error_log('❌ ERREUR: face_mpreg_template.xml introuvable');
      throw new Exception('Template face_mpreg_template.xml not found');
    }
    //*error_log('✅ Template MPReg chargé');
    
    // Template pour une face MWG-RS
    $mwgrs_template = @file_get_contents($this->template_dir . 'face_mwgrs_template.xml');
    if ($mwgrs_template === false) {
      //*error_log('❌ ERREUR: face_mwgrs_template.xml introuvable');
      throw new Exception('Template face_mwgrs_template.xml not found');
    }
    //*error_log('✅ Template MWG-RS chargé');
    
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
   * Écrire les données IPTC (Keywords + Description)
   * Cette fonction remplace writeIptcProfile() et writeIptcComment()
   */
  private function writeIptcData($imagick, $keywords, $description = null)
  {
    // Récupérer profil existant pour préserver les autres champs
    try {
      $iptc_profile = $imagick->getImageProfile('iptc');
    } catch (Exception $e) {
      $iptc_profile = false;
    }
    
    if ($iptc_profile) {
      $iptc_data = $this->parseIptcProfile($iptc_profile);
    } else {
      $iptc_data = array();
    }
    
    // Écrire les keywords
    $iptc_data['2#025'] = $keywords;
    
 // Gérer la description (ajouter, modifier ou supprimer)
if ($description !== null && strlen($description) > 0) {
  $iptc_data['2#120'] = $description;
  //*error_log('IPTC Description ajoutée: ' . strlen($description) . ' caractères');
} else {
  // Supprimer la description si elle existe
  if (isset($iptc_data['2#120'])) {
    unset($iptc_data['2#120']);
    //*error_log('IPTC Description supprimée');
  }
}

    



    // Reconstruire le profil IPTC complet
    $new_profile = $this->buildIptcProfile($iptc_data);
    
    // Écrire en une seule fois
    $imagick->setImageProfile('iptc', $new_profile);
    
    //*error_log('IPTC Keywords écrits : ' . count($keywords));
  }
  
  /**
   * Parser un profil IPTC binaire
   */
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
  
  /**
   * Construire un profil IPTC binaire
   */
  private function buildIptcProfile($data)
  {
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
      
      foreach ($values as $val) {
        $size = strlen($val);
        $binary .= chr(0x1C);
        $binary .= chr($record);
        $binary .= chr($tag);
        $binary .= pack('n', $size);
        $binary .= $val;
      }
    }
    
    return $binary;
  }
  
}
?>