jQuery( function ( $ ) {

    /* ── CRM type switching ────────────────────────────────────────────────── */

    function switchCrmType( type ) {
        // Show the matching section, hide the rest.
        $( '.wpns-crm-fields' ).hide();
        $( '.wpns-crm-fields[data-crm="' + type + '"]' ).show();
    }

    $( '#wpns-crm-type' ).on( 'change', function () {
        switchCrmType( $( this ).val() );
    } );

    // Init on page load.
    switchCrmType( $( '#wpns-crm-type' ).val() || 'netsuite' );

    /* ── Show / hide secret fields ─────────────────────────────────────────── */

    $( document ).on( 'click', '.wpns-toggle-secret', function ( e ) {
        e.preventDefault();
        var $input = $( this ).siblings( 'input' );
        var isPass = $input.attr( 'type' ) === 'password';
        $input.attr( 'type', isPass ? 'text' : 'password' );
        $( this ).text( isPass ? 'Hide' : 'Show' );
    } );

    /* ── Helpers ───────────────────────────────────────────────────────────── */

    function fillForm( cred ) {
        var type = cred.crm_type || 'netsuite';
        $( '#wpns-credential-id' ).val( cred.id || 0 );
        $( '#wpns-crm-type' ).val( type ).trigger( 'change' );
        $( '#wpns-profile-name' ).val( cred.profile_name || '' );

        // NetSuite fields.
        $( '#wpns-account-id' ).val( cred.account_id || '' );
        $( '#wpns-realm' ).val( cred.realm || '' );
        $( '#wpns-consumer-key' ).val( cred.consumer_key || '' );
        $( '#wpns-consumer-secret' ).val( cred.consumer_secret || '' );
        $( '#wpns-token-key' ).val( cred.token_key || '' );
        $( '#wpns-token-secret' ).val( cred.token_secret || '' );
        $( '#wpns-script-id' ).val( cred.script_id || '' );
        $( '#wpns-deploy-id' ).val( cred.deploy_id || '1' );

        // Non-NetSuite: parse config_json and fill the right inputs.
        var config = {};
        if ( cred.config_json ) {
            try { config = JSON.parse( cred.config_json ); } catch ( e ) { /* ignore */ }
        }
        $.each( config, function ( key, val ) {
            $( '[name="config[' + key + ']"]' ).val( val );
        } );
    }

    function resetForm() {
        $( '#wpns-credential-form' )[ 0 ].reset();
        $( '#wpns-credential-id' ).val( 0 );
        $( '#wpns-deploy-id' ).val( '1' );
        switchCrmType( 'netsuite' );
        $( '#wpns-credential-form-title' ).text( 'Add New Connection' );
        $( '.wpns-credential-status' ).text( '' );
        // Reset all secret fields back to password type.
        $( '#wpns-credential-form input[type="text"]' ).filter( '[id*="secret"],[id*="token"],[id*="key"],[id*="access"]' ).attr( 'type', 'password' );
        $( '.wpns-toggle-secret' ).text( 'Show' );
    }

    /* ── Reset / Cancel ────────────────────────────────────────────────────── */

    $( document ).on( 'click', '#wpns-reset-credential', function () {
        resetForm();
    } );

    /* ── Edit button ───────────────────────────────────────────────────────── */

    $( document ).on( 'click', '.wpns-edit-credential', function () {
        var $row = $( this ).closest( 'tr' );
        var raw  = $row.attr( 'data-credential' );
        if ( ! raw ) { return; }

        try {
            var cred = JSON.parse( raw );
            fillForm( cred );
            $( '#wpns-credential-form-title' ).text( 'Edit Connection: ' + ( cred.profile_name || '' ) );
            $( '.wpns-credential-status' ).text( '' );
            var $title = $( '#wpns-credential-form-title' );
            if ( $title.length ) {
                $( 'html, body' ).animate( { scrollTop: $title.offset().top - 40 }, 300 );
            }
        } catch ( e ) {
            alert( 'Failed to load credential data.' );
        }
    } );

    /* ── Save form ─────────────────────────────────────────────────────────── */

    $( '#wpns-credential-form' ).on( 'submit', function ( e ) {
        e.preventDefault();
        var $status  = $( '.wpns-credential-status' );
        var $btn     = $( '#wpns-save-credential' );
        $status.text( 'Saving…' );
        $btn.prop( 'disabled', true );

        var type = $( '#wpns-crm-type' ).val();

        // Collect config fields for the active CRM section.
        var config = {};
        $( '.wpns-crm-fields[data-crm="' + type + '"] [name^="config["]' ).each( function () {
            var match = $( this ).attr( 'name' ).match( /config\[(.+)\]/ );
            if ( match ) {
                config[ match[ 1 ] ] = $( this ).val() || '';
            }
        } );

        var data = {
            action:        'wpns_save_credential',
            nonce:         wpns_admin.nonce,
            credential_id: $( '#wpns-credential-id' ).val() || 0,
            crm_type:      type,
            profile_name:  $( '#wpns-profile-name' ).val() || '',
            // NetSuite fields (empty strings for non-NS types).
            account_id:      $( '#wpns-account-id' ).val()      || '',
            realm:           $( '#wpns-realm' ).val()           || '',
            consumer_key:    $( '#wpns-consumer-key' ).val()    || '',
            consumer_secret: $( '#wpns-consumer-secret' ).val() || '',
            token_key:       $( '#wpns-token-key' ).val()       || '',
            token_secret:    $( '#wpns-token-secret' ).val()    || '',
            script_id:       $( '#wpns-script-id' ).val()       || '',
            deploy_id:       $( '#wpns-deploy-id' ).val()       || '1',
            config:          config,
        };

        $.post( wpns_admin.ajax_url, data )
            .done( function ( res ) {
                if ( res.success ) {
                    $status.text( 'Saved successfully.' );
                    setTimeout( function () { location.reload(); }, 800 );
                } else {
                    $status.text( res.data && res.data.message ? res.data.message : 'Save failed.' );
                    $btn.prop( 'disabled', false );
                }
            } )
            .fail( function () {
                $status.text( 'Save failed.' );
                $btn.prop( 'disabled', false );
            } );
    } );

    /* ── Delete ────────────────────────────────────────────────────────────── */

    $( document ).on( 'click', '.wpns-delete-credential', function () {
        if ( ! confirm( 'Delete this connection? This cannot be undone.' ) ) { return; }
        var id = $( this ).data( 'credential-id' );
        $.post( wpns_admin.ajax_url, {
            action: 'wpns_delete_credential',
            nonce:  wpns_admin.nonce,
            credential_id: id,
        } ).done( function ( res ) {
            if ( res.success ) {
                location.reload();
            } else {
                alert( res.data && res.data.message ? res.data.message : 'Delete failed.' );
            }
        } ).fail( function () {
            alert( 'Delete failed.' );
        } );
    } );

    /* ── Test connection ───────────────────────────────────────────────────── */

    $( document ).on( 'click', '.wpns-test-credential', function () {
        var $btn = $( this );
        var id   = $btn.data( 'credential-id' );
        $btn.text( 'Testing…' ).prop( 'disabled', true );
        $.post( wpns_admin.ajax_url, {
            action:        'wpns_test_netsuite',
            nonce:         wpns_admin.nonce,
            credential_id: id,
        } ).done( function ( res ) {
            if ( res.success ) {
                alert( 'Connection successful!' );
            } else {
                var msg = res.data && res.data.message ? res.data.message : 'Connection failed.';
                alert( msg );
            }
        } ).fail( function () {
            alert( 'Connection test failed.' );
        } ).always( function () {
            $btn.text( 'Test' ).prop( 'disabled', false );
        } );
    } );

} );
