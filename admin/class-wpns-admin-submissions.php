<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPNS_Admin_Submissions
{
    /**
     * Renders the Submissions admin page markup.
     *
     * Outputs the submissions list table and filter form and includes a modal
     * used to view individual submission details.
     */
    public function render(): void
    {
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

class WPNS_Submissions_List_Table extends WP_List_Table
{
    private array $forms = [];

    /**
     * Initializes the submissions list table and loads a mapping of form IDs to form names.
     *
     * Sets up the list table labels and behavior, then populates $this->forms with available
     * forms (id => name) for use in column rendering and filtering.
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'submission',
            'plural' => 'submissions',
            'ajax' => false,
        ]);

        foreach (WPNS_Form_Model::get_all() as $form) {
            $this->forms[(int) $form->id] = $form->name;
        }
    }

    /**
     * Provide column definitions for the submissions list table.
     *
     * @return array Associative array mapping column identifiers to header labels or HTML.
    *  Keys:
    *  - 'cb'         => checkbox HTML for bulk actions
    *  - 'id'         => submission ID label
    *  - 'form_id'    => form name label
    *  - 'created_at' => submission timestamp label
    *  - 'ns_success' => NetSuite success status label
    *  - 'email_sent' => email sent status label
    *                 - 'actions'    => actions column label
     */
    public function get_columns(): array
    {
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

    /**
     * Define the bulk actions available for the list table.
     *
     * @return array An associative array mapping bulk action slugs to their display labels.
     */
    protected function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete', 'wp-netsuite-forms'),
        ];
    }

    /**
     * Prepares list table data and UI state for display.
     *
     * Processes any pending bulk action, loads submissions for the current page (optionally filtered by the `form_id` GET parameter),
     * and configures the list table's items, pagination arguments, and column headers.
     *
     * The method sets $this->items, pagination args (total items and per-page), and $_column_headers for rendering.
     */
    public function prepare_items(): void
    {
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

    /**
     * Renders a "Filter by form" dropdown for the submissions list and submits the list form when changed.
     *
     * The dropdown includes an "All Forms" option and one option per known form; the currently selected form is
     * determined from the `form_id` query parameter.
     */
    public function views(): void
    {
        $current = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        echo '<div class="wpns-filters"><label for="wpns-filter-form">' . esc_html__('Filter by form:', 'wp-netsuite-forms') . '</label> ';
        echo '<select id="wpns-filter-form" name="form_id" onchange="this.form.submit()">';
        echo '<option value="0">' . esc_html__('All Forms', 'wp-netsuite-forms') . '</option>';
        foreach ($this->forms as $id => $name) {
            echo '<option value="' . esc_attr($id) . '"' . selected($current, $id, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select></div>';
    }

    /**
     * Renders the row checkbox input for selecting a submission.
     *
     * @param object $item The submission record; must have an `id` property.
     * @return string The HTML checkbox input markup with the submission id as its value.
     */
    protected function column_cb($item): string
    {
        return '<input type="checkbox" name="submission_ids[]" value="' . esc_attr($item->id) . '" />';
    }

    /**
     * Render the human-readable form name for a submission table row.
     *
     * @param object $item Row object representing a submission; its `form_id` property is used to look up the form name.
     * @return string The form name escaped for safe HTML output, or `'Unknown'` if the form id is not found.
     */
    protected function column_form_id($item): string
    {
        $form_name = $this->forms[(int) $item->form_id] ?? __('Unknown', 'wp-netsuite-forms');
        return esc_html($form_name);
    }

    /**
     * Render a status badge indicating whether Netsuite submission succeeded.
     *
     * @param object $item Submission record object; expected to contain an `ns_success` property.
     * @return string HTML markup for a badge with "Yes" when `ns_success` is non-empty, otherwise "No".
     */
    protected function column_ns_success($item): string
    {
        return !empty($item->ns_success) ? '<span class="wpns-badge success">' . esc_html__('Yes', 'wp-netsuite-forms') . '</span>' : '<span class="wpns-badge">' . esc_html__('No', 'wp-netsuite-forms') . '</span>';
    }

    /**
     * Render the "Email Sent" column as an HTML badge reflecting whether an email was sent for the submission.
     *
     * @param object $item Submission record object with an `email_sent` property.
     * @return string HTML markup for the column: a "Yes" badge when `email_sent` is present, otherwise a "No" badge.
     */
    protected function column_email_sent($item): string
    {
        return !empty($item->email_sent) ? '<span class="wpns-badge success">' . esc_html__('Yes', 'wp-netsuite-forms') . '</span>' : '<span class="wpns-badge">' . esc_html__('No', 'wp-netsuite-forms') . '</span>';
    }

    /**
     * Render action buttons for a submission row.
     *
     * @param object $item Submission record containing `id`, `submitted_data`, `netsuite_payload`, `netsuite_response`, and `error_message`.
     * @return string HTML for the "View" button (with a JSON-encoded submission payload in `data-submission`) and the "Delete" button (with `data-submission-id`).
     */
    protected function column_actions($item): string
    {
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

    /**
     * Renders default cell content for a submissions list table column.
     *
     * For 'id' returns the item's id as a string; for 'created_at' returns the escaped created_at value. Returns an empty string for any other column.
     *
     * @param object $item The submission item.
     * @param string $column_name The column key being rendered.
     * @return string The content to display in the cell.
     */
    protected function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'id':
                return (string) $item->id;
            case 'created_at':
                return esc_html($item->created_at);
            default:
                return '';
        }
    }

    /**
     * Deletes submissions whose IDs are submitted via POST when the current bulk action is "delete" and the current user has the `manage_options` capability.
     *
     * The function reads `submission_ids` from POST, casts each to an integer, ignores zero/invalid IDs, and removes each valid submission.
     */
    public function process_bulk_action(): void
    {
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
