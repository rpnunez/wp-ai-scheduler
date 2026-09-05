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
	<div class="aips-header-row" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
		<div>
			<h1 class="wp-heading-inline" style="margin:0;">
				<?php esc_html_e( 'Monetization Hub & Ad Revenue', 'ai-post-scheduler' ); ?>
			</h1>
			<p class="description" style="margin:4px 0 0 0;">
				<?php esc_html_e( 'Manage automated in-article ad slots, sponsor campaigns, affiliate links, and monitor real-time viewability & revenue metrics.', 'ai-post-scheduler' ); ?>
			</p>
		</div>
		<div>
			<button type="button" class="button button-secondary" id="aips-btn-toggle-engine-settings">
				<span class="dashicons dashicons-admin-generic" style="vertical-align:text-bottom;"></span>
				<?php esc_html_e( 'Engine & Ad-Block Settings', 'ai-post-scheduler' ); ?>
			</button>
		</div>
	</div>

	<!-- Collapsible Monetization Engine & Ad-Block Settings Panel -->
	<div id="aips-engine-settings-panel" class="aips-engine-panel" style="display: none; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:20px; margin-bottom:20px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
		<h3 style="margin-top:0; margin-bottom:12px; font-size:16px; display:flex; align-items:center; gap:8px;">
			<span class="dashicons dashicons-shield"></span>
			<?php esc_html_e( 'Monetization Optimization & Ad-Block Recovery Engine', 'ai-post-scheduler' ); ?>
		</h3>
		<form id="aips-form-engine-settings">
			<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:16px;">
				<div>
					<label style="display:block; font-weight:600; margin-bottom:6px;">
						<?php esc_html_e( 'Ad-Block Recovery Strategy', 'ai-post-scheduler' ); ?>
					</label>
					<?php $rec_mode = AIPS_Config::get_instance()->get_option( 'aips_adblock_recovery_mode', 'silent_fallback' ); ?>
					<select name="aips_adblock_recovery_mode" id="aips-setting-adblock-mode" style="width:100%;">
						<option value="disabled" <?php selected( $rec_mode, 'disabled' ); ?>><?php esc_html_e( 'Disabled (Standard ads only)', 'ai-post-scheduler' ); ?></option>
						<option value="silent_fallback" <?php selected( $rec_mode, 'silent_fallback' ); ?>><?php esc_html_e( 'Tier 1: Silent Fallback (Fill with House Sponsor Ad)', 'ai-post-scheduler' ); ?></option>
						<option value="soft_notice" <?php selected( $rec_mode, 'soft_notice' ); ?>><?php esc_html_e( 'Tier 2: Soft Notice (Polite floating toast prompt)', 'ai-post-scheduler' ); ?></option>
						<option value="polite_dimmer" <?php selected( $rec_mode, 'polite_dimmer' ); ?>><?php esc_html_e( 'Tier 3: Polite Content Dimmer (Dim after Paragraph 3)', 'ai-post-scheduler' ); ?></option>
					</select>
					<p class="description" style="margin-top:4px; font-size:12px;">
						<?php esc_html_e( 'Detects client-side ad blockers and applies high-converting recovery.', 'ai-post-scheduler' ); ?>
					</p>
				</div>

				<div>
					<label style="display:block; font-weight:600; margin-bottom:6px;">
						<?php esc_html_e( 'Fallback Sponsor Campaign (House Ad)', 'ai-post-scheduler' ); ?>
					</label>
					<?php $current_fb_id = (int) AIPS_Config::get_instance()->get_option( 'aips_adblock_fallback_campaign_id', 0 ); ?>
					<select name="aips_adblock_fallback_campaign_id" id="aips-setting-fallback-campaign" style="width:100%;">
						<option value="0"><?php esc_html_e( 'Auto-Match by Post Topic / Category', 'ai-post-scheduler' ); ?></option>
						<?php foreach ( $campaigns as $camp ) : ?>
							<option value="<?php echo esc_attr( $camp->id ); ?>" <?php selected( $current_fb_id, $camp->id ); ?>>
								<?php echo esc_html( $camp->brand_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label style="display:block; font-weight:600; margin-bottom:6px;">
						<?php esc_html_e( 'Affiliate Link Cloaking Prefix', 'ai-post-scheduler' ); ?>
					</label>
					<div style="display:flex; align-items:center; gap:8px;">
						<label style="display:inline-flex; align-items:center; gap:6px; margin-right:12px;">
							<input type="checkbox" name="aips_link_cloaking_enabled" id="aips-setting-cloaking-enabled" value="1" <?php checked( AIPS_Config::get_instance()->get_option( 'aips_link_cloaking_enabled', true ) ); ?> />
							<span><?php esc_html_e( 'Enable', 'ai-post-scheduler' ); ?></span>
						</label>
						<input type="text" name="aips_link_cloaking_prefix" id="aips-setting-cloaking-prefix" value="<?php echo esc_attr( AIPS_Config::get_instance()->get_option( 'aips_link_cloaking_prefix', 'go' ) ); ?>" style="width:90px;" placeholder="go" />
						<span class="description" style="font-size:12px;" id="aips-preview-cloaking-path">/<?php echo esc_html( AIPS_Config::get_instance()->get_option( 'aips_link_cloaking_prefix', 'go' ) ); ?>/{slug}</span>
					</div>
				</div>
			</div>

			<div style="margin-bottom:16px;">
				<label style="display:block; font-weight:600; margin-bottom:6px;">
					<?php esc_html_e( 'Custom Ad-Block Whitelist Prompt Message', 'ai-post-scheduler' ); ?>
				</label>
				<textarea name="aips_adblock_notice_text" id="aips-setting-notice-text" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'We notice you are using an ad blocker. Please consider supporting our free content by disabling your ad blocker.', 'ai-post-scheduler' ); ?>"><?php echo esc_textarea( AIPS_Config::get_instance()->get_option( 'aips_adblock_notice_text' ) ); ?></textarea>
			</div>

			<div style="margin-bottom:16px;">
				<label style="display:inline-flex; align-items:center; gap:8px; font-weight:600;">
					<input type="checkbox" name="aips_ad_refresh_enabled" id="aips-setting-refresh-master" value="1" <?php checked( AIPS_Config::get_instance()->get_option( 'aips_ad_refresh_enabled', true ) ); ?> />
					<span><?php esc_html_e( 'Master Switch: Enable Smart Ad Refresh across all configured units', 'ai-post-scheduler' ); ?></span>
				</label>
				<p class="description" style="margin-left:24px; font-size:12px;">
					<?php esc_html_e( 'Ensures AdSense/Mediavine policy compliance: ads only refresh when in viewport (>50% visible) and user has recent activity.', 'ai-post-scheduler' ); ?>
				</p>
			</div>

			<div style="display:flex; justify-content:flex-end; gap:10px;">
				<button type="submit" class="button button-primary" id="aips-btn-save-engine-settings">
					<?php esc_html_e( 'Save Engine Settings', 'ai-post-scheduler' ); ?>
				</button>
			</div>
		</form>
	</div>

	<script>
		window.aipsMonetizationInitialData = {
			slots: <?php echo wp_json_encode( $slots ); ?>,
			campaigns: <?php echo wp_json_encode( $campaigns ); ?>,
			referrals: <?php echo wp_json_encode( $referrals ); ?>,
			networkProfiles: <?php echo wp_json_encode( $network_profiles ); ?>
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
		<a href="#tab-referrals" class="nav-tab" data-tab="referrals">
			<span class="dashicons dashicons-share"></span>
			<?php esc_html_e( 'Referrals & Affiliate Networks', 'ai-post-scheduler' ); ?>
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
									<?php elseif ( 'sticky_bottom_anchor' === $slot->position ) : ?>
										<span class="aips-badge" style="background: #e0e7ff; color: #3730a3;"><?php esc_html_e( 'Sticky Bottom Anchor', 'ai-post-scheduler' ); ?></span>
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

		<!-- TAB 4: Referrals & Affiliate Networks -->
		<div id="tab-referrals" class="aips-tab-panel">
			<div class="aips-tab-header">
				<div class="aips-tab-header-text">
					<h2><?php esc_html_e( 'Partner Referral Programs & Affiliate Networks', 'ai-post-scheduler' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Manage partner coupon/promo codes, network profiles, and high-converting automated in-article discount ribbons.', 'ai-post-scheduler' ); ?></p>
				</div>
				<div class="aips-tab-header-actions" style="display:flex; gap:10px;">
					<button type="button" class="button button-secondary" id="aips-btn-toggle-network-profiles">
						<span class="dashicons dashicons-networking" style="vertical-align:text-bottom;"></span>
						<?php esc_html_e( 'Network Profiles', 'ai-post-scheduler' ); ?>
					</button>
					<button type="button" class="button button-primary" id="aips-btn-add-referral">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Referral Program', 'ai-post-scheduler' ); ?>
					</button>
				</div>
			</div>

			<!-- Network Profiles Configuration Box (Collapsible) -->
			<div id="aips-network-profiles-panel" style="display: none; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:20px; margin-bottom:20px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
				<h3 style="margin-top:0; margin-bottom:12px; font-size:16px; display:flex; align-items:center; gap:8px;">
					<span class="dashicons dashicons-networking"></span>
					<?php esc_html_e( 'Global Affiliate Network Profiles & SubID Tracking Defaults', 'ai-post-scheduler' ); ?>
				</h3>
				<p class="description" style="margin-bottom:16px;">
					<?php esc_html_e( 'Configure default affiliate IDs and subID tokens ({post_id}, {slug}, {date}, {category}) for automatic URL generation.', 'ai-post-scheduler' ); ?>
				</p>
				<form id="aips-form-network-profiles">
					<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:16px; margin-bottom:16px;">
						<?php
						$networks_catalog = array(
							'amazon'     => array( 'title' => 'Amazon Associates', 'id_field' => 'tag', 'id_label' => 'Associate Store Tag', 'placeholder' => 'mystore-20' ),
							'shareasale' => array( 'title' => 'ShareASale', 'id_field' => 'affiliate_id', 'id_label' => 'Affiliate ID (u=)', 'placeholder' => '1234567' ),
							'cj'         => array( 'title' => 'CJ Affiliate', 'id_field' => 'publisher_id', 'id_label' => 'Publisher/Website ID', 'placeholder' => '9876543' ),
							'impact'     => array( 'title' => 'Impact', 'id_field' => 'media_partner_id', 'id_label' => 'Media Partner ID', 'placeholder' => '54321' ),
							'awin'       => array( 'title' => 'Awin', 'id_field' => 'publisher_id', 'id_label' => 'Publisher ID (awinaffid=)', 'placeholder' => '456789' ),
							'rakuten'    => array( 'title' => 'Rakuten Advertising', 'id_field' => 'affiliate_id', 'id_label' => 'Member/Affiliate ID', 'placeholder' => '102030' ),
							'direct'     => array( 'title' => 'Direct / In-House Partner', 'id_field' => 'affiliate_id', 'id_label' => 'Default Referral ID', 'placeholder' => 'mybrandref' ),
						);
						foreach ( $networks_catalog as $net_key => $net_info ) :
							$prof = $network_profiles[ $net_key ] ?? array();
							?>
							<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px;">
								<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
									<strong><?php echo esc_html( $net_info['title'] ); ?></strong>
									<label style="display:inline-flex; align-items:center; gap:4px; font-size:12px;">
										<input type="checkbox" name="profiles[<?php echo esc_attr( $net_key ); ?>][enabled]" value="1" <?php checked( ! empty( $prof['enabled'] ) ); ?> />
										<span><?php esc_html_e( 'Enabled', 'ai-post-scheduler' ); ?></span>
									</label>
								</div>
								<div style="margin-bottom:6px;">
									<label style="font-size:11px; display:block; color:#475569;"><?php echo esc_html( $net_info['id_label'] ); ?></label>
									<input type="text" name="profiles[<?php echo esc_attr( $net_key ); ?>][<?php echo esc_attr( $net_info['id_field'] ); ?>]" value="<?php echo esc_attr( $prof[ $net_info['id_field'] ] ?? ( $prof['affiliate_id'] ?? '' ) ); ?>" class="regular-text" style="width:100%;" placeholder="<?php echo esc_attr( $net_info['placeholder'] ); ?>" />
								</div>
								<div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
									<div>
										<label style="font-size:11px; display:block; color:#475569;"><?php esc_html_e( 'SubID Query Param', 'ai-post-scheduler' ); ?></label>
										<input type="text" name="profiles[<?php echo esc_attr( $net_key ); ?>][subid_param]" value="<?php echo esc_attr( $prof['subid_param'] ?? 'subid' ); ?>" style="width:100%;" />
									</div>
									<div>
										<label style="font-size:11px; display:block; color:#475569;"><?php esc_html_e( 'SubID Template', 'ai-post-scheduler' ); ?></label>
										<input type="text" name="profiles[<?php echo esc_attr( $net_key ); ?>][subid_template]" value="<?php echo esc_attr( $prof['subid_template'] ?? '{post_id}' ); ?>" style="width:100%;" />
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<div style="display:flex; justify-content:flex-end;">
						<button type="submit" class="button button-primary" id="aips-btn-save-network-profiles">
							<?php esc_html_e( 'Save Network Profiles', 'ai-post-scheduler' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Filter Bar -->
			<div class="aips-filter-bar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; background:#f8fafc; padding:10px 14px; border:1px solid #e2e8f0; border-radius:6px;">
				<div style="display:flex; gap:10px; align-items:center;">
					<label for="aips-filter-referral-network" class="screen-reader-text"><?php esc_html_e( 'Filter by Network', 'ai-post-scheduler' ); ?></label>
					<select id="aips-filter-referral-network">
						<option value=""><?php esc_html_e( 'All Networks', 'ai-post-scheduler' ); ?></option>
						<?php foreach ( AIPS_Referral_Programs_Repository::SUPPORTED_NETWORKS as $net ) : ?>
							<option value="<?php echo esc_attr( $net ); ?>"><?php echo esc_html( ucfirst( $net ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="aips-filter-referral-status" class="screen-reader-text"><?php esc_html_e( 'Filter by Status', 'ai-post-scheduler' ); ?></label>
					<select id="aips-filter-referral-status">
						<option value=""><?php esc_html_e( 'All Statuses', 'ai-post-scheduler' ); ?></option>
						<option value="active"><?php esc_html_e( 'Active Only', 'ai-post-scheduler' ); ?></option>
						<option value="paused"><?php esc_html_e( 'Paused Only', 'ai-post-scheduler' ); ?></option>
					</select>
				</div>
				<div>
					<input type="search" id="aips-search-referrals" placeholder="<?php esc_attr_e( 'Search programs or promo code…', 'ai-post-scheduler' ); ?>" style="min-width:220px;" />
				</div>
			</div>

			<!-- Programs Table -->
			<table class="wp-list-table widefat fixed striped table-view-list" id="aips-table-referrals">
				<thead>
					<tr>
						<th scope="col" style="width: 70px;"><?php esc_html_e( 'Status', 'ai-post-scheduler' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Partner Program', 'ai-post-scheduler' ); ?></th>
						<th scope="col" style="width: 120px;"><?php esc_html_e( 'Network', 'ai-post-scheduler' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Promo Code & Discount', 'ai-post-scheduler' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Cloaked Link / Destination', 'ai-post-scheduler' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Keywords / Categories', 'ai-post-scheduler' ); ?></th>
						<th scope="col" style="width: 140px; text-align: right;"><?php esc_html_e( 'Actions', 'ai-post-scheduler' ); ?></th>
					</tr>
				</thead>
				<tbody id="aips-tbody-referrals">
					<?php if ( empty( $referrals ) ) : ?>
						<tr class="no-items">
							<td class="colspanchange" colspan="7"><?php esc_html_e( 'No partner referral programs created yet.', 'ai-post-scheduler' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $referrals as $ref ) : ?>
							<tr data-referral-id="<?php echo esc_attr( $ref['id'] ); ?>">
								<td>
									<button type="button" class="button button-small aips-referral-toggle <?php echo 'active' === $ref['status'] ? 'button-primary' : ''; ?>" data-id="<?php echo esc_attr( $ref['id'] ); ?>">
										<?php echo 'active' === $ref['status'] ? esc_html__( 'Active', 'ai-post-scheduler' ) : esc_html__( 'Paused', 'ai-post-scheduler' ); ?>
									</button>
								</td>
								<td>
									<strong><?php echo esc_html( $ref['partner_name'] ); ?></strong>
									<?php if ( ! empty( $ref['commission_rate'] ) ) : ?>
										<div class="description" style="font-size:11px;"><?php echo esc_html( $ref['commission_rate'] ); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<span class="aips-badge aips-badge-type"><?php echo esc_html( ucfirst( $ref['network'] ) ); ?></span>
								</td>
								<td>
									<?php if ( ! empty( $ref['promo_code'] ) ) : ?>
										<code><?php echo esc_html( $ref['promo_code'] ); ?></code>
									<?php endif; ?>
									<?php if ( ! empty( $ref['discount_description'] ) ) : ?>
										<div style="font-size:12px; color:#475569;"><?php echo esc_html( $ref['discount_description'] ); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$cloaking_pfx = AIPS_Config::get_instance()->get_option( 'aips_link_cloaking_prefix', 'go' );
									$cloaked_url  = home_url( '/' . $cloaking_pfx . '/' . $ref['slug'] . '/' );
									?>
									<a href="<?php echo esc_url( $cloaked_url ); ?>" target="_blank" rel="noopener" style="font-weight:600;">/<?php echo esc_html( $cloaking_pfx . '/' . $ref['slug'] ); ?>/</a>
									<div class="description" style="font-size:11px; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
										&rarr; <?php echo esc_html( $ref['referral_url'] ); ?>
									</div>
								</td>
								<td>
									<?php if ( ! empty( $ref['keywords'] ) ) : ?>
										<span class="aips-badge aips-badge-kw"><?php echo esc_html( $ref['keywords'] ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $ref['categories'] ) ) : ?>
										<div style="font-size:11px; color:#64748b; margin-top:2px;"><?php echo esc_html( $ref['categories'] ); ?></div>
									<?php endif; ?>
								</td>
								<td style="text-align: right;">
									<button type="button" class="button button-small aips-btn-edit-referral" data-id="<?php echo esc_attr( $ref['id'] ); ?>">
										<?php esc_html_e( 'Edit', 'ai-post-scheduler' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete aips-btn-delete-referral" data-id="<?php echo esc_attr( $ref['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'ai-post-scheduler' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- TAB 5: Analytics -->
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
				<div class="aips-stat-card">
					<span class="aips-stat-label"><?php esc_html_e( 'Smart Refreshes', 'ai-post-scheduler' ); ?></span>
					<span class="aips-stat-value" id="aips-stat-refreshes"><?php echo number_format_i18n( $summary['refreshes'] ?? 0 ); ?></span>
				</div>
				<div class="aips-stat-card">
					<span class="aips-stat-label"><?php esc_html_e( 'Ad-Block Detection Rate', 'ai-post-scheduler' ); ?></span>
					<span class="aips-stat-value" id="aips-stat-adblock"><?php echo esc_html( ( $summary['ad_block_rate'] ?? 0 ) . '%' ); ?></span>
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
								<option value="sticky_bottom_anchor"><?php esc_html_e( 'Sticky Bottom Anchor (Mobile & Desktop)', 'ai-post-scheduler' ); ?></option>
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

					<!-- Anchor Options (visible only when position is sticky_bottom_anchor) -->
					<div id="aips-wrap-anchor-options" class="aips-form-row aips-row-flex" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; margin-bottom: 15px;">
						<div>
							<label for="aips-slot-anchor-trigger"><?php esc_html_e( 'Anchor Display Trigger', 'ai-post-scheduler' ); ?></label>
							<select name="anchor_trigger" id="aips-slot-anchor-trigger">
								<option value="scroll_depth"><?php esc_html_e( 'Scroll Depth (% of page)', 'ai-post-scheduler' ); ?></option>
								<option value="immediate"><?php esc_html_e( 'Immediate on Load', 'ai-post-scheduler' ); ?></option>
								<option value="smart_scroll"><?php esc_html_e( 'Smart Scroll (Hide on scroll up)', 'ai-post-scheduler' ); ?></option>
							</select>
						</div>
						<div>
							<label for="aips-slot-anchor-scroll-depth"><?php esc_html_e( 'Scroll Trigger (%)', 'ai-post-scheduler' ); ?></label>
							<input type="number" name="anchor_scroll_depth" id="aips-slot-anchor-scroll-depth" value="25" min="5" max="95" style="width: 80px;" />
						</div>
						<div style="align-self: flex-end; padding-bottom: 5px;">
							<label>
								<input type="checkbox" name="anchor_dismissible" id="aips-slot-anchor-dismissible" value="1" checked />
								<?php esc_html_e( 'User Dismissible (✕)', 'ai-post-scheduler' ); ?>
							</label>
						</div>
					</div>

					<!-- Smart Ad Refresh Options -->
					<div id="aips-wrap-refresh-options" class="aips-form-row aips-row-flex" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; padding: 10px; margin-bottom: 15px;">
						<div style="align-self: center; padding-right: 15px;">
							<label style="font-weight: 600; color: #166534;">
								<input type="checkbox" name="auto_refresh" id="aips-slot-auto-refresh" value="1" />
								<?php esc_html_e( 'Enable Smart Ad Refresh', 'ai-post-scheduler' ); ?>
							</label>
							<p class="description" style="margin: 2px 0 0 20px; font-size: 11px;"><?php esc_html_e( 'Refreshes only when >50% visible & user active in last 30s.', 'ai-post-scheduler' ); ?></p>
						</div>
						<div>
							<label for="aips-slot-refresh-interval"><?php esc_html_e( 'Refresh Interval', 'ai-post-scheduler' ); ?></label>
							<select name="refresh_interval" id="aips-slot-refresh-interval">
								<option value="30"><?php esc_html_e( '30 seconds (Standard)', 'ai-post-scheduler' ); ?></option>
								<option value="45"><?php esc_html_e( '45 seconds', 'ai-post-scheduler' ); ?></option>
								<option value="60"><?php esc_html_e( '60 seconds (Conservative)', 'ai-post-scheduler' ); ?></option>
								<option value="90"><?php esc_html_e( '90 seconds', 'ai-post-scheduler' ); ?></option>
								<option value="120"><?php esc_html_e( '120 seconds', 'ai-post-scheduler' ); ?></option>
							</select>
						</div>
						<div>
							<label for="aips-slot-max-refreshes"><?php esc_html_e( 'Max Refreshes / Session', 'ai-post-scheduler' ); ?></label>
							<select name="max_refreshes" id="aips-slot-max-refreshes">
								<option value="3"><?php esc_html_e( '3 Refreshes', 'ai-post-scheduler' ); ?></option>
								<option value="5"><?php esc_html_e( '5 Refreshes (Recommended)', 'ai-post-scheduler' ); ?></option>
								<option value="10"><?php esc_html_e( '10 Refreshes', 'ai-post-scheduler' ); ?></option>
							</select>
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

	<!-- Modal: Add/Edit Referral Program -->
	<div id="aips-modal-referral" class="aips-modal" style="display: none;">
		<div class="aips-modal-backdrop"></div>
		<div class="aips-modal-content" style="max-width: 600px;">
			<div class="aips-modal-header">
				<h3 id="aips-modal-referral-title"><?php esc_html_e( 'Partner Referral Program Configuration', 'ai-post-scheduler' ); ?></h3>
				<button type="button" class="aips-modal-close">&times;</button>
			</div>
			<form id="aips-form-referral">
				<input type="hidden" name="id" id="aips-referral-id" value="0" />
				<div class="aips-modal-body">
					<div class="aips-form-row">
						<label for="aips-referral-name"><?php esc_html_e( 'Partner / Program Name', 'ai-post-scheduler' ); ?> <span class="required">*</span></label>
						<input type="text" name="partner_name" id="aips-referral-name" class="regular-text" required placeholder="NordVPN, Canva, Bluehost, etc." />
					</div>
					<div class="aips-form-row aips-row-flex">
						<div>
							<label for="aips-referral-network"><?php esc_html_e( 'Affiliate Network Provider', 'ai-post-scheduler' ); ?></label>
							<select name="network" id="aips-referral-network">
								<option value="direct"><?php esc_html_e( 'Direct / In-House Partner', 'ai-post-scheduler' ); ?></option>
								<option value="amazon"><?php esc_html_e( 'Amazon Associates', 'ai-post-scheduler' ); ?></option>
								<option value="shareasale"><?php esc_html_e( 'ShareASale', 'ai-post-scheduler' ); ?></option>
								<option value="cj"><?php esc_html_e( 'CJ Affiliate', 'ai-post-scheduler' ); ?></option>
								<option value="impact"><?php esc_html_e( 'Impact', 'ai-post-scheduler' ); ?></option>
								<option value="awin"><?php esc_html_e( 'Awin', 'ai-post-scheduler' ); ?></option>
								<option value="rakuten"><?php esc_html_e( 'Rakuten Advertising', 'ai-post-scheduler' ); ?></option>
							</select>
						</div>
						<div>
							<label for="aips-referral-slug"><?php esc_html_e( 'Cloaked URL Slug', 'ai-post-scheduler' ); ?></label>
							<input type="text" name="slug" id="aips-referral-slug" class="regular-text" placeholder="<?php esc_attr_e( 'auto-generated if empty', 'ai-post-scheduler' ); ?>" />
						</div>
					</div>
					<div class="aips-form-row">
						<label for="aips-referral-url"><?php esc_html_e( 'Destination Referral / Affiliate URL', 'ai-post-scheduler' ); ?> <span class="required">*</span></label>
						<input type="url" name="referral_url" id="aips-referral-url" class="large-text" required placeholder="https://nordvpn.com/?coupon=SAVE70" />
					</div>
					<div class="aips-form-row aips-row-flex">
						<div>
							<label for="aips-referral-code"><?php esc_html_e( 'Coupon / Promo Code', 'ai-post-scheduler' ); ?></label>
							<input type="text" name="promo_code" id="aips-referral-code" class="regular-text" placeholder="SAVE70" />
						</div>
						<div>
							<label for="aips-referral-commission"><?php esc_html_e( 'Commission Rate / Note', 'ai-post-scheduler' ); ?></label>
							<input type="text" name="commission_rate" id="aips-referral-commission" class="regular-text" placeholder="40% recurring or $60 CPA" />
						</div>
					</div>
					<div class="aips-form-row">
						<label for="aips-referral-discount"><?php esc_html_e( 'Discount / Offer Headline', 'ai-post-scheduler' ); ?></label>
						<input type="text" name="discount_description" id="aips-referral-discount" class="large-text" placeholder="<?php esc_attr_e( 'Get 70% off 2-year plans plus 3 months free', 'ai-post-scheduler' ); ?>" />
					</div>
					<div class="aips-form-row aips-row-flex">
						<div>
							<label for="aips-referral-keywords"><?php esc_html_e( 'Matching Keywords (comma-separated)', 'ai-post-scheduler' ); ?></label>
							<input type="text" name="keywords" id="aips-referral-keywords" class="regular-text" placeholder="vpn, security, privacy" />
						</div>
						<div>
							<label for="aips-referral-categories"><?php esc_html_e( 'Matching Categories (comma-separated)', 'ai-post-scheduler' ); ?></label>
							<input type="text" name="categories" id="aips-referral-categories" class="regular-text" placeholder="Tech, Security, Hosting" />
						</div>
					</div>
					<div class="aips-form-row">
						<label for="aips-referral-expires"><?php esc_html_e( 'Expiry Date (Optional)', 'ai-post-scheduler' ); ?></label>
						<input type="date" name="expires_at" id="aips-referral-expires" />
					</div>
				</div>
				<div class="aips-modal-footer">
					<button type="button" class="button aips-modal-cancel"><?php esc_html_e( 'Cancel', 'ai-post-scheduler' ); ?></button>
					<button type="submit" class="button button-primary" id="aips-btn-save-referral"><?php esc_html_e( 'Save Referral Program', 'ai-post-scheduler' ); ?></button>
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
			<div class="aips-table-thumb {{logoHidden}}"><img src="{{logo_url}}" alt="" /></div>
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

<script type="text/html" id="aips-tmpl-referral-row">
	<tr data-referral-id="{{id}}">
		<td>
			<button type="button" class="button button-small aips-referral-toggle {{activeClass}}" data-id="{{id}}">
				{{statusLabel}}
			</button>
		</td>
		<td>
			<strong>{{partner_name}}</strong>
			<div class="description {{commissionHidden}}" style="font-size:11px;">{{commission_rate}}</div>
		</td>
		<td>
			<span class="aips-badge aips-badge-type">{{networkLabel}}</span>
		</td>
		<td>
			<code class="{{promoHidden}}">{{promo_code}}</code>
			<div class="{{discountHidden}}" style="font-size:12px; color:#475569;">{{discount_description}}</div>
		</td>
		<td>
			<a href="{{cloaked_url}}" target="_blank" rel="noopener" style="font-weight:600;">/{{cloaking_prefix}}/{{slug}}/</a>
			<div class="description" style="font-size:11px; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
				&rarr; {{referral_url}}
			</div>
		</td>
		<td>
			<span class="aips-badge aips-badge-kw {{kwHidden}}">{{keywords}}</span>
			<div class="{{catHidden}}" style="font-size:11px; color:#64748b; margin-top:2px;">{{categories}}</div>
		</td>
		<td style="text-align: right;">
			<button type="button" class="button button-small aips-btn-edit-referral" data-id="{{id}}">
				<?php esc_html_e( 'Edit', 'ai-post-scheduler' ); ?>
			</button>
			<button type="button" class="button button-small button-link-delete aips-btn-delete-referral" data-id="{{id}}">
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
