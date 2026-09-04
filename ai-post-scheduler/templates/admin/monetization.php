<?php
/**
 * Monetization Hub Admin Template
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 *
 * @var object[] $slots
 * @var object[] $campaigns
 * @var array    $summary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap aips-wrap aips-monetization-wrap">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Monetization Hub & Ad Revenue', 'ai-post-scheduler' ); ?>
	</h1>
	<p class="description">
		<?php esc_html_e( 'Manage automated in-article ad slots, sponsor campaigns, affiliate links, and monitor real-time viewability & revenue metrics.', 'ai-post-scheduler' ); ?>
	</p>

	<script>
		window.aipsMonetizationInitialData = {
			slots: <?php echo wp_json_encode( $slots ); ?>,
			campaigns: <?php echo wp_json_encode( $campaigns ); ?>
		};
	</script>

	<!-- Nav Tabs -->
	<nav class="nav-tab-wrapper aips-nav-tab-wrapper" id="aips-monetization-tabs">
		<a href="#tab-slots" class="nav-tab nav-tab-active" data-tab="slots">
			<span class="dashicons dashicons-layout"></span>
			<?php esc_html_e( 'Ad Slots & Display Units', 'ai-post-scheduler' ); ?>
		</a>
		<a href="#tab-sponsors" class="nav-tab" data-tab="sponsors">
			<span class="dashicons dashicons-awards"></span>
			<?php esc_html_e( 'Sponsor Campaigns & FTC', 'ai-post-scheduler' ); ?>
		</a>
		<a href="#tab-affiliates" class="nav-tab" data-tab="affiliates">
			<span class="dashicons dashicons-admin-links"></span>
			<?php esc_html_e( 'Affiliate Links', 'ai-post-scheduler' ); ?>
		</a>
		<a href="#tab-analytics" class="nav-tab" data-tab="analytics">
			<span class="dashicons dashicons-chart-line"></span>
			<?php esc_html_e( 'Revenue & Performance Analytics', 'ai-post-scheduler' ); ?>
		</a>
	</nav>

	<div class="aips-tab-contents">

		<!-- TAB 1: Ad Slots -->
		<div id="tab-slots" class="aips-tab-panel aips-tab-active">
			<div class="aips-tab-header">
				<div class="aips-tab-header-text">
					<h2><?php esc_html_e( 'In-Article Ad Placements', 'ai-post-scheduler' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Configure dynamic ad slots injected into published articles according to paragraph spacing and content flow.', 'ai-post-scheduler' ); ?></p>
				</div>
				<div class="aips-tab-header-actions">
					<button type="button" class="button button-primary" id="aips-btn-add-slot">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Ad Slot', 'ai-post-scheduler' ); ?>
					</button>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped table-view-list" id="aips-table-slots">
				<thead>
					<tr>
						<th scope="col" style="width: 70px;"><?php esc_html_e( 'Status', 'ai-post-scheduler' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Slot Name', 'ai-post-scheduler' ); ?></th>
						<th scope="col" style="width: 130px;"><?php esc_html_e( 'Type', 'ai-post-scheduler' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Placement & Pacing', 'ai-post-scheduler' ); ?></th>
						<th scope="col" style="width: 100px;"><?php esc_html_e( 'Device', 'ai-post-scheduler' ); ?></th>
						<th scope="col" style="width: 80px;"><?php esc_html_e( 'Priority', 'ai-post-scheduler' ); ?></th>
						<th scope="col" style="width: 140px; text-align: right;"><?php esc_html_e( 'Actions', 'ai-post-scheduler' ); ?></th>
					</tr>
				</thead>
				<tbody id="aips-tbody-slots">
					<?php if ( empty( $slots ) ) : ?>
						<tr class="no-items">
							<td class="colspanchange" colspan="7"><?php esc_html_e( 'No ad slots configured yet. Click "Add Ad Slot" above.', 'ai-post-scheduler' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $slots as $slot ) : ?>
							<tr data-slot-id="<?php echo esc_attr( $slot->id ); ?>">
								<td>
									<button type="button" class="button button-small aips-slot-toggle <?php echo 'active' === $slot->status ? 'button-primary' : ''; ?>" data-id="<?php echo esc_attr( $slot->id ); ?>">
										<?php echo 'active' === $slot->status ? esc_html__( 'Active', 'ai-post-scheduler' ) : esc_html__( 'Paused', 'ai-post-scheduler' ); ?>
									</button>
								</td>
								<td>
									<strong><?php echo esc_html( $slot->name ); ?></strong>
									<?php if ( ! empty( $slot->css_classes ) ) : ?>
										<code>.<?php echo esc_html( $slot->css_classes ); ?></code>
									<?php endif; ?>
								</td>
								<td><span class="aips-badge aips-badge-type"><?php echo esc_html( $slot->slot_type ); ?></span></td>
								<td>
									<?php if ( 'after_paragraph' === $slot->position ) : ?>
										<?php printf( esc_html__( 'After Paragraph %d (Min words: %d)', 'ai-post-scheduler' ), (int) $slot->paragraph_offset, (int) $slot->min_word_count ); ?>
									<?php elseif ( 'mid_content' === $slot->position ) : ?>
										<?php printf( esc_html__( 'Mid-Content (50%% Depth, Min words: %d)', 'ai-post-scheduler' ), (int) $slot->min_word_count ); ?>
									<?php elseif ( 'end_of_post' === $slot->position ) : ?>
										<?php esc_html_e( 'End of Post / Conclusion', 'ai-post-scheduler' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Custom Shortcode / Block Only', 'ai-post-scheduler' ); ?>
									<?php endif; ?>
								</td>
								<td><span class="aips-badge"><?php echo esc_html( ucfirst( $slot->device_targeting ) ); ?></span></td>
								<td><?php echo (int) $slot->priority; ?></td>
								<td style="text-align: right;">
									<button type="button" class="button button-small aips-btn-edit-slot" data-id="<?php echo esc_attr( $slot->id ); ?>">
										<?php esc_html_e( 'Edit', 'ai-post-scheduler' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete aips-btn-delete-slot" data-id="<?php echo esc_attr( $slot->id ); ?>">
										<?php esc_html_e( 'Delete', 'ai-post-scheduler' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- TAB 2: Sponsor Campaigns -->
		<div id="tab-sponsors" class="aips-tab-panel">
			<div class="aips-tab-header">
				<div class="aips-tab-header-text">
					<h2><?php esc_html_e( 'Direct Sponsor Campaigns & FTC Disclosures', 'ai-post-scheduler' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Manage brand sponsors, direct advertiser deals, and automated FTC compliance notices.', 'ai-post-scheduler' ); ?></p>
				</div>
				<div class="aips-tab-header-actions">
					<button type="button" class="button button-primary" id="aips-btn-add-campaign">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Sponsor Campaign', 'ai-post-scheduler' ); ?>
					</button>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped table-view-list" id="aips-table-campaigns">
				<thead>
					<tr>
						<th scope="col" style="width: 70px;"><?php esc_html_e( 'Status', 'ai-post-scheduler' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Brand Name', 'ai-post-scheduler' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Target URL', 'ai-post-scheduler' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Matching Keywords / Categories', 'ai-post-scheduler' ); ?></th>
						<th scope="col" style="width: 180px;"><?php esc_html_e( 'Campaign Duration', 'ai-post-scheduler' ); ?></th>
						<th scope="col" style="width: 140px; text-align: right;"><?php esc_html_e( 'Actions', 'ai-post-scheduler' ); ?></th>
					</tr>
				</thead>
				<tbody id="aips-tbody-campaigns">
					<?php if ( empty( $campaigns ) ) : ?>
						<tr class="no-items">
							<td class="colspanchange" colspan="6"><?php esc_html_e( 'No sponsor campaigns created yet.', 'ai-post-scheduler' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $campaigns as $camp ) : ?>
							<tr data-campaign-id="<?php echo esc_attr( $camp->id ); ?>">
								<td>
									<button type="button" class="button button-small aips-campaign-toggle <?php echo 'active' === $camp->status ? 'button-primary' : ''; ?>" data-id="<?php echo esc_attr( $camp->id ); ?>">
										<?php echo 'active' === $camp->status ? esc_html__( 'Active', 'ai-post-scheduler' ) : esc_html__( 'Paused', 'ai-post-scheduler' ); ?>
									</button>
								</td>
								<td>
									<strong><?php echo esc_html( $camp->brand_name ); ?></strong>
									<?php if ( ! empty( $camp->logo_url ) ) : ?>
										<div class="aips-table-thumb"><img src="<?php echo esc_url( $camp->logo_url ); ?>" alt="" /></div>
									<?php endif; ?>
								</td>
								<td><a href="<?php echo esc_url( $camp->target_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $camp->target_url ); ?></a></td>
								<td>
									<?php if ( ! empty( $camp->keywords ) ) : ?>
										<span class="aips-badge aips-badge-kw"><?php echo esc_html( $camp->keywords ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$start = $camp->start_date ?: esc_html__( 'Immediately', 'ai-post-scheduler' );
									$end   = $camp->end_date ?: esc_html__( 'Ongoing', 'ai-post-scheduler' );
									echo esc_html( $start . ' &rarr; ' . $end );
									?>
								</td>
								<td style="text-align: right;">
									<button type="button" class="button button-small aips-btn-edit-campaign" data-id="<?php echo esc_attr( $camp->id ); ?>">
										<?php esc_html_e( 'Edit', 'ai-post-scheduler' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete aips-btn-delete-campaign" data-id="<?php echo esc_attr( $camp->id ); ?>">
										<?php esc_html_e( 'Delete', 'ai-post-scheduler' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- TAB 3: Affiliate Links -->
		<div id="tab-affiliates" class="aips-tab-panel">
			<?php
			// Render Affiliate Links Table from existing template
			if ( file_exists( AIPS_PLUGIN_DIR . 'templates/admin/affiliate-links.php' ) ) {
				include AIPS_PLUGIN_DIR . 'templates/admin/affiliate-links.php';
			}
			?>
		</div>

		<!-- TAB 4: Analytics -->
		<div id="tab-analytics" class="aips-tab-panel">
			<div class="aips-tab-header">
				<div class="aips-tab-header-text">
					<h2><?php esc_html_e( 'Revenue & Viewability Telemetry', 'ai-post-scheduler' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Real-time aggregated viewable impressions, clicks, and CTR breakdown across ad units.', 'ai-post-scheduler' ); ?></p>
				</div>
				<div class="aips-tab-header-actions">
					<select id="aips-analytics-range">
						<option value="7"><?php esc_html_e( 'Last 7 Days', 'ai-post-scheduler' ); ?></option>
						<option value="14" selected><?php esc_html_e( 'Last 14 Days', 'ai-post-scheduler' ); ?></option>
						<option value="30"><?php esc_html_e( 'Last 30 Days', 'ai-post-scheduler' ); ?></option>
					</select>
					<button type="button" class="button" id="aips-btn-refresh-analytics">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh', 'ai-post-scheduler' ); ?>
					</button>
				</div>
			</div>

			<!-- Summary Cards -->
			<div class="aips-stat-cards">
				<div class="aips-stat-card">
					<span class="aips-stat-label"><?php esc_html_e( 'Total Impressions (Viewable)', 'ai-post-scheduler' ); ?></span>
					<span class="aips-stat-value" id="aips-stat-impressions"><?php echo number_format_i18n( $summary['impressions'] ?? 0 ); ?></span>
				</div>
				<div class="aips-stat-card">
					<span class="aips-stat-label"><?php esc_html_e( 'Ad Clicks', 'ai-post-scheduler' ); ?></span>
					<span class="aips-stat-value" id="aips-stat-clicks"><?php echo number_format_i18n( $summary['clicks'] ?? 0 ); ?></span>
				</div>
				<div class="aips-stat-card">
					<span class="aips-stat-label"><?php esc_html_e( 'Estimated CTR', 'ai-post-scheduler' ); ?></span>
					<span class="aips-stat-value" id="aips-stat-ctr"><?php echo esc_html( ( $summary['ctr'] ?? 0 ) . '%' ); ?></span>
				</div>
			</div>

			<!-- Chart Section -->
			<div class="aips-chart-container">
				<canvas id="aips-monetization-chart" height="90"></canvas>
			</div>

			<!-- Tables Section: Top Posts & Slot Breakdown -->
			<div class="aips-two-col-grid">
				<div class="aips-grid-col">
					<h3><?php esc_html_e( 'Top Performing Posts', 'ai-post-scheduler' ); ?></h3>
					<table class="wp-list-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Post Title', 'ai-post-scheduler' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Impr.', 'ai-post-scheduler' ); ?></th>
								<th style="width: 70px;"><?php esc_html_e( 'Clicks', 'ai-post-scheduler' ); ?></th>
								<th style="width: 70px;"><?php esc_html_e( 'CTR', 'ai-post-scheduler' ); ?></th>
							</tr>
						</thead>
						<tbody id="aips-tbody-top-posts">
							<tr><td colspan="4"><?php esc_html_e( 'Loading stats…', 'ai-post-scheduler' ); ?></td></tr>
						</tbody>
					</table>
				</div>

				<div class="aips-grid-col">
					<h3><?php esc_html_e( 'Slot Breakdown', 'ai-post-scheduler' ); ?></h3>
					<table class="wp-list-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Slot Name', 'ai-post-scheduler' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Impr.', 'ai-post-scheduler' ); ?></th>
								<th style="width: 70px;"><?php esc_html_e( 'Clicks', 'ai-post-scheduler' ); ?></th>
								<th style="width: 70px;"><?php esc_html_e( 'CTR', 'ai-post-scheduler' ); ?></th>
							</tr>
						</thead>
						<tbody id="aips-tbody-slot-stats">
							<tr><td colspan="4"><?php esc_html_e( 'Loading stats…', 'ai-post-scheduler' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal: Add/Edit Ad Slot -->
	<div id="aips-modal-slot" class="aips-modal" style="display: none;">
		<div class="aips-modal-backdrop"></div>
		<div class="aips-modal-content">
			<div class="aips-modal-header">
				<h3 id="aips-modal-slot-title"><?php esc_html_e( 'Ad Slot Configuration', 'ai-post-scheduler' ); ?></h3>
				<button type="button" class="aips-modal-close">&times;</button>
			</div>
			<form id="aips-form-slot">
				<input type="hidden" name="id" id="aips-slot-id" value="0" />
				<div class="aips-modal-body">
					<div class="aips-form-row">
						<label for="aips-slot-name"><?php esc_html_e( 'Slot Name', 'ai-post-scheduler' ); ?> <span class="required">*</span></label>
						<input type="text" name="name" id="aips-slot-name" class="regular-text" required />
					</div>
					<div class="aips-form-row">
						<label for="aips-slot-type"><?php esc_html_e( 'Unit Type', 'ai-post-scheduler' ); ?></label>
						<select name="slot_type" id="aips-slot-type">
							<option value="custom_html"><?php esc_html_e( 'Custom HTML / Ad Code', 'ai-post-scheduler' ); ?></option>
							<option value="adsense"><?php esc_html_e( 'Google AdSense Tag', 'ai-post-scheduler' ); ?></option>
							<option value="image_banner"><?php esc_html_e( 'Image Banner (Desktop/Mobile)', 'ai-post-scheduler' ); ?></option>
							<option value="sponsor_card"><?php esc_html_e( 'Direct Sponsor Card', 'ai-post-scheduler' ); ?></option>
						</select>
					</div>
					<div class="aips-form-row">
						<label for="aips-slot-code"><?php esc_html_e( 'Ad Code / Tag / HTML', 'ai-post-scheduler' ); ?></label>
						<textarea name="code" id="aips-slot-code" rows="5" class="large-text code" placeholder="<script>...</script> or <div>...</div>"></textarea>
					</div>
					<div class="aips-form-row aips-row-flex">
						<div>
							<label for="aips-slot-position"><?php esc_html_e( 'Placement Position', 'ai-post-scheduler' ); ?></label>
							<select name="position" id="aips-slot-position">
								<option value="after_paragraph"><?php esc_html_e( 'After Paragraph N', 'ai-post-scheduler' ); ?></option>
								<option value="mid_content"><?php esc_html_e( 'Mid-Content (50% depth)', 'ai-post-scheduler' ); ?></option>
								<option value="end_of_post"><?php esc_html_e( 'End of Post / Conclusion', 'ai-post-scheduler' ); ?></option>
								<option value="custom_shortcode"><?php esc_html_e( 'Shortcode / Block Only', 'ai-post-scheduler' ); ?></option>
							</select>
						</div>
						<div id="aips-wrap-paragraph-offset">
							<label for="aips-slot-paragraph-offset"><?php esc_html_e( 'Paragraph Offset', 'ai-post-scheduler' ); ?></label>
							<input type="number" name="paragraph_offset" id="aips-slot-paragraph-offset" value="2" min="1" max="50" style="width: 80px;" />
						</div>
						<div>
							<label for="aips-slot-min-words"><?php esc_html_e( 'Min Word Count', 'ai-post-scheduler' ); ?></label>
							<input type="number" name="min_word_count" id="aips-slot-min-words" value="300" min="0" step="50" style="width: 100px;" />
						</div>
					</div>
					<div class="aips-form-row aips-row-flex">
						<div>
							<label for="aips-slot-device"><?php esc_html_e( 'Device Targeting', 'ai-post-scheduler' ); ?></label>
							<select name="device_targeting" id="aips-slot-device">
								<option value="all"><?php esc_html_e( 'All Devices', 'ai-post-scheduler' ); ?></option>
								<option value="desktop"><?php esc_html_e( 'Desktop Only', 'ai-post-scheduler' ); ?></option>
								<option value="mobile"><?php esc_html_e( 'Mobile Only', 'ai-post-scheduler' ); ?></option>
							</select>
						</div>
						<div>
							<label for="aips-slot-priority"><?php esc_html_e( 'Priority (Sort Order)', 'ai-post-scheduler' ); ?></label>
							<input type="number" name="priority" id="aips-slot-priority" value="10" min="1" max="100" style="width: 80px;" />
						</div>
						<div>
							<label for="aips-slot-classes"><?php esc_html_e( 'Custom CSS Classes', 'ai-post-scheduler' ); ?></label>
							<input type="text" name="css_classes" id="aips-slot-classes" class="regular-text" placeholder="my-custom-ad" />
						</div>
					</div>
				</div>
				<div class="aips-modal-footer">
					<button type="button" class="button aips-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-post-scheduler' ); ?></button>
					<button type="submit" class="button button-primary" id="aips-btn-save-slot"><?php esc_html_e( 'Save Ad Slot', 'ai-post-scheduler' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<!-- Modal: Add/Edit Sponsor Campaign -->
	<div id="aips-modal-campaign" class="aips-modal" style="display: none;">
		<div class="aips-modal-backdrop"></div>
		<div class="aips-modal-content">
			<div class="aips-modal-header">
				<h3 id="aips-modal-campaign-title"><?php esc_html_e( 'Sponsor Campaign Configuration', 'ai-post-scheduler' ); ?></h3>
				<button type="button" class="aips-modal-close">&times;</button>
			</div>
			<form id="aips-form-campaign">
				<input type="hidden" name="id" id="aips-campaign-id" value="0" />
				<div class="aips-modal-body">
					<div class="aips-form-row">
						<label for="aips-campaign-brand"><?php esc_html_e( 'Brand / Sponsor Name', 'ai-post-scheduler' ); ?> <span class="required">*</span></label>
						<input type="text" name="brand_name" id="aips-campaign-brand" class="regular-text" required />
					</div>
					<div class="aips-form-row">
						<label for="aips-campaign-url"><?php esc_html_e( 'Target Website / Affiliate URL', 'ai-post-scheduler' ); ?> <span class="required">*</span></label>
						<input type="url" name="target_url" id="aips-campaign-url" class="large-text" required placeholder="https://brand.com/?ref=..." />
					</div>
					<div class="aips-form-row">
						<label for="aips-campaign-logo"><?php esc_html_e( 'Logo Image URL', 'ai-post-scheduler' ); ?></label>
						<input type="url" name="logo_url" id="aips-campaign-logo" class="large-text" placeholder="https://example.com/logo.png" />
					</div>
					<div class="aips-form-row aips-row-flex">
						<div>
							<label for="aips-campaign-cta"><?php esc_html_e( 'CTA Button Text', 'ai-post-scheduler' ); ?></label>
							<input type="text" name="cta_text" id="aips-campaign-cta" class="regular-text" placeholder="Get 50% Off" />
						</div>
						<div>
							<label for="aips-campaign-keywords"><?php esc_html_e( 'Matching Keywords (comma-separated)', 'ai-post-scheduler' ); ?></label>
							<input type="text" name="keywords" id="aips-campaign-keywords" class="regular-text" placeholder="vpn, privacy, security" />
						</div>
					</div>
					<div class="aips-form-row">
						<label for="aips-campaign-disclosure"><?php esc_html_e( 'Custom FTC Disclosure (leave blank for site default)', 'ai-post-scheduler' ); ?></label>
						<textarea name="disclosure_text" id="aips-campaign-disclosure" rows="2" class="large-text" placeholder="<?php echo esc_attr( AIPS_Config::get_instance()->get_option( 'aips_default_ftc_disclosure' ) ); ?>"></textarea>
					</div>
					<div class="aips-form-row aips-row-flex">
						<div>
							<label for="aips-campaign-start"><?php esc_html_e( 'Start Date', 'ai-post-scheduler' ); ?></label>
							<input type="date" name="start_date" id="aips-campaign-start" />
						</div>
						<div>
							<label for="aips-campaign-end"><?php esc_html_e( 'End Date', 'ai-post-scheduler' ); ?></label>
							<input type="date" name="end_date" id="aips-campaign-end" />
						</div>
					</div>
				</div>
				<div class="aips-modal-footer">
					<button type="button" class="button aips-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-post-scheduler' ); ?></button>
					<button type="submit" class="button button-primary" id="aips-btn-save-campaign"><?php esc_html_e( 'Save Campaign', 'ai-post-scheduler' ); ?></button>
				</div>
			</form>
		</div>
	</div>

</div>

<!-- Client-side HTML Templates via AIPS.Templates -->
<script type="text/html" id="aips-tmpl-ad-slot-row">
	<tr data-slot-id="{{id}}">
		<td>
			<button type="button" class="button button-small aips-slot-toggle {{activeClass}}" data-id="{{id}}">
				{{statusLabel}}
			</button>
		</td>
		<td>
			<strong>{{name}}</strong>
			<span class="{{cssClassHidden}}"><code>.{{css_classes}}</code></span>
		</td>
		<td><span class="aips-badge aips-badge-type">{{slot_type}}</span></td>
		<td>{{placementDescription}}</td>
		<td><span class="aips-badge">{{deviceLabel}}</span></td>
		<td>{{priority}}</td>
		<td style="text-align: right;">
			<button type="button" class="button button-small aips-btn-edit-slot" data-id="{{id}}">
				<?php esc_html_e( 'Edit', 'ai-post-scheduler' ); ?>
			</button>
			<button type="button" class="button button-small button-link-delete aips-btn-delete-slot" data-id="{{id}}">
				<?php esc_html_e( 'Delete', 'ai-post-scheduler' ); ?>
			</button>
		</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-sponsor-campaign-row">
	<tr data-campaign-id="{{id}}">
		<td>
			<button type="button" class="button button-small aips-campaign-toggle {{activeClass}}" data-id="{{id}}">
				{{statusLabel}}
			</button>
		</td>
		<td>
			<strong>{{brand_name}}</strong>
		</td>
		<td><a href="{{target_url}}" target="_blank" rel="noopener">{{target_url}}</a></td>
		<td>
			<span class="aips-badge aips-badge-kw {{kwHidden}}">{{keywords}}</span>
		</td>
		<td>{{duration}}</td>
		<td style="text-align: right;">
			<button type="button" class="button button-small aips-btn-edit-campaign" data-id="{{id}}">
				<?php esc_html_e( 'Edit', 'ai-post-scheduler' ); ?>
			</button>
			<button type="button" class="button button-small button-link-delete aips-btn-delete-campaign" data-id="{{id}}">
				<?php esc_html_e( 'Delete', 'ai-post-scheduler' ); ?>
			</button>
		</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-top-post-row">
	<tr>
		<td><a href="{{edit_url}}">{{post_title}}</a></td>
		<td>{{impressions}}</td>
		<td>{{clicks}}</td>
		<td><strong>{{ctr}}%</strong></td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-slot-stat-row">
	<tr>
		<td><strong>{{slot_name}}</strong> <span class="description">({{position}})</span></td>
		<td>{{impressions}}</td>
		<td>{{clicks}}</td>
		<td><strong>{{ctr}}%</strong></td>
	</tr>
</script>
