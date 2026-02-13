jQuery(document).ready(function($) {

    // Function to refresh index information fields
    function refreshIndexFields() {
        $.ajax({
            url: aiProductFinderAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_product_finder_get_index_info',
                nonce: aiProductFinderAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#active_index_name').val(response.data.index_name);
                    $('#pinecone_index_url').val(response.data.index_url);
                }
            }
        });
    }

    // Create Index button handler
    $('#create-index-btn').on('click', function() {
        const button = $(this);
        const statusDiv = $('#sync-status');

        button.prop('disabled', true).text('Creating Index...');
        statusDiv.removeClass('success error').addClass('loading')
               .empty().append($('<p>').text('Creating Pinecone index and uploading products...'));

        $.ajax({
            url: aiProductFinderAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_product_finder_create_index',
                nonce: aiProductFinderAjax.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Create Index');

                if (response.success) {
                    statusDiv.removeClass('loading error').addClass('success')
                           .empty().append($('<p>').append($('<strong>').text('Success! ')).append(document.createTextNode(response.data.message)));

                    // Refresh index info fields
                    refreshIndexFields();
                } else {
                    statusDiv.removeClass('loading success').addClass('error')
                           .empty().append($('<p>').append($('<strong>').text('Error: ')).append(document.createTextNode(response.data.message)));
                }
            },
            error: function() {
                button.prop('disabled', false).text('Create Index');
                statusDiv.removeClass('loading success').addClass('error')
                       .empty().append($('<p>').append($('<strong>').text('Error: ')).append(document.createTextNode('Failed to communicate with server.')));
            }
        });
    });

    // Update Index button handler
    $('#update-index-btn').on('click', function() {
        const button = $(this);
        const statusDiv = $('#sync-status');

        button.prop('disabled', true).text('Updating Index...');
        statusDiv.removeClass('success error').addClass('loading')
               .empty().append($('<p>').text('Updating Pinecone index with current products...'));

        $.ajax({
            url: aiProductFinderAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_product_finder_update_index',
                nonce: aiProductFinderAjax.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Update Index');

                if (response.success) {
                    statusDiv.removeClass('loading error').addClass('success')
                           .empty().append($('<p>').append($('<strong>').text('Success! ')).append(document.createTextNode(response.data.message)));
                } else {
                    statusDiv.removeClass('loading success').addClass('error')
                           .empty().append($('<p>').append($('<strong>').text('Error: ')).append(document.createTextNode(response.data.message)));
                }
            },
            error: function() {
                button.prop('disabled', false).text('Update Index');
                statusDiv.removeClass('loading success').addClass('error')
                       .empty().append($('<p>').append($('<strong>').text('Error: ')).append(document.createTextNode('Failed to communicate with server.')));
            }
        });
    });

});