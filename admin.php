<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

// Inclure les fonctions communes avec chemin relatif
include_once(dirname(__FILE__) . '/admin/functions.inc.php'); // fonction d'affichage des onglets

// Récupérer la page demandée
$page_name = isset($_GET['tab']) ? $_GET['tab'] : 'help'; /// définit l'onglet pré selectionné

// Charger la page correspondante
$page_path = FACETAGWRITE_PATH . 'admin/' . $page_name . '.php';

if (file_exists($page_path))
{
  include($page_path);
}
else
{
  // Par défaut, charger help.php
  include(FACETAGWRITE_PATH . 'admin/help.php');
}
?>