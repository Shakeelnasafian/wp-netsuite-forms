jQuery(function($) {
    $(document).on('submit', '.wpns-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('[type="submit"]');
        var $resp = $form.find('.wpns-response');

        var errors = [];
        $form.find('[required]').each(function() {
            var $field = $(this);
            if ($field.is(':checkbox') || $field.is(':radio')) {
                var name = $field.attr('name');
                if (name && !$form.find('[name=\"' + name + '\"]:checked').length) {
                    errors.push($field.data('label') + ' is required');
                    $field.addClass('wpns-error');
                } else {
                    $field.removeClass('wpns-error');
                }
                return;
            }
            if (!$field.val() || !$field.val().toString().trim()) {
                errors.push($field.data('label') + ' is required');
                $field.addClass('wpns-error');
            } else {
                $field.removeClass('wpns-error');
            }
        });

        if (errors.length) {
            $resp.html('<div class="wpns-error-msg">' + errors.join('<br>') + '</div>');
            return;
        }

        $btn.prop('disabled', true).text('Sending...');
        $resp.html('');

        var formData = new FormData($form[0]);
        formData.append('action', 'wpns_submit_form');
        formData.append('nonce', wpns_ajax.nonce);

        ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(function(k) {
            formData.append(k, localStorage.getItem(k) || '');
        });

        $.ajax({
            url: wpns_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    $resp.html('<div class="wpns-success">' + res.data.message + '</div>');
                    $form[0].reset();
                    if (res.data.redirect_url) {
                        window.location.href = res.data.redirect_url;
                    }
                } else {
                    if (res.data && res.data.errors) {
                        var msgs = [];
                        $.each(res.data.errors, function(_, m) { msgs.push(m); });
                        $resp.html('<div class="wpns-error-msg">' + msgs.join('<br>') + '</div>');
                    } else {
                        var msg = (res.data && res.data.message) ? res.data.message : 'Error. Please try again.';
                        $resp.html('<div class="wpns-error-msg">' + msg + '</div>');
                    }
                }
            },
            error: function() {
                $resp.html('<div class="wpns-error-msg">Network error. Please try again.</div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Submit');
            }
        });
    });
});
