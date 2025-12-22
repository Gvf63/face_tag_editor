<?php
defined('FACETAGWRITE_PATH') or die('Hacking attempt!');

/**
 * Récupère les albums visibles par un utilisateur spécifique
 * @param int $user_id ID de l'utilisateur
 * @return array Tableau [id => array(name, uppercats, level)] des albums autorisés
 */
function get_user_authorized_categories($user_id) {
    global $user;
    
    // Sauvegarder l'utilisateur actuel
    $current_user = $user;
    
    // Charger temporairement l'autre utilisateur
    $user = build_user($user_id, false);
    
    // Récupérer ses albums autorisés
    $query = '
        SELECT id, name, uppercats
        FROM ' . CATEGORIES_TABLE . '
        ' . get_sql_condition_FandF(
            array('forbidden_categories' => 'id'),
            'WHERE'
        ) . '
        ORDER BY uppercats';
    
    $result = pwg_query($query);
    $categories = array();
    
    while ($row = pwg_db_fetch_assoc($result)) {
        // Calculer le niveau d'indentation (nombre de virgules dans uppercats)
        $level = substr_count($row['uppercats'], ',');
        
        $categories[$row['id']] = array(
            'name' => $row['name'],
            'uppercats' => $row['uppercats'],
            'level' => $level
        );
    }
    
    // Restaurer l'utilisateur actuel
    $user = $current_user;
    
    return $categories;
}

/**
 * Récupère tous les utilisateurs du groupe FaceTag
 * @return array Tableau [user_id => username]
 */
function get_facetag_group_users() {
    $query = '
        SELECT u.id, u.username
        FROM ' . USER_GROUP_TABLE . ' AS ug
        INNER JOIN ' . GROUPS_TABLE . ' AS g ON ug.group_id = g.id
        INNER JOIN ' . USERS_TABLE . ' AS u ON ug.user_id = u.id
        WHERE g.name = "FaceTag"
        ORDER BY u.username';
    
    $result = pwg_query($query);
    $users = array();
    
    while ($row = pwg_db_fetch_assoc($result)) {
        $users[$row['id']] = $row['username'];
    }
    
    return $users;
}

/**
 * Récupère la configuration des droits
 * @return array Configuration
 */
function get_facetag_rights_config() {
    $config_string = conf_get_param('face_tag_editor_rights', false);
    
    if (!$config_string) {
        // Configuration par défaut
        return array(
            'mode' => 'all',
            'users' => array()
        );
    }
    
    return unserialize($config_string);
}

/**
 * Sauvegarde la configuration des droits
 * @param array $config Configuration
 * @return bool Succès
 */
function save_facetag_rights_config($config) {
    conf_update_param('face_tag_editor_rights', serialize($config));
    return true;
}

/**
 * Vérifie si un utilisateur appartient au groupe FaceTag
 * @param int $user_id ID de l'utilisateur
 * @return bool
 */
function user_in_facetag_group($user_id) {
    $query = '
        SELECT COUNT(*) as count
        FROM ' . USER_GROUP_TABLE . ' AS ug
        INNER JOIN ' . GROUPS_TABLE . ' AS g ON ug.group_id = g.id
        WHERE ug.user_id = ' . intval($user_id) . '
        AND g.name = "FaceTag"';
    
    $result = pwg_query($query);
    $row = pwg_db_fetch_assoc($result);
    
    return ($row['count'] > 0);
}

/**
 * Récupère tous les albums d'une image (y compris les parents via uppercats)
 * @param int $image_id ID de l'image
 * @return array Tableau d'IDs d'albums
 */
function get_image_categories_with_parents($image_id) {
    $query = '
        SELECT c.id, c.uppercats
        FROM ' . IMAGE_CATEGORY_TABLE . ' AS ic
        INNER JOIN ' . CATEGORIES_TABLE . ' AS c ON ic.category_id = c.id
        WHERE ic.image_id = ' . intval($image_id);
    
    $result = pwg_query($query);
    $all_categories = array();
    
    while ($row = pwg_db_fetch_assoc($result)) {
        // Ajouter l'album lui-même
        $all_categories[] = $row['id'];
        
        // Ajouter tous les parents via uppercats
        // uppercats format: "1,5,12" (ancêtre,parent,album)
        $parents = explode(',', $row['uppercats']);
        foreach ($parents as $parent_id) {
            if (!in_array($parent_id, $all_categories)) {
                $all_categories[] = intval($parent_id);
            }
        }
    }
    
    return $all_categories;
}

/**
 * Récupère tous les sous-albums d'un album (récursif)
 * @param int $category_id ID de l'album parent
 * @return array Tableau d'IDs incluant l'album parent et tous ses descendants
 */
function get_category_with_descendants($category_id) {
    $query = '
        SELECT id
        FROM ' . CATEGORIES_TABLE . '
        WHERE uppercats REGEXP "(^|,)' . intval($category_id) . '(,|$)"';
    
    $result = pwg_query($query);
    $categories = array();
    
    while ($row = pwg_db_fetch_assoc($result)) {
        $categories[] = intval($row['id']);
    }
    
    return $categories;
}

/**
 * Vérifie si un utilisateur peut taguer une image spécifique
 * @param int $user_id ID de l'utilisateur
 * @param int $image_id ID de l'image
 * @return bool
 */
function can_user_tag_image($user_id, $image_id) {
    // Webmaster/Admin : toujours OK
    if (is_webmaster() || is_admin()) {
        return true;
    }
    
    // Vérifier groupe FaceTag
    if (!user_in_facetag_group($user_id)) {
        return false;
    }
    
    // Charger la config
    $config = get_facetag_rights_config();
    
    // Mode "Tous les albums"
    if ($config['mode'] === 'all') {
        return true;
    }
    
    // Mode "Sélectif" : vérifier les albums de l'utilisateur
    $user_albums = isset($config['users'][$user_id]) ? $config['users'][$user_id] : array();
    
    if (empty($user_albums)) {
        return false; // User FaceTag mais aucun album autorisé
    }
    
    // Étendre les albums autorisés pour inclure tous les sous-albums
    $extended_albums = array();
    foreach ($user_albums as $album_id) {
        $descendants = get_category_with_descendants($album_id);
        $extended_albums = array_merge($extended_albums, $descendants);
    }
    $extended_albums = array_unique($extended_albums);
    
    // Récupérer les albums de l'image (y compris parents)
    $image_categories = get_image_categories_with_parents($image_id);
    
    // Vérifier intersection
    return count(array_intersect($image_categories, $extended_albums)) > 0;
}
?>