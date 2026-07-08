import BaseModel from './base';

/**
 * Settings Model
 * Manages site-wide configuration settings
 */
export const SettingsModel = BaseModel.extend({
	defaults: {
		// API settings
		api_key: '',
		api_provider: 'meow',
		// Cache settings
		enable_cache_system: false,
		cache_driver: 'memory',
		cache_ttl: 3600,
		// Email settings
		enable_email_notifications: false,
		email_address: '',
		// Other settings
		enable_telemetry: true,
		enable_auto_scheduling: true
	},

	idAttribute: null,

	actionMap: {
		create: 'aips_save_settings',
		read: 'aips_get_settings',
		update: 'aips_save_settings',
		delete: ''
	},

	sync(method, model, options) {
		if (method === 'delete') {
			return false;
		}
		return BaseModel.prototype.sync.apply(this, arguments);
	}
});
