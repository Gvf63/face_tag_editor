// face_tag_write - Interface de dessin des visages
// Nécessite Fabric.js : https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js
console.log ("draw_face.js chargé");

(function($) {
  'use strict';
  
  $(document).ready(function() {
    
    var canvas = null;
    var currentRect = null;
    var faces = []; // Liste des visages créés
    
    // ==================== INITIALISATION ====================
    function initDrawingCanvas() {
      var $img = $('img#theMainImage, img#theImage').first();
      
      if ($img.length === 0) {
        console.error('Image non trouvée');
        return;
      }
      
      var $container = $img.parent();
      
      // Créer le canvas par-dessus l'image
      var canvasId = 'facetag-draw-canvas';
      
      // Supprimer le canvas existant si présent
      $('#' + canvasId).remove();
      
      // Créer le canvas
      var $canvas = $('<canvas>')
        .attr('id', canvasId)
        .css({
          position: 'absolute',
          top: $img.position().top,
          left: $img.position().left,
          zIndex: 1000,
          cursor: 'crosshair'
        });
      
      $container.css('position', 'relative').append($canvas);
      
      // Initialiser Fabric.js
      canvas = new fabric.Canvas(canvasId, {
        width: $img.width(),
        height: $img.height(),
        selection: false // Désactiver la sélection multiple
      });
      
      // Stocker les dimensions de l'image pour les conversions
      canvas.imageWidth = $img[0].naturalWidth;
      canvas.imageHeight = $img[0].naturalHeight;
      canvas.displayWidth = $img.width();
      canvas.displayHeight = $img.height();
      
      console.log('Canvas initialisé:', canvas.displayWidth, 'x', canvas.displayHeight);
      console.log('Image naturelle:', canvas.imageWidth, 'x', canvas.imageHeight);
      
      setupDrawingMode();
    }
    
    // ==================== MODE DESSIN ====================
    function setupDrawingMode() {
      var isDrawing = false;
      var startX, startY;
      
      // Désactiver le mode sélection par défaut
      canvas.selection = false;
      
      canvas.on('mouse:down', function(options) {
        if (options.target) {
          // Clic sur un rectangle existant -> mode édition
          return;
        }
        
        // Début du dessin
        isDrawing = true;
        var pointer = canvas.getPointer(options.e);
        startX = pointer.x;
        startY = pointer.y;
        
        // Créer un rectangle temporaire
        currentRect = new fabric.Rect({
          left: startX,
          top: startY,
          width: 0,
          height: 0,
          fill: 'rgba(0, 255, 0, 0.2)',
          stroke: '#00ff00',
          strokeWidth: 3,
          selectable: true,
          hasRotatingPoint: false
        });
        
        canvas.add(currentRect);
      });
      
      canvas.on('mouse:move', function(options) {
        if (!isDrawing) return;
        
        var pointer = canvas.getPointer(options.e);
        
        // Mettre à jour la taille du rectangle
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
        
        // Vérifier que le rectangle a une taille minimale
        if (currentRect.width < 10 || currentRect.height < 10) {
          canvas.remove(currentRect);
          currentRect = null;
          return;
        }
        
        // Demander le nom de la personne
        promptForName(currentRect);
      });
    }
    
    // ==================== DIALOGUE POUR NOMMER ====================
    function promptForName(rect) {
      var modal = `
        <div id="facetag-name-modal" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3); z-index:10001; min-width:400px;">
          <h3 style="margin-top:0;">Nommer la personne</h3>
          <input type="text" id="facetag-name-input" placeholder="Nom de la personne" style="width:100%; padding:10px; margin:15px 0; font-size:16px; border:2px solid #ddd; border-radius:4px;">
          <div style="margin-top:10px; color:#666; font-size:13px;">
            Personnes existantes : <span id="facetag-existing-names"></span>
          </div>
          <div style="text-align:right; margin-top:20px;">
            <button id="facetag-cancel-btn" style="padding:10px 20px; margin-right:10px; background:#ccc; border:none; border-radius:4px; cursor:pointer;">Annuler</button>
            <button id="facetag-save-btn" style="padding:10px 20px; background:#4CAF50; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">Valider</button>
          </div>
        </div>
        <div id="facetag-modal-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10000;"></div>
      `;
      
      $('body').append(modal);
      
      // Afficher les noms existants
      var existingNames = faces.map(f => f.name).filter((v, i, a) => a.indexOf(v) === i);
      $('#facetag-existing-names').text(existingNames.join(', ') || 'Aucun');
      
      // Focus sur l'input
      $('#facetag-name-input').focus();
      
      // Bouton Valider
      $('#facetag-save-btn').click(function() {
        var name = $('#facetag-name-input').val().trim();
        
        if (!name) {
          alert('Veuillez entrer un nom');
          return;
        }
        
        // Ajouter le label sur le rectangle
        addLabelToRect(rect, name);
        
        // Sauvegarder les données du visage
        saveFaceData(rect, name);
        
        // Fermer le modal
        closeNameModal();
      });
      
      // Bouton Annuler
      $('#facetag-cancel-btn, #facetag-modal-overlay').click(function() {
        // Supprimer le rectangle
        canvas.remove(rect);
        canvas.renderAll();
        closeNameModal();
      });
      
      // Validation avec Entrée
      $('#facetag-name-input').keypress(function(e) {
        if (e.which === 13) {
          $('#facetag-save-btn').click();
        }
      });
    }
    
    function closeNameModal() {
      $('#facetag-name-modal, #facetag-modal-overlay').remove();
      currentRect = null;
    }
    
    // ==================== AJOUT DU LABEL ====================
    function addLabelToRect(rect, name) {
      var label = new fabric.Text(name, {
        left: rect.left,
        top: rect.top - 20,
        fontSize: 14,
        fill: '#ffffff',
        backgroundColor: 'rgba(0, 0, 0, 0.7)',
        padding: 3,
        selectable: false
      });
      
      canvas.add(label);
      
      // Lier le label au rectangle
      rect.label = label;
      
      // Mettre à jour le label quand le rectangle bouge
      rect.on('moving', function() {
        label.set({
          left: rect.left,
          top: rect.top - 20
        });
        canvas.renderAll();
      });
      
      rect.on('scaling', function() {
        label.set({
          left: rect.left * rect.scaleX,
          top: (rect.top * rect.scaleY) - 20
        });
        canvas.renderAll();
      });
    }
    
    // ==================== SAUVEGARDE DES DONNÉES ====================
    function saveFaceData(rect, name) {
      // Convertir les coordonnées canvas -> image -> XMP (normalisées)
      var x = rect.left / canvas.displayWidth;
      var y = rect.top / canvas.displayHeight;
      var w = (rect.width * (rect.scaleX || 1)) / canvas.displayWidth;
      var h = (rect.height * (rect.scaleY || 1)) / canvas.displayHeight;
      
      // Format mwg-rs : x,y sont le CENTRE
      var centerX = x + (w / 2);
      var centerY = y + (h / 2);
      
      var face = {
        name: name,
        x: centerX,
        y: centerY,
        w: w,
        h: h,
        format: 'mwg-rs',
        rect: rect // Référence au rectangle Fabric
      };
      
      faces.push(face);
      
      console.log('Visage ajouté:', face);
      
      // Mettre à jour la liste des visages
      updateFacesList();
    }
    
    // ==================== LISTE DES VISAGES ====================
    function updateFacesList() {
      var $list = $('#facetag-faces-list');
      $list.empty();
      
      if (faces.length === 0) {
        $list.html('<p style="color:#999;">Aucun visage tagué</p>');
        return;
      }
      
      faces.forEach(function(face, index) {
        var $item = $('<div class="facetag-face-item">')
          .css({
            padding: '8px',
            margin: '5px 0',
            background: '#f5f5f5',
            borderRadius: '4px',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center'
          })
          .html(`
            <span><strong>${face.name}</strong></span>
            <button class="facetag-delete-face" data-index="${index}" style="padding:4px 10px; background:#ff4444; color:white; border:none; border-radius:3px; cursor:pointer;">Supprimer</button>
          `);
        
        $list.append($item);
      });
      
      // Bouton de suppression
      $('.facetag-delete-face').click(function() {
        var index = $(this).data('index');
        deleteFace(index);
      });
    }
    
    function deleteFace(index) {
      var face = faces[index];
      
      // Supprimer le rectangle du canvas
      canvas.remove(face.rect);
      if (face.rect.label) {
        canvas.remove(face.rect.label);
      }
      canvas.renderAll();
      
      // Supprimer de la liste
      faces.splice(index, 1);
      
      updateFacesList();
    }
    
    // ==================== SAUVEGARDE DANS XMP ====================
    $('#facetag-save-xmp').click(function() {
      if (faces.length === 0) {
        alert('Aucun visage à enregistrer');
        return;
      }
      
      var imageId = $(this).data('image-id');
      var ajaxUrl = $(this).data('ajax-url');
      
      // Préparer les données à envoyer
      var facesData = faces.map(function(face) {
        return {
          name: face.name,
          x: face.x,
          y: face.y,
          w: face.w,
          h: face.h
        };
      });
      
      console.log('Envoi des données:', facesData);
      
      // Désactiver le bouton
      $(this).prop('disabled', true).text('Enregistrement...');
      
      // Envoyer via AJAX
      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
          image_id: imageId,
          faces: JSON.stringify(facesData)
        },
        success: function(data) {
          if (data.stat === 'ok') {
            alert('Visages enregistrés avec succès !');
            // Recharger la page ou vider le canvas
            location.reload();
          } else {
            alert('Erreur: ' + (data.message || 'Erreur inconnue'));
          }
        },
        error: function(xhr, status, error) {
          alert('Erreur de connexion: ' + error);
        },
        complete: function() {
          $('#facetag-save-xmp').prop('disabled', false).text('💾 Enregistrer dans XMP');
        }
      });
    });
    
    // ==================== BOUTON TOUT EFFACER ====================
    $('#facetag-clear-all').click(function() {
      if (faces.length === 0) {
        alert('Aucun visage à effacer');
        return;
      }
      
      if (!confirm('Êtes-vous sûr de vouloir effacer tous les rectangles ?')) {
        return;
      }
      
      // Supprimer tous les rectangles du canvas
      faces.forEach(function(face) {
        canvas.remove(face.rect);
        if (face.rect.label) {
          canvas.remove(face.rect.label);
        }
      });
      
      canvas.renderAll();
      
      // Vider la liste
      faces = [];
      updateFacesList();
    });
    
    // ==================== INITIALISATION AU CHARGEMENT ====================
    // Attendre que l'image soit chargée
    $('img#theMainImage, img#theImage').first().on('load', function() {
      initDrawingCanvas();
    });
    
    // Si l'image est déjà chargée
    if ($('img#theMainImage, img#theImage').first()[0].complete) {
      initDrawingCanvas();
    }
    
  }); // fin document.ready
})(jQuery);