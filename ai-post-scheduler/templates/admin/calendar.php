<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<h1><?php esc_html_e('Schedule Calendar', 'ai-post-scheduler'); ?></h1>
	
	<div class="aips-calendar-container">
		<!-- Calendar Header with Navigation -->
		<div class="aips-calendar-header">
			<div class="aips-calendar-nav">
				<button class="button aips-calendar-prev" title="<?php esc_attr_e('Previous Month', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
				</button>
				<h2 class="aips-calendar-title">
					<span class="aips-calendar-month-year"></span>
				</h2>
				<button class="button aips-calendar-next" title="<?php esc_attr_e('Next Month', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
			
			<div class="aips-calendar-view-switcher">
				<button class="button aips-calendar-view-btn active" data-view="month">
					<?php esc_html_e('Month', 'ai-post-scheduler'); ?>
				</button>
				<button class="button aips-calendar-view-btn" data-view="week">
					<?php esc_html_e('Week', 'ai-post-scheduler'); ?>
				</button>
				<button class="button aips-calendar-view-btn" data-view="day">
					<?php esc_html_e('Day', 'ai-post-scheduler'); ?>
				</button>
			</div>
			
			<div class="aips-calendar-today">
				<button class="button button-secondary aips-calendar-today-btn">
					<?php esc_html_e('Today', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
		
		<!-- Legend -->
		<div class="aips-calendar-legend">
			<h3><?php esc_html_e('Color Coding', 'ai-post-scheduler'); ?></h3>
			<div class="aips-calendar-legend-items">
				<div class="aips-calendar-legend-item">
					<span class="aips-calendar-legend-color" style="background-color: #2271b1;"></span>
					<span><?php esc_html_e('Template 1', 'ai-post-scheduler'); ?></span>
				</div>
				<div class="aips-calendar-legend-item">
					<span class="aips-calendar-legend-color" style="background-color: #d63638;"></span>
					<span><?php esc_html_e('Template 2', 'ai-post-scheduler'); ?></span>
				</div>
				<div class="aips-calendar-legend-item">
					<span class="aips-calendar-legend-color" style="background-color: #00a32a;"></span>
					<span><?php esc_html_e('Template 3', 'ai-post-scheduler'); ?></span>
				</div>
				<div class="aips-calendar-legend-item">
					<span class="aips-calendar-legend-color" style="background-color: #dba617;"></span>
					<span><?php esc_html_e('Other Templates', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
		</div>
		
		<!-- Loading Indicator -->
		<div class="aips-calendar-loading" style="display: none;">
			<span class="spinner is-active"></span>
			<p><?php esc_html_e('Loading calendar...', 'ai-post-scheduler'); ?></p>
		</div>
		
		<!-- Calendar Grid -->
		<div class="aips-calendar-grid" data-view="month">
			<!-- Day headers -->
			<div class="aips-calendar-day-headers">
				<div class="aips-calendar-day-header"><?php esc_html_e('Sun', 'ai-post-scheduler'); ?></div>
				<div class="aips-calendar-day-header"><?php esc_html_e('Mon', 'ai-post-scheduler'); ?></div>
				<div class="aips-calendar-day-header"><?php esc_html_e('Tue', 'ai-post-scheduler'); ?></div>
				<div class="aips-calendar-day-header"><?php esc_html_e('Wed', 'ai-post-scheduler'); ?></div>
				<div class="aips-calendar-day-header"><?php esc_html_e('Thu', 'ai-post-scheduler'); ?></div>
				<div class="aips-calendar-day-header"><?php esc_html_e('Fri', 'ai-post-scheduler'); ?></div>
				<div class="aips-calendar-day-header"><?php esc_html_e('Sat', 'ai-post-scheduler'); ?></div>
			</div>
			
			<!-- Days grid (populated by JavaScript) -->
			<div class="aips-calendar-days"></div>
		</div>
		
		<!-- Week View (hidden by default) -->
		<div class="aips-calendar-week-view" style="display: none;">
			<div class="aips-calendar-week-grid"></div>
		</div>
		
		<!-- Day View (hidden by default) -->
		<div class="aips-calendar-day-view" style="display: none;">
			<div class="aips-calendar-day-grid"></div>
		</div>
	</div>
	
	<!-- Event Details Modal -->
	<div id="aips-calendar-event-modal" class="aips-calendar-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="aips-calendar-event-modal-title">
		<div class="aips-calendar-modal-overlay"></div>
		<div class="aips-calendar-modal-content">
			<div class="aips-calendar-modal-header">
				<h2 id="aips-calendar-event-modal-title"><?php esc_html_e('Schedule Details', 'ai-post-scheduler'); ?></h2>
				<button type="button" class="aips-calendar-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<div class="aips-calendar-modal-body">
				<div class="aips-calendar-event-detail">
					<p><strong><?php esc_html_e('Template:', 'ai-post-scheduler'); ?></strong> <span class="aips-event-template"></span></p>
					<p><strong><?php esc_html_e('Scheduled Time:', 'ai-post-scheduler'); ?></strong> <span class="aips-event-time"></span></p>
					<p><strong><?php esc_html_e('Frequency:', 'ai-post-scheduler'); ?></strong> <span class="aips-event-frequency"></span></p>
					<p><strong><?php esc_html_e('Topic:', 'ai-post-scheduler'); ?></strong> <span class="aips-event-topic"></span></p>
					<p><strong><?php esc_html_e('Category:', 'ai-post-scheduler'); ?></strong> <span class="aips-event-category"></span></p>
					<p><strong><?php esc_html_e('Author:', 'ai-post-scheduler'); ?></strong> <span class="aips-event-author"></span></p>
				</div>
			</div>
			<div class="aips-calendar-modal-footer">
				<a href="<?php echo esc_url(admin_url('admin.php?page=aips-schedule')); ?>" class="button button-primary">
					<?php esc_html_e('View All Schedules', 'ai-post-scheduler'); ?>
				</a>
				<button type="button" class="button aips-calendar-modal-close">
					<?php esc_html_e('Close', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>
</div>
