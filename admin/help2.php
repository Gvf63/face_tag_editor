<?php
defined('FACETAG_PATH') or die('Hacking attempt!');


// Afficher le tabsheet
facetag_admin_tabsheet('help2');

// Charger le template
$template->set_filename('face_tag_editor_help2', dirname(__FILE__).'/../template/help2.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'face_tag_editor_help2');
?>