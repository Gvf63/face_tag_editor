<?php
defined('FACETAGWRITE_PATH') or die('Hacking attempt!');

/**
 * Recherche tous les fichiers .original
 * @param string $date_filter Date limite (Y-m-d), null pour tous
 * @param string $directory_filter Filtre sur le chemin du répertoire (contient), null pour tous
 * @return array Liste des fichiers avec leurs infos
 */
function find_original_files($date_filter = null, $directory_filter = null) {

    

    $originals = array();
    $timestamp_filter = $date_filter ? strtotime($date_filter . ' 23:59:59') : null;

    // Récupérer tous les chemins d'images
    //$query = 'SELECT id, path, file FROM ' . IMAGES_TABLE . ' ORDER BY path';
    $query = 'SELECT id, path, file FROM ' . IMAGES_TABLE . ' WHERE 1=1 /* ' . time() . ' */ ORDER BY path';
    $result = pwg_query($query);
    
    while ($row = pwg_db_fetch_assoc($result)) {
        // Chemin utilisé pour localiser le fichier .original sur le disque (résolu si lien
        // symbolique) : à ne pas confondre avec $row['path'], le chemin DB utilisé plus bas
        // pour construire l'URL de la photo actuelle.
        $lookup_path = $row['path'];
        
        // Gérer les liens symboliques si nécessaire
        if (is_link($lookup_path)) {
            $real_path = readlink($lookup_path);
            if ($real_path !== false) {
                $lookup_path = $real_path;
            }
        }
        
        $original_path = $lookup_path . '.original';
        
        if (file_exists($original_path)) {
            $mtime = filemtime($original_path);
            
            // Appliquer le filtre de date si défini
            if ($timestamp_filter !== null && $mtime > $timestamp_filter) {
                continue;
            }
            
            $pathinfo = pathinfo($original_path);
            
            // Appliquer le filtre de répertoire si défini
            if ($directory_filter !== null && $directory_filter !== '') {
                // Recherche insensible à la casse
                if (stripos($pathinfo['dirname'], $directory_filter) === false) {
                    continue;
                }
            }
            
            // Lien vers la page de visualisation Piwigo de la photo (pas le fichier brut) :
            // fonctionne indépendamment de pdp, picture.php lit le fichier côté serveur.
            $picture_url = make_picture_url(array(
                'image_id'   => $row['id'],
                'image_file' => $row['file'],
            ));

            $originals[] = array(
                'full_path' => $original_path,
                'directory' => $pathinfo['dirname'],
                'filename' => $pathinfo['basename'],
                'date' => $mtime,
                'date_formatted' => date('Y-m-d H:i:s', $mtime),
                'size' => filesize($original_path),
                'size_formatted' => format_bytes(filesize($original_path)),
                'picture_url' => $picture_url
            );
        }
    }
    
    // Calculer les statistiques
    $total_size = array_sum(array_column($originals, 'size'));
    $total_count = count($originals);
    
    return array(
        'files' => $originals,
        'total_count' => $total_count,
        'total_size' => $total_size,
        'total_size_formatted' => format_bytes($total_size)
    );
}

/**
 * Supprime les fichiers .original spécifiés
 * @param array $files Liste des chemins complets
 * @return array Résultat de la suppression
 */
function delete_original_files($files) {

    // Protection contre null ou non-array
    if (!is_array($files)) {
        error_log('❌ ERREUR: $files n\'est pas un tableau');
        return array(
            'success' => false,
            'deleted' => 0,
            'failed' => 0,
            'errors' => array('Erreur: données invalides'),
            'message' => 'Erreur: aucun fichier à supprimer'
        );
    }
    
    $deleted = 0;
    $failed = 0;
    $errors = array();
    
    error_log('=== DELETE ORIGINAL FILES ===');
    error_log('Nombre de fichiers à supprimer: ' . count($files));


    $deleted = 0;
    $failed = 0;
    $errors = array();
    
    error_log('=== DELETE ORIGINAL FILES ===');
    error_log('Nombre de fichiers à supprimer: ' . count($files));
    
    foreach ($files as $file) {
        error_log('Tentative suppression: ' . $file);
        
        // Sécurité : vérifier que c'est bien un fichier .original
        if (!preg_match('/\.original$/i', $file)) {
            $failed++;
            $errors[] = "Fichier invalide: " . basename($file);
            error_log('  -> INVALIDE (pas .original)');
            continue;
        }
        
        if (file_exists($file)) {
            error_log('  -> Fichier existe');
            error_log('  -> Permissions: ' . decoct(fileperms($file) & 0777));
            error_log('  -> Owner: ' . fileowner($file));
            error_log('  -> Writable: ' . (is_writable(dirname($file)) ? 'OUI' : 'NON'));
            
            // Essayer de rendre le fichier accessible en écriture
            @chmod($file, 0666);
            error_log('  -> Permissions après chmod: ' . decoct(fileperms($file) & 0777));
            
            if (@unlink($file)) {
                $deleted++;
                error_log('  -> SUPPRIMÉ');
            } else {
                $failed++;
                $last_error = error_get_last();
                $error_msg = $last_error ? $last_error['message'] : 'Raison inconnue';
                $errors[] = "Impossible de supprimer: " . basename($file) . " (" . $error_msg . ")";
                error_log('  -> ÉCHEC: ' . $error_msg);
            }
        } else {
            $failed++;
            $errors[] = "Fichier introuvable: " . basename($file);
            error_log('  -> FICHIER INTROUVABLE');
        }
    }
    
    error_log('Résultat: ' . $deleted . ' supprimés, ' . $failed . ' échecs');
    
    return array(
        'success' => ($failed === 0),
        'deleted' => $deleted,
        'failed' => $failed,
        'errors' => $errors,
        'message' => sprintf('%d fichier(s) supprimé(s), %d échec(s)', $deleted, $failed)
    );
}
/**
 * Formate la taille en octets en format lisible
 * @param int $bytes Taille en octets
 * @return string Taille formatée
 */
function format_bytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>