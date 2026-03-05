jQuery(function($) {
    /**
     * Convert a string into a slug suitable for use as an identifier.
     * @param {string} value - The input string to convert.
     * @returns {string} The input transformed to lowercase with non-alphanumeric sequences replaced by underscores and leading/trailing underscores removed.
     */
    function slugify(value) {
        return value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    /**
     * Show or hide the field's options area based on the field type.
     * @param {jQuery} $row - The field row element containing a `.wpns-field-type` select and a `.wpns-field-options` container; shows `.wpns-field-options` when type is `select`, `radio`, or `checkbox`, hides it otherwise.
     */
    function toggleOptions($row) {
        var type = $row.find('.wpns-field-type').val();
        var $opts = $row.find('.wpns-field-options');
        if (type === 'select' || type === 'radio' || type === 'checkbox') {
            $opts.show();
        } else {
            $opts.hide();
        }
    }

    /**
     * Insert given text at the current cursor/selection in a text input or textarea element.
     *
     * Replaces the current selection (if any) with the provided text, moves the caret to the end of the inserted text, and focuses the element.
     * @param {HTMLInputElement|HTMLTextAreaElement} el - The target input or textarea element.
     * @param {string} text - The text to insert at the cursor position.
     */
    function insertAtCursor(el, text) {
        if (!el) {
            return;
        }
        var start = el.selectionStart || 0;
        var end = el.selectionEnd || 0;
        var value = el.value || '';
        el.value = value.substring(0, start) + text + value.substring(end);
        el.selectionStart = el.selectionEnd = start + text.length;
        el.focus();
    }

    if ($('#wpns-fields-list').length) {
        $('#wpns-fields-list').sortable({
            handle: '.dashicons-move',
            items: '> .wpns-field-row:not(.wpns-field-template)'
        });
    }
    $('.wpns-field-row').not('.wpns-field-template').each(function() {
        toggleOptions($(this));
    });

    $(document).on('click', '#wpns-add-field', function() {
        var $tpl = $('.wpns-field-template').first().clone();
        $tpl.removeClass('wpns-field-template').show();
        $tpl.find('input, select').val('');
        $tpl.find('.wpns-field-required').prop('checked', false);
        $tpl.find('.wpns-options-list').empty();
        $('#wpns-fields-list').append($tpl);
        toggleOptions($tpl);
    });

    $(document).on('click', '.wpns-remove-field', function() {
        $(this).closest('.wpns-field-row').remove();
    });

    $(document).on('change', '.wpns-field-type', function() {
        toggleOptions($(this).closest('.wpns-field-row'));
    });

    $(document).on('click', '.wpns-add-option', function() {
        var $list = $(this).siblings('.wpns-options-list');
        var row = '<div class="wpns-option-row">'
            + '<input type="text" class="wpns-option-label" placeholder="Option Label">'
            + '<input type="text" class="wpns-option-value" placeholder="Option Value">'
            + '<button type="button" class="button-link wpns-remove-option">Remove</button>'
            + '</div>';
        $list.append(row);
    });

    $(document).on('click', '.wpns-remove-option', function() {
        $(this).closest('.wpns-option-row').remove();
    });

    $(document).on('input', '.wpns-field-label', function() {
        var $row = $(this).closest('.wpns-field-row');
        var $name = $row.find('.wpns-field-name');
        if ($name.data('manual')) {
            return;
        }
        $name.val(slugify($(this).val()));
    });

    $(document).on('input', '.wpns-field-name', function() {
        $(this).data('manual', true);
    });

    $(document).on('click', '#wpns-add-static', function() {
        var row = '<tr class="wpns-static-row">'
            + '<td><input type="text" class="regular-text wpns-static-path"></td>'
            + '<td><input type="text" class="regular-text wpns-static-value"></td>'
            + '<td><button type="button" class="button-link wpns-remove-static">Remove</button></td>'
            + '</tr>';
        $('#wpns-static-values-body').append(row);
    });

    $(document).on('click', '.wpns-remove-static', function() {
        $(this).closest('tr').remove();
    });

    $(document).on('click', '.nav-tab', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.wpns-tab-content').hide();
        $('.wpns-tab-content[data-tab="' + tab + '"]').show();
    });

    $(document).on('click', '.wpns-email-token', function() {
        var token = $(this).data('token');
        var el = document.getElementById('wpns-email-subject');
        insertAtCursor(el, token);
    });

    $('#wpns-form-edit').on('submit', function(e) {
        e.preventDefault();
        var $status = $('.wpns-save-status');
        $status.text('Saving...');

        var fields = [];
        $('#wpns-fields-list .wpns-field-row').not('.wpns-field-template').each(function() {
            var $row = $(this);
            var options = [];
            $row.find('.wpns-option-row').each(function() {
                var $opt = $(this);
                options.push({
                    label: $opt.find('.wpns-option-label').val() || '',
                    value: $opt.find('.wpns-option-value').val() || ''
                });
            });

            fields.push({
                field_label: $row.find('.wpns-field-label').val() || '',
                field_name: $row.find('.wpns-field-name').val() || '',
                field_type: $row.find('.wpns-field-type').val() || 'text',
                placeholder: $row.find('.wpns-field-placeholder').val() || '',
                default_val: $row.find('.wpns-field-default').val() || '',
                css_class: $row.find('.wpns-field-css').val() || '',
                is_required: $row.find('.wpns-field-required').is(':checked') ? 1 : 0,
                options: options
            });
        });

        var staticValues = {};
        $('#wpns-static-values-body .wpns-static-row').each(function() {
            var path = $(this).find('.wpns-static-path').val() || '';
            var value = $(this).find('.wpns-static-value').val() || '';
            if (path) {
                staticValues[path] = value;
            }
        });

        var data = {
            action: 'wpns_save_form',
            nonce: wpns_admin.nonce,
            form_id: parseInt($('input[name="form_id"]').val(), 10) || 0,
            name: $('#wpns-form-name').val() || '',
            description: $('#wpns-form-description').val() || '',
            status: $('#wpns-form-status').val() || 'active',
            success_message: $('#wpns-form-success').val() || '',
            redirect_url: $('#wpns-form-redirect').val() || '',
            fields_json: JSON.stringify(fields),
            credential_id: $('#wpns-credential-id').val() || 0,
            payload_template: $('#wpns-payload-template').val() || '',
            static_values_json: JSON.stringify(staticValues),
            enable_netsuite: $('input[name="enable_netsuite"]').is(':checked') ? 1 : 0,
            enable_email: $('input[name="enable_email"]').is(':checked') ? 1 : 0,
            email_from_name: $('#wpns-email-from-name').val() || '',
            email_from_address: $('#wpns-email-from-address').val() || '',
            email_to: $('#wpns-email-to').val() || '',
            email_cc: $('#wpns-email-cc').val() || '',
            email_bcc: $('#wpns-email-bcc').val() || '',
            email_subject: $('#wpns-email-subject').val() || '',
            email_body: $('#wpns-email-body').val() || ''
        };

        $.post(wpns_admin.ajax_url, data).done(function(res) {
            if (res.success) {
                $status.text('Saved. Shortcode: ' + res.data.shortcode);
                if (data.form_id === 0 && res.data.form_id) {
                    $('input[name="form_id"]').val(res.data.form_id);
                    var newUrl = window.location.href;
                    if (newUrl.indexOf('form_id=') === -1) {
                        newUrl += (newUrl.indexOf('?') === -1 ? '?' : '&') + 'form_id=' + res.data.form_id;
                        window.history.replaceState({}, document.title, newUrl);
                    }
                }
            } else {
                $status.text(res.data && res.data.message ? res.data.message : 'Save failed.');
            }
        }).fail(function() {
            $status.text('Save failed.');
        });
    });

    $(document).on('click', '.wpns-delete-form', function(e) {
        e.preventDefault();
        if (!confirm('Delete this form?')) {
            return;
        }
        var formId = $(this).data('form-id');
        $.post(wpns_admin.ajax_url, {
            action: 'wpns_delete_form',
            nonce: wpns_admin.nonce,
            form_id: formId
        }).done(function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Delete failed.');
            }
        }).fail(function() {
            alert('Delete failed.');
        });
    });

    $(document).on('click', '#wpns-test-netsuite', function() {
        var $result = $('.wpns-test-result');
        $result.text('Testing...');
        $.post(wpns_admin.ajax_url, {
            action: 'wpns_test_netsuite',
            nonce: wpns_admin.nonce,
            credential_id: $('#wpns-credential-id').val() || 0,
            form_id: $('input[name="form_id"]').val() || 0
        }).done(function(res) {
            if (res.success) {
                $result.text('Success');
            } else {
                $result.text('Failed');
            }
        }).fail(function() {
            $result.text('Failed');
        });
    });

    $(document).on('click', '.wpns-view-submission', function() {
        var data = $(this).data('submission');
        if (typeof data === 'string') {
            try { data = JSON.parse(data); } catch (e) { data = { error: data }; }
        }
        var pretty = JSON.stringify(data, null, 2);
        $('#wpns-submission-modal .wpns-modal-pre').text(pretty);
        $('#wpns-submission-modal').show();
    });

    $(document).on('click', '.wpns-modal-close', function() {
        $(this).closest('.wpns-modal').hide();
    });

    $(document).on('click', '.wpns-delete-submission', function() {
        if (!confirm('Delete this submission?')) {
            return;
        }
        var submissionId = $(this).data('submission-id');
        $.post(wpns_admin.ajax_url, {
            action: 'wpns_delete_submission',
            nonce: wpns_admin.nonce,
            submission_id: submissionId
        }).done(function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Delete failed.');
            }
        }).fail(function() {
            alert('Delete failed.');
        });
    });
});
