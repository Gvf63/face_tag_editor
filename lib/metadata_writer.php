<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class FaceTagMetadataWriter
{
  private $config;
  
  public function __construct()
  {
    $config_file = FACETAGWRITE_PATH . 'config/metadata_fields.json';
    if (file_exists($config_file)) {
      $this->config = json_decode(file_get_contents($config_file), true);
    } else {
      $this->config = array(
        'write_fields' => array(
          'exif_xpkeywords' => true,
          'iptc_keywords' => true,
          'iptc_standards' => true,
          'xmp_subject' => true,
          'xmp_hierarchical' => true,
          'xmp_tagslist' => true,
          'xmp_catalogsets' => true,
          'xmp_lastkeyword' => false,
          'xmp_categories' => false
        ),
        'category_format' => 'Personnes|{name}',
        'tags_format' => 'Personnes/{name}'
      );
    }
  }
  
  public function writeMetadata($image_path, $faces, $merged_data = null)
  {
    if (!extension_loaded('imagick')) {
      return array('success' => false, 'error' => 'Imagick not loaded');
    }
    
    try {
      // Le fichier est déjà  un temporaire, on le modifie directement
      error_log('Traitement: ' . $image_path);
      
      $imagick = new Imagick($image_path);
      
      // Tenter de récupérer le profil XMP existant
      try {
        $existing_xmp = $imagick->getImageProfile('xmp');
      } catch (Exception $e) {
        // Pas de profil XMP existant - normal pour certaines images
        $existing_xmp = false;
        error_log('ℹ️ Aucun profil XMP existant (normal)');
      }
      
      if ($existing_xmp) {
        $dom = new DOMDocument();
        $dom->loadXML($existing_xmp);
        error_log('✅ XMP existant chargé et parsé');
      } else {
        $dom = $this->createEmptyXmpDom();
        error_log('✅ Nouveau document XMP créé from scratch');
      }
      
      $description = $this->getOrCreateDescription($dom);
      
      $this->removeOldRegions($dom, $description);
      $this->removeOldKeywords($dom, $description);
      
      $this->addNamespaces($description);
      
      if ($merged_data) {
        $person_names = $merged_data['person_names'];
        $all_keywords = $merged_data['all_keywords'];
        $all_subjects = $merged_data['all_subjects'];
        $all_hierarchical = $merged_data['all_hierarchical'];
        $all_tagslist = $merged_data['all_tagslist'];
        $all_catalogsets = $merged_data['all_catalogsets'];
      } else {
        $person_names = $this->extractPersonNames($faces);
        $all_keywords = $person_names;
        $all_subjects = $person_names;
        $all_hierarchical = array_map(function($n) { return 'Personnes|' . $n; }, $person_names);
        $all_tagslist = array_map(function($n) { return 'Personnes/' . $n; }, $person_names);
        $all_catalogsets = array_map(function($n) { return 'Personnes|' . $n; }, $person_names);
      }
      
      $this->writeRegionsMwgRs($dom, $description, $faces);
      $this->writeRegionsMPReg($dom, $description, $faces);
      
      $this->writeKeywords($dom, $description, $imagick, $person_names, $all_keywords, $all_subjects, $all_hierarchical, $all_tagslist, $all_catalogsets);
      
      $new_xmp = $dom->saveXML();
      $imagick->setImageProfile('xmp', $new_xmp);
      
      // NOUVELLE METHODE: écrire les IPTC Keywords via le profil IPTC binaire
      if ($this->config['write_fields']['iptc_keywords'] && count($all_subjects) > 0) {
        $this->writeIptcProfile($imagick, $all_subjects);
      }
      
      // écrire directement sur le fichier temporaire
      $imagick->writeImage($image_path);
      
      $imagick->clear();
      $imagick->destroy();
      
      error_log('XMP et IPTC écrits avec Imagick');
      
      return array('success' => true);
      
    } catch (Exception $e) {
      return array('success' => false, 'error' => $e->getMessage());
    }
  }
  
  /**
   * Ecrire le profil IPTC complet avec les keywords
   */
  private function writeIptcProfile($imagick, $keywords)
  {
    // Recuperer le profil IPTC existant ou creer un nouveau
    try {
      $iptc_profile = $imagick->getImageProfile('iptc');
    } catch (Exception $e) {
      $iptc_profile = false;
      error_log('INFO: Aucun profil IPTC existant (normal)');
    }
    
    if ($iptc_profile) {
      // Parser le profil existant
      $iptc_data = $this->parseIptcProfile($iptc_profile);
    } else {
      $iptc_data = array();
    }
    
    // Ajouter/remplacer les keywords (tag 2:25)
    $iptc_data['2#025'] = $keywords;
    
    // Reconstruire le profil IPTC binaire
    $new_profile = $this->buildIptcProfile($iptc_data);
    
    // Définir le nouveau profil
    $imagick->setImageProfile('iptc', $new_profile);
    
    error_log('IPTC Profile défini avec ' . count($keywords) . ' keywords: ' . implode(', ', $keywords));
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
      // Vérifier le tag marker (0x1C)
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
      
      // Les keywords (2:25) peuvent être multiples
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
    
    // Ajouter l'envelope record version (1:00) si pas présent
    if (!isset($data['1#000'])) {
      $data['1#000'] = pack('n', 4); // Version 4
    }
    
    // Ajouter le coded character set (1:90) pour UTF-8
    if (!isset($data['1#090'])) {
      $data['1#090'] = "\x1B%G"; // UTF-8
    }
    
    // Ajouter l'application record version (2:00) si pas présent
    if (!isset($data['2#000'])) {
      $data['2#000'] = pack('n', 4); // Version 4
    }
    
    foreach ($data as $key => $value) {
      list($record, $tag) = explode('#', $key);
      $record = intval($record);
      $tag = intval($tag);
      
      // Les keywords peuvent Ãªtre un tableau
      $values = is_array($value) ? $value : array($value);
      
      foreach ($values as $val) {
        $size = strlen($val);
        
        // Format: 0x1C + record + tag + size(2 bytes) + value
        $binary .= chr(0x1C);
        $binary .= chr($record);
        $binary .= chr($tag);
        $binary .= pack('n', $size); // Big-endian 16-bit
        $binary .= $val;
      }
    }
    
    return $binary;
  }
  
  private function createEmptyXmpDom()
  {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    
    $xmpmeta = $dom->createElementNS('adobe:ns:meta/', 'x:xmpmeta');
    $xmpmeta->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:x', 'adobe:ns:meta/');
    $dom->appendChild($xmpmeta);
    
    $rdf = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:RDF');
    $rdf->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    $xmpmeta->appendChild($rdf);
    
    $description = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Description');
    $description->setAttribute('rdf:about', '');
    $rdf->appendChild($description);
    
    return $dom;
  }
  
  private function getOrCreateDescription($dom)
  {
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    
    $descriptions = $xpath->query('//rdf:Description');
    if ($descriptions->length > 0) {
      return $descriptions->item(0);
    }
    
    $rdf = $xpath->query('//rdf:RDF')->item(0);
    $description = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Description');
    $description->setAttribute('rdf:about', '');
    $rdf->appendChild($description);
    
    return $description;
  }
  
private function removeOldRegions($dom, $description)
{
  error_log('Suppression des anciennes régions...');
  
  $xpath = new DOMXPath($dom);
  $xpath->registerNamespace('mwg-rs', 'http://www.metadataworkinggroup.com/schemas/regions/');
  $xpath->registerNamespace('MPRI', 'http://ns.microsoft.com/photo/1.2/');
  $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
  
  // Supprimer mwg-rs:Regions
  $oldRegions = $xpath->query('//mwg-rs:Regions', $description);
  error_log('mwg-rs:Regions trouvées: ' . $oldRegions->length);
  foreach ($oldRegions as $old) {
    $old->parentNode->removeChild($old);
  }
  
  // NE PAS supprimer MPRI:Regions, mais supprimer son CONTENU
  // Supprimer le rdf:Bag à  l'intérieur
  $mpBags = $xpath->query('//MPRI:Regions/rdf:Bag', $description);
  error_log('MPRI:Regions/rdf:Bag trouvés: ' . $mpBags->length);
  foreach ($mpBags as $bag) {
    // Supprimer tous les enfants du Bag
    while ($bag->firstChild) {
      $bag->removeChild($bag->firstChild);
    }
  }
}
  
  private function removeOldKeywords($dom, $description)
  {
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
    $xpath->registerNamespace('lr', 'http://ns.adobe.com/lightroom/1.0/');
    $xpath->registerNamespace('digiKam', 'http://www.digikam.org/ns/1.0/');
    
    // On supprime les anciens pour les recréer avec les données fusionnées
    $toRemove = array('//dc:subject', '//lr:hierarchicalSubject', '//digiKam:TagsList', '//digiKam:CatalogSets');
    
    foreach ($toRemove as $query) {
      $elements = $xpath->query($query, $description);
      foreach ($elements as $el) {
        $el->parentNode->removeChild($el);
      }
    }
  }
  
  private function addNamespaces($description)
  {
    $namespaces = array(
      'mwg-rs' => 'http://www.metadataworkinggroup.com/schemas/regions/',
      'stArea' => 'http://ns.adobe.com/xmp/sType/Area#',
      'stDim' => 'http://ns.adobe.com/xmp/sType/Dimensions#',
      'MPRI' => 'http://ns.microsoft.com/photo/1.2/',
      'MPReg' => 'http://ns.microsoft.com/photo/1.2/t/RegionInfo#',
      'dc' => 'http://purl.org/dc/elements/1.1/',
      'lr' => 'http://ns.adobe.com/lightroom/1.0/',
      'digiKam' => 'http://www.digikam.org/ns/1.0/'
    );
    
    foreach ($namespaces as $prefix => $uri) {
      if (!$description->hasAttribute('xmlns:' . $prefix)) {
        $description->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, $uri);
      }
    }
  }
  
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
  
  private function writeRegionsMwgRs($dom, $description, $faces)
  {
    

  error_log('=== ECRITURE mwg-rs : ' . count($faces) . ' visages ===');
  foreach ($faces as $i => $face) {
    error_log('Visage #' . $i . ' : ' . $face['name'] . 
              ' x=' . $face['x'] . 
              ' y=' . $face['y'] . 
              ' w=' . $face['w'] . 
              ' h=' . $face['h']);
  }
    
    $regions = $dom->createElementNS('http://www.metadataworkinggroup.com/schemas/regions/', 'mwg-rs:Regions');
    $description->appendChild($regions);
    
    $appliedToDimensions = $dom->createElementNS('http://www.metadataworkinggroup.com/schemas/regions/', 'mwg-rs:AppliedToDimensions');
    $appliedToDimensions->setAttribute('stDim:w', '1.0');
    $appliedToDimensions->setAttribute('stDim:h', '1.0');
    $appliedToDimensions->setAttribute('stDim:unit', 'normalized');
    $regions->appendChild($appliedToDimensions);
    
    $regionList = $dom->createElementNS('http://www.metadataworkinggroup.com/schemas/regions/', 'mwg-rs:RegionList');
    $regions->appendChild($regionList);
    
    $rdfBag = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Bag');
    $regionList->appendChild($rdfBag);
    
    foreach ($faces as $face) {
      $li = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:li');
      $rdfBag->appendChild($li);
      
      $type = $dom->createElementNS('http://www.metadataworkinggroup.com/schemas/regions/', 'mwg-rs:Type');
      $type->nodeValue = 'Face';
      $li->appendChild($type);
      
      $name = $dom->createElementNS('http://www.metadataworkinggroup.com/schemas/regions/', 'mwg-rs:Name');
      $name->nodeValue = $face['name'];
      $li->appendChild($name);
      
      $area = $dom->createElementNS('http://www.metadataworkinggroup.com/schemas/regions/', 'mwg-rs:Area');
      $area->setAttribute('stArea:x', number_format($face['x'], 6, '.', ''));
      $area->setAttribute('stArea:y', number_format($face['y'], 6, '.', ''));
      $area->setAttribute('stArea:w', number_format($face['w'], 6, '.', ''));
      $area->setAttribute('stArea:h', number_format($face['h'], 6, '.', ''));
      $area->setAttribute('stArea:unit', 'normalized');
      $li->appendChild($area);
    }
  }
  
  private function writeRegionsMPReg($dom, $description, $faces)
{
  error_log('=== ECRITURE MPReg : ' . count($faces) . ' visages ===');
  
  $xpath = new DOMXPath($dom);
  $xpath->registerNamespace('MPRI', 'http://ns.microsoft.com/photo/1.2/');
  $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
  
  // Chercher MPRI:Regions existant
  $mpriRegions = $xpath->query('//MPRI:Regions', $description);
  
  if ($mpriRegions->length > 0) {
    $mpRegions = $mpriRegions->item(0);
    error_log('MPRI:Regions existant trouvé');
  } else {
    $mpRegions = $dom->createElementNS('http://ns.microsoft.com/photo/1.2/', 'MPRI:Regions');
    $description->appendChild($mpRegions);
    error_log('MPRI:Regions créé');
  }
  
  // Chercher rdf:Bag existant
  $bags = $xpath->query('.//rdf:Bag', $mpRegions);
  
  if ($bags->length > 0) {
    $mpRdfBag = $bags->item(0);
    error_log(' rdf:Bag existant trouvé');
  } else {
    $mpRdfBag = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Bag');
    $mpRegions->appendChild($mpRdfBag);
    error_log(' rdf:Bag créé');
  }
  
  // Ajouter les visages
  foreach ($faces as $face) {
    $topLeftX = $face['x'] - ($face['w'] / 2);
    $topLeftY = $face['y'] - ($face['h'] / 2);
    error_log('Ecriture MPReg pour: ' . $face['name']);
    
    $mpLi = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:li');
    $mpRdfBag->appendChild($mpLi);
    
    $personName = $dom->createElementNS('http://ns.microsoft.com/photo/1.2/t/RegionInfo#', 'MPReg:PersonDisplayName');
    $personName->nodeValue = $face['name'];
    $mpLi->appendChild($personName);
    
    $rectangle = $dom->createElementNS('http://ns.microsoft.com/photo/1.2/t/RegionInfo#', 'MPReg:Rectangle');
    $rectangle->nodeValue = number_format($topLeftX, 6, '.', '') . ', ' . 
                           number_format($topLeftY, 6, '.', '') . ', ' . 
                           number_format($face['w'], 6, '.', '') . ', ' . 
                           number_format($face['h'], 6, '.', '');
    $mpLi->appendChild($rectangle);
  }
}
  
  private function writeKeywords($dom, $description, $imagick, $person_names, $all_keywords, $all_subjects, $all_hierarchical, $all_tagslist, $all_catalogsets)
  {
    $cfg = $this->config['write_fields'];
    
    if (count($person_names) == 0) {
      return;
    }
    
    if ($cfg['exif_xpkeywords']) {
      foreach ($person_names as $name) {
        $imagick->setImageProperty('exif:XPKeywords', $name);
      }
    }
    
    // Note: IPTC Keywords sont maintenant écrits via writeIptcProfile()
    // dans la méthode writeMetadata(), après setImageProfile('xmp')
    
    if ($cfg['iptc_standards']) {
      $imagick->setImageProperty('iptc:CodedCharacterSet', 'UTF8');
      $imagick->setImageProperty('iptc:EnvelopeRecordVersion', '4');
      $imagick->setImageProperty('iptc:ApplicationRecordVersion', '4');
    }
    
    if ($cfg['xmp_subject']) {
      $subject = $dom->createElementNS('http://purl.org/dc/elements/1.1/', 'dc:subject');
      $description->appendChild($subject);
      
      $subjectBag = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Bag');
      $subject->appendChild($subjectBag);
      
      foreach ($all_subjects as $subj) {
        $li = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:li');
        $li->nodeValue = $subj;
        $subjectBag->appendChild($li);
      }
    }
    
    if ($cfg['xmp_hierarchical']) {
      $hierSubject = $dom->createElementNS('http://ns.adobe.com/lightroom/1.0/', 'lr:hierarchicalSubject');
      $description->appendChild($hierSubject);
      
      $hierBag = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Bag');
      $hierSubject->appendChild($hierBag);
      
      foreach ($all_hierarchical as $hier) {
        $li = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:li');
        $li->nodeValue = $hier;
        $hierBag->appendChild($li);
      }
    }
    
    if ($cfg['xmp_tagslist']) {
      $tagsList = $dom->createElementNS('http://www.digikam.org/ns/1.0/', 'digiKam:TagsList');
      $description->appendChild($tagsList);
      
      $tagsBag = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Bag');
      $tagsList->appendChild($tagsBag);
      
      foreach ($all_tagslist as $tag) {
        $li = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:li');
        $li->nodeValue = $tag;
        $tagsBag->appendChild($li);
      }
    }
    
    if ($cfg['xmp_catalogsets']) {
      $catalogSets = $dom->createElementNS('http://www.digikam.org/ns/1.0/', 'digiKam:CatalogSets');
      $description->appendChild($catalogSets);
      
      $catalogBag = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:Bag');
      $catalogSets->appendChild($catalogBag);
      
      foreach ($all_catalogsets as $cat) {
        $li = $dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:li');
        $li->nodeValue = $cat;
        $catalogBag->appendChild($li);
      }
    }
  }
}
?>