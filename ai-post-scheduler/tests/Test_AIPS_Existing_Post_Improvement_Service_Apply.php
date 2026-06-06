<?php
/**
 * Tests apply logic for existing-post suggestions.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Existing_Post_Improvement_Service_Apply extends WP_UnitTestCase {

/** @var AIPS_Existing_Post_Improvement_Repository */
private $repository;
/** @var AIPS_Existing_Post_Improvement_Service */
private $service;
private $post_id = 0;
private $schedule_id = 0;

public function setUp(): void {
parent::setUp();
$this->repository = new AIPS_Existing_Post_Improvement_Repository();
$this->service = new AIPS_Existing_Post_Improvement_Service($this->repository);

$this->post_id = self::factory()->post->create(array(
'post_title' => 'Old Title',
'post_content' => 'Initial content body.',
'post_excerpt' => 'Old excerpt',
'post_status' => 'publish',
));

$this->schedule_id = $this->repository->create_schedule(array(
'title' => 'Apply Test Schedule',
'frequency' => 'daily',
'next_run' => time() + HOUR_IN_SECONDS,
));
}

public function tearDown(): void {
if ($this->post_id) {
wp_delete_post($this->post_id, true);
}
if ($this->schedule_id) {
$this->repository->delete_schedule($this->schedule_id);
}
parent::tearDown();
}

public function test_apply_items_updates_post_title_and_marks_item_applied() {
$run_id = $this->repository->create_run($this->schedule_id, 'running', 'manual');
$suggestion_id = $this->repository->create_suggestion(array(
'post_id' => $this->post_id,
'run_id' => $run_id,
'schedule_id' => $this->schedule_id,
'content_hash' => 'hash',
'freshness_marker' => gmdate('Y-m-d'),
));

$item_id = $this->repository->add_suggestion_item(array(
'suggestion_id' => $suggestion_id,
'run_id' => $run_id,
'post_id' => $this->post_id,
'component' => 'title',
'item_type' => 'rewrite',
'original_value' => 'Old Title',
'suggested_value' => 'New Better Title',
'rationale' => 'More descriptive',
'confidence' => 0.95,
));

$result = $this->service->apply_items($suggestion_id, array($item_id), 1);
$this->assertIsArray($result);
$this->assertSame(1, (int) $result['applied']);

$updated = get_post($this->post_id);
$this->assertSame('New Better Title', $updated->post_title);

$items = $this->repository->get_items_by_ids(array($item_id));
$this->assertNotEmpty($items);
$this->assertSame('applied', $items[0]->status);
}
}
