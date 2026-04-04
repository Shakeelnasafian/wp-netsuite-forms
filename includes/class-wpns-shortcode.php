<?php

class WPNS_Shortcode {
    private WPNS_Loader $loader;

    /**
     * Create a WPNS_Shortcode instance and store the provided loader.
     *
     * @param WPNS_Loader $loader Loader instance used to register hooks and shortcodes.
     */
    public function __construct(WPNS_Loader $loader) {
        $this->loader = $loader;
    }

    /**
     * Registers the 'wpns_form' WordPress shortcode and binds it to this object's render method.
     *
     * The shortcode will invoke WPNS_Shortcode::render when used in post content.
     */
    public function init(): void {
        add_shortcode('wpns_form', [$this, 'render']);
    }

    /**
     * Render the WPNS form for the provided shortcode attributes.
     *
     * Also enqueues and localizes the front-end script and enqueues the stylesheet required for form submission.
     *
     * @param array $atts Shortcode attributes; recognizes an 'id' key for the form ID.
     * @return string The rendered form HTML, or an empty string if the form ID is invalid or the form is not active.
     */
    public function render(array $atts): string {
        $atts = shortcode_atts(['id' => 0], $atts);
        $form_id = absint($atts['id']);
        if (!$form_id) {
            return '';
        }

        $form = WPNS_Form_Model::get($form_id);
        if (!$form || $form->status !== 'active') {
            return '';
        }

        $fields   = WPNS_Field_Model::get_fields( $form_id );
        $settings = WPNS_Settings_Model::get( $form_id );

        wp_enqueue_style( 'wpns-forms', WPNS_PLUGIN_URL . 'public/css/forms.css', [], WPNS_VERSION );
        wp_enqueue_script( 'wpns-form-submit', WPNS_PLUGIN_URL . 'public/js/form-submit.js', [ 'jquery' ], WPNS_VERSION, true );
        wp_localize_script( 'wpns-form-submit', 'wpns_ajax', [
            'url'              => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'wpns_form_nonce' ),
            'recaptcha_active' => ( $settings && ! empty( $settings->enable_recaptcha ) && WPNS_Recaptcha::is_enabled() ) ? '1' : '0',
        ] );

        // Enqueue reCAPTCHA v3 script if enabled for this form.
        if ( $settings && ! empty( $settings->enable_recaptcha ) && WPNS_Recaptcha::is_enabled() ) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . esc_attr( WPNS_Recaptcha::get_site_key() ),
                [],
                null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
                true
            );
        }

        ob_start();
        include WPNS_PLUGIN_DIR . 'public/templates/form.php'; // $form, $fields, $settings available
        return ob_get_clean();
    }
}
