<?php
/**
 * Définition de l'icône SVG du plugin face_tag_editor
 * L'icône utilise currentColor pour s'adapter automatiquement au thème
 */

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

define('FACETAGWRITE_ICON', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" style="vertical-align: middle;">
  <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/>
  <circle cx="9" cy="10" r="1.5" fill="currentColor"/>
  <circle cx="15" cy="10" r="1.5" fill="currentColor"/>
  <path d="M 8 14 Q 12 16 16 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
  <path d="M 18 3 L 21 3 L 21 6 L 19.5 7.5 L 18 6 Z" fill="currentColor" stroke="currentColor" stroke-width="1"/>
  <circle cx="19.5" cy="4.5" r="0.8" fill="none" stroke="currentColor" stroke-width="0.5"/>
</svg>');
?>