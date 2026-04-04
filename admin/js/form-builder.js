jQuery( function ( $ ) {

    /* ── Helpers ────────────────────────────────────────────────────────── */

    function slugify( value ) {
        return value
            .toLowerCase()
            .replace( /[^a-z0-9]+/g, '_' )
            .replace( /^_+|_+$/g, '' );
    }

    function insertAtCursor( el, text ) {
        if ( ! el ) { return; }
        var start = el.selectionStart || 0;
        var end   = el.selectionEnd   || 0;
        var val   = el.value          || '';
        el.value = val.substring( 0, start ) + text + val.substring( end );
        el.selectionStart = el.selectionEnd = start + text.length;
        el.focus();
    }

    /**
     * Show or hide the options section inside a field card based on its type.
     */
    function toggleOptions( $item ) {
        var type  = $item.find( '.wpns-field-type' ).val();
        var $opts = $item.find( '.wpns-field-options' );
        if ( type === 'select' || type === 'radio' || type === 'checkbox' ) {
            $opts.show();
        } else {
            $opts.hide();
        }
    }

    /**
     * Refresh the collapsed header of a field card to reflect current values.
     */
    function refreshFieldHeader( $item ) {
        var label = $item.find( '.wpns-field-label' ).val();
        var type  = $item.find( '.wpns-field-type' ).val();
        var req   = $item.find( '.wpns-field-required' ).is( ':checked' );

        var $labelText = $item.find( '.wpns-field-label-text' );
        if ( label ) {
            $labelText.text( label ).removeClass( 'wpns-no-label' );
        } else {
            $labelText.text( '(no label)' ).addClass( 'wpns-no-label' );
        }

        $item.find( '.wpns-type-badge' )
            .text( type )
            .attr( 'class', 'wpns-type-badge wpns-type-' + type );

        $item.find( '.wpns-required-badge' ).toggle( req );
    }

    /* ── Sortable field list ─────────────────────────────────────────────── */

    if ( $( '#wpns-fields-list' ).length ) {
        $( '#wpns-fields-list' ).sortable( {
            handle: '.wpns-drag-handle',
            items:  '> .wpns-field-item:not(.wpns-field-template)',
            axis:   'y',
            tolerance: 'pointer',
            placeholder: 'wpns-sort-placeholder',
            start: function ( e, ui ) {
                ui.placeholder.css( {
                    height:     ui.item.outerHeight(),
                    background: '#f0f6fc',
                    border:     '2px dashed #0073aa',
                    display:    'block',
                    borderRadius: '3px',
                    marginBottom: '5px',
                } );
            },
        } );
    }

    // Initialise existing field cards
    $( '.wpns-field-item' ).not( '.wpns-field-template' ).each( function () {
        toggleOptions( $( this ) );
    } );

    /* ── Tab navigation ──────────────────────────────────────────────────── */

    $( document ).on( 'click', '.nav-tab', function ( e ) {
        e.preventDefault();
        var tab = $( this ).data( 'tab' );
        $( '.nav-tab' ).removeClass( 'nav-tab-active' );
        $( this ).addClass( 'nav-tab-active' );
        $( '.wpns-tab-content' ).hide();
        $( '.wpns-tab-content[data-tab="' + tab + '"]' ).show();
    } );

    /* ── Shortcode copy ──────────────────────────────────────────────────── */

    $( document ).on( 'click', '.wpns-copy-shortcode', function () {
        var $input  = $( '.wpns-shortcode-input' );
        var $notice = $( '.wpns-copy-confirm' );

        $input[0].select();
        try {
            document.execCommand( 'copy' );
            $notice.stop( true, true ).show().delay( 2000 ).fadeOut();
        } catch ( err ) {
            $input[0].focus();
        }
    } );

    /* ── Tag Generator: open panel ───────────────────────────────────────── */

    var activeTagType = null;

    $( document ).on( 'click', '.wpns-tag-btn', function () {
        activeTagType = $( this ).data( 'type' );

        // Update panel title badge
        $( '#wpns-tg-type-display' )
            .text( activeTagType )
            .attr( 'class', 'wpns-type-badge wpns-type-' + activeTagType );

        // Reset fields
        $( '#wpns-tg-label, #wpns-tg-name, #wpns-tg-placeholder' ).val( '' );
        $( '#wpns-tg-name' ).removeData( 'manual' );
        $( '#wpns-tg-required' ).prop( 'checked', false );

        // Hidden fields have no placeholder/required
        var isHidden = ( activeTagType === 'hidden' );
        $( '#wpns-tg-placeholder-row, #wpns-tg-required-row' ).toggle( ! isHidden );

        // Mark active button
        $( '.wpns-tag-btn' ).removeClass( 'wpns-tag-active' );
        $( this ).addClass( 'wpns-tag-active' );

        // Show panel and focus label
        $( '#wpns-tag-panel' ).slideDown( 150 );
        $( '#wpns-tg-label' ).focus();
    } );

    /* ── Tag Generator: close panel ─────────────────────────────────────── */

    $( document ).on( 'click', '#wpns-tg-cancel', function () {
        $( '#wpns-tag-panel' ).slideUp( 150 );
        $( '.wpns-tag-btn' ).removeClass( 'wpns-tag-active' );
        activeTagType = null;
    } );

    /* ── Tag Generator: auto-slug label ─────────────────────────────────── */

    $( document ).on( 'input', '#wpns-tg-label', function () {
        var $name = $( '#wpns-tg-name' );
        if ( ! $name.data( 'manual' ) ) {
            $name.val( slugify( $( this ).val() ) );
        }
    } );

    $( document ).on( 'input', '#wpns-tg-name', function () {
        $( this ).data( 'manual', true );
    } );

    /* ── Tag Generator: insert field ─────────────────────────────────────── */

    $( document ).on( 'click', '#wpns-tg-insert', function () {
        var label       = $( '#wpns-tg-label' ).val().trim();
        var name        = $( '#wpns-tg-name' ).val().trim();
        var placeholder = $( '#wpns-tg-placeholder' ).val().trim();
        var required    = $( '#wpns-tg-required' ).is( ':checked' );
        var type        = activeTagType || 'text';

        if ( ! name ) {
            $( '#wpns-tg-name' ).focus();
            $( '#wpns-tg-name' ).css( 'border-color', '#dc3232' );
            return;
        }
        $( '#wpns-tg-name' ).css( 'border-color', '' );

        // Clone the hidden template
        var $tpl = $( '.wpns-field-template' ).first().clone();
        $tpl.removeClass( 'wpns-field-template' ).show();

        // Populate field inputs
        $tpl.find( '.wpns-field-label' ).val( label );
        $tpl.find( '.wpns-field-name' ).val( name );
        $tpl.find( '.wpns-field-type' ).val( type );
        $tpl.find( '.wpns-field-placeholder' ).val( placeholder );
        $tpl.find( '.wpns-field-default' ).val( '' );
        $tpl.find( '.wpns-field-css' ).val( '' );
        $tpl.find( '.wpns-field-required' ).prop( 'checked', required );
        $tpl.find( '.wpns-options-list' ).empty();

        // Refresh collapsed header
        var $labelText = $tpl.find( '.wpns-field-label-text' );
        if ( label ) {
            $labelText.text( label ).removeClass( 'wpns-no-label' );
        } else {
            $labelText.text( '(no label)' ).addClass( 'wpns-no-label' );
        }
        $tpl.find( '.wpns-type-badge' )
            .text( type )
            .attr( 'class', 'wpns-type-badge wpns-type-' + type );
        $tpl.find( '.wpns-required-badge' ).toggle( required );

        toggleOptions( $tpl );

        $( '#wpns-fields-list' ).append( $tpl );
        $( '.wpns-empty-state' ).hide();

        // Close panel
        $( '#wpns-tag-panel' ).slideUp( 150 );
        $( '.wpns-tag-btn' ).removeClass( 'wpns-tag-active' );
        activeTagType = null;

        // Open the new card and scroll to it
        $tpl.find( '.wpns-field-body' ).show();
        $tpl.addClass( 'wpns-field-open' );
        $tpl.find( '.wpns-toggle-field' ).text( 'Close' );

        $( 'html, body' ).animate(
            { scrollTop: $tpl.offset().top - 80 },
            300
        );
    } );

    /* ── Field card: expand / collapse ──────────────────────────────────── */

    // "Edit" / "Close" button
    $( document ).on( 'click', '.wpns-toggle-field', function ( e ) {
        e.stopPropagation();
        var $item = $( this ).closest( '.wpns-field-item' );
        toggleFieldBody( $item );
    } );

    // Click anywhere on the header row
    $( document ).on( 'click', '.wpns-field-header', function ( e ) {
        // Ignore clicks on control buttons and drag handle
        if ( $( e.target ).closest( '.wpns-field-ctrl' ).length ) { return; }
        if ( $( e.target ).hasClass( 'wpns-drag-handle' ) )        { return; }
        var $item = $( this ).closest( '.wpns-field-item' );
        toggleFieldBody( $item );
    } );

    function toggleFieldBody( $item ) {
        var isOpen = $item.hasClass( 'wpns-field-open' );
        if ( isOpen ) {
            $item.find( '.wpns-field-body' ).slideUp( 150 );
            $item.removeClass( 'wpns-field-open' );
            $item.find( '.wpns-toggle-field' ).text( 'Edit' );
        } else {
            $item.find( '.wpns-field-body' ).slideDown( 150 );
            $item.addClass( 'wpns-field-open' );
            $item.find( '.wpns-toggle-field' ).text( 'Close' );
        }
    }

    /* ── Field card: delete ──────────────────────────────────────────────── */

    $( document ).on( 'click', '.wpns-remove-field', function ( e ) {
        e.stopPropagation();
        var $item = $( this ).closest( '.wpns-field-item' );
        $item.slideUp( 150, function () {
            $( this ).remove();
            if ( $( '#wpns-fields-list .wpns-field-item:not(.wpns-field-template)' ).length === 0 ) {
                $( '.wpns-empty-state' ).show();
            }
        } );
    } );

    /* ── Field card: live header updates ─────────────────────────────────── */

    $( document ).on( 'input', '.wpns-field-label', function () {
        var $item = $( this ).closest( '.wpns-field-item' );
        var $name = $item.find( '.wpns-field-name' );

        // Auto-slug field name
        if ( ! $name.data( 'manual' ) ) {
            $name.val( slugify( $( this ).val() ) );
        }
        refreshFieldHeader( $item );
    } );

    $( document ).on( 'input', '.wpns-field-name', function () {
        $( this ).data( 'manual', true );
    } );

    $( document ).on( 'change', '.wpns-field-type', function () {
        var $item = $( this ).closest( '.wpns-field-item' );
        toggleOptions( $item );
        refreshFieldHeader( $item );
    } );

    $( document ).on( 'change', '.wpns-field-required', function () {
        refreshFieldHeader( $( this ).closest( '.wpns-field-item' ) );
    } );

    /* ── Field options (select / radio / checkbox) ───────────────────────── */

    $( document ).on( 'click', '.wpns-add-option', function () {
        var $list = $( this ).siblings( '.wpns-options-list' );
        var row = '<div class="wpns-option-row">'
            + '<input type="text" class="wpns-option-label" placeholder="Label">'
            + '<input type="text" class="wpns-option-value" placeholder="Value">'
            + '<button type="button" class="button-link wpns-remove-option">Remove</button>'
            + '</div>';
        $list.append( row );
    } );

    $( document ).on( 'click', '.wpns-remove-option', function () {
        $( this ).closest( '.wpns-option-row' ).remove();
    } );

    /* ── Conditional logic toggle ───────────────────────────────────────────── */

    $( document ).on( 'change', '.wpns-condition-enable', function () {
        var $body = $( this ).closest( '.wpns-condition-section' ).find( '.wpns-condition-body' );
        if ( $( this ).is( ':checked' ) ) {
            $body.slideDown( 150 );
        } else {
            $body.slideUp( 150 );
        }
    } );

    /* ── Static values table ─────────────────────────────────────────────── */

    $( document ).on( 'click', '#wpns-add-static', function () {
        var row = '<tr class="wpns-static-row">'
            + '<td><input type="text" class="regular-text wpns-static-path"></td>'
            + '<td><input type="text" class="regular-text wpns-static-value"></td>'
            + '<td><button type="button" class="button-link button-link-delete wpns-remove-static">Remove</button></td>'
            + '</tr>';
        $( '#wpns-static-values-body' ).append( row );
    } );

    $( document ).on( 'click', '.wpns-remove-static', function () {
        $( this ).closest( 'tr' ).remove();
    } );

    /* ── Mail tokens ─────────────────────────────────────────────────────── */

    var lastMailField = null;

    $( document ).on( 'focus', '#wpns-email-subject, #wpns-email-body', function () {
        lastMailField = this;
    } );

    $( document ).on( 'click', '.wpns-email-token', function () {
        var token = $( this ).data( 'token' );
        var el    = lastMailField || document.getElementById( 'wpns-email-body' );
        insertAtCursor( el, token );
    } );

    /* ── NetSuite: test connection ───────────────────────────────────────── */

    $( document ).on( 'click', '#wpns-test-netsuite', function () {
        var $result = $( '.wpns-test-result' );
        $result.text( 'Testing…' ).removeClass( 'success error' );
        $.post( wpns_admin.ajax_url, {
            action:        'wpns_test_netsuite',
            nonce:         wpns_admin.nonce,
            credential_id: $( '#wpns-credential-id' ).val() || 0,
            form_id:       $( 'input[name="form_id"]' ).val() || 0,
        } ).done( function ( res ) {
            if ( res.success ) {
                $result.text( 'Connected ✓' ).addClass( 'success' );
            } else {
                $result.text( 'Failed ✗' ).addClass( 'error' );
            }
        } ).fail( function () {
            $result.text( 'Failed ✗' ).addClass( 'error' );
        } );
    } );

    /* ── Save form ───────────────────────────────────────────────────────── */

    $( '#wpns-form-edit' ).on( 'submit', function ( e ) {
        e.preventDefault();

        var $btn    = $( '#wpns-save-form' );
        var $status = $( '.wpns-save-status' );
        $btn.prop( 'disabled', true );
        $status.text( 'Saving…' ).removeClass( 'success error' );

        // Collect fields
        var fields = [];
        $( '#wpns-fields-list .wpns-field-item' ).not( '.wpns-field-template' ).each( function () {
            var $item   = $( this );
            var options = [];
            $item.find( '.wpns-option-row' ).each( function () {
                var $opt = $( this );
                options.push( {
                    label: $opt.find( '.wpns-option-label' ).val() || '',
                    value: $opt.find( '.wpns-option-value' ).val() || '',
                } );
            } );
            // Collect conditional logic.
            var conditionJson = '';
            if ( $item.find( '.wpns-condition-enable' ).is( ':checked' ) ) {
                var condField    = $item.find( '.wpns-condition-field' ).val()    || '';
                var condOperator = $item.find( '.wpns-condition-operator' ).val() || '=';
                var condValue    = $item.find( '.wpns-condition-value' ).val()    || '';
                if ( condField ) {
                    conditionJson = JSON.stringify( {
                        field:    condField,
                        operator: condOperator,
                        value:    condValue,
                    } );
                }
            }

            fields.push( {
                field_label:    $item.find( '.wpns-field-label' ).val()       || '',
                field_name:     $item.find( '.wpns-field-name' ).val()        || '',
                field_type:     $item.find( '.wpns-field-type' ).val()        || 'text',
                placeholder:    $item.find( '.wpns-field-placeholder' ).val() || '',
                default_val:    $item.find( '.wpns-field-default' ).val()     || '',
                css_class:      $item.find( '.wpns-field-css' ).val()         || '',
                is_required:    $item.find( '.wpns-field-required' ).is( ':checked' ) ? 1 : 0,
                options:        options,
                condition_json: conditionJson,
            } );
        } );

        // Collect static values
        var staticValues = {};
        $( '#wpns-static-values-body .wpns-static-row' ).each( function () {
            var path  = $( this ).find( '.wpns-static-path' ).val()  || '';
            var value = $( this ).find( '.wpns-static-value' ).val() || '';
            if ( path ) { staticValues[ path ] = value; }
        } );

        var data = {
            action:            'wpns_save_form',
            nonce:             wpns_admin.nonce,
            form_id:           parseInt( $( 'input[name="form_id"]' ).val(), 10 ) || 0,
            name:              $( '#wpns-form-name' ).val()              || '',
            description:       $( '#wpns-form-description' ).val()      || '',
            status:            $( '#wpns-form-status' ).val()           || 'active',
            success_message:   $( '#wpns-form-success' ).val()          || '',
            redirect_url:      $( '#wpns-form-redirect' ).val()         || '',
            fields_json:       JSON.stringify( fields ),
            credential_id:     $( '#wpns-credential-id' ).val()         || 0,
            payload_template:  $( '#wpns-payload-template' ).val()      || '',
            static_values_json: JSON.stringify( staticValues ),
            enable_netsuite:   $( 'input[name="enable_netsuite"]' ).is( ':checked' ) ? 1 : 0,
            enable_email:      $( 'input[name="enable_email"]' ).is( ':checked' )    ? 1 : 0,
            enable_recaptcha:  $( 'input[name="enable_recaptcha"]' ).is( ':checked' ) ? 1 : 0,
            email_from_name:   $( '#wpns-email-from-name' ).val()       || '',
            email_from_address: $( '#wpns-email-from-address' ).val()   || '',
            email_to:          $( '#wpns-email-to' ).val()              || '',
            email_cc:          $( '#wpns-email-cc' ).val()              || '',
            email_bcc:         $( '#wpns-email-bcc' ).val()             || '',
            email_subject:     $( '#wpns-email-subject' ).val()         || '',
            email_body:        $( '#wpns-email-body' ).val()            || '',
        };

        $.post( wpns_admin.ajax_url, data )
            .done( function ( res ) {
                if ( res.success ) {
                    var shortcode = res.data.shortcode;
                    $status.text( 'Saved! Shortcode: ' + shortcode ).addClass( 'success' );

                    // Update shortcode bar if newly created
                    if ( data.form_id === 0 && res.data.form_id ) {
                        $( 'input[name="form_id"]' ).val( res.data.form_id );
                        $( '.wpns-shortcode-input' ).val( shortcode );
                        if ( $( '.wpns-shortcode-bar' ).length === 0 ) {
                            var barHtml = '<div class="wpns-shortcode-bar">'
                                + '<label>Shortcode:</label>'
                                + '<input type="text" class="wpns-shortcode-input" readonly value="' + shortcode + '">'
                                + '<button type="button" class="button wpns-copy-shortcode">Copy</button>'
                                + '<span class="wpns-copy-confirm" style="display:none;">Copied!</span>'
                                + '</div>';
                            $( '.wpns-title-bar' ).after( barHtml );
                        }
                        // Update browser URL without reload
                        var newUrl = window.location.href;
                        if ( newUrl.indexOf( 'form_id=' ) === -1 ) {
                            newUrl += ( newUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'form_id=' + res.data.form_id;
                            window.history.replaceState( {}, document.title, newUrl );
                        }
                    }
                } else {
                    var msg = ( res.data && res.data.message ) ? res.data.message : 'Save failed.';
                    $status.text( msg ).addClass( 'error' );
                }
            } )
            .fail( function () {
                $status.text( 'Save failed.' ).addClass( 'error' );
            } )
            .always( function () {
                $btn.prop( 'disabled', false );
            } );
    } );

    /* ── Delete form (forms list page) ──────────────────────────────────── */

    $( document ).on( 'click', '.wpns-delete-form', function ( e ) {
        e.preventDefault();
        if ( ! confirm( 'Are you sure you want to delete this form? This cannot be undone.' ) ) {
            return;
        }
        var formId = $( this ).data( 'form-id' );
        $.post( wpns_admin.ajax_url, {
            action:  'wpns_delete_form',
            nonce:   wpns_admin.nonce,
            form_id: formId,
        } ).done( function ( res ) {
            if ( res.success ) {
                location.reload();
            } else {
                alert( ( res.data && res.data.message ) ? res.data.message : 'Delete failed.' );
            }
        } ).fail( function () {
            alert( 'Delete failed.' );
        } );
    } );

    /* ── View submission modal ───────────────────────────────────────────── */

    $( document ).on( 'click', '.wpns-view-submission', function () {
        var data = $( this ).data( 'submission' );
        if ( typeof data === 'string' ) {
            try { data = JSON.parse( data ); } catch ( e ) { data = { raw: data }; }
        }
        $( '#wpns-submission-modal .wpns-modal-pre' ).text( JSON.stringify( data, null, 2 ) );
        $( '#wpns-submission-modal' ).show();
    } );

    $( document ).on( 'click', '.wpns-modal-close', function () {
        $( this ).closest( '.wpns-modal' ).hide();
    } );

    /* ── Delete submission ───────────────────────────────────────────────── */

    $( document ).on( 'click', '.wpns-delete-submission', function () {
        if ( ! confirm( 'Delete this submission?' ) ) { return; }
        var submissionId = $( this ).data( 'submission-id' );
        $.post( wpns_admin.ajax_url, {
            action:        'wpns_delete_submission',
            nonce:         wpns_admin.nonce,
            submission_id: submissionId,
        } ).done( function ( res ) {
            if ( res.success ) {
                location.reload();
            } else {
                alert( ( res.data && res.data.message ) ? res.data.message : 'Delete failed.' );
            }
        } ).fail( function () {
            alert( 'Delete failed.' );
        } );
    } );

} );
