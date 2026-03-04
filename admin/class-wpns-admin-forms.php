<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPNS_Admin_Forms {
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
    public function get_columns(): array {
        return [
            'id' => __('ID', 'wp-netsuite-forms'),
            'name' => __('Name', 'wp-netsuite-forms'),
            'status' => __('Status', 'wp-netsuite-forms'),
            'shortcode' => __('Shortcode', 'wp-netsuite-forms'),
            'created_at' => __('Created', 'wp-netsuite-forms'),
        ];
    }

    public function prepare_items(): void {
        $data = WPNS_Form_Model::get_all();

        $this->items = $data;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];
    }

    protected function column_name($item): string {
        $edit_url = admin_url('admin.php?page=wpns-form-edit&form_id=' . $item->id);
        $actions = [
            'edit' => '<a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'wp-netsuite-forms') . '</a>',
            'delete' => '<a href="#" class="wpns-delete-form" data-form-id="' . esc_attr($item->id) . '">' . esc_html__('Delete', 'wp-netsuite-forms') . '</a>',
        ];
        return '<strong><a href="' . esc_url($edit_url) . '">' . esc_html($item->name) . '</a></strong>' . $this->row_actions($actions);
    }

    protected function column_shortcode($item): string {
        return '<code>[wpns_form id="' . esc_html($item->id) . '"]</code>';
    }

    protected function column_default($item, $column_name): string {
        switch ($column_name) {
            case 'id':
                return (string) $item->id;
            case 'status':
                return esc_html($item->status);
            case 'created_at':
                return esc_html($item->created_at);
            default:
                return '';
        }
    }
}
