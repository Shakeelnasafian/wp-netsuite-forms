jQuery( function ( $ ) {

    /* ── URL parameter prefill ───────────────────────────────────────────── */
    (function prefillFromUrl() {
        var params = new URLSearchParams( window.location.search );
        $( '.wpns-form [data-url-param]' ).each( function () {
            var param = $( this ).data( 'url-param' );
            var val   = params.get( param );
            if ( val !== null && $( this ).val() === '' ) {
                $( this ).val( val );
            }
        } );
    })();

    /* ── Conditional field logic ─────────────────────────────────────────── */
    function evaluateConditions( $form ) {
        $form.find( '.wpns-field[data-condition]' ).each( function () {
            var cond = $( this ).data( 'condition' );
            if ( ! cond || ! cond.enabled ) { return; }

            var $trigger   = $form.find( '[name="' + cond.field + '"]' );
            var triggerVal = $trigger.is( ':radio, :checkbox' )
                ? $form.find( '[name="' + cond.field + '"]:checked' ).map( function () { return this.value; } ).get().join( ',' )
                : $trigger.val() || '';

            var met = false;
            switch ( cond.operator ) {
                case '=':         met = triggerVal === cond.value;                       break;
                case '!=':        met = triggerVal !== cond.value;                       break;
                case 'contains':  met = triggerVal.indexOf( cond.value ) !== -1;         break;
                case '!contains': met = triggerVal.indexOf( cond.value ) === -1;         break;
                case 'empty':     met = triggerVal === '';                               break;
                case 'not_empty': met = triggerVal !== '';                               break;
                default:          met = false;
            }

            var show = ( cond.action === 'show' ) ? met : ! met;
            $( this ).toggle( show );

            // Remove required attribute from hidden fields to avoid browser validation blocking submission.
            $( this ).find( '[required]' )
                .prop( 'required', show )
                .attr( 'aria-required', show ? 'true' : null );
        } );
    }

    // Evaluate on page load and whenever any field changes.
    $( '.wpns-form' ).each( function () {
        evaluateConditions( $( this ) );
    } );

    $( document ).on( 'change input', '.wpns-form input, .wpns-form select, .wpns-form textarea', function () {
        evaluateConditions( $( this ).closest( '.wpns-form' ) );
    } );

    /* ── Helpers ─────────────────────────────────────────────────────────── */
    function clearErrors( $form ) {
        $form.find( '.wpns-field-invalid' ).removeClass( 'wpns-field-invalid' );
        $form.find( '.wpns-field-error' ).text( '' ).removeClass( 'visible' );
    }

    function showFieldError( $field, message ) {
        $field.addClass( 'wpns-field-invalid' );
        $field.closest( '.wpns-field' ).find( '.wpns-field-error' ).text( message ).addClass( 'visible' );
    }

    function validateForm( $form ) {
        var valid = true;

        $form.find( '[required]' ).each( function () {
            var $el   = $( this );
            // Skip fields inside hidden conditional sections.
            if ( $el.closest( '.wpns-field' ).is( ':hidden' ) ) { return; }

            var label = $el.data( 'label' ) || 'This field';
            var name  = $el.attr( 'name' );

            if ( $el.is( ':radio' ) ) {
                if ( $form.find( '[name="' + name + '"]:checked' ).length === 0 ) {
                    showFieldError( $el, label + ' is required.' );
                    valid = false;
                }
                return;
            }
            if ( $el.is( ':checkbox' ) ) {
                if ( $form.find( '[name="' + name + '"]' ).filter( ':checked' ).length === 0 ) {
                    showFieldError( $el, label + ' is required.' );
                    valid = false;
                }
                return;
            }
            if ( $.trim( $el.val() || '' ) === '' ) {
                showFieldError( $el, label + ' is required.' );
                valid = false;
            }
        } );

        return valid;
    }

    /* ── Form submission ─────────────────────────────────────────────────── */
    $( document ).on( 'submit', '.wpns-form', function ( e ) {
        e.preventDefault();

        var $form = $( this );
        var $btn  = $form.find( '.wpns-submit' );
        var $resp = $form.find( '.wpns-response' );

        clearErrors( $form );
        $resp.html( '' );

        if ( ! validateForm( $form ) ) {
            var $first = $form.find( '.wpns-field-invalid' ).first();
            if ( $first.length ) {
                $( 'html, body' ).animate( { scrollTop: $first.closest( '.wpns-field' ).offset().top - 80 }, 250 );
            }
            return;
        }

        var origLabel = $btn.text();
        $btn.prop( 'disabled', true ).html( '<span class="wpns-spinner"></span>' + origLabel );

        /**
         * Obtain a reCAPTCHA v3 token (if active) then post the form.
         * grecaptcha is loaded asynchronously; we check for it before executing.
         */
        function doSubmit() {
            var formData = new FormData( $form[0] );
            formData.append( 'action', 'wpns_submit_form' );
            formData.append( 'nonce',  wpns_ajax.nonce );

            [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ]
                .forEach( function ( k ) { formData.append( k, localStorage.getItem( k ) || '' ); } );

            $.ajax( {
                url:         wpns_ajax.url,
                type:        'POST',
                data:        formData,
                processData: false,
                contentType: false,

                success: function ( res ) {
                    if ( res.success ) {
                        $resp.html( '<div class="wpns-response-success">' + res.data.message + '</div>' );
                        $form[0].reset();
                        evaluateConditions( $form ); // re-evaluate after reset
                        if ( res.data.redirect_url ) {
                            window.location.href = res.data.redirect_url;
                        }
                    } else {
                        if ( res.data && res.data.errors ) {
                            var extra = [];
                            $.each( res.data.errors, function ( fieldName, message ) {
                                var $el = $form.find( '[name="' + fieldName + '"]' ).first();
                                if ( $el.length ) {
                                    showFieldError( $el, message );
                                } else {
                                    extra.push( message );
                                }
                            } );
                            if ( extra.length ) {
                                $resp.html( '<div class="wpns-response-error">' + extra.join( '<br>' ) + '</div>' );
                            }
                            var $firstErr = $form.find( '.wpns-field-invalid' ).first();
                            if ( $firstErr.length ) {
                                $( 'html, body' ).animate( { scrollTop: $firstErr.closest( '.wpns-field' ).offset().top - 80 }, 250 );
                            }
                        } else {
                            var msg = ( res.data && res.data.message ) ? res.data.message : 'An error occurred. Please try again.';
                            $resp.html( '<div class="wpns-response-error">' + msg + '</div>' );
                        }
                    }
                },

                error: function () {
                    $resp.html( '<div class="wpns-response-error">Network error. Please try again.</div>' );
                },

                complete: function () {
                    $btn.prop( 'disabled', false ).html( origLabel );
                },
            } );
        }

        // Inject reCAPTCHA token if active.
        if ( wpns_ajax.recaptcha_active === '1' && typeof grecaptcha !== 'undefined' ) {
            grecaptcha.ready( function () {
                grecaptcha.execute( undefined, { action: 'wpns_submit' } ).then( function ( token ) {
                    $form.find( '.wpns-recaptcha-token' ).val( token );
                    doSubmit();
                } );
            } );
        } else {
            doSubmit();
        }
    } );

    /* ── Clear field error on change ─────────────────────────────────────── */
    $( document ).on( 'input change', '.wpns-form .wpns-field-invalid', function () {
        $( this ).removeClass( 'wpns-field-invalid' );
        $( this ).closest( '.wpns-field' ).find( '.wpns-field-error' ).text( '' ).removeClass( 'visible' );
    } );

} );
