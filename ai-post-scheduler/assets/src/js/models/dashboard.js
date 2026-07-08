import BaseModel from './base';

/**
 * Dashboard Model
 * Manages dashboard chart data (read-only)
 */
export const DashboardModel = BaseModel.extend({
	defaults: {
		labels: [],
		completed: [],
		failed: [],
		topics: [],
		errorRate: []
	},

	idAttribute: null,

	sync() {
		// Dashboard data is embedded in the page, not fetched via AJAX
		return false;
	}
});
