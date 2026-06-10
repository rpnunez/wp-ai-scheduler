import _ from 'underscore';
import $ from 'jquery';
import Backbone from 'backbone';
import mediator from './utils/mediator';
import '../css/main.css';

// Import all legacy scripts to bundle them
import '../../js/datetime.js';
import '../../js/utilities.js';
import '../../js/templates.js';
import '../../js/admin-bar.js';
import '../../js/admin-dashboard.js';
import '../../js/admin-db.js';
import '../../js/admin-dev-tools.js';
import '../../js/admin-embeddings.js';
import '../../js/admin-generated-posts.js';
import '../../js/admin-history.js';
import '../../js/admin-internal-links.js';
import '../../js/admin-planner.js';
import '../../js/admin-post-review.js';
import '../../js/admin-post-slices.js';
import '../../js/admin-research.js';
import '../../js/admin-seeder.js';
import '../../js/admin-settings.js';
import '../../js/admin-sources.js';
import '../../js/admin-system-status.js';
import '../../js/admin-view-session.js';
import '../../js/ai-assistance.js';
import '../../js/cache-monitor.js';
import '../../js/calendar.js';
import '../../js/campaign-wizard.js';
import '../../js/campaigns.js';
import '../../js/onboarding.js';
import '../../js/taxonomy.js';
import '../../js/telemetry.js';
import '../../js/admin.js';
import '../../js/authors.js';

import { TemplatesView } from './views/templates';
import { SchedulesView } from './views/schedules';
import { AuthorsView } from './views/authors';
import { HistoryView } from './views/history';
import { ViewSessionModalView } from './views/session-modal';
import { PlannerView } from './views/planner';
import { CalendarView } from './views/calendar';
import { ResearchView } from './views/research';
import { InternalLinksView } from './views/internal-links';
import { SourcesView } from './views/sources';
import { TelemetryView } from './views/telemetry';
import { CampaignsView } from './views/campaigns';
import { SettingsView } from './views/settings';
import { PostSlicesView } from './views/post-slices';
import { SystemStatusView } from './views/system-status';
import { OnboardingView } from './views/onboarding';
import { DevToolsView } from './views/dev-tools';
import { EmbeddingsView } from './views/embeddings';
import { PostReviewView } from './views/post-review';
import { AdminBarView } from './views/admin-bar';
import { BlockEditorView } from './views/block-editor';

// Initialize global namespace
window.AIPS = window.AIPS || {};
const AIPS = window.AIPS;

// Configure Underscore templates to support the existing {{ placeholder }} syntax
// Re-expose Underscore template rendering under AIPS.Templates for backward compatibility
AIPS.Templates = { 
	render(id, data) {
		const $el = $('#' + id);
		if (!$el.length) return '';
		try {
			const compiled = _.template($el.html(), {
				interpolate: /\{\{([\s\S]+?)\}\}/g
			});
			return compiled(data || {});
		} catch (e) {
			console.error('Template render error for ID: ' + id, e);
			return '';
		}
	},
	renderRaw(id, data) {
		return this.render(id, data);
	},
	escape(str) {
		return _.escape(str);
	}
};

// Expose mediator globally
AIPS.mediator = mediator;

// Mount View Classes on the namespace for diagnostics/extensibility
AIPS.TemplatesViewClass = TemplatesView;
AIPS.SchedulesViewClass = SchedulesView;
AIPS.AuthorsViewClass = AuthorsView;
AIPS.HistoryViewClass = HistoryView;
AIPS.ViewSessionModalViewClass = ViewSessionModalView;
AIPS.PlannerViewClass = PlannerView;
AIPS.CalendarViewClass = CalendarView;
AIPS.ResearchViewClass = ResearchView;
AIPS.InternalLinksViewClass = InternalLinksView;
AIPS.SourcesViewClass = SourcesView;
AIPS.TelemetryViewClass = TelemetryView;
AIPS.CampaignsViewClass = CampaignsView;
AIPS.SettingsViewClass = SettingsView;
AIPS.PostSlicesViewClass = PostSlicesView;
AIPS.SystemStatusViewClass = SystemStatusView;
AIPS.OnboardingViewClass = OnboardingView;
AIPS.DevToolsViewClass = DevToolsView;
AIPS.EmbeddingsViewClass = EmbeddingsView;
AIPS.PostReviewViewClass = PostReviewView;
AIPS.AdminBarViewClass = AdminBarView;
AIPS.BlockEditorViewClass = BlockEditorView;

// Bootstrap application on document ready
$(document).ready(() => {
	// Initialize core Backbone views
	AIPS.templatesView = new TemplatesView();
	AIPS.schedulesView = new SchedulesView();
	AIPS.authorsView = new AuthorsView();

	// Unconditionally instantiate session modal view (attaches global listeners)
	AIPS.sessionModalView = new ViewSessionModalView();

	// Conditionally instantiate history view based on element presence
	if ($('#aips-history-search-input').length || $('#aips-history-logs-modal').length) {
		AIPS.historyView = new HistoryView();
	}

	// Conditionally instantiate Planner view
	if ($('#planner-niche').length || $('#topics-list').length) {
		AIPS.plannerView = new PlannerView();
	}

	// Conditionally instantiate Calendar view
	if ($('.aips-calendar-container').length) {
		AIPS.calendarView = new CalendarView();
	}

	// Conditionally instantiate Research view
	if ($('#aips-research-form').length || $('#topics-container').length) {
		AIPS.researchView = new ResearchView();
	}

	// Conditionally instantiate Internal Links view
	if ($('#aips-suggestions-tbody').length) {
		AIPS.internalLinksView = new InternalLinksView();
	}

	// Conditionally instantiate Sources view
	if ($('#aips-sources-table').length) {
		AIPS.sourcesView = new SourcesView();
	}

	// Conditionally instantiate Telemetry view
	if ($('#aips-telemetry-panel').length) {
		AIPS.telemetryView = new TelemetryView();
	}

	// Conditionally instantiate Campaigns view
	if ($('.aips-campaigns-table').length || $('.aips-admin-page').length || $('#aips-campaign-wizard-form').length) {
		AIPS.campaignsView = new CampaignsView();
	}

	// Conditionally instantiate Settings view
	if ($('#aips-settings-form').length) {
		AIPS.settingsView = new SettingsView();
	}

	// Conditionally instantiate Post Slices view
	if ($('#aips-post-slices-table').length || $('#aips-post-slice-modal').length) {
		AIPS.postSlicesView = new PostSlicesView();
	}

	// Conditionally instantiate System Status view
	if ($('.aips-status-data-panel').length) {
		AIPS.systemStatusView = new SystemStatusView();
	}

	// Conditionally instantiate Onboarding view
	if ($('#aips-onboarding-strategy-form').length || $('.aips-onboarding-container').length) {
		AIPS.onboardingView = new OnboardingView();
	}

	// Conditionally instantiate Dev Tools / Cache Monitor view
	if ($('#aips-dev-scaffold-form').length || $('#aips-seeder-form').length || $('#aips-cache-entries-tbody').length) {
		AIPS.devToolsView = new DevToolsView();
	}

	// Embeddings view
	AIPS.embeddingsView = new EmbeddingsView();

	// Conditionally instantiate Post Review view
	if ($('.aips-post-review-table').length || $('#aips-ai-edit-modal').length || $('#aips-post-preview-modal').length) {
		AIPS.postReviewView = new PostReviewView();
	}

	// Admin Bar view
	if ($('#wpadminbar').length) {
		AIPS.adminBarView = new AdminBarView();
	}

	// Block Editor view
	AIPS.blockEditorView = new BlockEditorView();

	// Call any legacy bootstrap hooks
	if (typeof AIPS.init === 'function') {
		AIPS.init();
	}
});
