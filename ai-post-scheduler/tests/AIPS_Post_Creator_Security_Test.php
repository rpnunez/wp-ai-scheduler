<?php
/**
 * Security Tests for AIPS_Post_Creator.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

/**
 * Validate that generated posts are sanitized before creation.
 */
class AIPS_Post_Creator_Security_Test extends WP_UnitTestCase {

    /**
     * Reset shared storage between tests.
     */
    public function setUp(): void {
        parent::setUp();
        global $aips_test_posts;
        $aips_test_posts = array();
    }

    /**
     * Ensure content and title are sanitized to prevent Stored XSS.
     */
    public function test_sanitizes_content_and_title() {
        global $aips_test_posts;

        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        $malicious_title = 'My Title <script>alert(1)</script>';
        $malicious_content = 'My Content <script>alert("xss")</script><iframe src="malicious"></iframe>';
        $malicious_excerpt = 'My Excerpt <script>alert("xss")</script>';

        $post_id = $creator->create_post(array(
            'title' => $malicious_title,
            'content' => $malicious_content,
            'excerpt' => $malicious_excerpt,
            'template' => $template,
        ));

        $this->assertArrayHasKey($post_id, $aips_test_posts);
        $post = $aips_test_posts[$post_id];

        // Check Title Sanitization (sanitize_text_field strips tags)
        $this->assertEquals('My Title alert(1)', $post['post_title'], 'Title should be sanitized via sanitize_text_field');

        // Check Content Sanitization (wp_kses_post mock strips unknown tags)
        // The mock in bootstrap.php allows <a><strong><em><p><br><ul><ol><li>
        // It strips <script> and <iframe>.
        $this->assertStringNotContainsString('<script>', $post['post_content'], 'Content should not contain script tags');
        $this->assertStringNotContainsString('<iframe>', $post['post_content'], 'Content should not contain iframe tags');

        // Check Excerpt Sanitization
        $this->assertStringNotContainsString('<script>', $post['post_excerpt'], 'Excerpt should not contain script tags');
    }
}
