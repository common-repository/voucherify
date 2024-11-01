<?php
global $post;
?>
<p style="border-bottom:solid 1px #eeeeee;padding-bottom:13px;">
    <input type="button" id="vcrf_unlock_order" class="metabox_submit" value="Release now"/>
</p>
<script>
    jQuery(document).on('click', '#vcrf_unlock_order', function (evt) {
        evt.preventDefault();
        jQuery.ajax({
            url: '<?php echo admin_url( 'admin-ajax.php' ) ?>',
            method: "POST",
            data: {
                action: 'vcrf_unlock_order',
                order_id: '<?php echo $post->ID ?>'
            },
            complete: function () {
            },
            success: function () {
                jQuery('#vcrf_unlock_order_fields').remove();
            }
        });
    });
</script>