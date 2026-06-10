import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Post Slice Model
 */
export const PostSliceModel = BaseModel.extend({
	defaults: {
		id: 0,
		name: '',
		description: '',
		sort_order: 0,
		is_active: 1
	},

	actionMap: {
		create: 'aips_save_post_slice',
		update: 'aips_save_post_slice',
		delete: 'aips_delete_post_slice'
	},

	toJSON() {
		const data = BaseModel.prototype.toJSON.apply(this, arguments);
		data.slice_id = data.id;
		return data;
	}
});

/**
 * Post Slice Collection
 */
export const PostSliceCollection = Backbone.Collection.extend({
	model: PostSliceModel,
	url: () => (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl
});

export default PostSliceModel;
