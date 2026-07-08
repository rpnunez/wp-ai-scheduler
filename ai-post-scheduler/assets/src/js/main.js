import _ from 'underscore';
import $ from 'jquery';
import Backbone from 'backbone';
import mediator from './utils/mediator';
import '../css/main.css';

// Import utility scripts (still legacy)
import '../../js/datetime.js';
import '../../js/utilities.js';
import '../../js/templates.js';

// Import remaining legacy admin scripts (to be ported)
import '../../js/admin-bar.js';
import '../../js/admin-db.js';
import '../../js/admin-dev-tools.js';
import '../../js/admin-embeddings.js';
import '../../js/admin-generated-posts.js';
import '../../js/admin-post-review.js';
import '../../js/admin-post-slices.js';
import '../../js/admin-seeder.js';
import '../../js/admin-system-status.js';
import '../../js/ai-assistance.js';
import '../../js/cache-monitor.js';
import '../../js/onboarding.js';
import '../../js/taxonomy.js';

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
import { CampaignsView } from './views/campaigns';
import { TelemetryView } from './views/telemetry';
import { StructuresView } from './views/structures';
import { VoicesView } from './views/voices';
import { SectionsView } from './views/sections';
import { SettingsView } from './views/settings';
import { DashboardView } from './views/dashboard';
import { PostSlicesView } from './views/post-slices';
import { SystemStatusView } from './views/system-status';

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
AIPS.CampaignsViewClass = CampaignsView;
AIPS.TelemetryViewClass = TelemetryView;
AIPS.StructuresViewClass = StructuresView;
AIPS.VoicesViewClass = VoicesView;
AIPS.SectionsViewClass = SectionsView;
AIPS.SettingsViewClass = SettingsView;
AIPS.DashboardViewClass = DashboardView;
AIPS.PostSlicesViewClass = PostSlicesView;
AIPS.SystemStatusViewClass = SystemStatusView;

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

	// Conditionally instantiate Campaigns view
	if ($('#aips-campaigns-table').length || $('#aips-campaign-wizard').length) {
		AIPS.campaignsView = new CampaignsView();
	}

	// Conditionally instantiate Telemetry view
	if ($('#aips-telemetry-container').length || $('.aips-telemetry-chart').length) {
		AIPS.telemetryView = new TelemetryView();
	}

	// Conditionally instantiate Structures view
	if ($('#aips-structures-list').length || $('#aips-structures-modal').length) {
		AIPS.structuresView = new StructuresView();
	}

	// Conditionally instantiate Voices view
	if ($('#aips-voices-list').length || $('#aips-voices-modal').length) {
		AIPS.voicesView = new VoicesView();
	}

	// Conditionally instantiate Sections view
	if ($('#aips-sections-list').length || $('#aips-sections-modal').length) {
		AIPS.sectionsView = new SectionsView();
	}

	// Conditionally instantiate Settings view
	if ($('#aips-settings-form').length || $('#aips-settings-tab-nav').length) {
		AIPS.settingsView = new SettingsView();
	}

	// Conditionally instantiate Dashboard view
	if ($('#aips-dashboard-panel').length) {
		AIPS.dashboardView = new DashboardView();
	}

	// Conditionally instantiate Post Slices view
	if ($('#aips-post-slices-table').length || $('#aips-post-slice-modal').length) {
		AIPS.postSlicesView = new PostSlicesView();
	}

	// Conditionally instantiate System Status view
	if ($('.aips-status-data-panel').length) {
		AIPS.systemStatusView = new SystemStatusView();
	}

	// Call any legacy bootstrap hooks
	if (typeof AIPS.init === 'function') {
		AIPS.init();
	}
});
