jQuery(function($) {
    /**
     * Insert the given text at the element's current cursor or selection, move the caret to the end of the inserted text, and focus the element.
     * @param {HTMLInputElement|HTMLTextAreaElement} el - The input or textarea element where the text will be inserted.
     * @param {string} text - The text to insert at the cursor/selection position.
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

    $('#wpns-format-json').on('click', function() {
        var $ta = $('#wpns-payload-template');
        try {
            var obj = JSON.parse($ta.val() || '{}');
            $ta.val(JSON.stringify(obj, null, 2));
        } catch (e) {
            alert('Invalid JSON: ' + e.message);
        }
    });

    $('#wpns-validate-json').on('click', function() {
        var $ta = $('#wpns-payload-template');
        try {
            JSON.parse($ta.val() || '{}');
            alert('JSON is valid.');
        } catch (e) {
            alert('Invalid JSON: ' + e.message);
        }
    });

    $('#wpns-insert-token').on('change', function() {
        var token = $(this).val();
        if (!token) {
            return;
        }
        insertAtCursor(document.getElementById('wpns-payload-template'), token);
        $(this).val('');
    });

    $('#wpns-preview-json').on('click', function() {
        var template = $('#wpns-payload-template').val() || '{}';
        var sampleMap = {};
        $('#wpns-fields-list .wpns-field-item').not('.wpns-field-template').each(function() {
            var name = $(this).find('.wpns-field-name').val();
            if (name) {
                sampleMap[name] = 'sample_' + name;
            }
        });
        var preview = template.replace(/{{\s*([a-zA-Z0-9_\.\-]+)\s*}}/g, function(_, key) {
            if (key.indexOf('__static__') === 0) {
                return '';
            }
            return sampleMap[key] || '';
        });
        try {
            var obj = JSON.parse(preview);
            preview = JSON.stringify(obj, null, 2);
        } catch (e) {
            // keep raw
        }

        var $modal = $('#wpns-preview-modal');
        if (!$modal.length) {
            $modal = $('<div id="wpns-preview-modal" class="wpns-modal" style="display:none;">'
                + '<div class="wpns-modal-content">'
                + '<h2>Payload Preview</h2>'
                + '<button type="button" class="wpns-modal-close" aria-label="Close">&times;</button>'
                + '<pre class="wpns-modal-pre"></pre>'
                + '</div></div>');
            $('body').append($modal);
        }
        $modal.find('.wpns-modal-pre').text(preview);
        $modal.show();
    });

    /* ── Submission: View modal ──────────────────────────────��──────────────── */

    $(document).on('click', '.wpns-view-submission', function() {
        var raw = $(this).data('submission');
        var text = '';
        try {
            var obj = typeof raw === 'string' ? JSON.parse(raw) : raw;
            text = JSON.stringify(obj, null, 2);
        } catch (e) {
            text = String(raw);
        }
        var $modal = $('#wpns-submission-modal');
        $modal.find('.wpns-modal-pre').text(text);
        $modal.show();
    });

    $(document).on('click', '.wpns-modal-close', function() {
        $(this).closest('.wpns-modal').hide();
    });

    /* ── Submission: Delete ─────────────────────────────────────────────────── */

    $(document).on('click', '.wpns-delete-submission', function() {
        if (!confirm('Delete this submission? This cannot be undone.')) { return; }
        var id   = $(this).data('submission-id');
        var $row = $(this).closest('tr');
        $.post(wpns_admin.ajax_url, {
            action: 'wpns_delete_submission',
            nonce:  wpns_admin.nonce,
            submission_id: id,
        }).done(function(res) {
            if (res.success) {
                $row.fadeOut(300, function() { $row.remove(); });
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Delete failed.');
            }
        }).fail(function() {
            alert('Delete failed.');
        });
    });

    /* ── Submission: Retry CRM push ─────────────────────────────────────────── */

    $(document).on('click', '.wpns-retry-submission', function() {
        var $btn = $(this);
        var id   = $btn.data('submission-id');
        $btn.text('Retrying…').prop('disabled', true);

        $.post(wpns_admin.ajax_url, {
            action:        'wpns_retry_submission',
            nonce:         wpns_admin.nonce,
            submission_id: id,
        }).done(function(res) {
            if (res.success) {
                $btn.text('Success!').css('color', 'green');
                // Optionally update the NS Success badge in this row.
                $btn.closest('tr').find('.wpns-badge:not(.success)').replaceWith(
                    '<span class="wpns-badge success">Yes</span>'
                );
            } else {
                var msg = res.data && res.data.message ? res.data.message : 'Retry failed.';
                $btn.text('Retry CRM').prop('disabled', false);
                alert(msg);
            }
        }).fail(function() {
            $btn.text('Retry CRM').prop('disabled', false);
            alert('Retry request failed.');
        });
    });
});
