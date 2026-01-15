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

        $creator = new AIPS_Post_Creator();

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
    }

    /**
     * Ensure explicit SEO inputs populate Yoast and RankMath fields when plugins are active.
     */
    public function test_sets_focus_keyword_and_meta_description() {
        global $aips_test_meta;

        $this->activate_seo_plugins();

        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        $post_id = $creator->create_post(array(
            'title' => 'AI SEO Title',
            'content' => 'Generated content body.',
            'excerpt' => 'Meta description value here.',
            'template' => $template,
            'focus_keyword' => 'Primary Keyword',
            'seo_title' => 'Custom SEO Title',
        ));

        $this->assertArrayHasKey($post_id, $aips_test_meta);
        $this->assertSame('Primary Keyword', $aips_test_meta[$post_id]['_yoast_wpseo_focuskw']);
        $this->assertSame('Primary Keyword', $aips_test_meta[$post_id]['rank_math_focus_keyword']);
        $this->assertSame('Meta description value here.', $aips_test_meta[$post_id]['_yoast_wpseo_metadesc']);
        $this->assertSame('Custom SEO Title', $aips_test_meta[$post_id]['rank_math_title']);
    }

    /**
     * Ensure sensible defaults populate SEO meta when optional fields are omitted and plugins are active.
     */
    public function test_defaults_focus_keyword_and_description_when_missing() {
        global $aips_test_meta;

        $this->activate_seo_plugins();

        $template = (object) array(
            'post_status' => 'publish',
            'post_author' => 2,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        $post_id = $creator->create_post(array(
            'title' => 'Title Used As Keyword',
            'content' => 'Another generated body with more details for description.',
            'excerpt' => '',
            'template' => $template,
        ));

        $this->assertSame('Title Used As Keyword', $aips_test_meta[$post_id]['_yoast_wpseo_focuskw']);
        $this->assertSame('Title Used As Keyword', $aips_test_meta[$post_id]['_yoast_wpseo_title']);
        $this->assertSame('Another generated body with more details for description.', $aips_test_meta[$post_id]['_yoast_wpseo_metadesc']);
        $this->assertSame('Another generated body with more details for description.', $aips_test_meta[$post_id]['rank_math_description']);
    }

    /**
     * Ensure post title is sanitized from AI-generated content (defense-in-depth).
     */
    public function test_sanitizes_post_title_from_ai_output() {
        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        // Title with potential XSS payload should be sanitized
        $post_id = $creator->create_post(array(
            'title' => 'Normal Title<script>alert("XSS")</script>',
            'content' => 'Content with <strong>formatting</strong>',
            'excerpt' => 'Short excerpt',
            'template' => $template,
        ));

        $this->assertIsInt($post_id);
        $post = get_post($post_id);
        
        // sanitize_text_field strips HTML tags and scripts
        $this->assertSame('Normal Titlealert("XSS")', $post->post_title);
        $this->assertStringNotContainsString('<script>', $post->post_title);
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
}
