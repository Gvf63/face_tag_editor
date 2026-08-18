jQuery(document).ready(function($) {
    
    // Fonction de traduction
    function _(key) {
        return (typeof adminConfigLang !== 'undefined' && adminConfigLang[key]) ? adminConfigLang[key] : key;
    }
    
    let currentFiles = [];
    let currentDateFilter = null;
    let currentDirectoryFilter = null;
    
    // Rechercher les fichiers .original
    $('#btn_find_originals').on('click', function() {
        findOriginals();
    });
    
    // Appliquer les filtres
    $('#btn_apply_filter').on('click', function() {
        const dateFilter = $('#date_filter').val();
        const directoryFilter = $('#directory_filter').val().trim();
        
        if (!dateFilter && !directoryFilter) {
            alert(_('select_at_least_one_filter'));
            return;
        }
        
        currentDateFilter = dateFilter || null;
        currentDirectoryFilter = directoryFilter || null;
        findOriginals(currentDateFilter, currentDirectoryFilter);
    });
    
    // Effacer les filtres
    $('#btn_clear_filter').on('click', function() {
        currentDateFilter = null;
        currentDirectoryFilter = null;
        $('#date_filter').val('');
        $('#directory_filter').val('');
        findOriginals();
    });
    
    // Supprimer les fichiers
    $('#btn_delete_originals').on('click', function() {
        if (currentFiles.length === 0) {
            alert(_('no_file_to_delete'));
            return;
        }
        
        const message = _('confirm_delete') + ' ' + currentFiles.length + ' ' + _('confirm_delete_suffix') + '\n\n' +
                       _('irreversible');
        
        if (confirm(message)) {
            deleteOriginals();
        }
    });
    
    /**
     * Recherche les fichiers .original
     */
    function findOriginals(dateFilter, directoryFilter) {
        const $status = $('#search_status');
        $status.removeClass('success error').addClass('loading')
               .text(_('search_in_progress'));
        
        const data = {
            action: 'find_originals'
        };
        
        if (dateFilter) {
            data.date_filter = dateFilter;
        }
        
        if (directoryFilter) {
            data.directory_filter = directoryFilter;
        }
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                currentFiles = response.files.map(f => f.full_path);
                displayResults(response);
                
                $status.removeClass('loading').addClass('success')
                       .text(response.total_count + ' ' + _('files_found'));
                
                $('#filter_section').show();
                $('#results_section').show();
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors de la recherche:', error);
                $status.removeClass('loading').addClass('error')
                       .text(_('search_error'));
                currentFiles = [];
            }
        });
    }
    
    /**
     * Affiche les résultats
     */
    function displayResults(data) {
        const $summary = $('#stats_summary');
        const $list = $('#originals_list');
        
        // Afficher le résumé
        let summaryHtml = data.total_count + ' ' + _('files_found') + ' - ' +
                         _('total_space') + ' ' + data.total_size_formatted;
        
        const filters = [];
        if (currentDateFilter) {
            filters.push(_('created_before') + ' ' + currentDateFilter);
        }
        if (currentDirectoryFilter) {
            filters.push(_('directory_contains') + ' "' + currentDirectoryFilter + '"');
        }
        
        if (filters.length > 0) {
            summaryHtml += ' (' + filters.join(', ') + ')';
        }
        
        $summary.html(summaryHtml);
        
        // Afficher la liste
        $list.empty();
        
        if (data.files.length === 0) {
            $list.html('<tr><td colspan="4" style="text-align:center; font-style:italic;">' + _('no_file_found') + '</td></tr>');
            $('#btn_delete_originals').prop('disabled', true);
        } else {
            data.files.forEach(function(file) {
                const $filenameCell = $('<td>');
                if (file.picture_url) {
                    // Clic sur le nom de fichier = ouvrir la photo actuelle dans Piwigo (pas le .original)
                    $('<a>')
                        .attr('href', file.picture_url)
                        .attr('target', '_blank')
                        .attr('rel', 'noopener')
                        .attr('title', _('view_current_photo'))
                        .text(file.filename)
                        .appendTo($filenameCell);
                } else {
                    $filenameCell.text(file.filename);
                }

                const row = $('<tr>')
                    .append($('<td>').text(file.directory))
                    .append($filenameCell)
                    .append($('<td>').text(file.date_formatted))
                    .append($('<td>').css('text-align', 'right').text(file.size_formatted));
                $list.append(row);
            });
            $('#btn_delete_originals').prop('disabled', false);
        }
    }
    
    /**
     * Supprime les fichiers .original
     */
    function deleteOriginals() {
        const $status = $('#search_status');
        $status.removeClass('success error').addClass('loading')
               .text(_('deletion_in_progress'));
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'delete_originals',
                files: JSON.stringify(currentFiles)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $status.removeClass('loading').addClass('success')
                           .text(response.deleted + ' ' + _('deleted') + ', ' + response.failed + ' ' + _('failures'));
                    
                    // Relancer la recherche pour mettre à jour la liste
                    setTimeout(function() {
                        findOriginals(currentDateFilter, currentDirectoryFilter);
                    }, 1000);
                } else {
                    $status.removeClass('loading').addClass('error')
                           .text(response.message);
                    
                    if (response.errors.length > 0) {
                        console.error('Erreurs:', response.errors);
                        alert(_('deletion_errors') + '\n' + response.errors.join('\n'));
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors de la suppression:', error);
                console.error('Response:', xhr.responseText);
                $status.removeClass('loading').addClass('error')
                       .text(_('search_error'));
            }
        });
    }
});