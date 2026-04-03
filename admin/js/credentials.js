jQuery(function($) {
    /**
     * Populate credential form fields from a credential object.
     *
     * @param {Object} cred - Credential data used to fill the form.
     * @param {number} [cred.id] - Credential ID; defaults to 0 if missing or falsy.
     * @param {string} [cred.profile_name] - Profile name; defaults to empty string.
     * @param {string} [cred.account_id] - Account ID; defaults to empty string.
     * @param {string} [cred.realm] - Realm; defaults to empty string.
     * @param {string} [cred.consumer_key] - Consumer key; defaults to empty string.
     * @param {string} [cred.consumer_secret] - Consumer secret; defaults to empty string.
     * @param {string} [cred.token_key] - Token key; defaults to empty string.
     * @param {string} [cred.token_secret] - Token secret; defaults to empty string.
     * @param {string} [cred.script_id] - Script ID; defaults to empty string.
     * @param {string} [cred.deploy_id] - Deploy ID; defaults to '1' if missing or falsy.
     */
    function fillForm(cred) {
        $('#wpns-credential-id').val(cred.id || 0);
        $('#wpns-profile-name').val(cred.profile_name || '');
        $('#wpns-account-id').val(cred.account_id || '');
        $('#wpns-realm').val(cred.realm || '');
        $('#wpns-consumer-key').val(cred.consumer_key || '');
        $('#wpns-consumer-secret').val(cred.consumer_secret || '');
        $('#wpns-token-key').val(cred.token_key || '');
        $('#wpns-token-secret').val(cred.token_secret || '');
        $('#wpns-script-id').val(cred.script_id || '');
        $('#wpns-deploy-id').val(cred.deploy_id || '1');
    }

    $(document).on('click', '.wpns-toggle-secret', function(e) {
        e.preventDefault();
        var $input = $(this).siblings('input');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $(this).text('Hide');
        } else {
            $input.attr('type', 'password');
            $(this).text('Show');
        }
    });

    function resetForm() {
        fillForm({ id: 0, profile_name: '', account_id: '', realm: '', consumer_key: '', consumer_secret: '', token_key: '', token_secret: '', script_id: '', deploy_id: '1' });
        $('#wpns-credential-form-title').text('Add New Credential');
        $('.wpns-credential-status').text('');
        // Reset all secret fields back to password type
        $('#wpns-credential-form input[type="text"][id^="wpns-consumer"], #wpns-credential-form input[type="text"][id^="wpns-token"]').each(function() {
            $(this).attr('type', 'password');
        });
        $('.wpns-toggle-secret').text('Show');
    }

    $(document).on('click', '#wpns-reset-credential', function() {
        resetForm();
    });

    $(document).on('click', '.wpns-edit-credential', function() {
        var $row = $(this).closest('tr');
        var raw = $row.attr('data-credential');
        if (!raw) {
            return;
        }
        try {
            var cred = JSON.parse(raw);
            fillForm(cred);
            $('#wpns-credential-form-title').text('Edit Credential: ' + (cred.profile_name || ''));
            $('.wpns-credential-status').text('');
            // Scroll to form
            $('html, body').animate({ scrollTop: $('#wpns-credential-form-title').offset().top - 40 }, 300);
        } catch (e) {
            alert('Failed to load credential data.');
        }
    });

    $('#wpns-credential-form').on('submit', function(e) {
        e.preventDefault();
        var $status = $('.wpns-credential-status');
        $status.text('Saving...');

        var data = {
            action: 'wpns_save_credential',
            nonce: wpns_admin.nonce,
            credential_id: $('#wpns-credential-id').val() || 0,
            profile_name: $('#wpns-profile-name').val() || '',
            account_id: $('#wpns-account-id').val() || '',
            realm: $('#wpns-realm').val() || '',
            consumer_key: $('#wpns-consumer-key').val() || '',
            consumer_secret: $('#wpns-consumer-secret').val() || '',
            token_key: $('#wpns-token-key').val() || '',
            token_secret: $('#wpns-token-secret').val() || '',
            script_id: $('#wpns-script-id').val() || '',
            deploy_id: $('#wpns-deploy-id').val() || '1'
        };

        $.post(wpns_admin.ajax_url, data).done(function(res) {
            if (res.success) {
                $status.text('Saved successfully.');
                location.reload();
            } else {
                $status.text(res.data && res.data.message ? res.data.message : 'Save failed.');
            }
        }).fail(function() {
            $status.text('Save failed.');
        });
    });

    $(document).on('click', '.wpns-delete-credential', function() {
        if (!confirm('Delete this credential?')) {
            return;
        }
        var id = $(this).data('credential-id');
        $.post(wpns_admin.ajax_url, {
            action: 'wpns_delete_credential',
            nonce: wpns_admin.nonce,
            credential_id: id
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

    $(document).on('click', '.wpns-test-credential', function() {
        var id = $(this).data('credential-id');
        $.post(wpns_admin.ajax_url, {
            action: 'wpns_test_netsuite',
            nonce: wpns_admin.nonce,
            credential_id: id
        }).done(function(res) {
            if (res.success) {
                alert('Connection successful.');
            } else {
                alert('Connection failed.');
            }
        }).fail(function() {
            alert('Connection failed.');
        });
    });
});
