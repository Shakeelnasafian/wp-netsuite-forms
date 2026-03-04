<?php

class WPNS_Payload_Builder {
    /**
     * Build a JSON payload by substituting template tokens, applying static nested values, and optionally injecting an image URL.
     *
     * @param string $payload_template Template containing tokens in the form {{ token }}.
     * @param array $submitted_data Map of token names to replacement values; array values are joined with ", ".
     * @param string $static_values_json JSON object whose keys are dot-separated paths and values are assigned into the payload.
     * @param string $image_url Optional URL to replace any image-related tokens (`image_url` or leaf values equal to `{{image_url}}`).
     * @return string The final payload as a JSON-encoded string; if the template after token replacement is not valid JSON, returns that replaced string unchanged.
     */
    public static function build(
        string $payload_template,
        array $submitted_data,
        string $static_values_json,
        string $image_url = ''
    ): string {
        $template = $payload_template;

        $replaced = preg_replace_callback('/{{\s*([a-zA-Z0-9_\.\-]+)\s*}}/', function ($matches) use ($submitted_data, $image_url) {
            $token = $matches[1];
            if (strpos($token, '__static__') === 0) {
                return '';
            }
            if ($token === 'image_url') {
                return $image_url;
            }
            $value = $submitted_data[$token] ?? '';
            if (is_array($value)) {
                return implode(', ', array_map('strval', $value));
            }
            return (string) $value;
        }, $template);

        $decoded = json_decode($replaced, true);
        if (!is_array($decoded)) {
            return $replaced;
        }

        $static_values = [];
        if (!empty($static_values_json)) {
            $decoded_static = json_decode($static_values_json, true);
            if (is_array($decoded_static)) {
                $static_values = $decoded_static;
            }
        }

        foreach ($static_values as $path => $value) {
            self::set_nested($decoded, (string) $path, $value);
        }

        if ($image_url !== '') {
            self::replace_image_tokens($decoded, $image_url);
        }

        return wp_json_encode($decoded);
    }

    /**
     * Set a value in a nested array using a dot-separated path.
     *
     * Creates intermediate arrays as needed and assigns `$value` at the final key.
     * If `$path` is an empty string the array is left unchanged.
     *
     * @param array<string,mixed> &$arr The array to modify (passed by reference).
     * @param string $path Dot-separated path of keys (e.g., "a.b.c").
     * @param mixed $value The value to assign at the final key.
     */
    private static function set_nested(array &$arr, string $path, $value): void {
        if ($path === '') {
            return;
        }
        $keys = explode('.', $path);
        $current =& $arr;
        foreach ($keys as $index => $key) {
            if ($key === '') {
                continue;
            }
            if ($index === count($keys) - 1) {
                $current[$key] = $value;
                return;
            }
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current =& $current[$key];
        }
    }

    /**
     * Recursively replaces any leaf values equal to '{{image_url}}' with the provided image URL.
     *
     * @param array &$arr The array to modify in place; nested arrays are traversed recursively.
     * @param string $image_url The URL to substitute for '{{image_url}}' tokens.
     */
    private static function replace_image_tokens(array &$arr, string $image_url): void {
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                self::replace_image_tokens($arr[$key], $image_url);
                continue;
            }
            if ($value === '{{image_url}}') {
                $arr[$key] = $image_url;
            }
        }
    }
}
