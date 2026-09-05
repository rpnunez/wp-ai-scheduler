<?php
class Test_AIPS_Post_Feedback_Scope_Persistence extends WP_UnitTestCase {
	public function test_json_scope_config_round_trips_through_template_dto() {
		$row = (object) array('id'=>1,'name'=>'T','description'=>'','prompt_template'=>'P','title_prompt'=>'','voice_id'=>null,'post_quantity'=>1,'image_prompt'=>'','generate_featured_image'=>0,'featured_image_source'=>'ai_prompt','featured_image_unsplash_keywords'=>'','featured_image_media_ids'=>'','post_status'=>'draft','post_category'=>null,'post_tags'=>'','post_author'=>1,'include_sources'=>0,'source_group_ids'=>'','is_active'=>1,'created_at'=>'','updated_at'=>'','feedback_enabled'=>0,'feedback_config'=>'{"like_weight":2}');
		$template = AIPS_Template_Data::from_row($row);
		$this->assertSame(0, $template->feedback_enabled);
		$this->assertSame(array('like_weight' => 2), $template->feedback_config);
	}
}
