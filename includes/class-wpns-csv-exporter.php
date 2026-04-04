<?php

class WPNS_CSV_Exporter {
    /**
     * Stream a CSV download of all submissions for the given form.
     * Sends appropriate headers and exits — call only from a request handler.
     */
    public static function export( int $form_id ): void {
        $form        = WPNS_Form_Model::get( $form_id );
        $fields      = WPNS_Field_Model::get_fields( $form_id );
        $submissions = WPNS_Submission_Model::get_all_for_form( $form_id );

        $form_name = $form ? sanitize_file_name( $form->name ) : 'form-' . $form_id;
        $filename  = $form_name . '-submissions-' . gmdate( 'Y-m-d' ) . '.csv';

        // Disable output buffering so we can stream directly.
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

        // UTF-8 BOM for correct display in Microsoft Excel.
        fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

        // ── Header row ────────────────────────────────────────────────────
        $header = [ 'ID', 'Submitted At', 'IP Address' ];
        foreach ( $fields as $field ) {
            $header[] = $field->field_label ?: $field->field_name;
        }
        $header[] = 'NS Success';
        $header[] = 'Email Sent';
        fputcsv( $output, $header );

        // ── Data rows ─────────────────────────────────────────────────────
        foreach ( $submissions as $sub ) {
            $data = json_decode( (string) $sub->submitted_data, true );
            $data = is_array( $data ) ? $data : [];

            $row = [ $sub->id, $sub->created_at, $sub->ip_address ];

            foreach ( $fields as $field ) {
                $val = $data[ $field->field_name ] ?? '';
                if ( is_array( $val ) ) {
                    $val = implode( ', ', $val );
                }
                $row[] = (string) $val;
            }

            $row[] = $sub->ns_success ? 'Yes' : 'No';
            $row[] = $sub->email_sent ? 'Yes' : 'No';

            fputcsv( $output, $row );
        }

        fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }
}
