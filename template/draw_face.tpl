{* Template pour l'éditeur de visages *}
{if $FACETAG_EDIT_MODE}

<div id="facetag-editor" style="margin: 20px auto; max-width: 1400px; padding: 0 20px;">
  <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <h3 style="margin-top: 0; color: #333;">✏️ Éditeur de visages</h3>
    <p style="color: #666; margin-bottom: 15px;">
      <strong>Instructions :</strong> Cliquez et faites glisser sur l'image ci-dessous pour dessiner un rectangle autour d'un visage, 
      puis entrez le nom de la personne.
    </p>
    
    <div style="margin-top: 15px;">
      <button id="facetag-save-xmp" 
              data-image-id="{$FACETAG_IMAGE_ID}" 
              data-ajax-url="{$FACETAG_SAVE_URL}"
              style="padding: 12px 24px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold;">
        💾 Enregistrer dans XMP
      </button>
      
      <button id="facetag-clear-all" 
              style="padding: 12px 24px; margin-left: 10px; background: #ff4444; color: white; border: none; border-radius: 4px; cursor: pointer;">
        🗑️ Tout effacer
      </button>
      
      <a href="{$U_PHOTO}" 
         style="padding: 12px 24px; margin-left: 10px; background: #666; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">
        ← Retour
      </a>
    </div>
  </div>
  
  <div style="display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start;">
    <div id="facetag-canvas-container" style="background: #000; padding: 10px; border-radius: 4px;">
      {* L'image et le canvas seront ici *}
    </div>
    
    <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
      <h4 style="margin-top: 0; color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 8px;">
        👤 Visages tagués
      </h4>
      <div id="facetag-faces-list" style="max-height: 500px; overflow-y: auto;">
        <p style="color:#999; text-align: center; padding: 20px;">Aucun visage tagué</p>
      </div>
    </div>
  </div>
</div>

{/if}

{* Charger Fabric.js depuis CDN *}
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>

{* Charger notre script de dessin *}
<link rel="stylesheet" href="{$FACETAG_PATH}template/draw_faces.css">
<script src="{$FACETAG_PATH}template/draw_faces.js"></script>