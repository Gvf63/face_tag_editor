<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * Crée et affiche le tabsheet (onglets) pour les pages d'administration
 * @param string $selected L'onglet actuellement sélectionné ('config', 'config2', 'help')
 */
function facetag_admin_tabsheet($selected = 'help')
{
  include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
  $tabsheet = new tabsheet();
  $tabsheet->set_id('face_tag_editor');
  //$tabsheet->add('config', l10n('Configuration générale'), FACETAG_ADMIN . '&tab=config');
  // $tabsheet->add('config2', 'Paramètres avancés', FACETAG_ADMIN . '&tab=config2');
  $tabsheet->add('help', l10n('Aide'), FACETAG_ADMIN . '&tab=help');
  $tabsheet->select($selected);
  $tabsheet->assign();
}
?>