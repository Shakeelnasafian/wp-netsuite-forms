<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPNS_Admin_Submissions {
    public function render(): void {
        $table = new WPNS_Submissions_List_Table();
        $table->prepare_items();

        echo '<div class="wrap wpns-submissions">';
        echo '<h1>' . esc_html__('Submissions', 'wp-netsuite-forms') . '</h1>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="wpns-submissions">';
        $table->views();
        $table->display();
        echo '</form>';

        echo '<div id="wpns-submission-modal" class="wpns-modal" style="display:none;">';
        echo '<div class="wpns-modal-content">';
        echo '<button type="button" class="button-link wpns-modal-close">' . esc_html__('Close', 'wp-netsuite-forms') . '</button>';
        echo '<pre class="wpns-modal-pre"></pre>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }
}

class WPNS_Submissions_List_Table extends WP_List_Table {
    private array $forms = [];

    public function __construct() {
        parent::__construct([
            'singular' => 'submission',
            'plural' => 'submissions',
            'ajax' => false,
        ]);

        foreach (WPNS_Form_Model::get_all() as $form) {
            $this->forms[(int) $form->id] = $form->name;
        }
    }

    public function get_columns(): array {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'wp-netsuite-forms'),
            'form_id' => __('Form', 'wp-netsuite-forms'),
            'created_at' => __('Submitted At', 'wp-netsuite-forms'),
            'ns_success' => __('NS Success', 'wp-netsuite-forms'),
            'email_sent' => __('Email Sent', 'wp-netsuite-forms'),
            'actions' => __('Actions', 'wp-netsuite-forms'),
        ];
    }

    protected function get_bulk_actions(): array {
        return [
            'delete' => __('Delete', 'wp-netsuite-forms'),
        ];
    }

    public function prepare_items(): void {
        $this->process_bulk_action();

        $per_page = 50;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $form_filter = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        if ($form_filter) {
            $items = WPNS_Submission_Model::get_by_form($form_filter, $per_page, $offset);
            $total_items = WPNS_Submission_Model::count_by_form($form_filter);
        } else {
            $items = WPNS_Submission_Model::get_all($per_page, $offset);
            $total_items = WPNS_Submission_Model::count_all();
        }

        $this->items = $items;
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
        ]);

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];
    }

    public function views(): void {
        $current = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        echo '<div class="wpns-filters"><label for="wpns-filter-form">' . esc_html__('Filter by form:', 'wp-netsuite-forms') . '</label> ';
        echo '<select id="wpns-filter-form" name="form_id" onchange="this.form.submit()">';
        echo '<option value="0">' . esc_html__('All Forms', 'wp-netsuite-forms') . '</option>';
        foreach ($this->forms as $id => $name) {
            echo '<option value="' . esc_attr($id) . '"' . selected($current, $id, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select></div>';
    }

    protected function column_cb($item): string {
        return '<input type="checkbox" name="submission_ids[]" value="' . esc_attr($item->id) . '" />';
    }

    protected function column_form_id($item): string {
        $form_name = $this->forms[(int) $item->form_id] ?? __('Unknown', 'wp-netsuite-forms');
        return esc_html($form_name);
    }

    protected function column_ns_success($item): string {
        return !empty($item->ns_success) ? '<span class="wpns-badge success">' . esc_html__('Yes', 'wp-netsuite-forms') . '</span>' : '<span class="wpns-badge">' . esc_html__('No', 'wp-netsuite-forms') . '</span>';
    }

    protected function column_email_sent($item): string {
        return !empty($item->email_sent) ? '<span class="wpns-badge success">' . esc_html__('Yes', 'wp-netsuite-forms') . '</span>' : '<span class="wpns-badge">' . esc_html__('No', 'wp-netsuite-forms') . '</span>';
    }

    protected function column_actions($item): string {
        $data = [
            'submitted_data' => $item->submitted_data,
            'netsuite_payload' => $item->netsuite_payload,
            'netsuite_response' => $item->netsuite_response,
            'error_message' => $item->error_message,
        ];
        $json = esc_attr(wp_json_encode($data));

        return '<button type="button" class="button wpns-view-submission" data-submission="' . $json . '">' . esc_html__('View', 'wp-netsuite-forms') . '</button> '
            . '<button type="button" class="button-link-delete wpns-delete-submission" data-submission-id="' . esc_attr($item->id) . '">' . esc_html__('Delete', 'wp-netsuite-forms') . '</button>';
    }

    protected function column_default($item, $column_name): string {
        switch ($column_name) {
            case 'id':
                return (string) $item->id;
            case 'created_at':
                return esc_html($item->created_at);
            default:
                return '';
        }
    }

    public function process_bulk_action(): void {
        if ($this->current_action() !== 'delete') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $ids = isset($_POST['submission_ids']) ? array_map('absint', (array) $_POST['submission_ids']) : [];
        foreach ($ids as $id) {
            if ($id) {
                WPNS_Submission_Model::delete($id);
            }
        }
    }
}
