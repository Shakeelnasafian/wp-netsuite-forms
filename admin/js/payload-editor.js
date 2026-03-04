jQuery(function($) {
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
        $('#wpns-fields-list .wpns-field-row').not('.wpns-field-template').each(function() {
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
                + '<button type="button" class="button-link wpns-modal-close">Close</button>'
                + '<pre class="wpns-modal-pre"></pre>'
                + '</div></div>');
            $('body').append($modal);
        }
        $modal.find('.wpns-modal-pre').text(preview);
        $modal.show();
    });
});
