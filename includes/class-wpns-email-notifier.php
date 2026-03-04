<?php

class WPNS_Email_Notifier {
    /**
     * Send an HTML email using the provided settings and token-replaced submitted data.
     *
     * The method builds the subject and body by replacing `{key}` tokens in the
     * provided templates with values from `$submitted_data`, assembles optional
     * From/Cc/Bcc headers, ensures the content type is HTML UTF-8, and sends the
     * message via WordPress `wp_mail`.
     *
     * @param object $settings An object containing email configuration. Expected properties:
     *                         - email_to: recipient address (required).
     *                         - email_subject: subject template containing `{key}` tokens.
     *                         - email_body: body template containing `{key}` tokens (HTML).
     *                         - email_from_name: optional sender display name.
     *                         - email_from_address: optional sender email address.
     *                         - email_cc: optional Cc addresses (string).
     *                         - email_bcc: optional Bcc addresses (string).
     * @param array $submitted_data Associative array of submitted form data used to replace tokens in templates.
     * @return bool `true` if the mail was accepted for delivery by wp_mail, `false` otherwise or when recipient is missing.
     */
    public static function send(object $settings, array $submitted_data): bool {
        $to = $settings->email_to ?? '';
        if ($to === '') {
            return false;
        }

        $subject = self::replace_tokens((string) ($settings->email_subject ?? ''), $submitted_data);
        $body = self::replace_tokens((string) ($settings->email_body ?? ''), $submitted_data);

        $headers = [];
        $from_name = trim((string) ($settings->email_from_name ?? ''));
        $from_email = trim((string) ($settings->email_from_address ?? ''));
        if ($from_email !== '') {
            $from = $from_email;
            if ($from_name !== '') {
                $from = $from_name . ' <' . $from_email . '>';
            }
            $headers[] = 'From: ' . $from;
        }

        if (!empty($settings->email_cc)) {
            $headers[] = 'Cc: ' . $settings->email_cc;
        }
        if (!empty($settings->email_bcc)) {
            $headers[] = 'Bcc: ' . $settings->email_bcc;
        }

        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Replace `{key}` tokens in a template with corresponding values from an associative array.
     *
     * Tokens matching the regex `{[A-Za-z0-9_-]+}` are replaced by the value found in `$data` for the token name.
     * If a key is missing, it is replaced with an empty string. Array values are converted to strings and joined
     * with `, `.
     *
     * @param string $template The template string containing `{key}` tokens.
     * @param array<string,mixed> $data Associative array of replacement values keyed by token name.
     * @return string The template with all tokens substituted.
     */
    private static function replace_tokens(string $template, array $data): string {
        return preg_replace_callback('/{([a-zA-Z0-9_\-]+)}/', function ($matches) use ($data) {
            $key = $matches[1];
            $value = $data[$key] ?? '';
            if (is_array($value)) {
                return implode(', ', array_map('strval', $value));
            }
            return (string) $value;
        }, $template);
    }
}
