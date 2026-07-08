import BaseModel from './base';

/**
 * Post Slice Model
 * Represents a reusable content slice
 */
export const PostSliceModel = BaseModel.extend({
	defaults: {
		id: null,
		name: '',
		description: '',
		sort_order: 0,
		is_active: true
	},

	actionMap: {
		create: 'aips_save_post_slice',
		read: 'aips_get_post_slice',
		update: 'aips_save_post_slice',
		delete: 'aips_delete_post_slice'
	}
});
