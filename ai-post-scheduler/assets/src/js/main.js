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

// Initialize global namespace
window.AIPS = window.AIPS || {};
const AIPS = window.AIPS;

// Configure Underscore templates to support the existing {{ placeholder }} syntax
_.templateSettings = {
	interpolate: /\{\{([\s\S]+?)\}\}/g
};

// Re-expose Underscore template rendering under AIPS.Templates for backward compatibility
AIPS.Templates = {
	render(id, data) {
		const $el = $('#' + id);
		if (!$el.length) return '';
		try {
			const compiled = _.template($el.html());
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

// Bootstrap application on document ready
$(document).ready(() => {
	// Initialize core Backbone views
	AIPS.templatesView = new TemplatesView();
	AIPS.schedulesView = new SchedulesView();
	AIPS.authorsView = new AuthorsView();

	// Call any legacy bootstrap hooks
	if (typeof AIPS.init === 'function') {
		AIPS.init();
	}
});
