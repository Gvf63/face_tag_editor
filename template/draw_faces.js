// face_tag_write - Interface modale de dessin des visages
// Version 2.1 - Support rotation EXIF

(function($) {
  'use strict';
  
  $(document).ready(function() {
    
    console.log('Face Tag Write: Script chargé )');
    
    var canvas = null;
    var currentRect = null;
    var faces = [];
    var imageId = null;
    var imageSrc = null;
    var saveUrl = null;
    var fabricLoaded = false;
    // Variables partagées pour stocker les XMP (comme face_tag)
    var xmpData = null;
    var existingFaces = [];
    
    // ==================== FONCTION DE TRANSFORMATION DES COORDONNÉES (EXIF) ====================
    // Fonction pour transformer les coordonnées selon l'orientation EXIF
    // Valeurs possibles : 1-8 (voir spec EXIF)
    function transformCoordinates(left, top, width, height, orientation) {
      var newLeft, newTop, newWidth, newHeight;
      
      switch(orientation) {
        case 1: // Normal
          return {left: left, top: top, width: width, height: height};
          
        case 2: // Flip horizontal
          newLeft = 100 - left - width;
          return {left: newLeft, top: top, width: width, height: height};
          
        case 3: // Rotate 180
          newLeft = 100 - left - width;
          newTop = 100 - top - height;
          return {left: newLeft, top: newTop, width: width, height: height};
          
        case 4: // Flip vertical
          newTop = 100 - top - height;
          return {left: left, top: newTop, width: width, height: height};
          
        case 5: // Rotate 90 CW + Flip horizontal
          // Transformation : x' = y, y' = x
          newLeft = top;
          newTop = left;
          newWidth = height;
          newHeight = width;
          return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
          
case 6: // Rotate 90 CW
  // L'image est tournée de 90° dans le sens horaire
  // Un point en bas à gauche de l'original apparaît en haut à gauche après rotation
  // Formule correcte : x' = y, y' = 100 - x - w
  newLeft = top;
  newTop = 100 - left - width;
  newWidth = height;
  newHeight = width;
  return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
          
        case 7: // Rotate 90 CCW + Flip horizontal
          // Transformation : x' = y, y' = 100 - x - width
          newLeft = top;
          newTop = 100 - left - width;
          newWidth = height;
          newHeight = width;
          return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
          
        case 8: // Rotate 90 CCW (270 CW)
          // Transformation : x' = 100 - y - height, y' = x
          newLeft = 100 - top - height;
          newTop = left;
          newWidth = height;
          newHeight = width;
          return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
          
        default:
          return {left: left, top: top, width: width, height: height};
      }
    }
    //---------------------------------------------------------------------------

    // Fonction inverse : canvas → coordonnées originales selon orientation EXIF
function inverseTransformCoordinates(left, top, width, height, orientation) {
  var newLeft, newTop, newWidth, newHeight;
  
  switch(orientation) {
    case 1: // Normal - pas de transformation
      return {left: left, top: top, width: width, height: height};
      
    case 2: // Flip horizontal
      newLeft = 100 - left - width;
      return {left: newLeft, top: top, width: width, height: height};
      
    case 3: // Rotate 180
      newLeft = 100 - left - width;
      newTop = 100 - top - height;
      return {left: newLeft, top: newTop, width: width, height: height};
      
    case 4: // Flip vertical
      newTop = 100 - top - height;
      return {left: left, top: newTop, width: width, height: height};
      
    case 5: // Rotate 90 CW + Flip horizontal
      newLeft = top;
      newTop = left;
      newWidth = height;
      newHeight = width;
      return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
      
case 6: // Rotate 90 CW - INVERSE
  // Affichage: x' = y, y' = 100 - x - w
  // Inverse: y = x', x = 100 - y' - h
  newLeft = 100 - top - height;
  newTop = left;
  newWidth = height;
  newHeight = width;
  return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
      
    case 7: // Rotate 90 CCW + Flip horizontal
      newLeft = 100 - top - height;
      newTop = left;
      newWidth = height;
      newHeight = width;
      return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
      
    case 8: // Rotate 90 CCW (270 CW) - INVERSE
      // Affichage: x' = y, y' = 100 - x - w
      // Inverse: y = x', x = 100 - y' - h
      newLeft = 100 - top - height;
      newTop = left;
      newWidth = height;
      newHeight = width;
      return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
      
    default:
      return {left: left, top: top, width: width, height: height};
  }
}






    // ==================== CHARGER FABRIC.JS À LA DEMANDE ====================
    function loadFabricJS(callback) {
      if (typeof fabric !== 'undefined') {
        //console.log('Fabric.js déjà chargé');
        callback();
        return;
      }
      
      //console.log('Chargement de Fabric.js...');
      
      var script = document.createElement('script');
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js';
      script.onload = function() {
        //console.log('Fabric.js chargé avec succès');
        fabricLoaded = true;
        callback();
      };
      script.onerror = function() {
        console.error('Erreur de chargement de Fabric.js');
        alert('Erreur : impossible de charger Fabric.js. Vérifiez votre connexion internet.');
      };
      
      document.head.appendChild(script);
    }
    
    // ==================== OUVERTURE DE LA MODAL ====================
    $(document).on('click', '#facetag-open-editor', function(e) {
      e.preventDefault();
      
      console.log('=== CLIC SUR TAGUER ===');
      
      imageId = $(this).data('image-id');
      imageSrc = $(this).data('image-src');
      saveUrl = $(this).data('save-url');
      
      console.log('Image ID:', imageId);
      console.log('Image URL:', imageSrc);
      console.log('Nom du fichier:', imageSrc.split('/').pop());
      console.log('Save URL:', saveUrl);
      
      // Charger Fabric.js puis ouvrir la modal
      loadFabricJS(function() {
        //console.log('Fabric.js prêt, ouverture de la modale');
        openModal();
      });
    });
    
    // ==================== CRÉER LA MODAL ====================
    function openModal() {
      // Supprimer les modales existantes
      $('#facetag-modal, #facetag-modal-overlay').remove();
      
      var modalHtml = `
        <div id="facetag-modal-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:9998;"></div>
        
        <div id="facetag-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; display:flex; flex-direction:column; background:#1a1a1a;">
          
          <!-- Header -->
          <div style="background:#4CAF50; color:white; padding:12px 20px; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
            <h3 style="margin:0; font-size:18px;">✏️ Éditeur de visages - Image #${imageId}</h3>
            <button id="facetag-close-modal" style="background:none; border:none; color:white; font-size:28px; cursor:pointer; padding:0; width:35px; height:35px; line-height:35px;">&times;</button>
          </div>
          
          <!-- Instructions -->
          <div style="padding:10px 20px; background:#2a2a2a; border-bottom:1px solid #444; flex-shrink:0;">
            <p style="margin:0; color:#ccc; font-size:14px;">
              <strong>Instructions :</strong> Cliquez et faites glisser sur l'image pour dessiner un rectangle autour d'un visage.
            </p>
          </div>
          
          <!-- Contenu principal -->
          <div style="display:flex; flex:1; overflow:hidden;">
            
            <!-- Zone image + canvas (prend tout l'espace disponible) -->
            <div style="flex:1; padding:20px; background:#1a1a1a; display:flex; align-items:center; justify-content:center; overflow:auto;">
              <div id="facetag-canvas-wrapper" style="position:relative;">
                <canvas id="facetag-canvas"></canvas>
              </div>
            </div>
            
            <!-- Sidebar : liste des visages -->
            <div style="width:320px; padding:20px; background:#2a2a2a; border-left:1px solid #444; overflow-y:auto; flex-shrink:0;">
              <h4 style="margin-top:0; padding-bottom:10px; border-bottom:2px solid #4CAF50; color:#fff;">👤 Visages tagués (<span id="facetag-count">0</span>)</h4>
              <div id="facetag-faces-list">
                <p style="color:#999; text-align:center; padding:20px;">Aucun visage tagué</p>
              </div>
            </div>
            
          </div>
          
<!-- Footer : boutons -->
          <div style="padding:12px 20px; background:#2a2a2a; border-top:1px solid #444; display:flex; justify-content:space-between; flex-shrink:0;">
            <div>
              <button id="facetag-clear-all" style="padding:10px 20px; background:#ff4444; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px;">
                🗑️ Tout effacer
              </button>
              <button id="facetag-restore-original" style="padding:10px 20px; margin-left:10px; background:#ff9800; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px;" title="Restaurer le fichier .original (supprime tous les tags)">
                ⏮️ Restaurer l'original
              </button>
            </div>
            <div>
              <button id="facetag-cancel" style="padding:10px 20px; margin-right:10px; background:#666; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px;">
                Annuler
              </button>
              <button id="facetag-save-xmp" style="padding:10px 24px; background:#4CAF50; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold; font-size:14px;">
                💾 Enregistrer dans XMP
              </button>
            </div>
          </div>
      `;
      
      $('body').append(modalHtml);
      
      // Charger l'image et initialiser le canvas
      loadImageAndInitCanvas();
      
      // Événements de fermeture
      $('#facetag-close-modal, #facetag-cancel').click(closeModal);
      
      // Événement d'enregistrement
  $('#facetag-save-xmp').click(function() {
        console.log("click sur Enregistrer");
        if (faces.length === 0) {
          if (!confirm('Voulez-vous vraiment supprimer tous les tags de visages de cette image ?')) {
            return;
          }
        }
        
        var facesData = faces.map(function(face) {
          return {
            name: face.name,
            x: face.x,
            y: face.y,
            w: face.w,
            h: face.h
          };
        });
        
        console.log('Sauvegarde:', facesData);
        console.log('JSON:', JSON.stringify(facesData));
        
        $(this).prop('disabled', true).text('Enregistrement...');
        
        // Créer un FormData pour envoyer en POST
        var formData = new FormData();
        formData.append('image_id', imageId);
        formData.append('faces', JSON.stringify(facesData));
        
        $.ajax({
          url: saveUrl,
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',

  /// -------------------------------------------------------------
success: function(data) {
  console.log('Réponse serveur:', data);
  
  var result = data.result || data;
  
  if (data.stat === 'ok' || result.stat === 'ok') {
    var msg = '✅ Visages enregistrés avec succès !\n\n';
    msg += 'Visages: ' + (result.faces_count || faces.length) + '\n';
    
    if (result.backup_created) {
      msg += 'Backup créé: Oui (.original)';
    } else {
      msg += 'Backup: Déjà existant';
    }
    
    alert(msg);
    closeModal();
    
    // FORCER RAFRAÎCHISSEMENT COMPLET
    window.location.href = window.location.href;
    



  } else {
    alert('Erreur: ' + (data.message || result.message || 'Erreur inconnue'));
  }
},

error: function(xhr, status, error) {
  console.error('Erreur AJAX:', xhr.responseText);
  
  // Vérifier si c'est une fausse erreur (succès en réalité)
  try {
    var response = JSON.parse(xhr.responseText);
    if (response.stat === 'ok') {
      alert('✅ Enregistré (malgré erreur HTTP)');
      closeModal();
      location.reload();
      return;
    }
  } catch(e) {}
  
  alert('Erreur de connexion: ' + error);
},

          error: function(xhr, status, error) {
            console.error('Erreur AJAX:', xhr.responseText);
            alert('Erreur de connexion: ' + error);
          },
          complete: function() {
            $('#facetag-save-xmp').prop('disabled', false).text('💾 Enregistrer');
          }
        });
      });
      
      // Événement effacer tout
      $('#facetag-clear-all').click(function() {
        if (faces.length === 0) {
          alert('Aucun visage à effacer');
          return;
        }
        
        if (!confirm('Êtes-vous sûr de vouloir effacer tous les rectangles ?')) {
          return;
        }
        
        faces.forEach(function(face) {
          canvas.remove(face.rect);
          if (face.rect.label) canvas.remove(face.rect.label);
        });
        
        canvas.renderAll();
        faces = [];
        updateFacesList();
      });
      
// Événement restaurer l'original
      $('#facetag-restore-original').click(function() {
        if (!confirm('⚠️ ATTENTION ⚠️\n\nCette action va :\n• Supprimer tous les tags de visages actuels\n• Restaurer le fichier .original (sans tags)\n• Régénérer les miniatures\n\nÊtes-vous sûr de vouloir continuer ?')) {
          return;
        }
        
        console.log('=== RESTAURATION DE L\'ORIGINAL ===');
        
        $(this).prop('disabled', true).text('Restauration...');
        
        var formData = new FormData();
        formData.append('image_id', imageId);
        formData.append('action', 'restore');
        
        $.ajax({
          url: saveUrl.replace('facetagwrite.saveXMP', 'facetagwrite.restoreOriginal'),
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(data) {
            console.log('Réponse serveur:', data);
            
            if (data.stat === 'ok') {
              alert('✅ Fichier original restauré avec succès !\n\nTous les tags de visages ont été supprimés.');
              closeModal();
              window.location.href = window.location.href;
            } else {
              alert('Erreur: ' + (data.message || 'Erreur inconnue'));
            }
          },
          error: function(xhr, status, error) {
            console.error('Erreur AJAX:', xhr.responseText);
            
            try {
              var response = JSON.parse(xhr.responseText);
              if (response.stat === 'ok') {
                alert('✅ Restauré (malgré erreur HTTP)');
                closeModal();
                location.reload();
                return;
              }
            } catch(e) {}
            
            var errorMsg = 'Erreur de connexion: ' + error;
            try {
              var response = JSON.parse(xhr.responseText);
              if (response.message) {
                errorMsg = response.message;
              }
            } catch(e) {}
            
            alert('❌ ' + errorMsg);
          },
          complete: function() {
            $('#facetag-restore-original').prop('disabled', false).text('⏮️ Restaurer l\'original');
          }
        });
      });




      // Touche Escape
      $(document).on('keyup.facetag', function(e) {
        if (e.keyCode === 27) closeModal();
      });
    }
    
    // ==================== CHARGER LES XMP (comme face_tag) ====================
    function loadXmpData(callback) {
      console.log('Chargement des XMP...');
      
      // Ajouter un timestamp pour éviter TOUT cache (navigateur + serveur)
      var nocache = '&_nocache=' + Date.now() + '&_rand=' + Math.random();
      var ajaxUrl = saveUrl.replace('facetagwrite.saveXMP', 'facetagwrite.getXMP') + '&image_id=' + imageId + nocache;
      console.log('URL:', ajaxUrl);
      
      $.ajax({
        url: ajaxUrl,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          console.log('=== RÉPONSE AJAX ===');
          console.log('data:', data);
          
          // Piwigo enveloppe dans data.result, et notre WS renvoie aussi result
          // Donc les XMP sont dans data.result.result.xmp !
          var xmpContainer = data.result && data.result.result ? data.result.result : data.result;
          
          console.log('xmpContainer:', xmpContainer);
          
          if (xmpContainer && xmpContainer.xmp) {
            console.log('✓ XMP trouvé');
            console.log('_raw_xmp présent:', '_raw_xmp' in xmpContainer.xmp);
            if (xmpContainer.xmp._raw_xmp) {
              console.log('_raw_xmp longueur:', xmpContainer.xmp._raw_xmp.length);
            }
          }
          
          if (data.stat === 'ok' && xmpContainer && xmpContainer.xmp) {
            xmpData = xmpContainer.xmp;
            
            // ==================== NOUVEAU : STOCKER L'ORIENTATION ====================
            xmpData.orientation = xmpContainer.orientation || 1;
            console.log('✓ Orientation EXIF:', xmpData.orientation);
            
            console.log('✓ XMP chargé');
            
            // Parser les visages
            existingFaces = parseFacesFromXMP(xmpData);
            console.log('✓ Visages parsés:', existingFaces.length);
            
            callback(existingFaces);
          } else {
            console.log('❌ Pas de XMP disponible');
            callback([]);
          }
        },
        error: function(xhr, status, error) {
          console.error('❌ Erreur chargement XMP:', error);
          console.error('Status:', status);
          console.error('Response:', xhr.responseText);
          callback([]);
        }
      });
    }
    
    // ==================== CHARGER L'IMAGE ET INITIALISER ====================
    function loadImageAndInitCanvas() {
      console.log('Chargement de l\'image:', imageSrc);
      
      // Créer un élément image temporaire pour obtenir les dimensions
      var img = new Image();
      
      img.onload = function() {
        console.log('Image chargée - Dimensions naturelles:', img.naturalWidth, 'x', img.naturalHeight);
        
        // Calculer les dimensions du canvas pour qu'il tienne dans la zone disponible
        var maxWidth = window.innerWidth - 400; // -400 pour la sidebar
        var maxHeight = window.innerHeight - 200; // -200 pour header/footer
        
        var scale = Math.min(
          maxWidth / img.naturalWidth,
          maxHeight / img.naturalHeight,
          1 // Ne pas agrandir si l'image est petite
        );
        
        var canvasWidth = img.naturalWidth * scale;
        var canvasHeight = img.naturalHeight * scale;
        
        console.log('Dimensions canvas:', canvasWidth, 'x', canvasHeight, '(scale:', scale, ')');
        
        // Initialiser Fabric.js
        canvas = new fabric.Canvas('facetag-canvas', {
          width: canvasWidth,
          height: canvasHeight,
          selection: false
        });
        
        // Stocker les dimensions pour les conversions
        canvas.imageWidth = img.naturalWidth;
        canvas.imageHeight = img.naturalHeight;
        canvas.displayWidth = canvasWidth;
        canvas.displayHeight = canvasHeight;
        canvas.scale = scale;
        
        // Définir l'image comme background du canvas
        fabric.Image.fromURL(imageSrc, function(fabricImg) {
          fabricImg.scaleToWidth(canvasWidth);
          canvas.setBackgroundImage(fabricImg, canvas.renderAll.bind(canvas));
          
          console.log('Background image défini');
          
          // Charger les visages existants (méthode simplifiée)
          loadXmpData(function(existingFacesData) {
            // Afficher les visages existants
            existingFacesData.forEach(function(face) {
              displayExistingFace(face);
            });
            
            updateFacesList();
            setupDrawingMode();
            console.log('Canvas prêt, mode dessin activé');
          });
        });
      };
      
      img.onerror = function() {
        console.error('Erreur de chargement de l\'image');
        alert('Impossible de charger l\'image');
        closeModal();
      };
      
      img.src = imageSrc;
    }
    
    // ==================== PARSER LES XMP (copié de face_tag) ====================
    function parseFacesFromXMP(data) {
      var faces = [];
      
      console.log('=== PARSING XMP ===');
      
      // Si on a le XMP brut, on le parse
      if (!data._raw_xmp) {
        console.log('❌ Pas de _raw_xmp');
        return faces;
      }
      
      console.log('✓ _raw_xmp trouvé, longueur:', data._raw_xmp.length);
      
      var xmlString = data._raw_xmp;
      
      // Parser avec DOMParser
      var parser = new DOMParser();
      var xmlDoc = parser.parseFromString(xmlString, "text/xml");
      
      // Vérifier les erreurs de parsing
      var parserError = xmlDoc.getElementsByTagName('parsererror');
      if (parserError.length > 0) {
        console.error('❌ Erreur parsing XML');
        return faces;
      }
      
      // === FORMAT 1 : MPReg (Microsoft Photo Region) ===
      var mpriRegions = xmlDoc.getElementsByTagName('MPRI:Regions');
      
      if (mpriRegions.length > 0) {
        var rdfLis = mpriRegions[0].getElementsByTagName('rdf:li');
        console.log('MPReg trouvé:', rdfLis.length, 'visages');
        
        for (var i = 0; i < rdfLis.length; i++) {
          var li = rdfLis[i];
          var name = null;
          var rect = null;
          
          // CAS 1 : Attributs directs
          name = li.getAttribute('MPReg:PersonDisplayName');
          rect = li.getAttribute('MPReg:Rectangle');
          
          // CAS 2 : Balises enfants
          if (!name || !rect) {
            var personNodes = li.getElementsByTagName('MPReg:PersonDisplayName');
            var rectNodes = li.getElementsByTagName('MPReg:Rectangle');
            
            if (personNodes.length > 0) {
              name = personNodes[0].textContent.trim();
            }
            if (rectNodes.length > 0) {
              rect = rectNodes[0].textContent.trim();
            }
          }
          
          if (name && rect) {
            var coords = rect.split(',').map(function(v) {
              return parseFloat(v.trim());
            });
            
            if (coords.length === 4) {
              // MPReg: x,y = coin supérieur gauche
              // Convertir en centre (format mwg-rs)
              var centerX = coords[0] + (coords[2] / 2);
              var centerY = coords[1] + (coords[3] / 2);
              
              faces.push({
                name: name,
                x: centerX,
                y: centerY,
                w: coords[2],
                h: coords[3],
                format: 'mpreg'
              });
              console.log('✓ Visage ajouté (MPReg):', name);
            }
          }
        }
      }
      
// === FORMAT 2 : mwg-rs (Metadata Working Group) ===
var mwgRegionList = xmlDoc.getElementsByTagName('mwg-rs:RegionList');

if (mwgRegionList.length > 0) {
  var descriptions = mwgRegionList[0].getElementsByTagName('rdf:Description');
  console.log('mwg-rs trouvé:', descriptions.length, 'visages');
  
  for (var i = 0; i < descriptions.length; i++) {
    var desc = descriptions[i];
    
    var name = desc.getAttribute('mwg-rs:Name');
    var type = desc.getAttribute('mwg-rs:Type');
    
    // Si pas d'attributs, chercher les balises
    if (!name) {
      var nameEl = desc.getElementsByTagName('mwg-rs:Name')[0];
      var typeEl = desc.getElementsByTagName('mwg-rs:Type')[0];
      if (nameEl) name = nameEl.textContent;
      if (typeEl) type = typeEl.textContent;
    }
    
    if (name && type === 'Face') {
      var areas = desc.getElementsByTagName('mwg-rs:Area');
      
      if (areas.length > 0) {
        var area = areas[0];
        
        // Essayer d'abord les attributs (format 1)
        var x = area.getAttribute('stArea:x');
        var y = area.getAttribute('stArea:y');
        var w = area.getAttribute('stArea:w');
        var h = area.getAttribute('stArea:h');
        
        // Si pas d'attributs, chercher les balises enfants (format 2)
        if (!x) {
          var xEl = area.getElementsByTagName('stArea:x')[0];
          var yEl = area.getElementsByTagName('stArea:y')[0];
          var wEl = area.getElementsByTagName('stArea:w')[0];
          var hEl = area.getElementsByTagName('stArea:h')[0];
          
          if (xEl) x = xEl.textContent;
          if (yEl) y = yEl.textContent;
          if (wEl) w = wEl.textContent;
          if (hEl) h = hEl.textContent;
        }
        
        if (x && y && w && h) {
          // Éviter les doublons
          var alreadyExists = faces.some(function(f) {
            return f.name === name;
          });
          
          if (!alreadyExists) {
            faces.push({
              name: name,
              x: parseFloat(x),
              y: parseFloat(y),
              w: parseFloat(w),
              h: parseFloat(h),
              format: 'mwg-rs'
            });
            console.log('✓ Visage ajouté (mwg-rs):', name);
          }
        }
      }
    }
  }
}
      
      console.log('=== Total visages parsés:', faces.length, '===');
      return faces;
    }
    
    // ==================== AFFICHER UN VISAGE EXISTANT ====================
    function displayExistingFace(face) {
      // Convertir les coordonnées normalisées -> pixels canvas
      // mwg-rs : x,y = centre
      var centerX = face.x * 100;
      var centerY = face.y * 100;
      var width = face.w * 100;
      var height = face.h * 100;
      
      var left = centerX - (width / 2);
      var top = centerY - (height / 2);
      
      // ==================== NOUVEAU : APPLIQUER LA TRANSFORMATION EXIF ====================
      var orientation = xmpData.orientation || 1;
      console.log('Orientation pour affichage:', orientation);
      
      var transformed = transformCoordinates(left, top, width, height, orientation);
      left = transformed.left;
      top = transformed.top;
      width = transformed.width;
      height = transformed.height;
      
      console.log('Affichage visage existant:', face.name, 'à', left.toFixed(2) + '%', top.toFixed(2) + '%', width.toFixed(2) + '%', height.toFixed(2) + '%');
      
      // Convertir % en pixels pour le canvas
      var leftPx = (left / 100) * canvas.displayWidth;
      var topPx = (top / 100) * canvas.displayHeight;
      var widthPx = (width / 100) * canvas.displayWidth;
      var heightPx = (height / 100) * canvas.displayHeight;
      
      // Créer le rectangle avec une couleur différente (bleu pour existant)
      var rect = new fabric.Rect({
        left: leftPx,
        top: topPx,
        width: widthPx,
        height: heightPx,
        fill: 'rgba(0, 150, 255, 0.2)',
        stroke: '#0096ff',
        strokeWidth: 2,
        selectable: true,
        hasRotatingPoint: false,
        // Activer tous les contrôles
        hasBorders: true,
        hasControls: true,
        lockRotation: true
      });
      
      canvas.add(rect);
      
      // Ajouter le label
      addLabelToRect(rect, face.name);
      
      // Sauvegarder dans notre liste
      faces.push({
        name: face.name,
        x: face.x,
        y: face.y,
        w: face.w,
        h: face.h,
        format: 'mwg-rs',
        rect: rect,
        existing: true
      });
    }
    
    // ==================== MODE DESSIN ====================
    function setupDrawingMode() {
      var isDrawing = false;
      var startX, startY;
      
      // Activer les contrôles sur les objets sélectionnés
      canvas.on('selection:created', function(e) {
        console.log('Objet sélectionné');
      });
      
      canvas.on('mouse:down', function(options) {
        // Si on clique sur un objet existant, ne rien faire (mode édition activé automatiquement)
        if (options.target) {
          console.log('Clic sur un rectangle existant - mode édition');
          return;
        }
        
        // Sinon, commencer à dessiner un nouveau rectangle
        isDrawing = true;
        var pointer = canvas.getPointer(options.e);
        startX = pointer.x;
        startY = pointer.y;
        
        currentRect = new fabric.Rect({
          left: startX,
          top: startY,
          width: 0,
          height: 0,
          fill: 'rgba(0, 255, 0, 0.2)',
          stroke: '#00ff00',
          strokeWidth: 3,
          selectable: true,
          hasRotatingPoint: false,
          // Activer tous les contrôles
          hasBorders: true,
          hasControls: true,
          lockRotation: true
        });
        
        canvas.add(currentRect);
        canvas.setActiveObject(currentRect);
      });
      
      canvas.on('mouse:move', function(options) {
        if (!isDrawing) return;
        
        var pointer = canvas.getPointer(options.e);
        
        if (pointer.x < startX) {
          currentRect.set({ left: pointer.x });
        }
        if (pointer.y < startY) {
          currentRect.set({ top: pointer.y });
        }
        
        currentRect.set({
          width: Math.abs(pointer.x - startX),
          height: Math.abs(pointer.y - startY)
        });
        
        canvas.renderAll();
      });
      
      canvas.on('mouse:up', function(options) {
        if (!isDrawing) return;
        isDrawing = false;
        
        if (currentRect.width < 10 || currentRect.height < 10) {
          canvas.remove(currentRect);
          currentRect = null;
          return;
        }
        
        promptForName(currentRect);
      });
      
      // Mettre à jour le label quand on déplace/redimensionne un rectangle
      canvas.on('object:moving', function(e) {
        var obj = e.target;
        if (obj.label) {
          obj.label.set({
            left: obj.left,
            top: obj.top - 25
          });
        }
      });
      
      canvas.on('object:scaling', function(e) {
        var obj = e.target;
        if (obj.label) {
          obj.label.set({
            left: obj.left * obj.scaleX,
            top: (obj.top * obj.scaleY) - 25
          });
        }
      });
      
      // Mettre à jour les données quand on modifie un rectangle existant
canvas.on('object:modified', function(e) {
  var obj = e.target;
  
  var faceIndex = faces.findIndex(f => f.rect === obj);
  
  if (faceIndex !== -1) {
    var x = obj.left / canvas.displayWidth;
    var y = obj.top / canvas.displayHeight;
    var w = (obj.width * obj.scaleX) / canvas.displayWidth;
    var h = (obj.height * obj.scaleY) / canvas.displayHeight;
    
    var centerX = x + (w / 2);
    var centerY = y + (h / 2);
    
    // ✅ Appliquer transformation inverse SEULEMENT si orientation ≠ 1
    var orientation = xmpData.orientation || 1;
    
    if (orientation !== 1) {
      var leftPct = centerX * 100 - (w * 100 / 2);
      var topPct = centerY * 100 - (h * 100 / 2);
      var widthPct = w * 100;
      var heightPct = h * 100;
      
      var original = inverseTransformCoordinates(leftPct, topPct, widthPct, heightPct, orientation);
      
      centerX = (original.left + original.width / 2) / 100;
      centerY = (original.top + original.height / 2) / 100;
      w = original.width / 100;
      h = original.height / 100;
    }
    
    faces[faceIndex].x = centerX;
    faces[faceIndex].y = centerY;
    faces[faceIndex].w = w;
    faces[faceIndex].h = h;
    
    console.log('Rectangle modifié, nouvelles coordonnées:', faces[faceIndex]);
  }
});
    }
    
    // ==================== DIALOGUE POUR NOMMER ====================
    function promptForName(rect) {
      var existingNames = faces.map(f => f.name).filter((v, i, a) => a.indexOf(v) === i);
      
      var nameModal = `
        <div id="facetag-name-modal" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3); z-index:10001; min-width:400px;">
          <h3 style="margin-top:0;">Nommer la personne</h3>
          <input type="text" id="facetag-name-input" placeholder="Nom de la personne" style="width:100%; padding:10px; margin:15px 0; font-size:16px; border:2px solid #ddd; border-radius:4px;">
          ${existingNames.length > 0 ? '<div style="margin-top:10px; color:#666; font-size:13px;">Personnes existantes : ' + existingNames.join(', ') + '</div>' : ''}
          <div style="text-align:right; margin-top:20px;">
            <button id="facetag-name-cancel" style="padding:10px 20px; margin-right:10px; background:#ccc; border:none; border-radius:4px; cursor:pointer;">Annuler</button>
            <button id="facetag-name-save" style="padding:10px 20px; background:#4CAF50; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">Valider</button>
          </div>
        </div>
        <div id="facetag-name-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000;"></div>
      `;
      
      $('body').append(nameModal);
      $('#facetag-name-input').focus();
      
      $('#facetag-name-save').click(function() {
        var name = $('#facetag-name-input').val().trim();
        if (!name) {
          alert('Veuillez entrer un nom');
          return;
        }
        
        addLabelToRect(rect, name);
        saveFaceData(rect, name);
        closeNameModal();
      });
      
      $('#facetag-name-cancel, #facetag-name-overlay').click(function() {
        canvas.remove(rect);
        canvas.renderAll();
        closeNameModal();
      });
      
      $('#facetag-name-input').keypress(function(e) {
        if (e.which === 13) $('#facetag-name-save').click();
      });
    }
    
    function closeNameModal() {
      $('#facetag-name-modal, #facetag-name-overlay').remove();
      currentRect = null;
    }
    
    // ==================== LABEL SUR LE RECTANGLE ====================
    function addLabelToRect(rect, name) {
      var label = new fabric.Text(name, {
        left: rect.left,
        top: rect.top - 25,
        fontSize: 14,
        fill: '#ffffff',
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        padding: 4,
        selectable: false,
        evented: false, // Le label n'intercepte pas les événements
        textBaseline: 'top' // Correction du warning Fabric.js
      });
      
      canvas.add(label);
      rect.label = label;
      label.bringToFront();
      
      // Note: les événements moving/scaling sont gérés dans setupDrawingMode()
    }
    
    // ==================== SAUVEGARDER LES DONNÉES ====================
 function saveFaceData(rect, name) {
  var x = rect.left / canvas.displayWidth;
  var y = rect.top / canvas.displayHeight;
  var w = (rect.width * (rect.scaleX || 1)) / canvas.displayWidth;
  var h = (rect.height * (rect.scaleY || 1)) / canvas.displayHeight;
  
  // Coordonnées centre (format mwg-rs)
  var centerX = x + (w / 2);
  var centerY = y + (h / 2);
  
  // ✅ Appliquer transformation inverse SEULEMENT si orientation ≠ 1
  var orientation = xmpData.orientation || 1;
  
  if (orientation !== 1) {
    // Convertir en pourcentages pour la transformation
    var leftPct = centerX * 100 - (w * 100 / 2);
    var topPct = centerY * 100 - (h * 100 / 2);
    var widthPct = w * 100;
    var heightPct = h * 100;
    
    var original = inverseTransformCoordinates(leftPct, topPct, widthPct, heightPct, orientation);
    
    // Recalculer le centre dans les coordonnées originales
    centerX = (original.left + original.width / 2) / 100;
    centerY = (original.top + original.height / 2) / 100;
    w = original.width / 100;
    h = original.height / 100;
    
    console.log('Transformation inverse appliquée (orientation=' + orientation + ')');
  }
  
  var face = {
    name: name,
    x: centerX,
    y: centerY,
    w: w,
    h: h,
    format: 'mwg-rs',
    rect: rect
  };
  
  faces.push(face);
  updateFacesList();
}
    
    // ==================== LISTE DES VISAGES ====================
    function updateFacesList() {
      var $list = $('#facetag-faces-list');
      var $count = $('#facetag-count');
      
      $count.text(faces.length);
      $list.empty();
      
      if (faces.length === 0) {
        $list.html('<p style="color:#999; text-align:center; padding:20px;">Aucun visage tagué</p>');
        return;
      }
      
      faces.forEach(function(face, index) {
        var bgColor = face.existing ? '#e3f2fd' : '#fff';
        var badge = face.existing ? '<span style="font-size:10px; background:#2196F3; color:white; padding:2px 6px; border-radius:3px; margin-left:5px;">existant</span>' : '';
        
        var $item = $('<div>')
          .css({
            padding: '10px',
            margin: '5px 0',
            background: bgColor,
            border: '1px solid #444',
            borderRadius: '4px',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center'
          })
          .html(`
            <span style="color:#c41d1d;"><strong>${face.name}</strong>${badge}</span>
            <button class="facetag-delete-face" data-index="${index}" style="padding:5px 12px; background:#ff4444; color:white; border:none; border-radius:3px; cursor:pointer; font-size:12px;">Supprimer</button>
          `);
        
        $list.append($item);
      });
      
      $('.facetag-delete-face').click(function() {
        var index = $(this).data('index');
        deleteFace(index);
      });
    }
    
    function deleteFace(index) {
      var face = faces[index];
      canvas.remove(face.rect);
      if (face.rect.label) canvas.remove(face.rect.label);
      canvas.renderAll();
      faces.splice(index, 1);
      updateFacesList();
    }
    
    // ==================== FERMER LA MODAL ====================
    function closeModal() {
      $('#facetag-modal, #facetag-modal-overlay').remove();
      $(document).off('keyup.facetag');
      canvas = null;
      faces = [];
      
    }
    
  }); // fin document.ready
})(jQuery);