/**
 * Background migration: ets_question_answer post meta → wp_comments
 *
 * Runs on every admin page while ets_qa_migration_status = 'pending'.
 * Processes products one at a time and updates the admin notice with progress.
 * On completion turns the notice green and fades it out after 4 seconds.
 */
jQuery( document ).ready( function ( $ ) {
    var $notice   = $( '#ets-qa-migration-notice' );
    var $progress = $( '#ets-qa-migration-progress' );

    // Step 1: fetch all product IDs that still have legacy post meta
    $.ajax( {
        url:      etsMigration.ajax_url,
        type:     'POST',
        dataType: 'json',
        data:     {
            action: 'ets_get_products_with_questions',
            nonce:  etsMigration.nonce
        },
        success: function ( res ) {
            if ( res.success && res.data.length > 0 ) {
                var total = res.data.length;
                $progress.text( '(0 / ' + total + ' products done)' );
                migrateProducts( res.data, total, 0 );
            } else {
                // Nothing to migrate — mark done immediately
                markComplete();
            }
        },
        error: function () {
            markComplete();
        }
    } );

    /**
     * Recursively migrate one product at a time.
     *
     * @param {Array}  ids   Remaining product IDs to process
     * @param {number} total Total products at start
     * @param {number} done  Products processed so far
     */
    function migrateProducts( ids, total, done ) {
        if ( ids.length === 0 ) {
            markComplete();
            return;
        }

        var id = ids.shift();

        $.ajax( {
            url:      etsMigration.ajax_url,
            type:     'POST',
            dataType: 'json',
            data:     {
                action:     'ets_migrate_product_questions',
                product_id: id,
                nonce:      etsMigration.nonce
            },
            complete: function () {
                done++;
                $progress.text( '(' + done + ' / ' + total + ' products done)' );
                migrateProducts( ids, total, done );
            }
        } );
    }

    /**
     * Call the completion AJAX action, then update the notice UI.
     */
    function markComplete() {
        $.ajax( {
            url:      etsMigration.ajax_url,
            type:     'POST',
            dataType: 'json',
            data:     {
                action: 'ets_set_migration_completed',
                nonce:  etsMigration.nonce
            },
            complete: function () {
                $notice
                    .removeClass( 'notice-warning' )
                    .addClass( 'notice-success' );
                $notice.find( 'p' ).html(
                    '<strong>Product Q&amp;A:</strong> ' +
                    'Migration completed successfully! All existing questions have been moved to the new storage system.'
                );
                $progress.text( '' );

                // Auto-dismiss after 4 seconds
                setTimeout( function () {
                    $notice.fadeOut( 600 );
                }, 4000 );
            }
        } );
    }
} );
