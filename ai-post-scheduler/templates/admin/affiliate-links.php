<?php
/**
 * Affiliate Links Admin Page
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e( 'Affiliate Links', 'ai-post-scheduler' ); ?></h1>
					<p class="aips-page-description"><?php esc_html_e( 'Map post tags to affiliate URLs and configure CTA block injection into generated posts.', 'ai-post-scheduler' ); ?></p>
				</div>
				<div class="aips-page-actions">
					<button type="button" id="aips-afl-add-btn" class="aips-btn aips-btn-primary">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Mapping', 'ai-post-scheduler' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Filter Bar -->
		<div class="aips-content-panel" style="margin-bottom:20px;">
			<div class="aips-filter-bar" style="padding:12px 16px;">
				<div class="aips-filter-left">
					<label class="screen-reader-text" for="aips-afl-search"><?php esc_html_e( 'Search mappings:', 'ai-post-scheduler' ); ?></label>
					<input type="search" id="aips-afl-search" class="aips-form-input" placeholder="<?php esc_attr_e( 'Search by tag or label…', 'ai-post-scheduler' ); ?>" style="min-width:240px;">
					<button type="button" id="aips-afl-search-clear" class="aips-btn aips-btn-sm aips-btn-secondary" style="display:none;"><?php esc_html_e( 'Clear', 'ai-post-scheduler' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Mappings Table -->
		<div class="aips-content-panel">
			<div class="aips-panel-body no-padding">
				<table class="aips-table" id="aips-afl-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tag', 'ai-post-scheduler' ); ?></th>
							<th><?php esc_html_e( 'Label', 'ai-post-scheduler' ); ?></th>
							<th><?php esc_html_e( 'Affiliate URL', 'ai-post-scheduler' ); ?></th>
							<th><?php esc_html_e( 'CTA Position', 'ai-post-scheduler' ); ?></th>
							<th><?php esc_html_e( 'AI Injection', 'ai-post-scheduler' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'ai-post-scheduler' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'ai-post-scheduler' ); ?></th>
						</tr>
					</thead>
					<tbody id="aips-afl-tbody">
						<tr class="aips-table-loading">
							<td colspan="7">
								<span class="spinner is-active" style="float:none;margin:0 8px 0 0;vertical-align:middle;"></span>
								<?php esc_html_e( 'Loading…', 'ai-post-scheduler' ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Pagination -->
			<div class="aips-panel-footer" id="aips-afl-pagination" style="padding:12px 16px;display:none;">
				<div class="aips-pagination">
					<button type="button" id="aips-afl-prev" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e( '&laquo; Prev', 'ai-post-scheduler' ); ?></button>
					<span id="aips-afl-page-info" style="margin:0 12px;font-size:13px;"></span>
					<button type="button" id="aips-afl-next" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e( 'Next &raquo;', 'ai-post-scheduler' ); ?></button>
				</div>
			</div>
		</div>

	</div><!-- /.aips-page-container -->
</div><!-- /.wrap -->

<!-- Create / Edit Modal -->
<div id="aips-afl-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-afl-modal-title">
	<div class="aips-modal-backdrop"></div>
	<div class="aips-modal-dialog" style="max-width:640px;">
		<div class="aips-modal-header">
			<h2 id="aips-afl-modal-title" class="aips-modal-title"><?php esc_html_e( 'Affiliate Link Mapping', 'ai-post-scheduler' ); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-post-scheduler' ); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<input type="hidden" id="aips-afl-id" value="">

			<!-- Basic Fields -->
			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-tag"><?php esc_html_e( 'Tag', 'ai-post-scheduler' ); ?> <span class="required">*</span></label>
				<input type="text" id="aips-afl-tag" class="aips-form-input" placeholder="<?php esc_attr_e( 'e.g. VPS', 'ai-post-scheduler' ); ?>">
				<p class="aips-form-help"><?php esc_html_e( 'Post tag name to match (case-insensitive).', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-label"><?php esc_html_e( 'Label', 'ai-post-scheduler' ); ?></label>
				<input type="text" id="aips-afl-label" class="aips-form-input" placeholder="<?php esc_attr_e( 'e.g. Namecheap VPS', 'ai-post-scheduler' ); ?>">
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-url"><?php esc_html_e( 'Affiliate URL', 'ai-post-scheduler' ); ?> <span class="required">*</span></label>
				<input type="url" id="aips-afl-url" class="aips-form-input" placeholder="https://…">
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label aips-toggle-label">
					<input type="checkbox" id="aips-afl-enabled" checked>
					<?php esc_html_e( 'Enabled', 'ai-post-scheduler' ); ?>
				</label>
			</div>

			<hr style="margin:20px 0;">

			<!-- CTA Configuration -->
			<h3 style="margin:0 0 16px;font-size:14px;font-weight:600;"><?php esc_html_e( 'CTA Block Configuration', 'ai-post-scheduler' ); ?></h3>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-cta-html"><?php esc_html_e( 'CTA HTML', 'ai-post-scheduler' ); ?></label>
				<textarea id="aips-afl-cta-html" class="aips-form-input" rows="4" placeholder="<?php esc_attr_e( '<p>Check out <a href="{{affiliate_url}}">this deal</a>.</p>', 'ai-post-scheduler' ); ?>"></textarea>
				<p class="aips-form-help"><?php esc_html_e( 'Use {{affiliate_url}} as the placeholder for the affiliate URL.', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-cta-position"><?php esc_html_e( 'Insertion Position', 'ai-post-scheduler' ); ?></label>
				<select id="aips-afl-cta-position" class="aips-form-select">
					<option value="append"><?php esc_html_e( 'Append to post', 'ai-post-scheduler' ); ?></option>
					<option value="prepend"><?php esc_html_e( 'Prepend to post', 'ai-post-scheduler' ); ?></option>
					<option value="after_heading"><?php esc_html_e( 'After heading', 'ai-post-scheduler' ); ?></option>
					<option value="after_text"><?php esc_html_e( 'After text snippet', 'ai-post-scheduler' ); ?></option>
				</select>
			</div>

			<div class="aips-form-group" id="aips-afl-heading-group" style="display:none;">
				<label class="aips-form-label" for="aips-afl-cta-heading"><?php esc_html_e( 'Heading Text to Match', 'ai-post-scheduler' ); ?></label>
				<input type="text" id="aips-afl-cta-heading" class="aips-form-input" placeholder="<?php esc_attr_e( 'e.g. Getting Started', 'ai-post-scheduler' ); ?>">
				<p class="aips-form-help"><?php esc_html_e( 'CTA will be inserted after the first heading that contains this text (case-insensitive).', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group" id="aips-afl-text-group" style="display:none;">
				<label class="aips-form-label" for="aips-afl-cta-match-text"><?php esc_html_e( 'Text Snippet to Match', 'ai-post-scheduler' ); ?></label>
				<input type="text" id="aips-afl-cta-match-text" class="aips-form-input" placeholder="<?php esc_attr_e( 'e.g. In conclusion', 'ai-post-scheduler' ); ?>">
				<p class="aips-form-help"><?php esc_html_e( 'CTA will be inserted after each occurrence of this text (case-insensitive).', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-max-insertions"><?php esc_html_e( 'Max Insertions per Post', 'ai-post-scheduler' ); ?></label>
				<input type="number" id="aips-afl-max-insertions" class="aips-form-input" value="1" min="1" max="20" style="max-width:80px;">
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label aips-toggle-label">
					<input type="checkbox" id="aips-afl-ai-injection">
					<?php esc_html_e( 'Use AI in-content injection', 'ai-post-scheduler' ); ?>
				</label>
				<p class="aips-form-help"><?php esc_html_e( 'Also ask AI to find a natural sentence in the post body to anchor the affiliate link.', 'ai-post-scheduler' ); ?></p>
			</div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-ghost aips-modal-close"><?php esc_html_e( 'Cancel', 'ai-post-scheduler' ); ?></button>
			<button type="button" id="aips-afl-save-btn" class="aips-btn aips-btn-primary"><?php esc_html_e( 'Save Mapping', 'ai-post-scheduler' ); ?></button>
		</div>
	</div>
</div>

<script type="text/javascript">
(function($) {
	'use strict';

	var aflState = {
		page: 1,
		perPage: 20,
		totalPages: 1,
		search: '',
	};

	var nonce = '<?php echo esc_js( wp_create_nonce( 'aips_ajax_nonce' ) ); ?>';

	function aflRequest(action, data, cb) {
		$.post(ajaxurl, $.extend({ action: action, nonce: nonce }, data), cb).fail(function() {
			alert('<?php echo esc_js( __( 'Request failed. Please try again.', 'ai-post-scheduler' ) ); ?>');
		});
	}

	function loadList() {
		$('#aips-afl-tbody').html('<tr><td colspan="7"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;vertical-align:middle;"></span><?php echo esc_js( __( 'Loading…', 'ai-post-scheduler' ) ); ?></td></tr>');
		aflRequest('aips_affiliate_links_list', { page: aflState.page, per_page: aflState.perPage, search: aflState.search }, function(res) {
			if (!res.success) { return; }
			var d = res.data;
			aflState.totalPages = d.total_pages || 1;
			renderRows(d.items || []);
			renderPagination(d.page, d.total_pages, d.total);
		});
	}

	var positionLabels = {
		append: '<?php echo esc_js( __( 'Append', 'ai-post-scheduler' ) ); ?>',
		prepend: '<?php echo esc_js( __( 'Prepend', 'ai-post-scheduler' ) ); ?>',
		after_heading: '<?php echo esc_js( __( 'After Heading', 'ai-post-scheduler' ) ); ?>',
		after_text: '<?php echo esc_js( __( 'After Text', 'ai-post-scheduler' ) ); ?>',
	};

	function renderRows(items) {
		var tbody = $('#aips-afl-tbody');
		if (!items.length) {
			tbody.html('<tr><td colspan="7" style="text-align:center;padding:24px;"><?php echo esc_js( __( 'No mappings found. Click "Add Mapping" to create one.', 'ai-post-scheduler' ) ); ?></td></tr>');
			return;
		}
		var rows = items.map(function(item) {
			var enabledToggle = '<label class="aips-toggle" title="<?php echo esc_js( __( 'Toggle enabled', 'ai-post-scheduler' ) ); ?>">'
				+ '<input type="checkbox" class="aips-afl-toggle" data-id="' + item.id + '"' + (item.enabled == 1 ? ' checked' : '') + '>'
				+ '<span class="aips-toggle-slider"></span></label>';
			var aiIcon = item.use_ai_injection == 1
				? '<span class="dashicons dashicons-yes-alt" style="color:#00a32a;" title="<?php echo esc_js( __( 'AI injection enabled', 'ai-post-scheduler' ) ); ?>"></span>'
				: '<span style="color:#ccc;">—</span>';
			var shortUrl = item.affiliate_url.length > 40 ? item.affiliate_url.substring(0, 40) + '…' : item.affiliate_url;
			return '<tr data-id="' + item.id + '">'
				+ '<td><code>' + $('<span>').text('#' + item.tag).html() + '</code></td>'
				+ '<td>' + $('<span>').text(item.label).html() + '</td>'
				+ '<td><a href="' + $('<span>').text(item.affiliate_url).html() + '" target="_blank" rel="noopener">' + $('<span>').text(shortUrl).html() + '</a></td>'
				+ '<td>' + (positionLabels[item.cta_position] || item.cta_position) + '</td>'
				+ '<td style="text-align:center;">' + aiIcon + '</td>'
				+ '<td>' + enabledToggle + '</td>'
				+ '<td>'
					+ '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-afl-edit-btn" data-id="' + item.id + '"><?php echo esc_js( __( 'Edit', 'ai-post-scheduler' ) ); ?></button> '
					+ '<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger aips-afl-delete-btn" data-id="' + item.id + '"><?php echo esc_js( __( 'Delete', 'ai-post-scheduler' ) ); ?></button>'
				+ '</td>'
			+ '</tr>';
		});
		tbody.html(rows.join(''));
	}

	function renderPagination(page, totalPages, total) {
		var pag = $('#aips-afl-pagination');
		if (totalPages <= 1) { pag.hide(); return; }
		pag.show();
		$('#aips-afl-page-info').text('<?php echo esc_js( __( 'Page', 'ai-post-scheduler' ) ); ?> ' + page + ' <?php echo esc_js( __( 'of', 'ai-post-scheduler' ) ); ?> ' + totalPages + ' (' + total + ')');
		$('#aips-afl-prev').prop('disabled', page <= 1);
		$('#aips-afl-next').prop('disabled', page >= totalPages);
	}

	function openModal(item) {
		var isEdit = !!item;
		$('#aips-afl-modal-title').text(isEdit ? '<?php echo esc_js( __( 'Edit Mapping', 'ai-post-scheduler' ) ); ?>' : '<?php echo esc_js( __( 'Add Mapping', 'ai-post-scheduler' ) ); ?>');
		$('#aips-afl-id').val(isEdit ? item.id : '');
		$('#aips-afl-tag').val(isEdit ? item.tag : '');
		$('#aips-afl-label').val(isEdit ? item.label : '');
		$('#aips-afl-url').val(isEdit ? item.affiliate_url : '');
		$('#aips-afl-enabled').prop('checked', !isEdit || item.enabled == 1);
		$('#aips-afl-cta-html').val(isEdit ? item.cta_html : '');
		$('#aips-afl-cta-position').val(isEdit ? item.cta_position : 'append').trigger('change');
		$('#aips-afl-cta-heading').val(isEdit ? item.cta_heading : '');
		$('#aips-afl-cta-match-text').val(isEdit ? item.cta_match_text : '');
		$('#aips-afl-max-insertions').val(isEdit ? item.cta_max_insertions : 1);
		$('#aips-afl-ai-injection').prop('checked', isEdit && item.use_ai_injection == 1);
		$('#aips-afl-modal').show();
		$('#aips-afl-tag').focus();
	}

	function closeModal() {
		$('#aips-afl-modal').hide();
	}

	$(document).on('change', '#aips-afl-cta-position', function() {
		var val = $(this).val();
		$('#aips-afl-heading-group').toggle(val === 'after_heading');
		$('#aips-afl-text-group').toggle(val === 'after_text');
	});

	$(document).on('click', '#aips-afl-add-btn', function() { openModal(null); });

	$(document).on('click', '.aips-modal-close, .aips-modal-backdrop', closeModal);

	$(document).on('click', '.aips-afl-edit-btn', function() {
		var id = $(this).data('id');
		aflRequest('aips_affiliate_links_get', { id: id }, function(res) {
			if (res.success) { openModal(res.data.item); }
		});
	});

	$(document).on('click', '.aips-afl-delete-btn', function() {
		if (!confirm('<?php echo esc_js( __( 'Delete this mapping? This cannot be undone.', 'ai-post-scheduler' ) ); ?>')) { return; }
		var id = $(this).data('id');
		aflRequest('aips_affiliate_links_delete', { id: id }, function(res) {
			if (res.success) { loadList(); }
			else { alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Delete failed.', 'ai-post-scheduler' ) ); ?>'); }
		});
	});

	$(document).on('change', '.aips-afl-toggle', function() {
		var id      = $(this).data('id');
		var enabled = $(this).is(':checked') ? 1 : 0;
		aflRequest('aips_affiliate_links_toggle', { id: id, enabled: enabled }, function(res) {
			if (!res.success) { loadList(); }
		});
	});

	$(document).on('click', '#aips-afl-save-btn', function() {
		var id = $('#aips-afl-id').val();
		var data = {
			tag:                $('#aips-afl-tag').val(),
			label:              $('#aips-afl-label').val(),
			affiliate_url:      $('#aips-afl-url').val(),
			enabled:            $('#aips-afl-enabled').is(':checked') ? 1 : 0,
			cta_html:           $('#aips-afl-cta-html').val(),
			cta_position:       $('#aips-afl-cta-position').val(),
			cta_heading:        $('#aips-afl-cta-heading').val(),
			cta_match_text:     $('#aips-afl-cta-match-text').val(),
			cta_max_insertions: $('#aips-afl-max-insertions').val(),
			use_ai_injection:   $('#aips-afl-ai-injection').is(':checked') ? 1 : 0,
		};

		if (!data.tag) { alert('<?php echo esc_js( __( 'Tag is required.', 'ai-post-scheduler' ) ); ?>'); return; }
		if (!data.affiliate_url) { alert('<?php echo esc_js( __( 'Affiliate URL is required.', 'ai-post-scheduler' ) ); ?>'); return; }

		var action = id ? 'aips_affiliate_links_update' : 'aips_affiliate_links_create';
		if (id) { data.id = id; }

		var btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Saving…', 'ai-post-scheduler' ) ); ?>');
		aflRequest(action, data, function(res) {
			btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Mapping', 'ai-post-scheduler' ) ); ?>');
			if (res.success) { closeModal(); loadList(); }
			else { alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Save failed.', 'ai-post-scheduler' ) ); ?>'); }
		});
	});

	// Pagination
	$(document).on('click', '#aips-afl-prev', function() { if (aflState.page > 1) { aflState.page--; loadList(); } });
	$(document).on('click', '#aips-afl-next', function() { if (aflState.page < aflState.totalPages) { aflState.page++; loadList(); } });

	// Search
	var searchTimer;
	$(document).on('input', '#aips-afl-search', function() {
		var val = $(this).val();
		$('#aips-afl-search-clear').toggle(val.length > 0);
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function() { aflState.search = val; aflState.page = 1; loadList(); }, 350);
	});
	$(document).on('click', '#aips-afl-search-clear', function() {
		$('#aips-afl-search').val('');
		$(this).hide();
		aflState.search = '';
		aflState.page   = 1;
		loadList();
	});

	// Escape key closes modal
	$(document).on('keydown', function(e) { if (e.key === 'Escape') { closeModal(); } });

	// Initial load
	loadList();

}(jQuery));
</script>
