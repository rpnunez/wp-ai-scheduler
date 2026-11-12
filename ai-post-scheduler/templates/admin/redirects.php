<?php
/**
 * Redirects Admin Tab Partial (Content hub)
 *
 * Redirects created by AI Post Scheduler, the provider serving them, and a
 * form to add one by hand. Data loads over AJAX (assets/js/admin-redirects.js).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 *
 * @var array[] $providers        Providers (key, label, available, active, count).
 * @var string  $provider_setting 'auto' or a provider key.
 * @var string  $active_provider  Label of the provider new redirects go to.
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-redirects-tab aips-link-report-tab" id="aips-redirects">

	<?php
	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-redirects-provider-panel',
			'title'       => __('Redirect Provider', 'ai-post-scheduler'),
			'icon'        => 'dashicons-randomize',
			'description' => __('AI Post Scheduler keeps its own record of every redirect it creates, and hands each one to a redirect plugin when one is installed: Redirection, Yoast SEO Premium or Rank Math. Without one, AI Post Scheduler serves the redirects itself. If you switch plugins later, move the existing redirects with one click.', 'ai-post-scheduler'),
		),
		function () use ($providers, $provider_setting, $active_provider) {
			?>
			<ul class="aips-redirects-providers" id="aips-redirects-providers">
				<?php foreach ($providers as $provider) : ?>
					<li>
						<strong><?php echo esc_html($provider['label']); ?></strong>
						<?php if ($provider['active']) : ?>
							<span class="aips-badge aips-badge-success"><?php esc_html_e('In use', 'ai-post-scheduler'); ?></span>
						<?php elseif ($provider['available']) : ?>
							<span class="aips-badge aips-badge-info"><?php esc_html_e('Available', 'ai-post-scheduler'); ?></span>
						<?php else : ?>
							<span class="aips-badge aips-badge-neutral"><?php esc_html_e('Not installed', 'ai-post-scheduler'); ?></span>
						<?php endif; ?>
						<span class="aips-text-muted">
							<?php
							/* translators: %d: number of redirects */
							echo esc_html(sprintf(_n('%d redirect', '%d redirects', (int) $provider['count'], 'ai-post-scheduler'), (int) $provider['count']));
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="aips-redirects-provider-form">
				<label for="aips-redirects-provider"><strong><?php esc_html_e('Serve new redirects with', 'ai-post-scheduler'); ?></strong></label>
				<select id="aips-redirects-provider">
					<option value="auto" <?php selected($provider_setting, 'auto'); ?>><?php esc_html_e('Automatic (first installed redirect plugin, else built in)', 'ai-post-scheduler'); ?></option>
					<?php foreach ($providers as $provider) : ?>
						<option value="<?php echo esc_attr($provider['key']); ?>" <?php selected($provider_setting, $provider['key']); ?> <?php disabled(!$provider['available']); ?>><?php echo esc_html($provider['label']); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-redirects-provider-save"><?php esc_html_e('Save', 'ai-post-scheduler'); ?></button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary" id="aips-redirects-provider-move"><?php esc_html_e('Save and Move Existing Redirects', 'ai-post-scheduler'); ?></button>
			</div>
			<p class="description">
				<?php
				/* translators: %s: provider name */
				echo esc_html(sprintf(__('Currently in use: %s.', 'ai-post-scheduler'), $active_provider));
				?>
			</p>
			<?php
		}
	);

	AIPS_Admin_UI_Primitives::render_card(
		array(
			'id'          => 'aips-redirects-panel',
			'title'       => __('Redirects Created by AI Post Scheduler', 'ai-post-scheduler'),
			'icon'        => 'dashicons-admin-links',
			'description' => __('Redirects added here or by AI Post Scheduler features (such as consolidating overlapping posts). Redirects you created directly in another plugin are not listed.', 'ai-post-scheduler'),
			'body_class'  => 'no-padding',
		),
		function () {
			?>
			<form class="aips-link-rules-form" id="aips-redirect-form">
				<div class="aips-link-rules-field">
					<label for="aips-redirect-source"><?php esc_html_e('Old URL or path', 'ai-post-scheduler'); ?></label>
					<input type="text" id="aips-redirect-source" class="aips-form-input" placeholder="/old-post/" required>
				</div>
				<div class="aips-link-rules-field aips-link-rules-target">
					<label for="aips-redirect-target"><?php esc_html_e('Redirect to (search a post or paste a URL)', 'ai-post-scheduler'); ?></label>
					<input type="text" id="aips-redirect-target" class="aips-form-input" autocomplete="off" placeholder="<?php esc_attr_e('Post title or https://…', 'ai-post-scheduler'); ?>">
					<input type="hidden" id="aips-redirect-target-id" value="">
					<ul class="aips-link-rules-results" id="aips-redirect-results"></ul>
				</div>
				<div class="aips-link-rules-field">
					<label for="aips-redirect-code"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></label>
					<select id="aips-redirect-code">
						<option value="301"><?php esc_html_e('301 Permanent', 'ai-post-scheduler'); ?></option>
						<option value="302"><?php esc_html_e('302 Temporary', 'ai-post-scheduler'); ?></option>
						<option value="307"><?php esc_html_e('307 Temporary (keep method)', 'ai-post-scheduler'); ?></option>
						<option value="308"><?php esc_html_e('308 Permanent (keep method)', 'ai-post-scheduler'); ?></option>
						<option value="410"><?php esc_html_e('410 Gone (no target)', 'ai-post-scheduler'); ?></option>
					</select>
				</div>
				<div class="aips-link-rules-field aips-link-rules-submit">
					<button type="submit" class="aips-btn aips-btn-primary" id="aips-redirect-save"><?php esc_html_e('Add Redirect', 'ai-post-scheduler'); ?></button>
				</div>
			</form>

			<div class="aips-redirects-toolbar">
				<input type="search" id="aips-redirects-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search URLs…', 'ai-post-scheduler'); ?>">
				<select id="aips-redirects-origin">
					<option value=""><?php esc_html_e('All sources', 'ai-post-scheduler'); ?></option>
					<option value="manual"><?php esc_html_e('Added by hand', 'ai-post-scheduler'); ?></option>
					<option value="consolidation"><?php esc_html_e('Consolidation', 'ai-post-scheduler'); ?></option>
				</select>
			</div>

			<table class="aips-table widefat striped" id="aips-redirects-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Old URL', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Redirects To', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Served By', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Hits', 'ai-post-scheduler'); ?></th>
						<th scope="col"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody id="aips-redirects-tbody"></tbody>
			</table>

			<div class="aips-pagination">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-redirects-prev" disabled><?php esc_html_e('Previous', 'ai-post-scheduler'); ?></button>
				<span class="aips-pagination-info" id="aips-redirects-page-info"></span>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-redirects-next" disabled><?php esc_html_e('Next', 'ai-post-scheduler'); ?></button>
			</div>
			<p class="description aips-link-rules-note"><?php esc_html_e('Hits are counted for redirects AI Post Scheduler serves itself; other plugins keep their own hit counts.', 'ai-post-scheduler'); ?></p>
			<?php
		}
	);
	?>

	<script type="text/html" id="aips-tmpl-redirect-row">
		<tr>
			<td><a href="{{source_url}}" target="_blank" rel="noopener"><code>{{source_path}}</code></a> <span class="aips-text-muted">{{origin_label}}</span></td>
			<td><a href="{{target_url}}" target="_blank" rel="noopener">{{target_label}}</a></td>
			<td>{{status_code}}</td>
			<td>{{provider_label}} <span class="aips-badge aips-badge-warning {{error_class}}" title="{{provider_error}}"><?php esc_html_e('Fallback', 'ai-post-scheduler'); ?></span></td>
			<td>{{hits}}</td>
			<td><span class="aips-badge {{status_class}}">{{status_label}}</span></td>
			<td class="column-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-redirect-toggle" data-id="{{id}}" data-enabled="{{toggle_to}}">{{toggle_label}}</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-redirect-delete" data-id="{{id}}"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
			</td>
		</tr>
	</script>

	<script type="text/html" id="aips-tmpl-redirect-result">
		<li><button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-redirect-pick" data-id="{{id}}" data-title="{{title}}">{{title}}</button></li>
	</script>

	<script type="text/html" id="aips-tmpl-redirect-empty">
		<tr><td colspan="7" class="aips-text-muted">{{message}}</td></tr>
	</script>
</div>
