<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPNS_Admin_Forms {
    /**
     * Render the WP NetSuite Forms admin page and display the forms list table.
     *
     * Outputs the page wrapper and header (including the localized title and an
     * "Add New" action), initializes and prepares a WPNS_Forms_List_Table, and
     * renders the table into the admin screen.
     */
    public function render(): void {
        $table = new WPNS_Forms_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('WP NetSuite Forms', 'wp-netsuite-forms') . '</h1>';
        echo ' <a href="' . esc_url(admin_url('admin.php?page=wpns-form-edit')) . '" class="page-title-action">' . esc_html__('Add New', 'wp-netsuite-forms') . '</a>';
        echo '<hr class="wp-header-end" />';

        $table->display();
        echo '</div>';
    }
}

class WPNS_Forms_List_Table extends WP_List_Table {
    /**
     * Define the table columns and their localized header labels for the forms list.
     *
     * @return array Associative array mapping column keys (`id`, `name`, `status`, `shortcode`, `created_at`) to their localized header labels.
     */
    public function get_columns(): array {
        return [
            'id'           => __( 'ID',          'wp-netsuite-forms' ),
            'name'         => __( 'Name',         'wp-netsuite-forms' ),
            'status'       => __( 'Status',       'wp-netsuite-forms' ),
            'submissions'  => __( 'Submissions',  'wp-netsuite-forms' ),
            'last_sub'     => __( 'Last Activity','wp-netsuite-forms' ),
            'shortcode'    => __( 'Shortcode',    'wp-netsuite-forms' ),
            'created_at'   => __( 'Created',      'wp-netsuite-forms' ),
        ];
    }

    /**
     * Populate the list table with all stored forms and initialize its column headers.
     *
     * Retrieves all form records, assigns them to the table's items, and sets the internal
     * `_column_headers` using the table's column definitions.
     */
    public function prepare_items(): void {
        $data = WPNS_Form_Model::get_all();

        $this->items = $data;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];
    }

    /**
     * Render the "Name" column for a form row, including an edit link and row action links.
     *
     * @param object $item Form record object with at least `id` and `name` properties.
     * @return string HTML for the column cell: a stronged edit link for the form name followed by row actions (Edit, Delete).
     */
    protected function column_name($item): string {
        $edit_url = admin_url('admin.php?page=wpns-form-edit&form_id=' . $item->id);
        $actions = [
            'edit' => '<a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'wp-netsuite-forms') . '</a>',
            'delete' => '<a href="#" class="wpns-delete-form" data-form-id="' . esc_attr($item->id) . '">' . esc_html__('Delete', 'wp-netsuite-forms') . '</a>',
        ];
        return '<strong><a href="' . esc_url($edit_url) . '">' . esc_html($item->name) . '</a></strong>' . $this->row_actions($actions);
    }

    /**
     * Render the form's shortcode wrapped in a `<code>` HTML element.
     *
     * @param object $item The form record; its `id` property will be used in the shortcode.
     * @return string The HTML string containing the shortcode `[wpns_form id="X"]`.
     */
    protected function column_shortcode($item): string {
        return '<code>[wpns_form id="' . esc_html($item->id) . '"]</code>';
    }

    /**
     * Render the default cell value for a given column in the forms list table.
     *
     * @param object $item        The form record object for the current row.
     * @param string $column_name The column identifier.
     * @return string The cell content: the form `id` as a string for `id`, the escaped `status` for `status`, the escaped `created_at` for `created_at`, or an empty string for unknown columns.
     */
    protected function column_default( $item, $column_name ): string {
        switch ( $column_name ) {
            case 'id':
                return (string) $item->id;

            case 'status':
                $cls = $item->status === 'active' ? 'active' : 'inactive';
                return '<span class="wpns-status-badge ' . esc_attr( $cls ) . '">'
                    . esc_html( ucfirst( $item->status ) ) . '</span>';

            case 'submissions': {
                $count    = WPNS_Submission_Model::count_by_form( (int) $item->id );
                $sub_url  = admin_url( 'admin.php?page=wpns-submissions&form_id=' . $item->id );
                $failed   = WPNS_Submission_Model::count_ns_failed( (int) $item->id );
                $out      = '<a href="' . esc_url( $sub_url ) . '">' . esc_html( $count ) . '</a>';
                if ( $failed > 0 ) {
                    $out .= ' <span class="wpns-badge wpns-badge-warn" title="'
                        . esc_attr( sprintf( __( '%d CRM push failed', 'wp-netsuite-forms' ), $failed ) )
                        . '">' . esc_html( $failed ) . ' failed</span>';
                }
                return $out;
            }

            case 'last_sub': {
                $date = WPNS_Submission_Model::get_last_submission_date( (int) $item->id );
                return $date ? esc_html( human_time_diff( strtotime( $date ) ) . ' ago' )
                             : '<span style="color:#aaa;">—</span>';
            }

            case 'created_at':
                return esc_html( $item->created_at );

            default:
                return '';
        }
    }
}
