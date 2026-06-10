import BaseModel from './base';

/**
 * Settings Model
 */
export const SettingsModel = BaseModel.extend({
	defaults: {
		settings: {}
	},

	actionMap: {
		update: 'aips_save_settings'
	}
});

export default SettingsModel;
