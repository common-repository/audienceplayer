jQuery(function ($) {

    $('#audienceplayer_admin_user_profile_force_sync_button').on('click', function (e) {

        var $this = $(this);

        $(".spinner").addClass("is-active");

        wp.ajax.post('audienceplayer_admin_user_profile_force_sync', {
            nonce: $('#_wpnonce').val(),
            user_id: $('#user_id').val()
        }).done(function (response) {

            $(".spinner").removeClass("is-active");
            $('#audienceplayer_admin_user_profile_audienceplayer_user_id').val(response.audienceplayer_user_id);

            $this.prop('disabled', true);
            $this.siblings('.notice').remove();
            $this.before('<div class="notice notice-success inline"><p>' + response.message + '</p></div>');

        }).fail(function (response) {

            $(".spinner").removeClass("is-active");
            $this.siblings('.notice').remove();
            $this.before('<div class="notice notice-error inline"><p>An error occurred!</p><span style="font-family:monospace;">' + JSON.stringify(response.responseJSON.data) + '</span></div>');

        });

        e.preventDefault();
    });

});
