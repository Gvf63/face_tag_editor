<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class FaceTagMetadataMerger
{
  private $reader;
  
  public function __construct()
  {
    require_once(FACETAGWRITE_PATH . 'lib/metadata_reader.php');
    $this->reader = new FaceTagMetadataReader();
  }
  
  public function merge($image_path, $new_faces)
  {
    // Lire métadonnées actuelles
    $current = $this->reader->readAll($image_path);
    
    error_log('=== DEBUG MERGER ===');
    error_log('Current metadata keys: ' . implode(', ', array_keys($current)));
    
    if (isset($current['xmp'])) {
      error_log('XMP keys: ' . implode(', ', array_keys($current['xmp'])));
      error_log('XMP subjects: ' . print_r($current['xmp']['subjects'], true));
      error_log('XMP hierarchical_subjects: ' . print_r($current['xmp']['hierarchical_subjects'], true));
      error_log('XMP tags_list: ' . print_r($current['xmp']['tags_list'], true));
      error_log('XMP faces: ' . print_r($current['xmp']['faces'], true));
    }
    
    if (isset($current['iptc'])) {
      error_log('IPTC Keys: ' . implode(', ', array_keys($current['iptc'])));
      if (isset($current['iptc']['Keywords'])) {
        error_log('IPTC Keywords: ' . print_r($current['iptc']['Keywords'], true));
      }
    }
    
    if (isset($current['error'])) {
      error_log('⚠ Erreur lecture métadonnées: ' . $current['error']);
      return array(
        'person_names' => $this->extractPersonNames($new_faces),
        'non_face_keywords' => array(),
        'non_face_subjects' => array(),
        'non_face_hierarchical' => array(),
        'non_face_tagslist' => array(),
        'non_face_catalogsets' => array(),
        'all_keywords' => $this->extractPersonNames($new_faces),
        'all_subjects' => $this->extractPersonNames($new_faces),
        'all_hierarchical' => $this->formatHierarchical($this->extractPersonNames($new_faces)),
        'all_tagslist' => $this->formatTagsList($this->extractPersonNames($new_faces)),
        'all_catalogsets' => $this->formatCatalogSets($this->extractPersonNames($new_faces))
      );
    }
    
    // Extraire noms des nouveaux visages
    $person_names = $this->extractPersonNames($new_faces);
    
    // Extraire keywords/subjects existants NON liés aux visages
    $non_face_keywords = $this->reader->extractNonFaceKeywords($current);
    $non_face_subjects = $this->reader->extractNonFaceSubjects($current);
    
    // Si pas de subjects XMP, utiliser les keywords IPTC comme subjects
    if (empty($non_face_subjects) && !empty($non_face_keywords)) {
      error_log('Pas de XMP subjects, utilisation des IPTC keywords comme base');
      $non_face_subjects = $non_face_keywords;
    }
    
    // Extraire les hierarchicalSubject existants NON liés aux visages
    $non_face_hierarchical = $this->extractNonFaceHierarchical($current);
    
    // Extraire les TagsList existants NON liés aux visages
    $non_face_tagslist = $this->extractNonFaceTagsList($current);
    
    // Extraire les CatalogSets existants NON liés aux visages
    $non_face_catalogsets = $this->extractNonFaceCatalogSets($current);
    
    error_log('Fusion - Non-face keywords: ' . count($non_face_keywords));
    error_log('Fusion - Non-face subjects: ' . count($non_face_subjects));
    error_log('Fusion - Non-face hierarchical: ' . count($non_face_hierarchical));
    error_log('Fusion - Non-face tagslist: ' . count($non_face_tagslist));
    error_log('Fusion - Non-face catalogsets: ' . count($non_face_catalogsets));
    
    // Fusionner
    return array(
      'person_names' => $person_names,
      'non_face_keywords' => $non_face_keywords,
      'non_face_subjects' => $non_face_subjects,
      'non_face_hierarchical' => $non_face_hierarchical,
      'non_face_tagslist' => $non_face_tagslist,
      'non_face_catalogsets' => $non_face_catalogsets,
      'all_keywords' => array_unique(array_merge($non_face_keywords, $person_names)),
      'all_subjects' => array_unique(array_merge($non_face_subjects, $person_names)),
      'all_hierarchical' => array_unique(array_merge($non_face_hierarchical, $this->formatHierarchical($person_names))),
      'all_tagslist' => array_unique(array_merge($non_face_tagslist, $this->formatTagsList($person_names))),
      'all_catalogsets' => array_unique(array_merge($non_face_catalogsets, $this->formatCatalogSets($person_names)))
    );
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
  
  private function extractNonFaceHierarchical($metadata)
  {
    $non_face = array();
    
    if (isset($metadata['xmp']['hierarchical_subjects'])) {
      $face_names = isset($metadata['xmp']['faces']) ? $metadata['xmp']['faces'] : array();
      
      foreach ($metadata['xmp']['hierarchical_subjects'] as $hier) {
        // Vérifier si c'est un tag de personne (format: Personnes|NomPersonne ou |Personnes|NomPersonne)
        $is_person_tag = false;
        foreach ($face_names as $face_name) {
          if (strpos($hier, '|' . $face_name) !== false || strpos($hier, 'Personnes|' . $face_name) !== false) {
            $is_person_tag = true;
            break;
          }
        }
        
        if (!$is_person_tag) {
          $non_face[] = $hier;
        }
      }
    }
    
    return array_unique($non_face);
  }
  
  private function extractNonFaceTagsList($metadata)
  {
    $non_face = array();
    
    if (isset($metadata['xmp']['tags_list'])) {
      $face_names = isset($metadata['xmp']['faces']) ? $metadata['xmp']['faces'] : array();
      
      foreach ($metadata['xmp']['tags_list'] as $tag) {
        // Vérifier si c'est un tag de personne (format: Personnes/NomPersonne)
        $is_person_tag = false;
        foreach ($face_names as $face_name) {
          if (strpos($tag, '/' . $face_name) !== false || strpos($tag, 'Personnes/' . $face_name) !== false) {
            $is_person_tag = true;
            break;
          }
        }
        
        if (!$is_person_tag) {
          $non_face[] = $tag;
        }
      }
    }
    
    return array_unique($non_face);
  }
  
  private function extractNonFaceCatalogSets($metadata)
  {
    $non_face = array();
    
    if (isset($metadata['xmp']['catalog_sets'])) {
      $face_names = isset($metadata['xmp']['faces']) ? $metadata['xmp']['faces'] : array();
      
      foreach ($metadata['xmp']['catalog_sets'] as $cat) {
        // Vérifier si c'est un tag de personne (format: Personnes|NomPersonne)
        $is_person_tag = false;
        foreach ($face_names as $face_name) {
          if (strpos($cat, '|' . $face_name) !== false || strpos($cat, 'Personnes|' . $face_name) !== false) {
            $is_person_tag = true;
            break;
          }
        }
        
        if (!$is_person_tag) {
          $non_face[] = $cat;
        }
      }
    }
    
    return array_unique($non_face);
  }
  
  private function formatHierarchical($person_names)
  {
    $formatted = array();
    foreach ($person_names as $name) {
      $formatted[] = 'Personnes|' . $name;
    }
    return $formatted;
  }
  
  private function formatTagsList($person_names)
  {
    $formatted = array();
    foreach ($person_names as $name) {
      $formatted[] = 'Personnes/' . $name;
    }
    return $formatted;
  }
  
  private function formatCatalogSets($person_names)
  {
    $formatted = array();
    foreach ($person_names as $name) {
      $formatted[] = 'Personnes|' . $name;
    }
    return $formatted;
  }
}
?>