/**
 * AI Post Scheduler - Frontend Monetization & GA4 Tracking
 * @package AI_Post_Scheduler
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		initTableOfContents();
		initClickTracking();
	});

	/**
	 * Build dynamic Table of Contents from article headings.
	 */
	function initTableOfContents() {
		var $toc = $('[data-aips-toc="true"]');
		if (!$toc.length) {
			return;
		}

		var $content = $('.entry-content, article, .post-content, main').first();
		if (!$content.length) {
			$content = $('body');
		}

		var $headings = $content.find('h2, h3').not('.aips-toc-header, .aips-product-title, .aips-faq-main-title, .aips-pros-cons-title');
		if ($headings.length < 2) {
			$toc.hide();
			return;
		}

		var $list = $toc.find('.aips-toc-list');
		$list.empty();

		$headings.each(function(index) {
			var $heading = $(this);
			var text = $heading.text().trim();
			var id = $heading.attr('id');

			if (!id) {
				id = 'aips-heading-' + index;
				$heading.attr('id', id);
			}

			var indentClass = $heading.is('h3') ? 'aips-toc-sub' : 'aips-toc-main';
			var $item = $('<li class="' + indentClass + '"><a href="#' + id + '">' + text + '</a></li>');
			$list.append($item);
		});
	}

	/**
	 * Attach event listeners to CTA buttons and comparison links.
	 */
	function initClickTracking() {
		$(document).on('click', '.aips-cta-button, .aips-product-link', function(e) {
			var $btn = $(this);
			var productId = $btn.data('product-id');
			var productTitle = $btn.data('product-title') || $btn.text().trim();
			var productPrice = $btn.data('product-price');
			var actionType = $btn.data('action-type') || 'click';
			var targetUrl = $btn.attr('href');

			// 1. Dispatch Google Analytics 4 (GA4) DataLayer Event
			if (window.dataLayer && typeof window.dataLayer.push === 'function' && window.aipsMonetizationData && window.aipsMonetizationData.ga4Enabled) {
				if (actionType === 'add_to_cart') {
					window.dataLayer.push({
						event: 'add_to_cart',
						ecommerce: {
							currency: 'USD',
							value: parseFloat(productPrice) || 0,
							items: [{
								item_id: String(productId),
								item_name: productTitle,
								price: parseFloat(productPrice) || 0,
								quantity: 1
							}]
						}
					});
				} else if (actionType === 'affiliate') {
					window.dataLayer.push({
						event: 'affiliate_outbound_click',
						ecommerce: {
							item_id: String(productId),
							item_name: productTitle,
							destination_url: targetUrl
						}
					});
				} else {
					window.dataLayer.push({
						event: 'select_item',
						ecommerce: {
							items: [{
								item_id: String(productId),
								item_name: productTitle
							}]
						}
					});
				}
			}

			// 2. Beacon to internal AIPS Link Click Tracking Service
			if (window.aipsMonetizationData && window.aipsMonetizationData.restUrl && productId) {
				var payload = {
					source_post_id: window.aipsMonetizationData.postId || 0,
					target_url: targetUrl,
					product_id: productId,
					action_type: actionType
				};

				if (navigator.sendBeacon) {
					var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
					navigator.sendBeacon(window.aipsMonetizationData.restUrl, blob);
				} else {
					$.ajax({
						url: window.aipsMonetizationData.restUrl,
						method: 'POST',
						contentType: 'application/json',
						data: JSON.stringify(payload),
						headers: {
							'X-WP-Nonce': window.aipsMonetizationData.nonce
						}
					});
				}
			}
		});
	}

})(jQuery);
