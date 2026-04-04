<?php
/**
 * Tests for image-only recoverable partial generation detection.
 *
 * Covers:
 *  - AIPS_Post_Manager::update_generation_status_meta() setting the
 *    aips_post_generation_image_recoverable flag correctly.
 *  - Recoverable flag cleared when image component is resolved.
 *  - Flag is NOT set for non-image partial failures.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
define('ABSPATH', dirname(__DIR__) . '/');
}

/**
 * Class Test_AIPS_Image_Recoverable
 */
class Test_AIPS_Image_Recoverable extends WP_UnitTestCase {

/**
 * Reset shared meta storage before each test.
 *
 * @return void
 */
public function setUp(): void {
parent::setUp();
global $aips_test_meta;
$aips_test_meta = array();
}

// -------------------------------------------------------------------------
// update_generation_status_meta – recoverable detection
// -------------------------------------------------------------------------

/**
 * When content/title succeed but featured image fails, mark as recoverable.
 */
public function test_image_only_failure_is_marked_recoverable() {
global $aips_test_meta;

$manager = new AIPS_Post_Manager();
$manager->update_generation_status_meta(
100,
array(
'post_title'     => true,
'post_excerpt'   => true,
'post_content'   => true,
'featured_image' => false,
),
true
);

$this->assertSame( 'true', $aips_test_meta[100]['aips_post_generation_image_recoverable'] );
}

/**
 * When featured image succeeds, the recoverable flag must be false.
 */
public function test_full_success_is_not_recoverable() {
global $aips_test_meta;

$manager = new AIPS_Post_Manager();
$manager->update_generation_status_meta(
101,
array(
'post_title'     => true,
'post_excerpt'   => true,
'post_content'   => true,
'featured_image' => true,
),
false
);

$this->assertSame( 'false', $aips_test_meta[101]['aips_post_generation_image_recoverable'] );
}

/**
 * When content fails AND image fails, the failure is not image-only; do not
 * mark as recoverable.
 */
public function test_content_failure_is_not_recoverable() {
global $aips_test_meta;

$manager = new AIPS_Post_Manager();
$manager->update_generation_status_meta(
102,
array(
'post_title'     => false,
'post_excerpt'   => true,
'post_content'   => false,
'featured_image' => false,
),
true
);

$this->assertSame( 'false', $aips_test_meta[102]['aips_post_generation_image_recoverable'] );
}

/**
 * When only title fails (not image), the failure is not image-only; do not
 * mark as recoverable.
 */
public function test_title_failure_is_not_recoverable() {
global $aips_test_meta;

$manager = new AIPS_Post_Manager();
$manager->update_generation_status_meta(
103,
array(
'post_title'     => false,
'post_excerpt'   => true,
'post_content'   => true,
'featured_image' => false,
),
true
);

$this->assertSame( 'false', $aips_test_meta[103]['aips_post_generation_image_recoverable'] );
}

/**
 * Calling update again with a resolved image clears the recoverable flag.
 */
public function test_recoverable_flag_cleared_when_image_resolved() {
global $aips_test_meta;

$manager  = new AIPS_Post_Manager();
$post_id  = 104;

// First call – image fails.
$manager->update_generation_status_meta(
$post_id,
array(
'post_title'     => true,
'post_excerpt'   => true,
'post_content'   => true,
'featured_image' => false,
),
true
);

$this->assertSame( 'true', $aips_test_meta[$post_id]['aips_post_generation_image_recoverable'] );

// Second call – image now resolved.
$manager->update_generation_status_meta(
$post_id,
array(
'post_title'     => true,
'post_excerpt'   => true,
'post_content'   => true,
'featured_image' => true,
),
false
);

$this->assertSame( 'false', $aips_test_meta[$post_id]['aips_post_generation_image_recoverable'] );
}

/**
 * Excerpt failure alone (image succeeded) is not an image-recoverable state.
 */
public function test_excerpt_only_failure_is_not_recoverable() {
global $aips_test_meta;

$manager = new AIPS_Post_Manager();
$manager->update_generation_status_meta(
105,
array(
'post_title'     => true,
'post_excerpt'   => false,
'post_content'   => true,
'featured_image' => true,
),
true
);

$this->assertSame( 'false', $aips_test_meta[105]['aips_post_generation_image_recoverable'] );
}

/**
 * When component_statuses is null, the recoverable meta must not be written.
 */
public function test_no_component_statuses_skips_recoverable_flag() {
global $aips_test_meta;

$manager = new AIPS_Post_Manager();
$manager->update_generation_status_meta( 106, null, false );

$this->assertArrayNotHasKey( 'aips_post_generation_image_recoverable', $aips_test_meta[106] ?? array() );
}
}
