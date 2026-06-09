import BaseModel from './base';
import Backbone from 'backbone';

/**
 * Campaign Model
 */
export const CampaignModel = BaseModel.extend({
	defaults: {
		id: '',
		campaign_name: '',
		content_goal: '',
		post_type: 'post',
		prompt_template: '',
		title_prompt: '',
		frequency: 'daily',
		is_active: 1,
		post_status: 'draft',
		review_policy: 'manual'
	},

	actionMap: {
		create: 'aips_campaign_wizard_finalize',
		read: 'aips_get_campaign',
		update: 'aips_campaign_wizard_save_draft',
		delete: 'aips_delete_campaign'
	}
});

/**
 * Campaign Collection
 */
export const CampaignCollection = Backbone.Collection.extend({
	model: CampaignModel,
	url: () => (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl
});
