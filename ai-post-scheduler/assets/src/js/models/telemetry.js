import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Telemetry Model
 */
export const TelemetryModel = BaseModel.extend({
	defaults: {
		id: '',
		type: '',
		page: '',
		event_categories: '',
		request_method: '',
		num_queries: 0,
		elapsed_ms: 0,
		inserted_at: ''
	},

	actionMap: {
		read: 'aips_get_telemetry_details'
	}
});

/**
 * Telemetry Collection
 */
export const TelemetryCollection = Backbone.Collection.extend({
	model: TelemetryModel,
	url: () => (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl
});
