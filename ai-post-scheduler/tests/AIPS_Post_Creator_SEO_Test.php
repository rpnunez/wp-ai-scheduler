<?php
/**
 * Tests for AIPS_Post_Creator SEO metadata handling.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

/**
 * Validate that generated posts receive SEO metadata for Yoast and RankMath.
 */
class AIPS_Post_Creator_SEO_Test extends WP_UnitTestCase {

    /**
     * Reset shared meta storage between tests.
     */
    public function setUp(): void {
        parent::setUp();
        global $aips_test_meta;
        $aips_test_meta = array();
    }

    /**
     * Ensure SEO metadata is skipped when neither Yoast nor RankMath are present.
     */
    public function test_skips_meta_when_no_seo_plugins() {
        global $aips_test_meta;

        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Manager();

        $post_id = $creator->create_post(array(
            'title' => 'AI SEO Title',
            'content' => 'Generated content body.',
            'excerpt' => 'Meta description value here.',
            'template' => $template,
            'focus_keyword' => 'Primary Keyword',
            'seo_title' => 'Custom SEO Title',
        ));

        $this->assertSame(array(), $aips_test_meta);
        $this->assertIsInt($post_id);
        $this->assertSame('1', $this->get_stored_post_meta($post_id, AIPS_Post_Manager::META_GENERATED_POST));
    }

    /**
     * Ensure explicit SEO inputs populate Yoast and RankMath fields when plugins are active.
     */
    public function test_sets_focus_keyword_and_meta_description() {
        $this->activate_seo_plugins();

        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Manager();

        $post_id = $creator->create_post(array(
            'title' => 'AI SEO Title',
            'content' => 'Generated content body.',
            'excerpt' => 'Meta description value here.',
            'template' => $template,
            'focus_keyword' => 'Primary Keyword',
            'seo_title' => 'Custom SEO Title',
        ));

        $this->assertSame('1', $this->get_stored_post_meta($post_id, AIPS_Post_Manager::META_GENERATED_POST));
        $this->assertSame('Primary Keyword', $this->get_stored_post_meta($post_id, '_yoast_wpseo_focuskw'));
        $this->assertSame('Primary Keyword', $this->get_stored_post_meta($post_id, 'rank_math_focus_keyword'));
        $this->assertSame('Meta description value here.', $this->get_stored_post_meta($post_id, '_yoast_wpseo_metadesc'));
        $this->assertSame('Custom SEO Title', $this->get_stored_post_meta($post_id, 'rank_math_title'));
    }

    /**
     * Ensure sensible defaults populate SEO meta when optional fields are omitted and plugins are active.
     */
    public function test_defaults_focus_keyword_and_description_when_missing() {
        $this->activate_seo_plugins();

        $template = (object) array(
            'post_status' => 'publish',
            'post_author' => 2,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Manager();

        $post_id = $creator->create_post(array(
            'title' => 'Title Used As Keyword',
            'content' => 'Another generated body with more details for description.',
            'excerpt' => '',
            'template' => $template,
        ));

        $this->assertSame('1', $this->get_stored_post_meta($post_id, AIPS_Post_Manager::META_GENERATED_POST));
        $this->assertSame('Title Used As Keyword', $this->get_stored_post_meta($post_id, '_yoast_wpseo_focuskw'));
        $this->assertSame('Title Used As Keyword', $this->get_stored_post_meta($post_id, '_yoast_wpseo_title'));
        $this->assertSame('Another generated body with more details for description.', $this->get_stored_post_meta($post_id, '_yoast_wpseo_metadesc'));
        $this->assertSame('Another generated body with more details for description.', $this->get_stored_post_meta($post_id, 'rank_math_description'));
    }

    /**
     * Ensure generation status metadata is stored for partial generations.
     */
    public function test_stores_partial_generation_meta_statuses() {
        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Manager();

        $post_id = $creator->create_post(array(
            'title' => 'AI SEO Title',
            'content' => 'Generated content body.',
            'excerpt' => 'Generated excerpt body.',
            'template' => $template,
            'generation_incomplete' => true,
            'component_statuses' => array(
                'post_title' => true,
                'post_excerpt' => true,
                'featured_image' => false,
                'post_content' => true,
            ),
        ));

        $this->assertSame('1', $this->get_stored_post_meta($post_id, AIPS_Post_Manager::META_GENERATED_POST));
        $this->assertSame('true', $this->get_stored_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE));

        $decoded_statuses = json_decode($this->get_stored_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES), true);
        $this->assertSame(
            array(
                'post_title' => true,
                'post_excerpt' => true,
                'featured_image' => false,
                'post_content' => true,
            ),
            $decoded_statuses
        );

        $this->assertSame('true', $this->get_stored_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL));
    }

    /**
     * Ensure historical partial flag remains true after a post is fully resolved.
     */
    public function test_historical_partial_flag_is_sticky_after_resolution() {
        $creator = new AIPS_Post_Manager();
        $post_id = $this->factory->post->create();

        $creator->update_generation_status_meta(
            $post_id,
            array(
                'post_title' => false,
                'post_excerpt' => true,
                'featured_image' => true,
                'post_content' => true,
            ),
            true
        );

        $creator->update_generation_status_meta(
            $post_id,
            array(
                'post_title' => true,
                'post_excerpt' => true,
                'featured_image' => true,
                'post_content' => true,
            ),
            false
        );

        $this->assertSame('false', $this->get_stored_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE));
        $this->assertSame('true', $this->get_stored_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL));
    }

    /**
     * Activate SEO plugins for tests that rely on plugin-specific meta fields.
     *
     * @return void
     */
    private function activate_seo_plugins() {
        if (!defined('WPSEO_VERSION')) {
            define('WPSEO_VERSION', 'test');
        }

        if (!defined('RANK_MATH_VERSION')) {
            define('RANK_MATH_VERSION', 'test');
        }
    }

    private function get_stored_post_meta($post_id, $meta_key) {
        $value = get_post_meta($post_id, $meta_key, true);
        if ($value !== '' || metadata_exists('post', $post_id, $meta_key)) {
            return $value;
        }

        global $aips_test_meta;
        if (isset($aips_test_meta[$post_id]) && array_key_exists($meta_key, $aips_test_meta[$post_id])) {
            return $aips_test_meta[$post_id][$meta_key];
        }

        return '';
    }
}
