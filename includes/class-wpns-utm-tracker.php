<?php

class WPNS_UTM_Tracker {
    public function init(): void {
        add_action('wp_head', [$this, 'inject_utm_script']);
    }

    public function inject_utm_script(): void {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $json_keys = wp_json_encode($keys);
        echo "<script>(function(){try{var keys=" . $json_keys . ";var params=new URLSearchParams(window.location.search);keys.forEach(function(k){var v=params.get(k);if(v){localStorage.setItem(k,v);}});}catch(e){}})();</script>";
    }

    public static function get_utm_data(): array {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
        }
        return $data;
    }
}
