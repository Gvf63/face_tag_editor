<div class="titrePage">
  <h2>Face Tag Editor</h2>
</div>

<style>
  .fte-help { margin:0 0 2em 20px; max-width:700px; line-height:1.6; text-align:left; }
  .fte-help h4 { margin:1.2em 0 0.3em 0; color:#333; border-bottom:1px solid #eee; padding-bottom:2px; }
  .fte-help p { margin:0.3em 0 0.5em 0; }
  .fte-help ul { margin:0.3em 0 0.5em 1.2em; padding:0; }
  .fte-help li { margin:0.2em 0; }
  .fte-help code { background:#f4f4f4; padding:1px 5px; border-radius:3px; font-size:0.9em; }
</style>

<div class="fte-help">

  <h4>{'Présentation'|@translate}</h4>
  <p>{'Le plugin face_tag_editor permet de créer et gérer des tags de visage sur les photos directement depuis Piwigo.'|@translate}</p>

  <h4>{'Fonctionnalités'|@translate}</h4>
  <p>{'Avec ce plugin vous pouvez :'|@translate}</p>
  <ul>
    <li>{'Créer ou supprimer des cadres de visage'|@translate}</li>
    <li>{'Déplacer ou redimensionner un cadre de visage'|@translate}</li>
    <li>{'Renommer une personne en double-cliquant sur son cadre'|@translate}</li>
    <li>{'Rédiger une description enrichie de la photo'|@translate}</li>
    <li>{'Télécharger la photo avec les cadres de visage incrustés'|@translate}</li>
  </ul>

  <h4>{'Droits d\'accès'|@translate}</h4>
  <p>{'Pour utiliser le plugin, il faut être webmaster, administrateur, ou un utilisateur appartenant au groupe FaceTag. Les webmasters et administrateurs ont toujours un accès total.'|@translate}</p>
  <p>{'Pour les membres du groupe FaceTag, l\'onglet "Gestion des droits" propose deux modes :'|@translate}</p>
  <ul>
    <li><strong>{'Tous les albums'|@translate}</strong> {': les utilisateurs du groupe peuvent taguer toutes les photos qu\'ils peuvent voir'|@translate}</li>
    <li><strong>{'Sélectif par utilisateur'|@translate}</strong> {': les albums autorisés sont configurés individuellement pour chaque utilisateur (jusqu\'à 5 albums, sous-albums inclus)'|@translate}</li>
  </ul>

  <h4>{'Description'|@translate}</h4>
  <p>{'Un éditeur de texte enrichi (Trumbowyg) permet de rédiger ou modifier la description de la photo, dans la colonne de droite de la fenêtre de tag : mise en forme (gras, italique, souligné, couleurs, polices), listes, liens, mode plein écran.'|@translate}</p>
  <p>{'La description est enregistrée en même temps que les tags de visage, via le même bouton "Enregistrer".'|@translate}</p>
  <p>{'Si une description existante contient une mise en forme HTML complexe, elle s\'affiche en lecture seule pour éviter de l\'altérer involontairement.'|@translate}</p>

  <h4>{'Visualisation des résultats'|@translate}</h4>
  <p>{'Pour visualiser les résultats, il faut installer et activer le plugin'|@translate} <strong>face_tag</strong> : <a href="https://fr.piwigo.org/ext/index.php?eid=1051" target="_blank">https://fr.piwigo.org/ext/index.php?eid=1051</a></p>
  <p>{'Ce plugin permet de visualiser instantanément le résultat de face_tag_editor.'|@translate}</p>

  <h4>{'Formats de métadonnées'|@translate}</h4>
  <p>{'Les tags de visage sont enregistrés dans les métadonnées des photos aux formats :'|@translate}</p>
  <ul>
    <li><strong>MPReg</strong> {'(Microsoft Photo Region)'|@translate}</li>
    <li><strong>MWG-RS</strong> {'(Metadata Working Group - Region Schema)'|@translate}</li>
  </ul>
  <p>{'Ces formats sont identiques à ceux utilisés par digiKam. Les tags de visage existants créés à ces formats par d\'autres logiciels sont pris en compte par face_tag_editor.'|@translate}</p>

  <h4>{'Permissions requises'|@translate}</h4>
  <p>{'Les fichiers jpg doivent avoir des droits en écriture.'|@translate}</p>
  <p>{'Pour les NAS Synology, il faut donner les droits lecture et écriture au groupe'|@translate} <code>http</code> {'sur les répertoires concernés :'|@translate}</p>
  <ul>
    <li><code>./data</code></li>
    <li><code>./upload</code></li>
    <li><code>./galleries</code></li>
  </ul>

  <h4>{'Compatibilité des chemins'|@translate}</h4>
  <p>{'L\'édition des tags fonctionne sur les photos situées dans :'|@translate}</p>
  <ul>
    <li><code>./upload</code></li>
    <li><code>./galleries</code> {'directement'|@translate}</li>
    <li><code>./galleries</code> {'via des liens symboliques'|@translate}</li>
  </ul>

  <h4>{'Sauvegarde et restauration'|@translate}</h4>
  <p>{'La première fois qu\'un enregistrement de tag visage est effectué, une copie de la photo'|@translate} <code>xyz.jpg</code> {'est réalisée sous le nom'|@translate} <code>xyz.jpg.original</code> {'(que ce soit dans'|@translate} <code>./galleries</code> {'ou'|@translate} <code>./upload</code>).</p>
  <p>{'Dans l\'interface, un bouton "Restaurer" permet de revenir à la photo initiale.'|@translate}</p>
  <p>{'Cette fonctionnalité peut être activée ou désactivée. La suppression sélective des fichiers .original est possible depuis l\'onglet "Gestion des .original".'|@translate}</p>

  <h4>{'Intégration Piwigo'|@translate}</h4>
  <p>{'Les tags sont ajoutés à la liste des tags Piwigo, et les miniatures sont mises à jour.'|@translate}</p>

</div>
