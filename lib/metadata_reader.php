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
      
      // Lire proprietes
      $properties = $imagick->getImageProperties();
      
      foreach ($properties as $key => $value) {
        if (strpos($key, 'exif:') === 0) {
          $metadata['exif'][substr($key, 5)] = $value;
        } elseif (strpos($key, 'iptc:') === 0) {
          $field = substr($key, 5);
          error_log('Reader - IPTC trouve: ' . $field . ' = ' . (is_array($value) ? print_r($value, true) : $value));
          // IPTC peut avoir plusieurs valeurs
          if (isset($metadata['iptc'][$field])) {
            if (!is_array($metadata['iptc'][$field])) {
              $metadata['iptc'][$field] = array($metadata['iptc'][$field]);
            }
            $metadata['iptc'][$field][] = $value;
          } else {
            $metadata['iptc'][$field] = $value;
          }
        }
      }
      
      error_log('Reader - Total IPTC fields: ' . count($metadata['iptc']));
      if (isset($metadata['iptc']['Keywords'])) {
        error_log('Reader - IPTC Keywords trouve: ' . print_r($metadata['iptc']['Keywords'], true));
      }
      
      // Lire XMP brut - AVEC GESTION D'ERREUR
      try {
        $xmp_profile = $imagick->getImageProfile('xmp');
      } catch (Exception $e) {
        $xmp_profile = false;
        error_log('INFO: Aucun profil XMP dans image (normal pour certaines photos)');
      }
      
      if ($xmp_profile) {
        error_log('Reader - XMP brut trouve, longueur: ' . strlen($xmp_profile) . ' octets');
        error_log('Reader - XMP preview: ' . substr($xmp_profile, 0, 800));
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
    
    // Faces (pour identifier keywords lies aux visages)
    $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    $faceNames = $xpath->query('//mwg-rs:RegionList/rdf:Bag/rdf:li/mwg-rs:Name');
    foreach ($faceNames as $name) {
      $data['faces'][] = $name->nodeValue;
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
      
      $face_names = isset($metadata['xmp']['faces']) ? $metadata['xmp']['faces'] : array();
      
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
      $face_names = isset($metadata['xmp']['faces']) ? $metadata['xmp']['faces'] : array();
      
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