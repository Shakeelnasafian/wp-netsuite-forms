<?php

class WPNS_Form_Processor {
    /**
     * Handle an incoming AJAX form submission.
     *
     * Checks the honeypot, enforces per-IP rate limiting, validates and sanitises
     * every field (including type-specific rules and option-list validation),
     * guards against double-submissions, processes file uploads, posts to NetSuite,
     * sends email notifications, saves the submission record, and returns JSON.
     */
    public function handle_ajax(): void {
        check_ajax_referer( 'wpns_form_nonce', 'nonce' );

        $form_id = isset( $_POST['wpns_form_id'] ) ? absint( $_POST['wpns_form_id'] ) : 0;
        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid form.', 'wp-netsuite-forms' ) ] );
        }

        $form = WPNS_Form_Model::get( $form_id );
        if ( ! $form ) {
            wp_send_json_error( [ 'message' => __( 'Form not found.', 'wp-netsuite-forms' ) ] );
        }

        // ── Honeypot check ────────────────────────────────────────────────
        $honeypot = isset( $_POST['_wpns_hp'] ) ? (string) $_POST['_wpns_hp'] : null;
        if ( $honeypot !== null && trim( $honeypot ) !== '' ) {
            // Silently succeed — don't reveal the trap to bots.
            $msg = $form->success_message ?: __( 'Thank you! Your submission has been received.', 'wp-netsuite-forms' );
            wp_send_json_success( [ 'message' => $msg ] );
        }

        // ── Per-IP rate limiting (max 5 submissions per 10 min per form) ──
        $ip          = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $rl_key      = 'wpns_rl_' . md5( $ip . '_' . $form_id );
        $rl_count    = (int) get_transient( $rl_key );
        if ( $rl_count >= 5 ) {
            wp_send_json_error( [ 'message' => __( 'Too many submissions. Please wait a few minutes and try again.', 'wp-netsuite-forms' ) ] );
        }
        set_transient( $rl_key, $rl_count + 1, 10 * MINUTE_IN_SECONDS );

        // ── reCAPTCHA v3 verification ─────────────────────────────────────
        $settings = WPNS_Settings_Model::get( $form_id );
        if ( $settings && ! empty( $settings->enable_recaptcha ) ) {
            $token = sanitize_text_field( wp_unslash( $_POST['wpns_recaptcha_token'] ?? '' ) );
            if ( ! WPNS_Recaptcha::verify( $token ) ) {
                wp_send_json_error( [ 'message' => __( 'reCAPTCHA verification failed. Please try again.', 'wp-netsuite-forms' ) ] );
            }
        }

        // ── Collect and validate fields ───────────────────────────────────
        $fields         = WPNS_Field_Model::get_fields( $form_id );
        $errors         = [];
        $submitted_data = [];
        $file_urls      = [];

        foreach ( $fields as $field ) {
            $name        = (string) $field->field_name;
            $label       = (string) $field->field_label;
            $type        = (string) $field->field_type;
            $is_required = ! empty( $field->is_required );

            // ── File fields ───────────────────────────────────────────────
            if ( $type === 'file' ) {
                if ( $is_required ) {
                    $file_err = $_FILES[ $name ]['error'] ?? UPLOAD_ERR_NO_FILE;
                    if ( empty( $_FILES[ $name ]['name'] ) || $file_err !== UPLOAD_ERR_OK ) {
                        /* translators: %s: field label */
                        $errors[ $name ] = sprintf( __( '%s is required.', 'wp-netsuite-forms' ), $label );
                    }
                }
                continue;
            }

            // ── Text-like fields ──────────────────────────────────────────
            $raw_value = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : '';

            if ( is_array( $raw_value ) ) {
                $sanitized = array_map( 'sanitize_text_field', $raw_value );
            } else {
                $raw_value = (string) $raw_value;
                switch ( $type ) {
                    case 'email':
                        $sanitized = sanitize_email( $raw_value );
                        break;
                    case 'textarea':
                        $sanitized = sanitize_textarea_field( $raw_value );
                        break;
                    default:
                        $sanitized = sanitize_text_field( $raw_value );
                }
            }

            // ── Required check ────────────────────────────────────────────
            $is_empty = is_array( $sanitized )
                ? count( array_filter( $sanitized, 'strlen' ) ) === 0
                : trim( (string) $sanitized ) === '';

            if ( $is_required && $is_empty ) {
                /* translators: %s: field label */
                $errors[ $name ] = sprintf( __( '%s is required.', 'wp-netsuite-forms' ), $label );
                $submitted_data[ $name ] = $sanitized;
                continue;
            }

            // ── Type-specific validation (skip if empty and not required) ─
            if ( ! $is_empty ) {
                $val_str = is_array( $sanitized ) ? '' : (string) $sanitized;

                switch ( $type ) {
                    case 'email':
                        if ( ! filter_var( $val_str, FILTER_VALIDATE_EMAIL ) ) {
                            /* translators: %s: field label */
                            $errors[ $name ] = sprintf( __( '%s must be a valid email address.', 'wp-netsuite-forms' ), $label );
                        }
                        break;

                    case 'number':
                        if ( ! is_numeric( $val_str ) ) {
                            /* translators: %s: field label */
                            $errors[ $name ] = sprintf( __( '%s must be a number.', 'wp-netsuite-forms' ), $label );
                        } else {
                            $sanitized = $val_str; // keep as string for consistency
                        }
                        break;

                    case 'url':
                        if ( ! filter_var( $val_str, FILTER_VALIDATE_URL ) ) {
                            /* translators: %s: field label */
                            $errors[ $name ] = sprintf( __( '%s must be a valid URL.', 'wp-netsuite-forms' ), $label );
                        }
                        break;

                    case 'select':
                    case 'radio':
                        // Validate submitted value against the configured option list.
                        if ( ! empty( $field->options_json ) ) {
                            $allowed_opts = json_decode( $field->options_json, true );
                            if ( is_array( $allowed_opts ) ) {
                                $allowed_values = array_column( $allowed_opts, 'value' );
                                if ( $val_str !== '' && ! in_array( $val_str, $allowed_values, true ) ) {
                                    /* translators: %s: field label */
                                    $errors[ $name ] = sprintf( __( '%s contains an invalid selection.', 'wp-netsuite-forms' ), $label );
                                }
                            }
                        }
                        break;

                    case 'checkbox':
                        // Each submitted value must be in the allowed option list.
                        if ( is_array( $sanitized ) && ! empty( $field->options_json ) ) {
                            $allowed_opts = json_decode( $field->options_json, true );
                            if ( is_array( $allowed_opts ) ) {
                                $allowed_values = array_column( $allowed_opts, 'value' );
                                foreach ( $sanitized as $checked_val ) {
                                    if ( ! in_array( $checked_val, $allowed_values, true ) ) {
                                        /* translators: %s: field label */
                                        $errors[ $name ] = sprintf( __( '%s contains an invalid selection.', 'wp-netsuite-forms' ), $label );
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                }
            }

            $submitted_data[ $name ] = $sanitized;
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [ 'errors' => $errors ] );
        }

        // ── Duplicate submission guard (30-second window) ─────────────────
        $dedup_key  = 'wpns_dedup_' . md5( $form_id . serialize( $submitted_data ) );
        if ( get_transient( $dedup_key ) ) {
            $msg = $form->success_message ?: __( 'Thank you! Your submission has been received.', 'wp-netsuite-forms' );
            wp_send_json_success( [ 'message' => $msg ] );
        }
        set_transient( $dedup_key, 1, 30 ); // 30 seconds

        // ── File uploads ──────────────────────────────────────────────────
        foreach ( $fields as $field ) {
            if ( $field->field_type !== 'file' ) {
                continue;
            }
            $name = (string) $field->field_name;
            if ( ! isset( $_FILES[ $name ] ) ) {
                continue;
            }
            $file = $_FILES[ $name ];
            if ( isset( $file['error'] ) && $file['error'] !== UPLOAD_ERR_OK ) {
                continue;
            }

            // Handle both single and multiple-file inputs (take first only).
            $tmp_path = is_array( $file['tmp_name'] ) ? reset( $file['tmp_name'] ) : $file['tmp_name'];
            $filename = is_array( $file['name'] )     ? reset( $file['name'] )     : $file['name'];

            if ( $tmp_path && $filename ) {
                $url = WPNS_File_Uploader::upload_from_path( $tmp_path, $filename );
                if ( $url !== '' ) {
                    $file_urls[ $name ]      = $url;
                    $submitted_data[ $name ] = $url;
                } else {
                    wpns_log( 'File upload failed', [ 'field' => $name, 'file' => $filename ] );
                }
            }
        }

        // ── UTM tracking ──────────────────────────────────────────────────
        $submitted_data = array_merge( $submitted_data, WPNS_UTM_Tracker::get_utm_data() );

        // ── NetSuite push ─────────────────────────────────────────────────
        $payload       = '';
        $ns_response   = '';
        $ns_success    = 0;
        $error_message = '';
        $email_sent    = 0;
        $image_url     = $file_urls ? reset( $file_urls ) : '';

        if ( $settings && ! empty( $settings->enable_netsuite ) && ! empty( $settings->credential_id ) ) {
            $credential = WPNS_Credential_Model::get( (int) $settings->credential_id );
            if ( $credential ) {
                $payload = WPNS_Payload_Builder::build(
                    (string) ( $settings->payload_template ?? '' ),
                    $submitted_data,
                    (string) ( $settings->static_values_json ?? '' ),
                    $image_url
                );

                $crm_client  = WPNS_CRM_Factory::make( $credential );
                $result      = $crm_client->post( $payload );
                $ns_response = $result['response'] ?? '';
                $ns_success  = ! empty( $result['success'] ) ? 1 : 0;

                if ( ! $ns_success ) {
                    $crm_label     = strtoupper( $credential->crm_type ?? 'CRM' );
                    $error_message = $result['response'] ?? sprintf( __( '%s request failed.', 'wp-netsuite-forms' ), $crm_label );
                    wpns_log( $crm_label . ' push failed', [
                        'form_id'   => $form_id,
                        'http_code' => $result['http_code'] ?? 0,
                        'response'  => $ns_response,
                    ] );
                }
            } else {
                $error_message = __( 'Credential profile not found.', 'wp-netsuite-forms' );
                wpns_log( 'Credential not found', [ 'credential_id' => $settings->credential_id ] );
            }
        }

        // ── Email notification ────────────────────────────────────────────
        if ( $settings && ! empty( $settings->enable_email ) ) {
            $email_sent = WPNS_Email_Notifier::send( $settings, $submitted_data ) ? 1 : 0;
            if ( ! $email_sent ) {
                wpns_log( 'Email notification failed', [ 'form_id' => $form_id ] );
            }
        }

        // ── Persist submission ────────────────────────────────────────────
        WPNS_Submission_Model::create( [
            'form_id'           => $form_id,
            'submitted_data'    => wp_json_encode( $submitted_data ),
            'netsuite_payload'  => $payload,
            'netsuite_response' => $ns_response,
            'email_sent'        => $email_sent,
            'ns_success'        => $ns_success,
            'error_message'     => $error_message,
            'ip_address'        => $ip,
            'user_agent'        => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
        ] );

        $success_message = $form->success_message ?: __( 'Thank you! Your submission has been received.', 'wp-netsuite-forms' );
        $response_data   = [ 'message' => $success_message ];

        if ( ! empty( $form->redirect_url ) ) {
            $response_data['redirect_url'] = esc_url_raw( $form->redirect_url );
        }

        wp_send_json_success( $response_data );
    }
}
