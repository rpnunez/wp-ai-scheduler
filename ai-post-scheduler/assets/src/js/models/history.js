import BaseModel from './base';
import Backbone from 'backbone';

/**
 * History Model
 */
export const HistoryModel = BaseModel.extend({
	defaults: {
		id: '',
		template_id: '',
		campaign_id: '',
		event_type: '',
		event_status: '',
		timestamp: ''
	},

	actionMap: {
		delete: 'aips_bulk_delete_history'
	},

	// Override destroy to wrap the ID in an array for aips_bulk_delete_history compatibility
	destroy(options) {
		options = options || {};
		options.data = {
			action: this.actionMap.delete,
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
			ids: [this.id]
		};
		options.type = 'POST';
		options.url = (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl;
		
		const success = options.success;
		options.success = function(resp) {
			if (resp && resp.success) {
				if (success) success(resp.data);
			} else {
				if (options.error) options.error(resp || { message: 'Delete failed' });
			}
		};

		return Backbone.sync.call(this, 'delete', this, options);
	}
});

/**
 * History Collection
 */
export const HistoryCollection = Backbone.Collection.extend({
	model: HistoryModel,
	url: () => (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl
});
