<?php

class WPNS_UTM_Tracker {
    /**
     * Registers the inject_utm_script callback on the WordPress 'wp_head' hook.
     */
    public function init(): void {
        add_action('wp_head', [$this, 'inject_utm_script']);
    }

    /**
     * Outputs a script tag that stores UTM parameters from the current page URL into localStorage.
     *
     * The injected script reads the query string and, for each of the keys
     * `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, and `utm_content`,
     * stores the parameter value in localStorage under the same key if present.
     */
    public function inject_utm_script(): void {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $json_keys = wp_json_encode($keys);
        echo "<script>(function(){try{var keys=" . $json_keys . ";var params=new URLSearchParams(window.location.search);keys.forEach(function(k){var v=params.get(k);if(v){localStorage.setItem(k,v);}});}catch(e){}})();</script>";
    }

    /**
     * Collects standard UTM parameter values from POST data into an associative array.
     *
     * Each value is retrieved from $_POST if present, processed with wp_unslash and sanitize_text_field,
     * and missing keys are represented as an empty string.
     *
     * @return array Associative array with keys 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', and 'utm_content'
     *               mapped to their corresponding sanitized string values (empty string if absent).
     */
    public static function get_utm_data(): array {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
        }
        return $data;
    }
}
