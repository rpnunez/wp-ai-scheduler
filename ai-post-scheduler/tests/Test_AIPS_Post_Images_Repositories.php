<?php
/**
 * Tests for Post Images Repositories
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Images_Repositories extends WP_UnitTestCase {

	private $post_images_repo;
	private $generated_repo;

	public function setUp(): void {
		parent::setUp();
		$this->post_images_repo = new AIPS_Post_Images_Repository();
		$this->generated_repo   = new AIPS_Post_Images_Generated_Repository();
	}

	public function test_post_images_repo_create_and_get() {
		$id = $this->post_images_repo->create(array(
			'entity_type'       => 'template',
			'entity_id'         => 101,
			'image_type'        => 'featured',
			'image_order'       => 0,
			'source'            => 'ai_prompt',
			'image_prompt'      => 'A beautiful sunrise',
			'unsplash_keywords' => '',
			'media_ids'         => '',
			'placement'         => 'auto',
			'caption'           => 'Sunrise',
		));

		$this->assertGreaterThan(0, $id);

		$item = $this->post_images_repo->get_by_id($id);
		$this->assertNotNull($item);
		$this->assertEquals('template', $item->entity_type);
		$this->assertEquals(101, (int) $item->entity_id);
		$this->assertEquals('featured', $item->image_type);
		$this->assertEquals('A beautiful sunrise', $item->image_prompt);

		$featured = $this->post_images_repo->get_featured_for_entity('template', 101);
		$this->assertNotNull($featured);
		$this->assertEquals($id, (int) $featured->id);
	}

	public function test_post_images_repo_sync_for_entity() {
		$items = array(
			array(
				'image_type'        => 'content',
				'image_order'       => 1,
				'source'            => 'ai_prompt',
				'image_prompt'      => 'First image',
				'placement'         => 'after_section_1',
			),
			array(
				'image_type'        => 'content',
				'image_order'       => 2,
				'source'            => 'unsplash',
				'unsplash_keywords' => 'nature, forest',
				'placement'         => 'after_section_2',
			),
		);

		$this->post_images_repo->sync_for_entity('author', 202, $items, 'content');

		$content_images = $this->post_images_repo->get_content_images_for_entity('author', 202);
		$this->assertCount(2, $content_images);
		$this->assertEquals(1, (int) $content_images[0]->image_order);
		$this->assertEquals('First image', $content_images[0]->image_prompt);
		$this->assertEquals('after_section_1', $content_images[0]->placement);
		$this->assertEquals('nature, forest', $content_images[1]->unsplash_keywords);

		// Now sync with 1 image; second should be removed
		$updated_items = array(
			array(
				'id'          => $content_images[0]->id,
				'image_order' => 1,
				'source'      => 'ai_prompt',
				'image_prompt' => 'Updated first image',
				'placement'   => 'auto',
			),
		);

		$this->post_images_repo->sync_for_entity('author', 202, $updated_items, 'content');
		$new_content_images = $this->post_images_repo->get_content_images_for_entity('author', 202);
		$this->assertCount(1, $new_content_images);
		$this->assertEquals('Updated first image', $new_content_images[0]->image_prompt);
		$this->assertEquals('auto', $new_content_images[0]->placement);
	}

	public function test_sync_updates_existing_rows_in_place() {
		$this->post_images_repo->sync_for_entity('template', 303, array(
			array('image_order' => 1, 'image_prompt' => 'Original'),
		), 'content');

		$original = $this->post_images_repo->get_content_images_for_entity('template', 303);

		$this->post_images_repo->sync_for_entity('template', 303, array(
			array('id' => (int) $original[0]->id, 'image_order' => 1, 'image_prompt' => 'Edited'),
		), 'content');

		$updated = $this->post_images_repo->get_content_images_for_entity('template', 303);

		$this->assertCount(1, $updated);
		$this->assertSame((int) $original[0]->id, (int) $updated[0]->id);
		$this->assertSame('Edited', $updated[0]->image_prompt);
	}

	public function test_post_images_repo_clone_for_entity() {
		$this->post_images_repo->create(array(
			'entity_type'  => 'template',
			'entity_id'    => 50,
			'image_type'   => 'featured',
			'image_order'  => 0,
			'source'       => 'ai_prompt',
			'image_prompt' => 'Template featured',
		));

		$this->post_images_repo->create(array(
			'entity_type'  => 'template',
			'entity_id'    => 50,
			'image_type'   => 'content',
			'image_order'  => 1,
			'source'       => 'unsplash',
			'image_prompt' => 'Template content 1',
		));

		$this->post_images_repo->clone_for_entity('template', 50, 51);

		$cloned_featured = $this->post_images_repo->get_featured_for_entity('template', 51);
		$this->assertNotNull($cloned_featured);
		$this->assertEquals('Template featured', $cloned_featured->image_prompt);

		$cloned_content = $this->post_images_repo->get_content_images_for_entity('template', 51);
		$this->assertCount(1, $cloned_content);
		$this->assertEquals('Template content 1', $cloned_content[0]->image_prompt);
	}

	public function test_generated_repo_crud_and_status() {
		$post_id = $this->factory->post->create();

		$gen_id = $this->generated_repo->create(array(
			'post_id'         => $post_id,
			'history_id'      => 999,
			'config_image_id' => 12,
			'image_index'     => 1,
			'attachment_id'   => 456,
			'image_source'    => 'ai_prompt',
			'image_prompt'    => 'Robot writing a book',
			'image_url'       => 'https://example.com/robot.jpg',
			'placement'       => 'after_section_1',
			'caption'         => 'AI at work',
			'status'          => 'completed',
		));

		$this->assertGreaterThan(0, $gen_id);

		$row = $this->generated_repo->get_by_id($gen_id);
		$this->assertNotNull($row);
		$this->assertEquals($post_id, (int) $row->post_id);
		$this->assertEquals('completed', $row->status);
		$this->assertEquals('https://example.com/robot.jpg', $row->image_url);

		$by_post = $this->generated_repo->get_by_post_id($post_id);
		$this->assertCount(1, $by_post);
		$this->assertEquals($gen_id, (int) $by_post[0]->id);

		// Update status
		$this->generated_repo->mark_status($gen_id, 'failed', 'Timeout reaching image provider');
		$updated = $this->generated_repo->get_by_id($gen_id);
		$this->assertEquals('failed', $updated->status);
		$this->assertEquals('Timeout reaching image provider', $updated->error_message);
	}
}
