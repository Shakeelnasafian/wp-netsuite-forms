<?php

class WPNS_Email_Notifier {
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
