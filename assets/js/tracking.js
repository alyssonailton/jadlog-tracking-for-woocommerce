jQuery(function ($) {

    $(document).on('click', '.jadlog-tracking-code .button-secondary', function (e) {
        e.preventDefault();

        var $el = $('#add-jadlog-code');
        var trackingCode = $el.val();

        var data = {
            action: 'woocommerce_jadlog_add_tracking_code',
            _ajax_nonce: jadlog_security_nonce,
            order_id: jadlog_order_id,
            tracking_code: trackingCode
        };

        $('#wc_jadlog_tracking').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        // Add tracking code.
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: data,
            success: function (response) {
                if ('' === trackingCode) {
                    $('.jadlog-tracking-code .button-secondary').removeClass('dashicons-edit');
                    $('.jadlog-tracking-code .button-secondary').addClass('dashicons-plus');
                } else {
                    $('.jadlog-tracking-code .button-secondary').removeClass('dashicons-plus');
                    $('.jadlog-tracking-code .button-secondary').addClass('dashicons-edit');
                }
                $('#wc_jadlog_tracking').unblock();
            }
        });
    });

});