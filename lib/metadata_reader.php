<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class FaceTagMetadataReader
{
  public function readAll($image_path)
  {
    // Use wrapper for fallback support
    $imagick = ImagickWrapper::load($image_path);

    if ($imagick->hasError()) {
      return array('error' => $imagick->getError());
    }

    try {
      
      $metadata = array(
        'exif' => array(),
        'iptc' => array(),
        'xmp' => array(),
        'xmp_raw' => ''
      );
      
      // Lire XMP brut
      try {
        $xmp_profile = $imagick->getImageProfile('xmp');
      } catch (Exception $e) {
        $xmp_profile = false;
        //*error_log('INFO: Aucun profil XMP dans image');
      }
      
      if ($xmp_profile) {
        //*error_log('Reader - XMP brut trouve, longueur: ' . strlen($xmp_profile) . ' octets');
        $metadata['xmp_raw'] = $xmp_profile;
        $metadata['xmp'] = $this->parseXmp($xmp_profile, $image_path);
      } else {
        //*error_log('INFO: Pas de XMP a parser');
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
  
  private function parseXmp($xmp_raw, $image_path)
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
    
    // dc:subject
    $subjects = $xpath->query('//dc:subject/rdf:Bag/rdf:li');
    //*error_log('parseXmp - dc:subject trouves: ' . $subjects->length);
    foreach ($subjects as $subject) {
      $data['subjects'][] = $subject->nodeValue;
    }
    
    // lr:hierarchicalSubject
    $hier = $xpath->query('//lr:hierarchicalSubject/rdf:Bag/rdf:li');
    //*error_log('parseXmp - hierarchicalSubject trouves: ' . $hier->length);
    foreach ($hier as $h) {
      $data['hierarchical_subjects'][] = $h->nodeValue;
    }
    
    // digiKam:TagsList
    $tags = $xpath->query('//digiKam:TagsList/rdf:Bag/rdf:li | //digiKam:TagsList/rdf:Seq/rdf:li');
    //*error_log('parseXmp - TagsList trouves: ' . $tags->length);
    foreach ($tags as $tag) {
      $data['tags_list'][] = $tag->nodeValue;
    }
    
    // digiKam:CatalogSets
    $catalog = $xpath->query('//digiKam:CatalogSets/rdf:Bag/rdf:li | //digiKam:CatalogSets/rdf:Seq/rdf:li');
    //*error_log('parseXmp - CatalogSets trouves: ' . $catalog->length);
    foreach ($catalog as $cat) {
      $data['catalog_sets'][] = $cat->nodeValue;
    }
    
    // === FACES : fonction de editor_xmp_ex (version 2 de face_tag )
    if (function_exists('facetag_editor_faces')) {
      //*error_log('Utilisation de facetag_editor_faces() pour les faces');
      $faces_data = facetag_editor_faces($image_path);
      $data['faces'] = $faces_data['faces'];
      //*error_log('parseXmp - Faces trouvees via xmp_extraction: ' . count($data['faces']));
    } else {
      //*error_log('ERREUR: facetag_editor_faces() non disponible !');
    }

    return $data;
  }
  
  public function extractNonFaceKeywords($metadata)
  {
    $non_face = array();
    
    if (isset($metadata['iptc']['Keywords'])) {
      $iptc_keywords = is_array($metadata['iptc']['Keywords']) ? 
        $metadata['iptc']['Keywords'] : array($metadata['iptc']['Keywords']);
      
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