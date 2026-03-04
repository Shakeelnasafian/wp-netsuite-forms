<?php

class WPNS_Shortcode {
    private WPNS_Loader $loader;

    public function __construct(WPNS_Loader $loader) {
        $this->loader = $loader;
    }

    public function init(): void {
        add_shortcode('wpns_form', [$this, 'render']);
    }

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

        $fields = WPNS_Field_Model::get_fields($form_id);

        wp_enqueue_script('wpns-form-submit', WPNS_PLUGIN_URL . 'public/js/form-submit.js', ['jquery'], WPNS_VERSION, true);
        wp_localize_script('wpns-form-submit', 'wpns_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpns_form_nonce'),
        ]);
        wp_enqueue_style('wpns-forms', WPNS_PLUGIN_URL . 'public/css/forms.css', [], WPNS_VERSION);

        ob_start();
        include WPNS_PLUGIN_DIR . 'public/templates/form.php';
        return ob_get_clean();
    }
}
