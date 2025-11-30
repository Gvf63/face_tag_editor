<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class FaceTagMetadataReader
{
  public function readAll($image_path)
  {
    if (!extension_loaded('imagick')) {
      return array('error' => 'Imagick not loaded');
    }
    
    try {
      $imagick = new Imagick($image_path);
      
      $metadata = array(
        'exif' => array(),
        'iptc' => array(),
        'xmp' => array(),
        'xmp_raw' => ''
      );
      
      

      
      // Lire XMP brut - AVEC GESTION D'ERREUR
      try {
        $xmp_profile = $imagick->getImageProfile('xmp');
      } catch (Exception $e) {
        $xmp_profile = false;
        error_log('INFO: Aucun profil XMP dans image (normal pour certaines photos)');
      }
      
      if ($xmp_profile) {
        error_log('Reader - XMP brut trouve, longueur: ' . strlen($xmp_profile) . ' octets');
        //error_log('Reader - XMP preview: ' . substr($xmp_profile, 0, 800)); // ------------------- affichage du xmp preview
        $metadata['xmp_raw'] = $xmp_profile;
        $metadata['xmp'] = $this->parseXmp($xmp_profile);
      } else {
        error_log('INFO: Pas de XMP a parser');
      }
      
      // Lire orientation EXIF
      if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($image_path);
        if ($exif && isset($exif['Orientation'])) {
          $metadata['orientation'] = $exif['Orientation'];
        } else {
          $metadata['orientation'] = 1;
        }
      } else {
        $metadata['orientation'] = 1;
      }
      
      $imagick->clear();
      $imagick->destroy();
      
      return $metadata;
      
    } catch (Exception $e) {
      return array('error' => $e->getMessage());
    }
  }
  
  private function parseXmp($xmp_raw)
  {
    $data = array(
      'subjects' => array(),
      'hierarchical_subjects' => array(),
      'tags_list' => array(),
      'catalog_sets' => array(),
      'faces' => array()
    );
    
    $parser = new DOMDocument();
    if (!@$parser->loadXML($xmp_raw)) {
      return $data;
    }
    
    $xpath = new DOMXPath($parser);
    $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
    $xpath->registerNamespace('lr', 'http://ns.adobe.com/lightroom/1.0/');
    $xpath->registerNamespace('digiKam', 'http://www.digikam.org/ns/1.0/');
    $xpath->registerNamespace('mwg-rs', 'http://www.metadataworkinggroup.com/schemas/regions/');
    $xpath->registerNamespace('stArea', 'http://ns.adobe.com/xmp/sType/Area#');
    
    // dc:subject
    $subjects = $xpath->query('//dc:subject/rdf:Bag/rdf:li');
    error_log('parseXmp - dc:subject trouves: ' . $subjects->length);
    foreach ($subjects as $subject) {
      $data['subjects'][] = $subject->nodeValue;
    }
    
    // lr:hierarchicalSubject
    $hier = $xpath->query('//lr:hierarchicalSubject/rdf:Bag/rdf:li');
    error_log('parseXmp - hierarchicalSubject trouves: ' . $hier->length);
    foreach ($hier as $h) {
      $data['hierarchical_subjects'][] = $h->nodeValue;
    }
    
    // digiKam:TagsList (peut etre Bag OU Seq)
    $tags = $xpath->query('//digiKam:TagsList/rdf:Bag/rdf:li | //digiKam:TagsList/rdf:Seq/rdf:li');
    error_log('parseXmp - TagsList trouves: ' . $tags->length);
    foreach ($tags as $tag) {
      $data['tags_list'][] = $tag->nodeValue;
    }
    
    // digiKam:CatalogSets (peut etre Bag OU Seq)
    $catalog = $xpath->query('//digiKam:CatalogSets/rdf:Bag/rdf:li | //digiKam:CatalogSets/rdf:Seq/rdf:li');
    error_log('parseXmp - CatalogSets trouves: ' . $catalog->length);
    foreach ($catalog as $cat) {
      $data['catalog_sets'][] = $cat->nodeValue;
    }
    
    // Faces avec coordonnées complètes
    $faceRegions = $xpath->query('//mwg-rs:RegionList/rdf:Bag/rdf:li');
    error_log('parseXmp - Face regions trouvees: ' . $faceRegions->length);

    foreach ($faceRegions as $region) {
      // Vérifier que c'est bien un visage
      $typeNodes = $xpath->query('mwg-rs:Type', $region);
      $type = ($typeNodes->length > 0) ? $typeNodes->item(0)->nodeValue : '';
      
      if ($type !== 'Face') {
        continue; // Ignorer les régions qui ne sont pas des visages
      }
      
      // Lire le nom
      $nameNodes = $xpath->query('mwg-rs:Name', $region);
      $name = ($nameNodes->length > 0) ? $nameNodes->item(0)->nodeValue : '';
      
      // Lire les coordonnées de l'Area
      $xNodes = $xpath->query('.//stArea:x', $region);
      $yNodes = $xpath->query('.//stArea:y', $region);
      $wNodes = $xpath->query('.//stArea:w', $region);
      $hNodes = $xpath->query('.//stArea:h', $region);
      
      if ($xNodes->length > 0 && $yNodes->length > 0 && 
          $wNodes->length > 0 && $hNodes->length > 0) {
        
        $data['faces'][] = array(
          'name' => $name,
          'x' => (float)$xNodes->item(0)->nodeValue,
          'y' => (float)$yNodes->item(0)->nodeValue,
          'w' => (float)$wNodes->item(0)->nodeValue,
          'h' => (float)$hNodes->item(0)->nodeValue
        );
        
        error_log("Face trouvee: $name at x=" . $xNodes->item(0)->nodeValue);
      }
    }

    return $data;
  }
  
  public function extractNonFaceKeywords($metadata)
  {
    $non_face = array();
    
    // Keywords IPTC
    if (isset($metadata['iptc']['Keywords'])) {
      $iptc_keywords = is_array($metadata['iptc']['Keywords']) ? 
        $metadata['iptc']['Keywords'] : array($metadata['iptc']['Keywords']);
      
      // Extraire juste les noms des faces
      $face_names = array();
      if (isset($metadata['xmp']['faces'])) {
        foreach ($metadata['xmp']['faces'] as $face) {
          if (is_array($face) && isset($face['name'])) {
            $face_names[] = $face['name'];
          }
        }
      }
      
      foreach ($iptc_keywords as $keyword) {
        if (!in_array($keyword, $face_names)) {
          $non_face[] = $keyword;
        }
      }
    }
    
    return array_unique($non_face);
  }
  
  public function extractNonFaceSubjects($metadata)
  {
    $non_face = array();
    
    if (isset($metadata['xmp']['subjects'])) {
      // Extraire juste les noms des faces
      $face_names = array();
      if (isset($metadata['xmp']['faces'])) {
        foreach ($metadata['xmp']['faces'] as $face) {
          if (is_array($face) && isset($face['name'])) {
            $face_names[] = $face['name'];
          }
        }
      }
      
      foreach ($metadata['xmp']['subjects'] as $subject) {
        if (!in_array($subject, $face_names)) {
          $non_face[] = $subject;
        }
      }
    }
    
    return array_unique($non_face);
  }
}
?>