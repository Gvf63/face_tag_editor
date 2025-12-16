<?php
/**
 * Bibliothèque partagée : Extraction XMP et détection de visages
 * Utilisée par face_tag (lecture) et face_tag_write (écriture)
 * Version: 2.0
 */

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

/**
 * Extraire le segment XMP d'un fichier JPEG
 * 
 * @param string $filepath Chemin vers le fichier JPEG
 * @return string|null Le contenu XML XMP ou null si non trouvé
 */
function facetag_editor_segment($filepath) {
    $fp = fopen($filepath, 'rb');
    if (!$fp) {
        return null;
    }
    
    // Vérifier SOI (Start Of Image)
    $soi = fread($fp, 2);
    if ($soi !== "\xFF\xD8") {
        fclose($fp);
        return null;
    }
    
    // Chercher le segment XMP
    while (!feof($fp)) {
        $marker = fread($fp, 2);
        if ($marker === false || strlen($marker) !== 2) break;
        if ($marker[0] !== "\xFF") break;
        
        $marker_type = ord($marker[1]);
        
        // SOS ou EOI = fin
        if ($marker_type === 0xDA || $marker_type === 0xD9) break;
        
        // Marqueurs sans longueur
        if ($marker_type === 0x01 || ($marker_type >= 0xD0 && $marker_type <= 0xD7)) {
            continue;
        }
        
        // Lire la longueur du segment
        $length_bytes = fread($fp, 2);
        if (strlen($length_bytes) !== 2) break;
        $length = unpack('n', $length_bytes)[1];
        $data_length = $length - 2;
        if ($data_length <= 0) break;
        
        // Lire les données
        $data = fread($fp, $data_length);
        if (strlen($data) !== $data_length) break;
        
        // Vérifier si c'est le segment XMP
        if ($marker_type === 0xE1) {
            $xmp_id = "http://ns.adobe.com/xap/1.0/\0";
            if (substr($data, 0, strlen($xmp_id)) === $xmp_id) {
                fclose($fp);
                return substr($data, strlen($xmp_id));
            }
        }
    }
    
    fclose($fp);
    return null;
}

/**
 * Extraire toutes les données de visages d'un fichier JPEG
 * 
 * @param string $filepath Chemin vers le fichier JPEG
 * @return array Tableau avec wjpg, hjpg, or (orientation), faces
 */
function facetag_editor_faces($filepath) {
    // Initialisation
    $result = array(
        'wjpg' => 0,
        'hjpg' => 0,
        'or' => 1,
        'faces' => array()
    );
    
    // 1. DIMENSIONS de l'image
    $size = @getimagesize($filepath);
    if ($size) {
        $result['wjpg'] = $size[0];
        $result['hjpg'] = $size[1];
    }
    
    // 2. ORIENTATION EXIF
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($filepath);
        if ($exif && isset($exif['Orientation'])) {
            $result['or'] = intval($exif['Orientation']);
        }
    }
    
    // 3. EXTRAIRE le segment XMP
    $xmp = facetag_editor_segment($filepath);
    if (!$xmp) {
        return $result; // Pas de XMP = pas de faces
    }
    
    // 4. CHERCHER les faces avec REGEX
    
    // === FORMAT MPReg (Microsoft Photo Region) ===
    // IMPORTANT : MPReg stocke coin supérieur gauche + dimensions
    // Il faut convertir vers centre pour l'éditeur
    if (preg_match_all('/<MPReg:PersonDisplayName>([^<]+)<\/MPReg:PersonDisplayName>/i', $xmp, $names_mpreg) &&
        preg_match_all('/<MPReg:Rectangle>([^<]+)<\/MPReg:Rectangle>/i', $xmp, $rects_mpreg)) {
        
        $count = min(count($names_mpreg[1]), count($rects_mpreg[1]));
        
        for ($i = 0; $i < $count; $i++) {
            $coords = array_map('trim', explode(',', $rects_mpreg[1][$i]));
            
            if (count($coords) === 4) {
                // MPReg = coin supérieur gauche, convertir vers centre
                $cornerX = floatval($coords[0]);
                $cornerY = floatval($coords[1]);
                $w = floatval($coords[2]);
                $h = floatval($coords[3]);
                
                $result['faces'][] = array(
                    'name' => trim($names_mpreg[1][$i]),
                    'x' => $cornerX + ($w / 2),  // Coin → Centre
                    'y' => $cornerY + ($h / 2),  // Coin → Centre
                    'w' => $w,
                    'h' => $h,
                    'methode' => 'MPReg'
                );
            }
        }
    }
    
    // === FORMAT MWG-RS avec BALISES (Digikam) ===
    // MWG-RS utilise déjà le centre, pas de conversion nécessaire
    if (preg_match_all('/<mwg-rs:Name>([^<]+)<\/mwg-rs:Name>/i', $xmp, $names_mwg_tag)) {
        preg_match_all('/<stArea:x>([^<]+)<\/stArea:x>/i', $xmp, $xs_tag);
        preg_match_all('/<stArea:y>([^<]+)<\/stArea:y>/i', $xmp, $ys_tag);
        preg_match_all('/<stArea:w>([^<]+)<\/stArea:w>/i', $xmp, $ws_tag);
        preg_match_all('/<stArea:h>([^<]+)<\/stArea:h>/i', $xmp, $hs_tag);
        
        $count = min(
            count($names_mwg_tag[1]),
            count($xs_tag[1]),
            count($ys_tag[1]),
            count($ws_tag[1]),
            count($hs_tag[1])
        );
        
        for ($i = 0; $i < $count; $i++) {
            $name = trim($names_mwg_tag[1][$i]);
            
            // Éviter les doublons
            $exists = false;
            foreach ($result['faces'] as $face) {
                if ($face['name'] === $name) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $result['faces'][] = array(
                    'name' => $name,
                    'x' => floatval($xs_tag[1][$i]),
                    'y' => floatval($ys_tag[1][$i]),
                    'w' => floatval($ws_tag[1][$i]),
                    'h' => floatval($hs_tag[1][$i]),
                    'methode' => 'MWG-RS'
                );
            }
        }
    }
    
    // === FORMAT MWG-RS avec ATTRIBUTS (Lightroom) ===
    // MWG-RS utilise déjà le centre, pas de conversion nécessaire
    if (preg_match_all('/mwg-rs:Name="([^"]+)"/i', $xmp, $names_mwg_attr)) {
        preg_match_all('/stArea:x="([^"]+)"/i', $xmp, $xs_attr);
        preg_match_all('/stArea:y="([^"]+)"/i', $xmp, $ys_attr);
        preg_match_all('/stArea:w="([^"]+)"/i', $xmp, $ws_attr);
        preg_match_all('/stArea:h="([^"]+)"/i', $xmp, $hs_attr);
        
        $count = min(
            count($names_mwg_attr[1]),
            count($xs_attr[1]),
            count($ys_attr[1]),
            count($ws_attr[1]),
            count($hs_attr[1])
        );
        
        for ($i = 0; $i < $count; $i++) {
            $name = trim($names_mwg_attr[1][$i]);
            
            // Éviter les doublons
            $exists = false;
            foreach ($result['faces'] as $face) {
                if ($face['name'] === $name) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $result['faces'][] = array(
                    'name' => $name,
                    'x' => floatval($xs_attr[1][$i]),
                    'y' => floatval($ys_attr[1][$i]),
                    'w' => floatval($ws_attr[1][$i]),
                    'h' => floatval($hs_attr[1][$i]),
                    'methode' => 'MWG-RS'
                );
            }
        }
    }
    
    return $result;
}
?>