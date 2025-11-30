

<div class="titrePage">
  <h2>Face Tag Editor</h2>
</div>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f9f9f9;
        }
        h1 {
            text-align: center;
            color: #667eea;
            margin-bottom: 30px;
        }
        .columns {
            display: flex;
            gap: 40px;
            max-width: 80%;
            margin: 0 auto;
        }
        .col {
            flex: 1;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { 
            margin-bottom: 20px;
            color: #667eea;
            font-size: 2.0em;
        }
        h3 {
            margin-top: 25px;
            margin-bottom: 12px;
            color: #764ba2;
            font-size: 1.2em;
        }
        p { 
            margin-bottom: 12px;
            line-height: 1.6;
            text-align: justify;
            font-size: 1.4em;
        }
        ul {
            margin: 15px 0;
            padding-left: 25px;
            font-size: 1.4em;
        }
        li {
            margin-bottom: 8px;
            line-height: 1.6;
        }
        .code {
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .highlight {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
            border-radius: 4px;
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        @media (max-width: 968px) {
            .columns {
                flex-direction: column;
            }
        }
    </style>




<div class="columns">
    <div class="col">
        <h2>🇫🇷 Français</h2>
        
        <p>Le plugin <strong>face_tag_editor</strong> permet l'édition des tags de visage.</p>
        
        <h3>Fonctionnalités</h3>
        <p>Avec ce plugin vous pouvez :</p>
        <ul>
            <li>créer/supprimer des cadres visages</li>
            <li>déplacer, redimensionner un cadre de visage</li>
            <li>pour renommer il faut créer un cadre visage et supprimer l'ancien</li>
        </ul>
        
        <h3>Droits d'accès</h3>
        <p>Pour pouvoir utiliser le plugin il faut être webmaster, administrateur ou un user appartenant au groupe FaceTag.</p>
        
        <h3>Visualisation des résultats</h3>
        <p>Pour visualiser les résultats il faut installer/activer le plugin face_tag : <a href="https://fr.piwigo.org/ext/index.php?eid=1051" target="_blank">https://fr.piwigo.org/ext/index.php?eid=1051</a></p>
        <p>Ce plugin permet de visualiser instantanément le résultat de face_tag_editor.</p>
        
        <h3>Formats de métadonnées</h3>
        <p>Les tags-visage sont enregistrés dans les métadonnées des photos aux formats :</p>
        <ul>
            <li><strong>MPReg</strong> (Microsoft Photo Region)</li>
            <li><strong>MWG-RS</strong> (Metadata Working Group - Region Schema)</li>
        </ul>
        <p>Ces formats sont identiques à ceux utilisés par digiKam.</p>
        
        <div class="highlight">
            <strong>✅ Compatibilité :</strong> Les tags visage existants créés à ces formats par d'autres logiciels sont pris en compte par face_tag_editor.
        </div>
        
        <h3>Permissions requises</h3>
        <p>Il faut que les fichiers jpg aient des droits en écriture.</p>
        <p><strong>Pour les NAS Synology :</strong> il faut donner les droits lecture et écriture au groupe <span class="code">http</span> sur les répertoires concernés :</p>
        <ul>
            <li><span class="code">./data</span></li>
            <li><span class="code">./upload</span></li>
            <li><span class="code">./galleries</span></li>
        </ul>
        
        <h3>Compatibilité des chemins</h3>
        <p>L'édition des tags fonctionne sur les photos dans :</p>
        <ul>
            <li><span class="code">./upload</span></li>
            <li><span class="code">./galleries</span> directement</li>
            <li><span class="code">./galleries</span> + liens symboliques</li>
        </ul>
        
        <h3>Sauvegarde et restauration</h3>
        <p>La 1ère fois qu'un enregistrement d'une édition de tag visage est faite, une copie de la photo <span class="code">xyz.jpg</span> est réalisée sous le nom <span class="code">xyz.jpg.original</span> (que ce soit en <span class="code">./galleries</span> ou en <span class="code">./upload</span>).</p>
        <p>Dans l'interface, un bouton <strong>"Restaurer"</strong> vous permet de revenir à la photo initiale.</p>
        
        <h3>Intégration Piwigo</h3>
        <p>Les tags sont ajoutés à la liste des tags Piwigo, et les miniatures sont mises à jour.</p>
    </div>

    <div class="col">
        <h2>🇬🇧 English</h2>
        
        <p>The <strong>face_tag_editor</strong> plugin allows you to edit face tags.</p>
        
        <h3>Features</h3>
        <p>With this plugin, you can:</p>
        <ul>
            <li>create/delete face frames</li>
            <li>move and resize a face frame</li>
            <li>to rename a face frame, you must create a new one and delete the old one</li>
        </ul>
        
        <h3>Access Rights</h3>
        <p>To use the plugin, you must be a webmaster, administrator, or a user belonging to the FaceTag group.</p>
        
        <h3>Viewing Results</h3>
        <p>To view the results, you must install/activate the face_tag plugin: <a href="https://fr.piwigo.org/ext/index.php?eid=1051" target="_blank">https://fr.piwigo.org/ext/index.php?eid=1051</a></p>
        <p>This plugin allows you to instantly view the results of face_tag_editor.</p>
        
        <h3>Metadata Formats</h3>
        <p>Face tags are saved in the photo metadata in the following formats:</p>
        <ul>
            <li><strong>MPReg</strong> (Microsoft Photo Region)</li>
            <li><strong>MWG-RS</strong> (Metadata Working Group - Region Schema)</li>
        </ul>
        <p>These formats are identical to those used by digiKam.</p>
        
        <div class="highlight">
            <strong>✅ Compatibility:</strong> Existing face tags created in these formats by other software are recognized by face_tag_editor.
        </div>
        
        <h3>Required Permissions</h3>
        <p>JPG files must have write permissions.</p>
        <p><strong>For Synology NAS devices:</strong> you must grant read and write permissions to the <span class="code">http</span> group on the relevant directories:</p>
        <ul>
            <li><span class="code">./data</span></li>
            <li><span class="code">./upload</span></li>
            <li><span class="code">./galleries</span></li>
        </ul>
        
        <h3>Path Compatibility</h3>
        <p>Tag editing works on photos in:</p>
        <ul>
            <li><span class="code">./upload</span></li>
            <li>directly in <span class="code">./galleries</span></li>
            <li><span class="code">./galleries</span> with symbolic links</li>
        </ul>
        
        <h3>Backup and Restore</h3>
        <p>The first time a face tag edit is saved, a copy of the photo <span class="code">xyz.jpg</span> is created under the name <span class="code">xyz.jpg.original</span> (whether in <span class="code">./galleries</span> or <span class="code">./upload</span>).</p>
        <p>A <strong>"Restore"</strong> button in the interface allows you to revert to the original photo.</p>
        
        <h3>Piwigo Integration</h3>
        <p>The tags are added to the Piwigo tag list, and the thumbnails are updated.</p>
    </div>
</div>

</body>
</html>