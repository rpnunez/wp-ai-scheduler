<?php
/**
 * Settings UI Links Presenter
 *
 * Handles rendering and sanitization for internal linking, link indexer,
 * keyword link rules, topic clusters, related posts, and semantic deduplication.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Settings_UI_Links {

	/**
	 * Render the description for the Auto-Linking settings section.
	 *
	 * @return void
	 */
	public function ai_autolink_section_callback() {
	}

	/**
	 * Render the description for the Link Index card (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_index_section_callback() {
	}

	/**
	 * Render the description for the Keyword Link Rules card (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_rules_section_callback() {
	}

	/**
	 * Render the description for Card 5: Frontend Related Posts Engine.
	 *
	 * @return void
	 */
	public function ai_related_posts_section_callback() {
	}

	/**
	 * Render the description for Card 6: Semantic Duplicate Detection & Gatekeeper Guard.
	 *
	 * @return void
	 */
	public function ai_deduplication_section_callback() {
	}

	/**
	 * Render the Indexed Post Types setting field (Card 4).
	 *
	 * @return void
	 */
	public function indexer_post_types_field_callback() {
		$selected_pts  = (array) AIPS_Config::get_instance()->get_option( 'aips_indexer_post_types', array( 'post' ) );
		$available_pts = get_post_types( array( 'public' => true ), 'objects' );
		unset( $available_pts['attachment'] );
		?>
		<fieldset>
			<?php foreach ( $available_pts as $pt_slug => $pt_obj ) : ?>
				<label class="aips-checkbox-label-block">
					<input type="checkbox" name="aips_indexer_post_types[]" value="<?php echo esc_attr( $pt_slug ); ?>" <?php checked( in_array( $pt_slug, $selected_pts, true ) ); ?>>
					<strong><?php echo esc_html( $pt_obj->labels->name ); ?></strong> <code>(<?php echo esc_html( $pt_slug ); ?>)</code>
				</label>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Select which post types to generate embeddings for and include in Related Posts.', 'ai-post-scheduler' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render auto-index on publish toggle (Card 4).
	 *
	 * @return void
	 */
	public function auto_index_on_publish_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option( 'aips_auto_index_on_publish', true );
		?>
		<label for="aips_auto_index_on_publish">
			<input type="checkbox" name="aips_auto_index_on_publish" id="aips_auto_index_on_publish" value="1" <?php checked( $value ); ?>>
			<?php esc_html_e( 'Automatically compute vector embeddings whenever a new post is published or updated', 'ai-post-scheduler' ); ?>
		</label>
		<?php
	}

	/**
	 * Render verbose activity logging toggle (Card 4).
	 *
	 * @return void
	 */
	public function indexer_verbose_history_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option( 'aips_indexer_verbose_history', false );
		?>
		<label for="aips_indexer_verbose_history">
			<input type="checkbox" name="aips_indexer_verbose_history" id="aips_indexer_verbose_history" value="1" <?php checked( $value ); ?>>
			<?php esc_html_e( 'Enable verbose indexer run logging in History tab', 'ai-post-scheduler' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Leave disabled for large reindex runs to limit History log volume.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render Rate Limiting and Quota Protection fields (Card 4).
	 *
	 * @return void
	 */
	public function embeddings_rate_limits_field_callback() {
		$config        = AIPS_Config::get_instance();
		$enabled       = (bool) $config->get_option( 'aips_embeddings_rate_limits_enabled', true );
		$daily_limit   = (int) $config->get_option( 'aips_embeddings_daily_limit', 50 );
		$weekly_limit  = (int) $config->get_option( 'aips_embeddings_weekly_limit', 200 );
		$monthly_limit = (int) $config->get_option( 'aips_embeddings_monthly_limit', 500 );
		?>
		<fieldset class="aips-embeddings-rate-limits">
			<label for="aips_embeddings_rate_limits_enabled">
				<input type="checkbox" name="aips_embeddings_rate_limits_enabled" id="aips_embeddings_rate_limits_enabled" value="1" <?php checked( $enabled ); ?>>
				<strong><?php esc_html_e( 'Block further vector embedding API calls when spending limits are reached', 'ai-post-scheduler' ); ?></strong>
			</label>
			<p class="description">
				<?php esc_html_e( 'Protects your AI API budget by capping total embedding calls across rolling 24-hour, 7-day, and 30-day windows.', 'ai-post-scheduler' ); ?>
			</p>
			<div class="aips-rate-limits-grid">
				<div class="aips-rate-limit-field">
					<label for="aips_embeddings_daily_limit"><?php esc_html_e( '24-Hour Limit:', 'ai-post-scheduler' ); ?></label>
					<input type="number" min="0" step="1" name="aips_embeddings_daily_limit" id="aips_embeddings_daily_limit" value="<?php echo esc_attr( (string) $daily_limit ); ?>" class="small-text">
					<span class="description"><?php esc_html_e( 'posts (0 = unlimited)', 'ai-post-scheduler' ); ?></span>
				</div>
				<div class="aips-rate-limit-field">
					<label for="aips_embeddings_weekly_limit"><?php esc_html_e( '7-Day Limit:', 'ai-post-scheduler' ); ?></label>
					<input type="number" min="0" step="1" name="aips_embeddings_weekly_limit" id="aips_embeddings_weekly_limit" value="<?php echo esc_attr( (string) $weekly_limit ); ?>" class="small-text">
					<span class="description"><?php esc_html_e( 'posts (0 = unlimited)', 'ai-post-scheduler' ); ?></span>
				</div>
				<div class="aips-rate-limit-field">
					<label for="aips_embeddings_monthly_limit"><?php esc_html_e( '30-Day Limit:', 'ai-post-scheduler' ); ?></label>
					<input type="number" min="0" step="1" name="aips_embeddings_monthly_limit" id="aips_embeddings_monthly_limit" value="<?php echo esc_attr( (string) $monthly_limit ); ?>" class="small-text">
					<span class="description"><?php esc_html_e( 'posts (0 = unlimited)', 'ai-post-scheduler' ); ?></span>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render the link index toggle and post types (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_index_field_callback() {
		$config        = AIPS_Config::get_instance();
		$enabled       = (bool) $config->get_option( 'aips_link_index_enabled', true );
		$selected_pts  = (array) $config->get_option( 'aips_link_index_post_types', array( 'post', 'page' ) );
		$available_pts = get_post_types( array( 'public' => true ), 'objects' );
		unset( $available_pts['attachment'] );
		?>
		<fieldset>
			<label for="aips_link_index_enabled">
				<input type="checkbox" name="aips_link_index_enabled" id="aips_link_index_enabled" value="1" <?php checked( $enabled ); ?>>
				<strong><?php esc_html_e( 'Keep a link index of published content (powers the Link Report and orphan detection)', 'ai-post-scheduler' ); ?></strong>
			</label>
			<p class="description"><?php esc_html_e( 'Links are re-read whenever a post is saved. No AI calls are made.', 'ai-post-scheduler' ); ?></p>
			<?php foreach ( $available_pts as $pt_slug => $pt_obj ) : ?>
				<label class="aips-checkbox-label-block">
					<input type="checkbox" name="aips_link_index_post_types[]" value="<?php echo esc_attr( $pt_slug ); ?>" <?php checked( in_array( $pt_slug, $selected_pts, true ) ); ?>>
					<strong><?php echo esc_html( $pt_obj->labels->name ); ?></strong> <code>(<?php echo esc_html( $pt_slug ); ?>)</code>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Render the keyword link rules toggle and per-post cap (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_rules_field_callback() {
		$config  = AIPS_Config::get_instance();
		$enabled = (bool) $config->get_option( 'aips_link_rules_enabled', true );
		$max     = (int) $config->get_option( 'aips_link_rules_max_per_post', 3 );
		?>
		<fieldset>
			<label for="aips_link_rules_enabled">
				<input type="checkbox" name="aips_link_rules_enabled" id="aips_link_rules_enabled" value="1" <?php checked( $enabled ); ?>>
				<strong><?php esc_html_e( 'Apply keyword link rules to published posts', 'ai-post-scheduler' ); ?></strong>
			</label>
			<p>
				<label for="aips_link_rules_max_per_post"><?php esc_html_e( 'Maximum rule links per post:', 'ai-post-scheduler' ); ?></label>
				<input type="number" min="1" max="20" step="1" name="aips_link_rules_max_per_post" id="aips_link_rules_max_per_post" value="<?php echo esc_attr( (string) $max ); ?>" class="small-text">
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the Link Rules page */
					esc_html__( 'Rules are added when a post is displayed, so post content is never changed. Manage rules under %s.', 'ai-post-scheduler' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=aips-generated-posts&tab=link-rules' ) ) . '">' . esc_html__( 'Content → Link Rules', 'ai-post-scheduler' ) . '</a>'
				);
				?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render the generation-time linking mode (Internal Linking tab).
	 *
	 * @return void
	 */
	public function publish_linking_field_callback() {
		$config   = AIPS_Config::get_instance();
		$mode     = (string) $config->get_option( 'aips_publish_linking_mode', 'review' );
		$outbound = (bool) $config->get_option( 'aips_publish_linking_outbound', true );
		$modes    = array(
			'off'    => __( 'Off', 'ai-post-scheduler' ),
			'review' => __( 'Find links and queue them for review (no content changes)', 'ai-post-scheduler' ),
			'apply'  => __( 'Insert confident links automatically, queue the rest for review', 'ai-post-scheduler' ),
		);
		?>
		<fieldset>
			<?php foreach ( $modes as $value => $label ) : ?>
				<label class="aips-checkbox-label-block">
					<input type="radio" name="aips_publish_linking_mode" value="<?php echo esc_attr( $value ); ?>" <?php checked( $mode, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
			<label for="aips_publish_linking_outbound" class="aips-checkbox-label-block">
				<input type="checkbox" name="aips_publish_linking_outbound" id="aips_publish_linking_outbound" value="1" <?php checked( $outbound ); ?>>
				<?php esc_html_e( 'Also suggest links from the new post to related posts (Internal Links page)', 'ai-post-scheduler' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'When a post generated by AI Post Scheduler is published, older related posts get links to it, about two minutes later in the background. "Insert automatically" uses the auto-apply threshold and caps above. Each publish appears in the Link Report run history, where its links can be undone at once.', 'ai-post-scheduler' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render the link click tracking toggle and retention (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_click_tracking_field_callback() {
		$config    = AIPS_Config::get_instance();
		$enabled   = (bool) $config->get_option( 'aips_link_click_tracking_enabled', false );
		$retention = (int) $config->get_option( 'aips_link_click_retention_days', 365 );
		?>
		<fieldset>
			<label for="aips_link_click_tracking_enabled">
				<input type="checkbox" name="aips_link_click_tracking_enabled" id="aips_link_click_tracking_enabled" value="1" <?php checked( $enabled ); ?>>
				<strong><?php esc_html_e( 'Count clicks on internal links in post content', 'ai-post-scheduler' ); ?></strong>
			</label>
			<p>
				<label for="aips_link_click_retention_days"><?php esc_html_e( 'Keep click counts for (days):', 'ai-post-scheduler' ); ?></label>
				<input type="number" min="30" max="730" step="1" name="aips_link_click_retention_days" id="aips_link_click_retention_days" value="<?php echo esc_attr( (string) $retention ); ?>" class="small-text">
			</p>
			<p class="description"><?php esc_html_e( 'Adds a small script to single posts that reports which internal link was clicked. Only a daily count per link is stored: no IP addresses, cookies or visitor IDs. Clicks by logged-in editors and known bots are ignored. Results appear in Content → Link Report.', 'ai-post-scheduler' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render the silo "In this guide" list settings (Internal Linking tab).
	 *
	 * @return void
	 */
	public function silo_guide_field_callback() {
		$config   = AIPS_Config::get_instance();
		$enabled  = (bool) $config->get_option( 'aips_silo_guide_enabled', true );
		$position = (string) $config->get_option( 'aips_silo_guide_position', 'end' );
		$max      = (int) $config->get_option( 'aips_silo_guide_max', 10 );
		$style    = (string) $config->get_option( 'aips_silo_guide_style', 'aips' );
		$heading  = (string) $config->get_option( 'aips_silo_guide_heading', '' );
		?>
		<fieldset>
			<label for="aips_silo_guide_enabled">
				<input type="checkbox" name="aips_silo_guide_enabled" id="aips_silo_guide_enabled" value="1" <?php checked( $enabled ); ?>>
				<strong><?php esc_html_e( 'Show an "In this guide" list of its articles on each silo pillar', 'ai-post-scheduler' ); ?></strong>
			</label>
			<p>
				<label for="aips_silo_guide_heading"><?php esc_html_e( 'Heading:', 'ai-post-scheduler' ); ?></label>
				<input type="text" name="aips_silo_guide_heading" id="aips_silo_guide_heading" value="<?php echo esc_attr( $heading ); ?>" placeholder="<?php esc_attr_e( 'In this guide', 'ai-post-scheduler' ); ?>" class="regular-text">
			</p>
			<p>
				<label for="aips_silo_guide_position"><?php esc_html_e( 'Place it:', 'ai-post-scheduler' ); ?></label>
				<select name="aips_silo_guide_position" id="aips_silo_guide_position">
					<option value="end" <?php selected( $position, 'end' ); ?>><?php esc_html_e( 'At the end of the post', 'ai-post-scheduler' ); ?></option>
					<option value="after_first_paragraph" <?php selected( $position, 'after_first_paragraph' ); ?>><?php esc_html_e( 'After the first paragraph', 'ai-post-scheduler' ); ?></option>
				</select>
			</p>
			<p>
				<label for="aips_silo_guide_max"><?php esc_html_e( 'List up to', 'ai-post-scheduler' ); ?></label>
				<input type="number" min="1" max="50" step="1" name="aips_silo_guide_max" id="aips_silo_guide_max" value="<?php echo esc_attr( (string) $max ); ?>" class="small-text">
				<?php esc_html_e( 'articles, closest to the pillar first', 'ai-post-scheduler' ); ?>
			</p>
			<p>
				<label for="aips_silo_guide_style"><?php esc_html_e( 'Style:', 'ai-post-scheduler' ); ?></label>
				<select name="aips_silo_guide_style" id="aips_silo_guide_style">
					<option value="aips" <?php selected( $style, 'aips' ); ?>><?php esc_html_e( "AI Post Scheduler box (a light bordered box in your theme's colours)", 'ai-post-scheduler' ); ?></option>
					<option value="theme" <?php selected( $style, 'theme' ); ?>><?php esc_html_e( 'Plain HTML (your theme styles the heading and list)', 'ai-post-scheduler' ); ?></option>
				</select>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: shortcode */
					esc_html__( "The list is added when the pillar is displayed; the pillar's content is never edited, and the list updates itself as articles join or leave the silo. Articles the pillar already links to in its text are left out. To place it yourself, put %s in the pillar. Silos are managed in Content → Silos.", 'ai-post-scheduler' ),
					'<code>[aips_silo_guide]</code>'
				);
				?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render the link index rebuild throttle (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_index_throttle_field_callback() {
		$config     = AIPS_Config::get_instance();
		$batch_size = (int) $config->get_option( 'aips_link_index_batch_size', 50 );
		$delay      = (int) $config->get_option( 'aips_link_index_batch_delay', 20 );
		?>
		<fieldset class="aips-link-index-throttle">
			<div class="aips-rate-limits-grid">
				<div class="aips-rate-limit-field">
					<label for="aips_link_index_batch_size"><?php esc_html_e( 'Posts per batch:', 'ai-post-scheduler' ); ?></label>
					<input type="number" min="10" max="500" step="10" name="aips_link_index_batch_size" id="aips_link_index_batch_size" value="<?php echo esc_attr( (string) $batch_size ); ?>" class="small-text">
				</div>
				<div class="aips-rate-limit-field">
					<label for="aips_link_index_batch_delay"><?php esc_html_e( 'Pause between batches:', 'ai-post-scheduler' ); ?></label>
					<input type="number" min="0" max="600" step="5" name="aips_link_index_batch_delay" id="aips_link_index_batch_delay" value="<?php echo esc_attr( (string) $delay ); ?>" class="small-text">
					<span class="description"><?php esc_html_e( 'seconds', 'ai-post-scheduler' ); ?></span>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'A full rebuild runs in the background as WP-Cron batches. Each batch only parses post HTML (no AI calls); lower the batch size or raise the pause on slow or shared hosting.', 'ai-post-scheduler' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render the bulk auto-linking toggle (Internal Linking tab).
	 *
	 * @return void
	 */
	public function autolink_enabled_field_callback() {
		$enabled = (bool) AIPS_Config::get_instance()->get_option( 'aips_autolink_enabled', false );
		?>
		<label for="aips_autolink_enabled">
			<input type="checkbox" name="aips_autolink_enabled" id="aips_autolink_enabled" value="1" <?php checked( $enabled ); ?>>
			<strong><?php esc_html_e( 'Allow bulk auto-link runs to insert internal links automatically', 'ai-post-scheduler' ); ?></strong>
		</label>
		<p class="description">
			<?php esc_html_e( 'Links at or above the auto-apply threshold are inserted; links between the review and auto-apply thresholds wait in a review queue. Every insertion can be undone individually or per run.', 'ai-post-scheduler' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the auto-apply and review confidence thresholds (Card 7).
	 *
	 * @return void
	 */
	public function autolink_thresholds_field_callback() {
		$config = AIPS_Config::get_instance();
		$auto   = (float) $config->get_option( 'aips_autolink_auto_apply_threshold', 0.85 );
		$review = (float) $config->get_option( 'aips_autolink_review_threshold', 0.70 );
		?>
		<fieldset class="aips-autolink-thresholds">
			<div class="aips-rate-limits-grid">
				<div class="aips-rate-limit-field">
					<label for="aips_autolink_auto_apply_threshold"><?php esc_html_e( 'Auto-apply at:', 'ai-post-scheduler' ); ?></label>
					<input type="number" min="0.5" max="1" step="0.01" name="aips_autolink_auto_apply_threshold" id="aips_autolink_auto_apply_threshold" value="<?php echo esc_attr( (string) $auto ); ?>" class="small-text">
				</div>
				<div class="aips-rate-limit-field">
					<label for="aips_autolink_review_threshold"><?php esc_html_e( 'Send to review at:', 'ai-post-scheduler' ); ?></label>
					<input type="number" min="0.5" max="1" step="0.01" name="aips_autolink_review_threshold" id="aips_autolink_review_threshold" value="<?php echo esc_attr( (string) $review ); ?>" class="small-text">
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Confidence combines semantic similarity with anchor-text quality (0.50 - 1.00). Suggestions below the review threshold are skipped.', 'ai-post-scheduler' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render the per-post and per-target link caps (Card 7).
	 *
	 * @return void
	 */
	public function autolink_limits_field_callback() {
		$config = AIPS_Config::get_instance();
		$fields = array(
			'aips_autolink_max_links_per_post'          => array( (int) $config->get_option( 'aips_autolink_max_links_per_post', 3 ), __( 'New links per post, per run:', 'ai-post-scheduler' ) ),
			'aips_autolink_max_total_internal_per_post' => array( (int) $config->get_option( 'aips_autolink_max_total_internal_per_post', 15 ), __( 'Max internal links in a post:', 'ai-post-scheduler' ) ),
			'aips_autolink_max_inbound_per_target'      => array( (int) $config->get_option( 'aips_autolink_max_inbound_per_target', 5 ), __( 'New inbound links per target, per run:', 'ai-post-scheduler' ) ),
		);
		?>
		<fieldset class="aips-autolink-limits">
			<div class="aips-rate-limits-grid">
				<?php foreach ( $fields as $name => $field ) : ?>
					<div class="aips-rate-limit-field">
						<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field[1] ); ?></label>
						<input type="number" min="1" max="100" step="1" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $field[0] ); ?>" class="small-text">
					</div>
				<?php endforeach; ?>
			</div>
			<p class="description"><?php esc_html_e( 'Posts already at the internal link cap never receive automatic links.', 'ai-post-scheduler' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render placement and link attribute options (Card 7).
	 *
	 * @return void
	 */
	public function autolink_placement_field_callback() {
		$config     = AIPS_Config::get_instance();
		$skip_first = (bool) $config->get_option( 'aips_autolink_skip_first_paragraph', true );
		$rel        = (string) $config->get_option( 'aips_autolink_rel', '' );
		$new_tab    = (bool) $config->get_option( 'aips_autolink_target_blank', false );
		$rel_labels = array(
			''          => __( 'None (recommended for internal links)', 'ai-post-scheduler' ),
			'nofollow'  => 'nofollow',
			'sponsored' => 'sponsored',
			'ugc'       => 'ugc',
		);
		?>
		<fieldset class="aips-autolink-placement">
			<label for="aips_autolink_skip_first_paragraph">
				<input type="checkbox" name="aips_autolink_skip_first_paragraph" id="aips_autolink_skip_first_paragraph" value="1" <?php checked( $skip_first ); ?>>
				<?php esc_html_e( 'Never link inside the first paragraph', 'ai-post-scheduler' ); ?>
			</label>
			<br>
			<label for="aips_autolink_target_blank">
				<input type="checkbox" name="aips_autolink_target_blank" id="aips_autolink_target_blank" value="1" <?php checked( $new_tab ); ?>>
				<?php esc_html_e( 'Open auto-inserted links in a new tab', 'ai-post-scheduler' ); ?>
			</label>
			<p>
				<label for="aips_autolink_rel"><?php esc_html_e( 'rel attribute:', 'ai-post-scheduler' ); ?></label>
				<select name="aips_autolink_rel" id="aips_autolink_rel">
					<?php foreach ( $rel_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $rel, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description"><?php esc_html_e( 'Links are never placed inside headings, existing links, code, buttons, shortcodes or HTML blocks.', 'ai-post-scheduler' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render related posts similarity threshold setting (Card 4).
	 *
	 * @return void
	 */
	public function indexer_similarity_threshold_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option( 'aips_indexer_similarity_threshold', 0.65 );
		?>
		<input type="number" step="0.05" min="0.40" max="0.95" name="aips_indexer_similarity_threshold" id="aips_indexer_similarity_threshold" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text">
		<p class="description"><?php esc_html_e( 'Minimum cosine similarity (0.40 - 0.95) for two posts to be considered related. Default: 0.65', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render publish indexing execution timing mode (Card 4).
	 *
	 * @return void
	 */
	public function indexer_publish_execution_timing_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option( 'aips_indexer_publish_execution_timing', 'queued' );
		?>
		<fieldset>
			<label style="display:block;margin-bottom:6px;">
				<input type="radio" name="aips_indexer_publish_execution_timing" value="queued" <?php checked( $value, 'queued' ); ?>>
				<strong><?php esc_html_e( 'Debounced Background Batch Queue (Recommended)', 'ai-post-scheduler' ); ?></strong>
				<span class="description" style="display:block;margin-left:22px;"><?php esc_html_e( 'Buffers posts when published and processes them in rate-limited background batches via WP-Cron.', 'ai-post-scheduler' ); ?></span>
			</label>
			<label style="display:block;margin-bottom:6px;">
				<input type="radio" name="aips_indexer_publish_execution_timing" value="immediate" <?php checked( $value, 'immediate' ); ?>>
				<strong><?php esc_html_e( 'Immediate (Synchronous on Publish)', 'ai-post-scheduler' ); ?></strong>
				<span class="description" style="display:block;margin-left:22px;"><?php esc_html_e( 'Generates vector embeddings and relationships immediately upon post publishing. May slightly slow down post saving.', 'ai-post-scheduler' ); ?></span>
			</label>
			<label style="display:block;">
				<input type="radio" name="aips_indexer_publish_execution_timing" value="disabled" <?php checked( $value, 'disabled' ); ?>>
				<strong><?php esc_html_e( 'Disabled', 'ai-post-scheduler' ); ?></strong>
				<span class="description" style="display:block;margin-left:22px;"><?php esc_html_e( 'Do not vectorize posts on publish. Indexing must be initiated manually from Content Indexer.', 'ai-post-scheduler' ); ?></span>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Render Batch Queue and Quota Pause configuration (Card 4).
	 *
	 * @return void
	 */
	public function indexer_batch_config_field_callback() {
		$config        = AIPS_Config::get_instance();
		$batch_size    = (int) $config->get_option( 'aips_indexer_batch_size', 10 );
		$debounce_sec  = (int) $config->get_option( 'aips_indexer_queue_debounce_seconds', 15 );
		$quota_pause   = (bool) $config->get_option( 'aips_indexer_quota_pause_enabled', true );
		$notifications = (bool) $config->get_option( 'aips_indexer_queue_notifications_enabled', true );
		?>
		<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;margin-bottom:12px;">
			<div>
				<label for="aips_indexer_batch_size"><strong><?php esc_html_e( 'Batch Size:', 'ai-post-scheduler' ); ?></strong></label><br>
				<input type="number" min="1" max="50" step="1" name="aips_indexer_batch_size" id="aips_indexer_batch_size" value="<?php echo esc_attr( (string) $batch_size ); ?>" class="small-text">
				<p class="description"><?php esc_html_e( 'Posts per chunk (1-50).', 'ai-post-scheduler' ); ?></p>
			</div>
			<div>
				<label for="aips_indexer_queue_debounce_seconds"><strong><?php esc_html_e( 'Debounce Delay:', 'ai-post-scheduler' ); ?></strong></label><br>
				<input type="number" min="5" max="300" step="5" name="aips_indexer_queue_debounce_seconds" id="aips_indexer_queue_debounce_seconds" value="<?php echo esc_attr( (string) $debounce_sec ); ?>" class="small-text">
				<p class="description"><?php esc_html_e( 'Seconds before processing queue.', 'ai-post-scheduler' ); ?></p>
			</div>
		</div>
		<div>
			<label for="aips_indexer_quota_pause_enabled" style="display:block;margin-bottom:6px;">
				<input type="checkbox" name="aips_indexer_quota_pause_enabled" id="aips_indexer_quota_pause_enabled" value="1" <?php checked( $quota_pause ); ?>>
				<?php esc_html_e( 'Auto-Pause indexing queue when approaching rate limits or remote quota', 'ai-post-scheduler' ); ?>
			</label>
			<label for="aips_indexer_queue_notifications_enabled" style="display:block;">
				<input type="checkbox" name="aips_indexer_queue_notifications_enabled" id="aips_indexer_queue_notifications_enabled" value="1" <?php checked( $notifications ); ?>>
				<?php esc_html_e( 'Notify admin via email when background indexing queue is paused or exhausts quota', 'ai-post-scheduler' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Render Rate Limit Error Pattern Auto-Cooldown fields (Card 4).
	 *
	 * @return void
	 */
	public function indexer_error_cooldown_field_callback() {
		$config    = AIPS_Config::get_instance();
		$duration  = (int) $config->get_option( 'aips_indexer_error_pause_duration', 30 );
		$unit      = (string) $config->get_option( 'aips_indexer_error_pause_unit', 'minutes' );
		$threshold = (int) $config->get_option( 'aips_indexer_consecutive_error_threshold', 2 );
		?>
		<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
			<div>
				<label for="aips_indexer_error_pause_duration"><strong><?php esc_html_e( 'Cooldown Duration:', 'ai-post-scheduler' ); ?></strong></label><br>
				<input type="number" min="1" max="100" step="1" name="aips_indexer_error_pause_duration" id="aips_indexer_error_pause_duration" value="<?php echo esc_attr( (string) $duration ); ?>" class="small-text">
				<select name="aips_indexer_error_pause_unit" id="aips_indexer_error_pause_unit">
					<option value="minutes" <?php selected( $unit, 'minutes' ); ?>><?php esc_html_e( 'Minutes', 'ai-post-scheduler' ); ?></option>
					<option value="hours" <?php selected( $unit, 'hours' ); ?>><?php esc_html_e( 'Hours', 'ai-post-scheduler' ); ?></option>
					<option value="days" <?php selected( $unit, 'days' ); ?>><?php esc_html_e( 'Days', 'ai-post-scheduler' ); ?></option>
				</select>
			</div>
			<div>
				<label for="aips_indexer_consecutive_error_threshold"><strong><?php esc_html_e( 'Consecutive Errors to Trigger:', 'ai-post-scheduler' ); ?></strong></label><br>
				<input type="number" min="1" max="10" step="1" name="aips_indexer_consecutive_error_threshold" id="aips_indexer_consecutive_error_threshold" value="<?php echo esc_attr( (string) $threshold ); ?>" class="small-text">
				<span class="description"><?php esc_html_e( 'failures', 'ai-post-scheduler' ); ?></span>
			</div>
		</div>
		<p class="description" style="margin-top:8px;">
			<?php esc_html_e( 'When remote provider returns HTTP 429 ("Resource exhausted" / "Quota exceeded"), indexing halts automatically for the configured duration.', 'ai-post-scheduler' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Post Cluster similarity threshold setting (Card 4).
	 *
	 * @return void
	 */
	public function indexer_post_cluster_threshold_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option( 'aips_indexer_post_cluster_threshold', 0.65 );
		?>
		<input type="number" step="0.05" min="0.30" max="0.95" name="aips_indexer_post_cluster_threshold" id="aips_indexer_post_cluster_threshold" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text">
		<p class="description"><?php esc_html_e( 'Minimum similarity threshold for grouping connected posts into a thematic cluster. Default: 0.65', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render the cluster detection safety limits (Card 4).
	 *
	 * @return void
	 */
	public function cluster_detection_limits_field_callback() {
		$config    = AIPS_Config::get_instance();
		$max_posts = (int) $config->get_option( 'aips_cluster_detection_max_posts', 250 );
		$budget    = (int) $config->get_option( 'aips_cluster_detection_time_budget', 8 );
		?>
		<label for="aips_cluster_detection_max_posts">
			<?php esc_html_e( 'Max posts per scan:', 'ai-post-scheduler' ); ?>
			<input type="number" step="50" min="50" max="5000" name="aips_cluster_detection_max_posts" id="aips_cluster_detection_max_posts" value="<?php echo esc_attr( (string) $max_posts ); ?>" class="small-text">
		</label>
		&nbsp;
		<label for="aips_cluster_detection_time_budget">
			<?php esc_html_e( 'Time budget (seconds):', 'ai-post-scheduler' ); ?>
			<input type="number" step="1" min="2" max="25" name="aips_cluster_detection_time_budget" id="aips_cluster_detection_time_budget" value="<?php echo esc_attr( (string) $budget ); ?>" class="small-text">
		</label>
		<p class="description"><?php esc_html_e( 'Topic Clusters compares every post with every other post, so the work grows with the square of the post count. Only the newest posts up to the cap are scanned, and a scan stops once the time budget is spent. Raise the cap on fast hosting; lower it if the scan times out. Defaults: 250 posts, 8 seconds.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render Topic Cluster defaults (Card 4).
	 *
	 * @return void
	 */
	public function cluster_defaults_field_callback() {
		$config    = AIPS_Config::get_instance();
		$min_size  = (int) $config->get_option( 'aips_cluster_default_min_size', 3 );
		$tightness = (string) $config->get_option( 'aips_cluster_tightness', 'balanced' );
		$spoke     = (float) $config->get_option( 'aips_pillar_spoke_similarity', 0.75 );
		$levels    = array(
			'loose'    => __( 'Loose (any single link joins posts; few, large clusters)', 'ai-post-scheduler' ),
			'balanced' => __( 'Balanced (a post must be close to about half the cluster)', 'ai-post-scheduler' ),
			'strict'   => __( 'Strict (a post must be close to every post in the cluster)', 'ai-post-scheduler' ),
		);
		?>
		<p>
			<label for="aips_cluster_tightness">
				<?php esc_html_e( 'Cluster tightness:', 'ai-post-scheduler' ); ?>
				<select name="aips_cluster_tightness" id="aips_cluster_tightness">
					<?php foreach ( $levels as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $tightness, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</p>
		<p>
			<label for="aips_cluster_default_min_size">
				<?php esc_html_e( 'Default minimum cluster size:', 'ai-post-scheduler' ); ?>
				<input type="number" step="1" min="2" max="50" name="aips_cluster_default_min_size" id="aips_cluster_default_min_size" value="<?php echo esc_attr( (string) $min_size ); ?>" class="small-text">
			</label>
			&nbsp;
			<label for="aips_pillar_spoke_similarity">
				<?php esc_html_e( 'Pillar link weight:', 'ai-post-scheduler' ); ?>
				<input type="number" step="0.05" min="0.30" max="1" name="aips_pillar_spoke_similarity" id="aips_pillar_spoke_similarity" value="<?php echo esc_attr( (string) $spoke ); ?>" class="small-text">
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Tightness controls how easily posts chain into one cluster. Loose reproduces one giant cluster on a site where everything is related; Balanced is recommended. The minimum size pre-selects the Min Size box on Topic Clusters, and the pillar link weight is the similarity recorded for pillar-to-post links. Defaults: Balanced, 3 posts, 0.75.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render Enable Post Insights UI setting (Card 4).
	 *
	 * @return void
	 */
	public function enable_post_insights_ui_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option( 'aips_enable_post_insights_ui', true );
		?>
		<label for="aips_enable_post_insights_ui">
			<input type="checkbox" name="aips_enable_post_insights_ui" id="aips_enable_post_insights_ui" value="1" <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Inject AI Insights column, duplication risk badges, and editor panels on native WordPress Posts and Editor screens.', 'ai-post-scheduler' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, adds the AI Insights column and filters to the Posts table, a Document Setting Panel to the Block Editor, and a Meta Box to the Classic Editor.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render Run Embeddings when Topics are Generated setting.
	 *
	 * @return void
	 */
	public function indexer_topics_continuous_sync_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option( 'aips_indexer_topics_continuous_sync', true );
		?>
		<label for="aips_indexer_topics_continuous_sync">
			<input type="checkbox" name="aips_indexer_topics_continuous_sync" id="aips_indexer_topics_continuous_sync" value="1" <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Automatically generate and persist vector embeddings immediately when Author Topics are created or generated.', 'ai-post-scheduler' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Enables real-time duplicate idea detection and cross-referencing between Author Topics and published WordPress articles.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render related posts enable toggle (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_enabled_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option( 'aips_related_posts_enabled', true );
		?>
		<label for="aips_related_posts_enabled">
			<input type="checkbox" name="aips_related_posts_enabled" id="aips_related_posts_enabled" value="1" <?php checked( $value ); ?>>
			<?php esc_html_e( 'Enable semantic recommendations via vector similarity', 'ai-post-scheduler' ); ?>
		</label>
		<?php
	}

	/**
	 * Render related posts auto-append toggle (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_auto_append_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option( 'aips_related_posts_auto_append', false );
		?>
		<label for="aips_related_posts_auto_append">
			<input type="checkbox" name="aips_related_posts_auto_append" id="aips_related_posts_auto_append" value="1" <?php checked( $value ); ?>>
			<?php esc_html_e( 'Automatically render related articles below single post content', 'ai-post-scheduler' ); ?>
		</label>
		<?php
	}

	/**
	 * Render related posts heading title (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_heading_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option( 'aips_related_posts_heading', 'Related Articles' );
		?>
		<input type="text" name="aips_related_posts_heading" id="aips_related_posts_heading" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<?php
	}

	/**
	 * Render related posts count and layout options (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_count_layout_field_callback() {
		$count  = (int) AIPS_Config::get_instance()->get_option( 'aips_related_posts_count', 4 );
		$layout = (string) AIPS_Config::get_instance()->get_option( 'aips_related_posts_layout', 'grid' );
		?>
		<input type="number" min="1" max="12" name="aips_related_posts_count" id="aips_related_posts_count" value="<?php echo esc_attr( (string) $count ); ?>" class="small-text"> <?php esc_html_e( 'articles', 'ai-post-scheduler' ); ?>
		<select name="aips_related_posts_layout" id="aips_related_posts_layout" class="aips-select-inline-gap">
			<option value="grid" <?php selected( $layout, 'grid' ); ?>><?php esc_html_e( 'Card Grid', 'ai-post-scheduler' ); ?></option>
			<option value="list" <?php selected( $layout, 'list' ); ?>><?php esc_html_e( 'List Layout', 'ai-post-scheduler' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Render related posts shortcode and block integration helper with copy button (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_shortcode_field_callback() {
		?>
		<div class="aips-shortcode-preview-card">
			<div class="aips-shortcode-code-wrap">
				<code class="aips-shortcode-display" id="aips-related-posts-shortcode">[aips_related_posts]</code>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-copy-btn" id="aips-copy-shortcode-btn" data-clipboard-text="[aips_related_posts]" title="<?php esc_attr_e( 'Copy shortcode to clipboard', 'ai-post-scheduler' ); ?>">
					<span class="dashicons dashicons-clipboard"></span>
					<span class="aips-copy-text"><?php esc_html_e( 'Copy Shortcode', 'ai-post-scheduler' ); ?></span>
				</button>
			</div>
			<p class="description">
				<?php esc_html_e( 'Place this shortcode anywhere in your content, page builders (Elementor, Divi, Beaver Builder), or widget templates to insert semantic related recommendations.', 'ai-post-scheduler' ); ?>
			</p>
			<div class="aips-shortcode-attributes-hint">
				<span class="aips-hint-tag"><strong><?php esc_html_e( 'Gutenberg Block:', 'ai-post-scheduler' ); ?></strong> <code>/Related Posts (AIPS)</code></span>
				<span class="aips-hint-tag"><strong><?php esc_html_e( 'Attributes:', 'ai-post-scheduler' ); ?></strong> <code>count="4"</code>, <code>heading="Related Articles"</code>, <code>layout="grid|list"</code></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render deduplication gatekeeper action select (Card 6).
	 *
	 * @return void
	 */
	public function deduplication_mode_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option( 'aips_deduplication_mode', 'warn' );
		?>
		<select name="aips_deduplication_mode" id="aips_deduplication_mode">
			<option value="warn" <?php selected( $value, 'warn' ); ?>><?php esc_html_e( 'Warn & Flag (Lowers Score & shows Duplicate Badge)', 'ai-post-scheduler' ); ?></option>
			<option value="block" <?php selected( $value, 'block' ); ?>><?php esc_html_e( 'Strict Block (Skip automated generation if duplicate exists)', 'ai-post-scheduler' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Render deduplication similarity threshold number input (Card 6).
	 *
	 * @return void
	 */
	public function deduplication_threshold_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option( 'aips_deduplication_threshold', 0.85 );
		?>
		<input type="number" step="0.05" min="0.70" max="0.99" name="aips_deduplication_threshold" id="aips_deduplication_threshold" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text">
		<p class="description"><?php esc_html_e( 'Cosine similarity threshold to classify a topic or post as a duplicate candidate. Default: 0.85', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render the duplicate review similarity threshold (Card 6).
	 *
	 * @return void
	 */
	public function duplicate_review_threshold_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option( 'aips_duplicate_review_threshold', 0.88 );
		?>
		<input type="number" step="0.01" min="0.70" max="0.99" name="aips_duplicate_review_threshold" id="aips_duplicate_review_threshold" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text">
		<p class="description"><?php esc_html_e( 'Posts in a Duplicate Review group must be at least this similar to every other post in the group. Higher means fewer, more certain duplicates. Default: 0.88', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render the duplicate group size and pair limits (Card 6).
	 *
	 * @return void
	 */
	public function duplicate_review_limits_field_callback() {
		$config     = AIPS_Config::get_instance();
		$group      = (int) $config->get_option( 'aips_duplicate_review_max_group_size', 5 );
		$max_pairs  = (int) $config->get_option( 'aips_duplicate_review_max_pairs', 1000 );
		$audit_rows = (int) $config->get_option( 'aips_audit_result_limit', 50 );
		?>
		<label for="aips_duplicate_review_max_group_size">
			<?php esc_html_e( 'Max posts per group:', 'ai-post-scheduler' ); ?>
			<input type="number" step="1" min="2" max="10" name="aips_duplicate_review_max_group_size" id="aips_duplicate_review_max_group_size" value="<?php echo esc_attr( (string) $group ); ?>" class="small-text">
		</label>
		&nbsp;
		<label for="aips_duplicate_review_max_pairs">
			<?php esc_html_e( 'Max similar pairs to review:', 'ai-post-scheduler' ); ?>
			<input type="number" step="100" min="100" max="5000" name="aips_duplicate_review_max_pairs" id="aips_duplicate_review_max_pairs" value="<?php echo esc_attr( (string) $max_pairs ); ?>" class="small-text">
		</label>
		&nbsp;
		<label for="aips_audit_result_limit">
			<?php esc_html_e( 'Overlap audit rows:', 'ai-post-scheduler' ); ?>
			<input type="number" step="10" min="10" max="500" name="aips_audit_result_limit" id="aips_audit_result_limit" value="<?php echo esc_attr( (string) $audit_rows ); ?>" class="small-text">
		</label>
		<p class="description"><?php esc_html_e( 'Keeps each review group small enough to decide at a glance, bounds how many similar pairs are loaded, and sets how many rows the overlap audit lists (it uses the Duplicate Similarity Threshold above). Defaults: 5 posts, 1000 pairs, 50 rows.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render the keep-post recommendation strategy (Card 6).
	 *
	 * @return void
	 */
	public function duplicate_keep_strategy_field_callback() {
		$value   = (string) AIPS_Config::get_instance()->get_option( 'aips_duplicate_keep_strategy', 'balanced' );
		$choices = AIPS_Duplicate_Group_Service::get_keep_strategies();
		?>
		<select name="aips_duplicate_keep_strategy" id="aips_duplicate_keep_strategy">
			<?php foreach ( $choices as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'How Duplicate Review picks the post to recommend keeping. Balanced weighs inbound links, length and age together. You always make the final choice.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render the inbound-link protection threshold (Card 6).
	 *
	 * @return void
	 */
	public function duplicate_protection_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option( 'aips_duplicate_protect_inbound_links', 10 );
		?>
		<label for="aips_duplicate_protect_inbound_links">
			<?php esc_html_e( 'Protect posts with at least', 'ai-post-scheduler' ); ?>
			<input type="number" step="1" min="0" max="1000" name="aips_duplicate_protect_inbound_links" id="aips_duplicate_protect_inbound_links" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text">
			<?php esc_html_e( 'inbound internal links', 'ai-post-scheduler' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'A protected post is always the recommended keeper and cannot be offered for retirement from a Duplicate Review group, so a heavily linked article is never merged away by accident. Set to 0 to turn protection off. Default: 10', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render the duplicate review exclusions (Card 6).
	 *
	 * @return void
	 */
	public function duplicate_exclusions_field_callback() {
		$config     = AIPS_Config::get_instance();
		$types      = array_map( 'strval', (array) $config->get_option( 'aips_duplicate_excluded_post_types', array() ) );
		$categories = array_map( 'intval', (array) $config->get_option( 'aips_duplicate_excluded_categories', array() ) );
		$ids        = (string) $config->get_option( 'aips_duplicate_excluded_post_ids', '' );
		$pillars    = (bool) $config->get_option( 'aips_duplicate_exclude_pillars', true );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$terms      = get_categories( array( 'hide_empty' => false, 'number' => 200 ) );
		?>
		<fieldset>
			<p><strong><?php esc_html_e( 'Post types', 'ai-post-scheduler' ); ?></strong></p>
			<p>
				<?php foreach ( $post_types as $slug => $object ) : ?>
					<label class="aips-inline-check">
						<input type="checkbox" name="aips_duplicate_excluded_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $types, true ) ); ?>>
						<?php echo esc_html( $object->labels->singular_name ); ?>
					</label>&nbsp;
				<?php endforeach; ?>
			</p>
			<?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
				<p><label for="aips_duplicate_excluded_categories"><strong><?php esc_html_e( 'Categories (Ctrl/Cmd-click to select several)', 'ai-post-scheduler' ); ?></strong></label></p>
				<select name="aips_duplicate_excluded_categories[]" id="aips_duplicate_excluded_categories" multiple size="5">
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $categories, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<p><label for="aips_duplicate_excluded_post_ids"><strong><?php esc_html_e( 'Specific post IDs (comma-separated)', 'ai-post-scheduler' ); ?></strong></label></p>
			<input type="text" name="aips_duplicate_excluded_post_ids" id="aips_duplicate_excluded_post_ids" value="<?php echo esc_attr( $ids ); ?>" class="regular-text" placeholder="12, 48, 301">
			<p>
				<label for="aips_duplicate_exclude_pillars">
					<input type="checkbox" name="aips_duplicate_exclude_pillars" id="aips_duplicate_exclude_pillars" value="1" <?php checked( $pillars, true ); ?>>
					<?php esc_html_e( 'Never review pillar posts you confirmed on Topic Clusters or Silos', 'ai-post-scheduler' ); ?>
				</label>
			</p>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Posts matching any of these are left out of Duplicate Review entirely. Useful for deliberate series, product pages and cornerstone content.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render the default merge handling select (Card 6).
	 *
	 * @return void
	 */
	public function duplicate_default_merge_field_callback() {
		$value   = (string) AIPS_Config::get_instance()->get_option( 'aips_duplicate_default_merge_mode', 'revision' );
		$choices = array(
			'none'     => __( 'Do not change the kept post (redirect and retire only)', 'ai-post-scheduler' ),
			'revision' => __( 'Save the AI-merged draft as a revision of the kept post', 'ai-post-scheduler' ),
			'rewrite'  => __( 'Rewrite the kept post with the AI-merged draft', 'ai-post-scheduler' ),
		);
		?>
		<select name="aips_duplicate_default_merge_mode" id="aips_duplicate_default_merge_mode">
			<?php foreach ( $choices as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'What the Consolidate dialog pre-selects once a merged draft has been generated. You can change it per consolidation, and consolidating can always be undone.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Render the scheduled duplicate scan select (Card 6).
	 *
	 * @return void
	 */
	public function duplicate_scan_schedule_field_callback() {
		$value   = (string) AIPS_Config::get_instance()->get_option( 'aips_duplicate_scan_schedule', 'off' );
		$choices = array(
			'off'    => __( 'Off (review manually)', 'ai-post-scheduler' ),
			'daily'  => __( 'Daily', 'ai-post-scheduler' ),
			'weekly' => __( 'Weekly', 'ai-post-scheduler' ),
		);
		?>
		<select name="aips_duplicate_scan_schedule" id="aips_duplicate_scan_schedule">
			<?php foreach ( $choices as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Re-scan for duplicates on a schedule and send a "Duplicate Posts Found" notification when new groups appear. Choose its channels under Settings → Notifications.', 'ai-post-scheduler' ); ?></p>
		<?php
	}

	/**
	 * Clamp a submitted integer into a range.
	 *
	 * @param mixed $value   Submitted value.
	 * @param int   $min     Lowest allowed value.
	 * @param int   $max     Highest allowed value.
	 * @param int   $default Value used when the input is empty.
	 * @return int
	 */
	public function clamp_int( $value, int $min, int $max, int $default ): int {
		if ( $value === '' || $value === null ) {
			return $default;
		}
		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Sanitize an auto-link confidence threshold (0.50 - 1.00).
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public function sanitize_autolink_threshold( $value ) {
		if ( ! is_numeric( $value ) ) {
			return 0.85;
		}
		return round( min( 1.0, max( 0.5, (float) $value ) ), 2 );
	}

	/**
	 * Sanitize an auto-link count limit (1 - 100).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_autolink_limit( $value ) {
		return min( 100, max( 1, absint( $value ) ) );
	}

	/**
	 * Sanitize the number of posts indexed per link index rebuild batch (10 - 500).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_link_index_batch_size( $value ) {
		return min( 500, max( 10, absint( $value ) ) );
	}

	/**
	 * Sanitize the pause between link index rebuild batches in seconds (0 - 600).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_link_index_batch_delay( $value ) {
		return min( 600, absint( $value ) );
	}

	/**
	 * Sanitize the maximum rule links added to one post (1 - 20).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_link_rules_max_per_post( $value ) {
		return min( 20, max( 1, absint( $value ) ) );
	}

	/**
	 * Sanitize the generation-time linking mode.
	 *
	 * @param mixed $value Raw value.
	 * @return string off|review|apply
	 */
	public function sanitize_publish_linking_mode( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'off', 'review', 'apply' ), true ) ? $value : 'review';
	}

	/**
	 * Sanitize the link click retention period (30–730 days).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_link_click_retention_days( $value ) {
		return min( 730, max( 30, absint( $value ) ) );
	}

	/**
	 * Sanitize where the silo "In this guide" list goes on a pillar.
	 *
	 * @param mixed $value Raw value.
	 * @return string 'end' or 'after_first_paragraph'.
	 */
	public function sanitize_silo_guide_position( $value ) {
		return $value === 'after_first_paragraph' ? 'after_first_paragraph' : 'end';
	}

	/**
	 * Sanitize the number of articles in the silo "In this guide" list.
	 *
	 * @param mixed $value Raw value.
	 * @return int 1-50.
	 */
	public function sanitize_silo_guide_max( $value ) {
		return min( 50, max( 1, absint( $value ) ) );
	}

	/**
	 * Sanitize the silo "In this guide" list style.
	 *
	 * @param mixed $value Raw value.
	 * @return string 'aips' or 'theme'.
	 */
	public function sanitize_silo_guide_style( $value ) {
		return $value === 'theme' ? 'theme' : 'aips';
	}

	/**
	 * Sanitize the rel attribute applied to auto-inserted links.
	 *
	 * @param mixed $value Raw value.
	 * @return string One of '', 'nofollow', 'sponsored', 'ugc'.
	 */
	public function sanitize_autolink_rel( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'nofollow', 'sponsored', 'ugc' ), true ) ? $value : '';
	}

	/**
	 * Sanitize cluster detection max posts.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_cluster_detection_max_posts( $value ) {
		return $this->clamp_int( $value, 50, 5000, 250 );
	}

	/**
	 * Sanitize cluster detection time budget.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_cluster_detection_time_budget( $value ) {
		return $this->clamp_int( $value, 2, 25, 8 );
	}

	/**
	 * Sanitize duplicate review threshold.
	 *
	 * @param mixed $value Submitted value.
	 * @return float
	 */
	public function sanitize_duplicate_review_threshold( $value ) {
		$value = ( $value === '' || $value === null ) ? 0.88 : (float) $value;
		return max( 0.70, min( 0.99, $value ) );
	}

	/**
	 * Sanitize duplicate review max group size.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_duplicate_review_max_group_size( $value ) {
		return $this->clamp_int( $value, 2, 10, 5 );
	}

	/**
	 * Sanitize duplicate review max pairs.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_duplicate_review_max_pairs( $value ) {
		return $this->clamp_int( $value, 100, 5000, 1000 );
	}

	/**
	 * Sanitize duplicate excluded post types.
	 *
	 * @param mixed $value Submitted value.
	 * @return string[]
	 */
	public function sanitize_duplicate_excluded_post_types( $value ) {
		$valid = array_keys( get_post_types( array( 'public' => true ) ) );
		return array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), $valid ) );
	}

	/**
	 * Sanitize duplicate excluded categories.
	 *
	 * @param mixed $value Submitted value.
	 * @return int[]
	 */
	public function sanitize_duplicate_excluded_categories( $value ) {
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
	}

	/**
	 * Keep only digits and commas, normalised to "1, 2, 3".
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_duplicate_excluded_post_ids( $value ) {
		$ids = array_unique( array_filter( array_map( 'absint', preg_split( '/[^0-9]+/', (string) $value ) ) ) );
		return implode( ', ', $ids );
	}

	/**
	 * Sanitize duplicate protect inbound links count.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_duplicate_protect_inbound_links( $value ) {
		return $this->clamp_int( $value, 0, 1000, 10 );
	}

	/**
	 * Sanitize duplicate default merge mode.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_duplicate_default_merge_mode( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'none', 'revision', 'rewrite' ), true ) ? $value : 'revision';
	}

	/**
	 * Sanitize duplicate scan schedule.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_duplicate_scan_schedule( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'off', 'daily', 'weekly' ), true ) ? $value : 'off';
	}

	/**
	 * Sanitize cluster default min size.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_cluster_default_min_size( $value ) {
		return $this->clamp_int( $value, 2, 50, 3 );
	}

	/**
	 * Sanitize cluster tightness.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_cluster_tightness( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'loose', 'balanced', 'strict' ), true ) ? $value : 'balanced';
	}

	/**
	 * Sanitize pillar spoke similarity.
	 *
	 * @param mixed $value Submitted value.
	 * @return float
	 */
	public function sanitize_pillar_spoke_similarity( $value ) {
		$value = ( $value === '' || $value === null ) ? 0.75 : (float) $value;
		return max( 0.30, min( 1.0, $value ) );
	}

	/**
	 * Sanitize audit result limit.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_audit_result_limit( $value ) {
		return $this->clamp_int( $value, 10, 500, 50 );
	}

	/**
	 * Sanitize duplicate keep strategy.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_duplicate_keep_strategy( $value ) {
		$value = sanitize_key( (string) $value );
		return array_key_exists( $value, AIPS_Duplicate_Group_Service::get_keep_strategies() ) ? $value : 'balanced';
	}

	/**
	 * Sanitize indexing scope option.
	 *
	 * @param mixed $value Submitted value.
	 * @return string Sanitized scope ('aips_only', 'date_range', 'all').
	 */
	public function sanitize_embeddings_scope( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'aips_only', 'date_range', 'all' ), true ) ? $value : 'aips_only';
	}

	/**
	 * Sanitize related posts layout option.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('grid', 'list').
	 */
	public function sanitize_related_posts_layout( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'grid', 'list' ), true ) ? $value : 'grid';
	}

	/**
	 * Sanitize deduplication mode option.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('warn', 'block').
	 */
	public function sanitize_deduplication_mode( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'warn', 'block' ), true ) ? $value : 'warn';
	}

	/**
	 * Sanitize post types array.
	 *
	 * @param mixed $value Submitted value.
	 * @return array<string>
	 */
	public function sanitize_post_types( $value ) {
		if ( ! is_array( $value ) ) {
			return array( 'post' );
		}
		$sanitized = array();
		foreach ( $value as $pt ) {
			if ( is_scalar( $pt ) ) {
				$pt = sanitize_key( (string) $pt );
				if ( ! empty( $pt ) ) {
					$sanitized[] = $pt;
				}
			}
		}
		return ! empty( $sanitized ) ? array_values( array_unique( $sanitized ) ) : array( 'post' );
	}

	/**
	 * Sanitize publish execution timing.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('queued', 'immediate', 'disabled').
	 */
	public function sanitize_publish_execution_timing( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'queued', 'immediate', 'disabled' ), true ) ? $value : 'queued';
	}

	/**
	 * Sanitize error pause unit.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('minutes', 'hours', 'days').
	 */
	public function sanitize_error_pause_unit( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'minutes', 'hours', 'days' ), true ) ? $value : 'minutes';
	}
}
