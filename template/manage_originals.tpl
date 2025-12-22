<div id="manage_originals_tab">
    <h2>{'Gestion des fichiers de sauvegarde .original'|@translate}</h2>
    
    <!-- Configuration -->
    <fieldset>
        <legend>{'Configuration'|@translate}</legend>
        <form method="post" action="{$FACETAGWRITE_ADMIN_URL}" id="config_form">
            <input type="hidden" name="action" value="save_config">
            <label>
                <input type="checkbox" name="save_original" value="1" {if $SAVE_ORIGINAL}checked{/if}>
                {'Créer un fichier de sauvegarde .original lors du premier enregistrement de tags'|@translate}
            </label>
            <p class="formButtons">
                <button type="submit" class="buttonLike">{'Enregistrer'|@translate}</button>
            </p>
        </form>
    </fieldset>
    
    <!-- Recherche et gestion -->
    <fieldset>
        <legend>{'Rechercher et gérer les fichiers .original'|@translate}</legend>
        
        <div class="search_section">
            <button type="button" id="btn_find_originals" class="buttonLike">
                {'Rechercher les fichiers .original'|@translate}
            </button>
            <span id="search_status"></span>
        </div>
        
        <div id="filter_section" style="display:none; margin-top:15px;">
            <label>
                {'Filtrer par date de création (avant le) :'|@translate} :
                <input type="date" id="date_filter" name="date_filter">
            </label>
            <label style="margin-left:20px;">
                {'Filtrer par répertoire (contient) :'|@translate} :
                <input type="text" id="directory_filter" name="directory_filter" placeholder="ex: 2025, Scan, Alpes...">
            </label>
            <button type="button" id="btn_apply_filter" class="buttonLike">
                {'Appliquer les filtres'|@translate}
            </button>
            <button type="button" id="btn_clear_filter" class="buttonLike">
                {'Effacer les filtres'|@translate}
            </button>
        </div>
        
        <div id="results_section" style="display:none; margin-top:20px;">
            <div id="stats_summary"></div>
            
            <div style="margin-top:15px;">
                <button type="button" id="btn_delete_originals" class="buttonLike" style="background-color:#d9534f; color:white;">
                    {'Supprimer les fichiers listés'|@translate}
                </button>
            </div>
            
            <table class="table2" style="margin-top:15px;">
                <thead>
                    <tr>
                        <th>{'Répertoire'|@translate}</th>
                        <th>{'Nom du fichier'|@translate}</th>
                        <th>{'Date de création'|@translate}</th>
                        <th style="text-align:right;">{'Taille'|@translate}</th>
                    </tr>
                </thead>
                <tbody id="originals_list">
                </tbody>
            </table>
        </div>
    </fieldset>
</div>

<style>

#manage_originals_tab .formButtons,
#manage_originals_tab .search_section,
#manage_originals_tab #filter_section,
#manage_originals_tab #results_section > div {
    text-align: left;
}

#manage_originals_tab .table2 {
    text-align: initial;
}

#manage_originals_tab fieldset {
    margin-bottom: 20px;
    padding: 15px;
}

#manage_originals_tab legend {
    font-weight: bold;
    padding: 0 10px;
}


#manage_originals_tab .search_section {
    margin-bottom: 10px;
}

#search_status {
    margin-left: 15px;
    font-style: italic;
    color: #666;
}

#search_status.loading {
    color: #0066cc;
}

#search_status.success {
    color: #28a745;
}

#search_status.error {
    color: #dc3545;
}

#stats_summary {
    padding: 10px 15px;
    background-color: #f0f8ff;
    border-left: 4px solid #4CAF50;
    font-weight: bold;
}

.table2 {
    width: 100%;
    border-collapse: collapse;
}

.table2 th, .table2 td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.table2 th {
    background-color: #f2f2f2;
    font-weight: bold;
}

.table2 tr:hover {
    background-color: #f5f5f5;
}

#filter_section label {
    margin-right: 10px;
}

#filter_section input[type="date"],
#filter_section input[type="text"] {
    margin: 0 10px;
}
</style>