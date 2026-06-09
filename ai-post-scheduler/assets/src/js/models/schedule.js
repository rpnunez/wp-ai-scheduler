import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Schedule Model
 */
export const ScheduleModel = BaseModel.extend({
	defaults: {
		id: '',
		template_id: '',
		schedule_type: 'hourly',
		interval_value: 1,
		interval_unit: 'hours',
		start_time: '',
		is_active: true
	},

	actionMap: {
		create: 'aips_save_schedule',
		read: 'aips_get_schedule',
		update: 'aips_save_schedule',
		delete: 'aips_delete_schedule'
	},

	parse(resp) {
		if (resp && resp.schedule) {
			return resp.schedule;
		}
		return resp;
	}
});

/**
 * Schedule Collection
 */
export const ScheduleCollection = Backbone.Collection.extend({
	model: ScheduleModel
});
