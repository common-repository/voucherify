<?php
/**
 * Created by PhpStorm.
 * User: robert
 * Date: 06.02.2018
 * Time: 11:25
 */
?>
<script>
	(function ($) {
		// 1. initialize all baners list
		var initialize_banners = function () {
			var banners_list = $('.vcrf_promotion_dropdown .vcrf_form_wrapper '
				+ '.vcrf_available_promotions .vcrf_banners_list');
			var available_promotions_count = banners_list.length;

			if (available_promotions_count > 0) {
				$('.vcrf_form_wrapper').show();
				$('.vcrf_available_promotions').show();
				$('.vcrf_available_promotions .show_banners').show();
				$('.vcrf_available_promotions .hide_banners').hide();

				$(document).on('click', '.vcrf_available_promotions .show_banners', function (evt) {
					evt.preventDefault();
					$('.vcrf_banners_list').show();
					$('.vcrf_available_promotions .hide_banners').show();
					$('.vcrf_available_promotions .show_banners').hide();
				});

				$(document).on('click', '.vcrf_available_promotions .hide_banners', function (evt) {
					evt.preventDefault();
					$('.vcrf_banners_list').hide();
					$('.vcrf_available_promotions .hide_banners').hide();
					$('.vcrf_available_promotions .show_banners').show();
				});
			}
		};

		// 2. fetch promotions valid for cart
		var fetch_valid_promotions = function () {
			block($('div.vcrf_promotion_dropdown'));

			var data = {
				'action': 'vcrf_fetch_promotions'
			};

			$.post('<?php echo admin_url( 'admin-ajax.php' );?>', data, function (response) {
				response = JSON.parse(response);
				$('[name="vcrf_promotion_code"]').find(":not([value=''])").remove();
				if (response.length > 0) {
					for (var i = 0; i < response.length; ++i) {
						$('[name="vcrf_promotion_code"]')
							.append('<option value="' + response[i].id + '">' + response[i].banner + '</option>');
					}
					var is_not_checkout =
						$('.vcrf_form_wrapper').closest(".woocommerce-checkout").length === 0;

					if (is_not_checkout) {
						$('.vcrf_form_wrapper').show();
						$('.vcrf_form_wrapper .vcrf_promotion_dropdown_wrapper').show();
					}
				}
				unblock($('div.vcrf_promotion_dropdown'));
			});
		};

		// 4. wrap above in one initialize function
		var initialize = function () {
			initialize_banners();
			fetch_valid_promotions();
		};

		// 5. other functions
		var is_blocked = function ($node) {
			return $node.is('.processing') || $node.parents('.processing').length;
		};

		var block = function ($node) {
			if (!is_blocked($node)) {
				$node.addClass('processing').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			}
		};

		/**
		 * Unblock a node after processing is complete.
		 *
		 * @param {JQuery Object} $node
		 */
		var unblock = function ($node) {
			$node.removeClass('processing').unblock();
		};

		$(document).on('click', '.woocommerce-cart [name=vcrf_apply_promotion]', function (evt) {
			evt.preventDefault();
			$(document).trigger('wc_update_cart');
		});

		$(document).on('click', '.woocommerce-checkout [name=vcrf_apply_promotion]', function (evt) {
			evt.preventDefault();
			var $blocked_node = $(this).closest('.vcrf_form_wrapper');
			block($blocked_node);
			$.ajax({
				url: location.href,
				method: "POST",
				data: {
					ajax_request: true,
					vcrf_promotion_code: $('.vcrf_promotion_code').val()
				},
				complete: function () {
					unblock($blocked_node);
				},
				success: function (response) {
					$(document.body).trigger('update_checkout');
				}
			});
		});

		$(document.body).on('updated_wc_div', initialize);
		$(initialize);
	})(jQuery);
</script>
