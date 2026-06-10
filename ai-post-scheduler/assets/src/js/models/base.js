import Backbone from 'backbone';
import $ from 'jquery';

/**
 * Base WordPress Model
 *
 * Implements Backbone.sync customized for WordPress admin-ajax.php
 */
const BaseModel = Backbone.Model.extend({
	actionMap: {
		create: '',
		read: '',
		update: '',
		delete: ''
	},

	sync(method, model, options) {
		options = options || {};

		const action = this.actionMap[method];
		if (!action) {
			// Fallback to standard sync if no action is mapped
			return Backbone.sync.apply(this, arguments);
		}

		// Ensure we always post to the WP AJAX url
		options.url = (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl;
		options.type = 'POST';

		// Package parameters
		const requestData = Object.assign(
			{
				action: action,
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
			},
			model.toJSON()
		);

		// Override request data
		options.data = requestData;

		// Standard Backbone success/error wrapper handling
		const success = options.success;
		options.success = function(resp) {
			if (resp && resp.success) {
				if (success) {
					// Pass the underlying data payload to success
					success(resp.data);
				}
			} else {
				if (options.error) {
					options.error(resp || { message: 'Request failed' });
				}
			}
		};

		return $.ajax(options);
	}
});

export default BaseModel;
