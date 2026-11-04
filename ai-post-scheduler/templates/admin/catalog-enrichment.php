<?php
/**
 * Catalog AI Enrichment Hub Admin Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wc_adapter = new AIPS_WooCommerce_Adapter();
$is_wc_active = $wc_adapter->is_active();
?>

<div class="wrap aips-wrap">
	<div class="aips-header">
		<div class="aips-header-content">
			<h1 class="aips-title">
				<span class="dashicons dashicons-cart" aria-hidden="true"></span>
				<?php esc_html_e( 'WooCommerce Catalog AI Enrichment Hub', 'ai-post-scheduler' ); ?>
			</h1>
			<p class="aips-subtitle">
				<?php esc_html_e( 'Audit and supercharge your product descriptions, feature bullets, and buy-box copy using AI generation.', 'ai-post-scheduler' ); ?>
			</p>
		</div>
	</div>

	<?php if ( ! $is_wc_active ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'WooCommerce Not Detected:', 'ai-post-scheduler' ); ?></strong>
				<?php esc_html_e( 'Install and activate WooCommerce to unlock direct product catalog synchronization and enrichment.', 'ai-post-scheduler' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="aips-content-panel">
		<!-- Filter Bar -->
		<div class="aips-filter-bar" style="display:flex; justify-content:space-between; align-items:center; padding:16px; background:#fff; border-bottom:1px solid #e2e8f0;">
			<div class="aips-filter-left" style="display:flex; gap:12px; align-items:center;">
				<label for="aips-ce-filter-status" class="screen-reader-text"><?php esc_html_e( 'Filter By', 'ai-post-scheduler' ); ?></label>
				<select id="aips-ce-filter-status" class="aips-form-select">
					<option value="all"><?php esc_html_e( 'All Products', 'ai-post-scheduler' ); ?></option>
					<option value="missing_short_desc"><?php esc_html_e( 'Missing Short Description (Buy Box)', 'ai-post-scheduler' ); ?></option>
					<option value="missing_content"><?php esc_html_e( 'Missing Long Description', 'ai-post-scheduler' ); ?></option>
					<option value="low_word_count"><?php esc_html_e( 'Thin Content (< 50 words)', 'ai-post-scheduler' ); ?></option>
				</select>

				<input type="search" id="aips-ce-search" class="aips-form-input" placeholder="<?php esc_attr_e( 'Search by product title…', 'ai-post-scheduler' ); ?>" style="width:240px;">
				<button type="button" id="aips-ce-filter-btn" class="button button-secondary"><?php esc_html_e( 'Filter', 'ai-post-scheduler' ); ?></button>
			</div>
			<div class="aips-filter-right">
				<span id="aips-ce-count" class="aips-text-muted" style="font-size:13px; color:#64748b;"></span>
			</div>
		</div>

		<!-- Products Table -->
		<div class="aips-panel-body no-padding">
			<table class="wp-list-table widefat fixed striped" id="aips-ce-table">
				<thead>
					<tr>
						<th style="width:60px;"><?php esc_html_e( 'Image', 'ai-post-scheduler' ); ?></th>
						<th><?php esc_html_e( 'Product Title', 'ai-post-scheduler' ); ?></th>
						<th><?php esc_html_e( 'Price / Status', 'ai-post-scheduler' ); ?></th>
						<th><?php esc_html_e( 'Short Description', 'ai-post-scheduler' ); ?></th>
						<th><?php esc_html_e( 'Full Copy Status', 'ai-post-scheduler' ); ?></th>
						<th style="width:140px; text-align:right;"><?php esc_html_e( 'Actions', 'ai-post-scheduler' ); ?></th>
					</tr>
				</thead>
				<tbody id="aips-ce-tbody">
					<tr>
						<td colspan="6" style="text-align:center; padding:24px;">
							<span class="spinner is-active" style="float:none; margin:0 auto 10px;"></span>
							<div><?php esc_html_e( 'Loading catalog items…', 'ai-post-scheduler' ); ?></div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Side-by-Side Enrichment Modal -->
	<div id="aips-ce-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center;">
		<div style="background:#fff; width:90%; max-width:1000px; max-height:90vh; border-radius:8px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
			<!-- Modal Header -->
			<div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
				<h3 style="margin:0; font-size:16px; font-weight:700;" id="aips-ce-modal-title">
					<?php esc_html_e( 'AI Product Enrichment & Diff Preview', 'ai-post-scheduler' ); ?>
				</h3>
				<button type="button" id="aips-ce-modal-close" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748b;">&times;</button>
			</div>

			<!-- Controls Bar -->
			<div style="padding:12px 20px; background:#f1f5f9; display:flex; gap:16px; align-items:center; border-bottom:1px solid #e2e8f0;">
				<div>
					<label style="font-weight:600; font-size:12px;"><?php esc_html_e( 'Target Field:', 'ai-post-scheduler' ); ?></label>
					<select id="aips-ce-target-field" style="margin-left:6px;">
						<option value="short_description"><?php esc_html_e( 'Short Description (Buy Box)', 'ai-post-scheduler' ); ?></option>
						<option value="full_description"><?php esc_html_e( 'Full Product Description', 'ai-post-scheduler' ); ?></option>
					</select>
				</div>
				<div>
					<label style="font-weight:600; font-size:12px;"><?php esc_html_e( 'Tone & Voice:', 'ai-post-scheduler' ); ?></label>
					<input type="text" id="aips-ce-tone" value="persuasive and benefits-focused" style="margin-left:6px; width:220px;" />
				</div>
				<button type="button" id="aips-ce-generate-preview-btn" class="button button-primary" style="margin-left:auto;">
					<span class="dashicons dashicons-admin-generic" style="vertical-align:middle; font-size:16px;"></span>
					<?php esc_html_e( 'Generate AI Preview', 'ai-post-scheduler' ); ?>
				</button>
			</div>

			<!-- Diff Columns -->
			<div style="display:flex; flex:1; overflow-y:auto; padding:20px; gap:20px;">
				<div style="flex:1; display:flex; flex-direction:column;">
					<h4 style="margin-top:0; color:#64748b; font-size:13px; text-transform:uppercase; letter-spacing:0.5px;">
						<?php esc_html_e( 'Current Content', 'ai-post-scheduler' ); ?>
					</h4>
					<div id="aips-ce-current-preview" style="flex:1; border:1px solid #e2e8f0; border-radius:6px; padding:12px; background:#f8fafc; overflow-y:auto; font-size:13px; line-height:1.5;">
					</div>
				</div>
				<div style="flex:1; display:flex; flex-direction:column;">
					<h4 style="margin-top:0; color:#2563eb; font-size:13px; text-transform:uppercase; letter-spacing:0.5px;">
						<?php esc_html_e( 'AI Proposed Content (Editable)', 'ai-post-scheduler' ); ?>
					</h4>
					<textarea id="aips-ce-proposed-preview" style="flex:1; border:1px solid #93c5fd; border-radius:6px; padding:12px; font-size:13px; font-family:monospace; line-height:1.5; resize:none;"></textarea>
				</div>
			</div>

			<!-- Modal Footer -->
			<div style="padding:16px 20px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:12px; background:#f8fafc;">
				<button type="button" id="aips-ce-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'ai-post-scheduler' ); ?></button>
				<button type="button" id="aips-ce-modal-apply" class="button button-primary" style="background:#16a34a; border-color:#15803d;">
					<span class="dashicons dashicons-saved" style="vertical-align:middle; font-size:16px;"></span>
					<?php esc_html_e( 'Apply to Product', 'ai-post-scheduler' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- AIPS Client-Side Templates -->
	<script type="text/html" id="aips-tmpl-ce-loading-row">
		<tr>
			<td colspan="6" style="text-align:center; padding:24px;">
				<span class="spinner is-active" style="float:none; margin:0 auto 10px;"></span>
				<div>{{message}}</div>
			</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-ce-empty-row">
		<tr>
			<td colspan="6" style="text-align:center; padding:20px;">{{message}}</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-ce-product-img">
		<img src="{{url}}" style="width:40px; height:40px; object-fit:cover; border-radius:4px;" />
	</script>

	<script type="text/html" id="aips-tmpl-ce-product-no-img">
		<span class="dashicons dashicons-format-image" style="font-size:32px; color:#cbd5e1;"></span>
	</script>

	<script type="text/html" id="aips-tmpl-ce-product-row">
		<tr>
			<td>{{image_html}}</td>
			<td>
				<strong>{{title}}</strong><br>
				<small style="color:#64748b;">SKU: {{sku}}</small>
			</td>
			<td>{{formatted_price}}</td>
			<td>{{short_desc_status}}</td>
			<td>{{content_status}}</td>
			<td style="text-align:right;">
				<button type="button" class="button button-small button-primary aips-ce-enrich-btn" data-product-id="{{id}}" data-product-title="{{title}}">
					<?php esc_html_e( 'AI Enrich', 'ai-post-scheduler' ); ?>
				</button>
			</td>
		</tr>
	</script>
</div>

<script>
(function($) {
	'use strict';
	var currentProductId = 0;
	var currentNonce = '<?php echo esc_js( wp_create_nonce( 'aips_catalog_enrichment_nonce' ) ); ?>';

	$(document).ready(function() {
		loadCatalogProducts();

		$('#aips-ce-filter-btn').on('click', function() {
			loadCatalogProducts();
		});

		$('#aips-ce-modal-close, #aips-ce-modal-cancel').on('click', function() {
			$('#aips-ce-modal').hide();
		});

		$(document).on('click', '.aips-ce-enrich-btn', function() {
			currentProductId = $(this).data('product-id');
			var title = $(this).data('product-title');
			$('#aips-ce-modal-title').text('Enrich: ' + title);
			$('#aips-ce-modal').css('display', 'flex');
			loadPreview();
		});

		$('#aips-ce-generate-preview-btn').on('click', function() {
			loadPreview();
		});

		$('#aips-ce-modal-apply').on('click', function() {
			applyEnrichment();
		});
	});

	function renderTemplate(id, data) {
		if (window.AIPS && AIPS.Templates && typeof AIPS.Templates.renderRaw === 'function') {
			return AIPS.Templates.renderRaw(id, data);
		}
		return '';
	}

	function loadCatalogProducts() {
		var filterBy = $('#aips-ce-filter-status').val();
		var search = $('#aips-ce-search').val();

		var loadingHtml = renderTemplate('aips-tmpl-ce-loading-row', {
			message: '<?php echo esc_js( __( 'Loading catalog items…', 'ai-post-scheduler' ) ); ?>'
		});
		$('#aips-ce-tbody').html(loadingHtml);

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'aips_catalog_enrichment_list',
				nonce: currentNonce,
				filter_by: filterBy,
				search: search
			},
			success: function(res) {
				if (res.success && res.data && res.data.items) {
					renderProductsTable(res.data.items, res.data.total);
				} else {
					var emptyHtml = renderTemplate('aips-tmpl-ce-empty-row', {
						message: '<?php echo esc_js( __( 'No products found matching criteria.', 'ai-post-scheduler' ) ); ?>'
					});
					$('#aips-ce-tbody').html(emptyHtml);
					$('#aips-ce-count').text('0 products');
				}
			},
			error: function() {
				var errHtml = renderTemplate('aips-tmpl-ce-empty-row', {
					message: '<?php echo esc_js( __( 'Failed to load products.', 'ai-post-scheduler' ) ); ?>'
				});
				$('#aips-ce-tbody').html(errHtml);
			}
		});
	}

	function renderProductsTable(items, total) {
		$('#aips-ce-count').text(total + ' products found');
		if (!items.length) {
			var emptyHtml = renderTemplate('aips-tmpl-ce-empty-row', {
				message: '<?php echo esc_js( __( 'No products found matching criteria.', 'ai-post-scheduler' ) ); ?>'
			});
			$('#aips-ce-tbody').html(emptyHtml);
			return;
		}

		var fullHtml = '';
		$.each(items, function(i, p) {
			var imgHtml = p.image_url
				? renderTemplate('aips-tmpl-ce-product-img', { url: p.image_url })
				: renderTemplate('aips-tmpl-ce-product-no-img', {});

			var shortDescStatus = p.short_description
				? '<span style="color:#166534; font-weight:600;">✓ Present</span>'
				: '<span style="color:#dc2626; font-weight:600;">⚠ Missing</span>';

			var wordCount = p.full_description ? p.full_description.split(/\s+/).length : 0;
			var contentStatus = wordCount > 50
				? '<span style="color:#166534;">' + wordCount + ' words</span>'
				: '<span style="color:#dc2626; font-weight:600;">Thin (' + wordCount + ' words)</span>';

			fullHtml += renderTemplate('aips-tmpl-ce-product-row', {
				id: p.id,
				title: p.title,
				sku: p.sku || 'N/A',
				formatted_price: p.formatted_price || ('$' + p.price),
				image_html: imgHtml,
				short_desc_status: shortDescStatus,
				content_status: contentStatus
			});
		});

		$('#aips-ce-tbody').html(fullHtml);
	}

	function loadPreview() {
		if (!currentProductId) return;

		var targetField = $('#aips-ce-target-field').val();
		var tone = $('#aips-ce-tone').val();

		$('#aips-ce-current-preview').html('<span class="spinner is-active" style="float:none;"></span> Loading...');
		$('#aips-ce-proposed-preview').val('Generating AI enhancement preview...');

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'aips_catalog_enrichment_preview',
				nonce: currentNonce,
				product_id: currentProductId,
				target_field: targetField,
				tone: tone
			},
			success: function(res) {
				if (res.success && res.data) {
					$('#aips-ce-current-preview').html(res.data.current || '<em>(Empty)</em>');
					$('#aips-ce-proposed-preview').val(res.data.proposed || '');
				} else {
					$('#aips-ce-proposed-preview').val('Failed to generate preview.');
				}
			},
			error: function() {
				$('#aips-ce-proposed-preview').val('Network or server error generating preview.');
			}
		});
	}

	function applyEnrichment() {
		if (!currentProductId) return;

		var targetField = $('#aips-ce-target-field').val();
		var proposedText = $('#aips-ce-proposed-preview').val();

		var postData = {
			action: 'aips_catalog_enrichment_apply',
			nonce: currentNonce,
			product_id: currentProductId
		};
		postData[targetField] = proposedText;

		var $btn = $('#aips-ce-modal-apply');
		$btn.prop('disabled', true).text('Applying...');

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			dataType: 'json',
			data: postData,
			success: function(res) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align:middle; font-size:16px;"></span> Apply to Product');
				if (res.success) {
					$('#aips-ce-modal').hide();
					loadCatalogProducts();
				} else {
					alert(res.data || 'Failed to apply update.');
				}
			},
			error: function() {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align:middle; font-size:16px;"></span> Apply to Product');
				alert('Server error while saving product.');
			}
		});
	}
})(jQuery);
</script>
