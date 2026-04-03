jQuery( function ( $ ) {

    /**
     * Clear all inline field errors in a form.
     */
    function clearErrors( $form ) {
        $form.find( '.wpns-field-invalid' ).removeClass( 'wpns-field-invalid' );
        $form.find( '.wpns-field-error' ).text( '' ).removeClass( 'visible' );
    }

    /**
     * Show an inline error message below a specific field.
     */
    function showFieldError( $field, message ) {
        $field.addClass( 'wpns-field-invalid' );
        var $wrap = $field.closest( '.wpns-field' );
        $wrap.find( '.wpns-field-error' ).text( message ).addClass( 'visible' );
    }

    /**
     * Validate all required fields; return true if form is valid, false otherwise.
     * Populates inline errors as a side effect.
     */
    function validateForm( $form ) {
        var valid = true;

        $form.find( '[required]' ).each( function () {
            var $el    = $( this );
            var label  = $el.data( 'label' ) || 'This field';
            var name   = $el.attr( 'name' );

            if ( $el.is( ':radio' ) ) {
                // Only check once per radio group
                if ( $form.find( '[name="' + name + '"]:checked' ).length === 0 ) {
                    showFieldError( $el, label + ' is required.' );
                    valid = false;
                }
                return; // continue .each
            }

            if ( $el.is( ':checkbox' ) ) {
                var $group = $form.find( '[name="' + name + '"]' );
                if ( $group.filter( ':checked' ).length === 0 ) {
                    showFieldError( $el, label + ' is required.' );
                    valid = false;
                }
                return;
            }

            var val = $.trim( $el.val() || '' );
            if ( val === '' ) {
                showFieldError( $el, label + ' is required.' );
                valid = false;
            }
        } );

        return valid;
    }

    $( document ).on( 'submit', '.wpns-form', function ( e ) {
        e.preventDefault();

        var $form = $( this );
        var $btn  = $form.find( '.wpns-submit' );
        var $resp = $form.find( '.wpns-response' );

        clearErrors( $form );
        $resp.html( '' );

        if ( ! validateForm( $form ) ) {
            // Scroll to first error
            var $first = $form.find( '.wpns-field-invalid' ).first();
            if ( $first.length ) {
                $( 'html, body' ).animate(
                    { scrollTop: $first.closest( '.wpns-field' ).offset().top - 80 },
                    250
                );
            }
            return;
        }

        // ── Show loading state ────────────────────────────────────────────
        var origLabel = $btn.text();
        $btn.prop( 'disabled', true ).html(
            '<span class="wpns-spinner"></span>' + origLabel
        );

        // ── Build form data ───────────────────────────────────────────────
        var formData = new FormData( $form[0] );
        formData.append( 'action', 'wpns_submit_form' );
        formData.append( 'nonce',  wpns_ajax.nonce );

        [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ]
            .forEach( function ( k ) {
                formData.append( k, localStorage.getItem( k ) || '' );
            } );

        $.ajax( {
            url:         wpns_ajax.url,
            type:        'POST',
            data:        formData,
            processData: false,
            contentType: false,

            success: function ( res ) {
                if ( res.success ) {
                    $resp.html(
                        '<div class="wpns-response-success">' +
                        res.data.message +
                        '</div>'
                    );
                    $form[0].reset();
                    if ( res.data.redirect_url ) {
                        window.location.href = res.data.redirect_url;
                    }
                } else {
                    // Server-side field errors
                    if ( res.data && res.data.errors ) {
                        var msgs = [];
                        $.each( res.data.errors, function ( fieldName, message ) {
                            // Try to highlight the specific field
                            var $el = $form.find( '[name="' + fieldName + '"]' ).first();
                            if ( $el.length ) {
                                showFieldError( $el, message );
                            } else {
                                msgs.push( message );
                            }
                        } );
                        if ( msgs.length ) {
                            $resp.html(
                                '<div class="wpns-response-error">' +
                                msgs.join( '<br>' ) +
                                '</div>'
                            );
                        }
                        // Scroll to first inline error
                        var $first = $form.find( '.wpns-field-invalid' ).first();
                        if ( $first.length ) {
                            $( 'html, body' ).animate(
                                { scrollTop: $first.closest( '.wpns-field' ).offset().top - 80 },
                                250
                            );
                        }
                    } else {
                        var msg = ( res.data && res.data.message )
                            ? res.data.message
                            : 'An error occurred. Please try again.';
                        $resp.html(
                            '<div class="wpns-response-error">' + msg + '</div>'
                        );
                    }
                }
            },

            error: function () {
                $resp.html(
                    '<div class="wpns-response-error">Network error. Please try again.</div>'
                );
            },

            complete: function () {
                $btn.prop( 'disabled', false ).html( origLabel );
            },
        } );
    } );

    // ── Clear field error on input ────────────────────────────────────────
    $( document ).on( 'input change', '.wpns-form .wpns-field-invalid', function () {
        $( this ).removeClass( 'wpns-field-invalid' );
        $( this ).closest( '.wpns-field' )
            .find( '.wpns-field-error' )
            .text( '' )
            .removeClass( 'visible' );
    } );

} );
