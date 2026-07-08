import BaseModel from './base';

/**
 * System Status Model
 * Manages system status operations (read-only state)
 */
export const SystemStatusModel = BaseModel.extend({
	defaults: {
		status: 'idle' // idle, running, completed
	},

	idAttribute: null,

	sync() {
		// System status is read-only, operations are triggered via AJAX
		return false;
	}
});
