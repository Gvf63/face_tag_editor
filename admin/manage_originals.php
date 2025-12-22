<?php
defined('FACETAGWRITE_PATH') or die('Hacking attempt!');

// Charger les traductions
load_language('plugin.lang', FACETAGWRITE_PATH);

include_once(FACETAGWRITE_PATH . 'lib/original_files.php');

// Charger le JS spécifique
$template->func_combine_script(array(
    'id' => 'admin_config',
    'path' => FACETAGWRITE_PATH . 'js/admin_config.js',
    'require' => 'jquery' 
));

// Passer les traductions au JavaScript
$template->append('head_elements', '
<script type="text/javascript">
var adminConfigLang = {
    "search_in_progress": "' . l10n('Recherche en cours...') . '",
    "files_found": "' . l10n('fichier(s) trouvé(s)') . '",
    "search_error": "' . l10n('Erreur lors de la recherche') . '",
    "no_file_found": "' . l10n('Aucun fichier .original trouvé') . '",
    "no_file_to_delete": "' . l10n('Aucun fichier à supprimer') . '",
    "confirm_delete": "' . l10n('Êtes-vous sûr de vouloir supprimer') . '",
    "confirm_delete_suffix": "' . l10n('fichier(s) .original ?') . '",
    "irreversible": "' . l10n('Cette action est irréversible !') . '",
    "deletion_in_progress": "' . l10n('Suppression en cours...') . '",
    "total_space": "' . l10n('Espace disque total :') . '",
    "created_before": "' . l10n('créés avant le') . '",
    "directory_contains": "' . l10n('répertoire contient') . '",
    "select_at_least_one_filter": "' . l10n('Veuillez sélectionner au moins un filtre (date ou répertoire)') . '",
    "select_date": "' . l10n('Veuillez sélectionner une date') . '",
    "deleted": "' . l10n('fichier(s) supprimé(s)') . '",
    "failures": "' . l10n('échec(s)') . '",
    "deletion_errors": "' . l10n('Erreurs lors de la suppression:') . '"
};
</script>
');



// Récupérer la configuration actuelle
$config_string = conf_get_param('face_tag_editor_config', false);
$config = $config_string ? unserialize($config_string) : array();
$save_original = isset($config['save_original']) ? $config['save_original'] : true;

// Traitement des actions AJAX
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'save_config':
            $config['save_original'] = isset($_POST['save_original']);
            conf_update_param('face_tag_editor_config', serialize($config));
            $page['infos'][] = l10n('Configuration enregistrée');
            $save_original = $config['save_original'];
            break;
        
        case 'find_originals':
    // Retourner JSON pour AJAX
    header('Content-Type: application/json');
    $date_filter = isset($_POST['date_filter']) ? $_POST['date_filter'] : null;
    $directory_filter = isset($_POST['directory_filter']) ? $_POST['directory_filter'] : null;
    $originals = find_original_files($date_filter, $directory_filter);
    echo json_encode($originals);
    exit;
        
case 'delete_originals':
    // Retourner JSON pour AJAX
    header('Content-Type: application/json');
    
    // Décoder les fichiers - VÉRIFIER si le paramètre existe
    $files = isset($_POST['files']) ? json_decode(stripslashes($_POST['files']), true) : array();
    
    // Log pour debug
    //*error_log('Paramètre files reçu: ' . (isset($_POST['files']) ? 'OUI' : 'NON'));
    if (isset($_POST['files'])) {
        //*error_log('Contenu files: ' . substr($_POST['files'], 0, 200));
    }
    //*error_log('Après json_decode: ' . (is_array($files) ? count($files) . ' fichiers' : 'ERREUR'));
    
    $result = delete_original_files($files);
    echo json_encode($result);
    exit;
    }
}

// Afficher le tabsheet
facetageditor_admin_tabsheet('manage_originals');

// Assigner les variables au template
$template->assign(array(
    'SAVE_ORIGINAL' => $save_original,
    'FACETAGWRITE_ADMIN_URL' => FACETAGWRITE_ADMIN . '&tab=manage_originals',
));

// Charger le template
$template->set_filename('face_tag_editor_manage_originals', dirname(__FILE__).'/../template/manage_originals.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'face_tag_editor_manage_originals');
?>