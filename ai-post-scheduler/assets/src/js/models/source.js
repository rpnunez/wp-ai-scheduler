import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Trusted Source Model
 */
export const SourceModel = BaseModel.extend({
	defaults: {
		id: 0,
		url: '',
		label: '',
		description: '',
		term_ids: [],
		fetch_interval: '',
		is_active: 1
	},

	actionMap: {
		create: 'aips_save_source',
		update: 'aips_save_source',
		delete: 'aips_delete_source'
	},

	sync(method, model, options) {
		options = options || {};
		if (method === 'create' || method === 'update') {
			const data = model.toJSON();
			data.source_id = data.id || 0;
			delete data.id;
			
			// Map parameters for Backbone request
			options.data = Object.assign({
				action: this.actionMap[method],
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
			}, data);
			
			options.url = (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl;
			options.type = 'POST';

			const success = options.success;
			options.success = function(resp) {
				if (resp && resp.success) {
					if (success) success(resp.data);
				} else {
					if (options.error) options.error(resp || { message: 'Save failed' });
				}
			};
			return $.ajax(options);
		} else if (method === 'delete') {
			options.data = {
				action: this.actionMap.delete,
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				source_id: this.id
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
		return BaseModel.prototype.sync.call(this, method, model, options);
	}
});

/**
 * Trusted Source Collection
 */
export const SourceCollection = Backbone.Collection.extend({
	model: SourceModel,
	url: () => (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl
});
