<?php
/**
 * Tests for AIPS_Post_Creator content cleaning functionality.
 *
 * Validates that AI-generated content is properly cleaned of markdown artifacts,
 * thinking text, and other formatting issues before being saved to WordPress.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

/**
 * Test content cleaning in AIPS_Post_Creator.
 */
class AIPS_Post_Creator_Content_Cleaning_Test extends WP_UnitTestCase {

    /**
     * Test that "Let's create..." preamble text is removed.
     */
    public function test_removes_lets_create_preamble() {
        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        // Content with "Let's create" preamble
        $content_with_preamble = "Let's create a beginner-friendly guide to CSS Grid Layout for web developers.\n\n<h2>Introduction</h2>\n<p>CSS Grid is a powerful layout system.</p>";

        $post_id = $creator->create_post(array(
            'title' => 'CSS Grid Guide',
            'content' => $content_with_preamble,
            'excerpt' => 'Learn CSS Grid',
            'template' => $template,
        ));

        $this->assertIsInt($post_id);
        
        $post = get_post($post_id);
        // The "Let's create..." line should be removed
        $this->assertStringNotContainsString("Let's create", $post->post_content);
        $this->assertStringContainsString("<h2>Introduction</h2>", $post->post_content);
    }

    /**
     * Test that markdown code fences with HTML content are unwrapped.
     */
    public function test_unwraps_markdown_code_fences_with_html() {
        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        // Content wrapped in markdown code fence
        $content_with_fence = "```html\n<h2>Mastering CSS Grid</h2>\n<p>Grid layout is essential for modern web development.</p>\n```";

        $post_id = $creator->create_post(array(
            'title' => 'CSS Grid Mastery',
            'content' => $content_with_fence,
            'excerpt' => 'Master CSS Grid',
            'template' => $template,
        ));

        $this->assertIsInt($post_id);
        
        $post = get_post($post_id);
        // The code fence markers should be removed
        $this->assertStringNotContainsString("```", $post->post_content);
        // But the HTML content should remain
        $this->assertStringContainsString("<h2>Mastering CSS Grid</h2>", $post->post_content);
        $this->assertStringContainsString("<p>Grid layout is essential", $post->post_content);
    }

    /**
     * Test that markdown code fences with actual code are converted to HTML entities.
     */
    public function test_converts_markdown_code_fences_with_code() {
        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        // Content with actual code sample in markdown fence
        $content_with_code = "<p>Here's a JavaScript example:</p>\n```javascript\nfunction hello() {\n  console.log('Hello');\n}\n```";

        $post_id = $creator->create_post(array(
            'title' => 'JavaScript Tutorial',
            'content' => $content_with_code,
            'excerpt' => 'Learn JavaScript',
            'template' => $template,
        ));

        $this->assertIsInt($post_id);
        
        $post = get_post($post_id);
        // The code fence markers should be removed
        $this->assertStringNotContainsString("```", $post->post_content);
        // The code should be wrapped in <pre><code>
        $this->assertStringContainsString("<pre><code>", $post->post_content);
        $this->assertStringContainsString("</code></pre>", $post->post_content);
    }

    /**
     * Test that horizontal rules (---) are removed.
     */
    public function test_removes_horizontal_rules() {
        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        // Content with horizontal rule separator
        $content_with_hr = "Let's create a guide.\n\n---\n\n<h2>Main Content</h2>\n<p>This is the article body.</p>";

        $post_id = $creator->create_post(array(
            'title' => 'Programming Guide',
            'content' => $content_with_hr,
            'excerpt' => 'A guide to programming',
            'template' => $template,
        ));

        $this->assertIsInt($post_id);
        
        $post = get_post($post_id);
        // The horizontal rule should be removed
        $this->assertStringNotContainsString("---", $post->post_content);
        // But content should remain
        $this->assertStringContainsString("<h2>Main Content</h2>", $post->post_content);
    }

    /**
     * Test complex real-world scenario with multiple artifacts.
     */
    public function test_handles_complex_real_world_content() {
        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        // Real-world example from the issue
        $complex_content = "Let's craft a beginner-friendly guide to **CSS Grid Layout** for web developers.\n\n---\n\n```html\n<h2>Mastering CSS Grid Layout: A Beginner's Guide</h2>\n<p>As web developers, we're constantly looking for more efficient ways to structure our web pages.</p>\n```\n\n<h2>Understanding the Basics</h2>\n<p>CSS Grid provides a two-dimensional layout system.</p>";

        $post_id = $creator->create_post(array(
            'title' => 'CSS Grid for Beginners',
            'content' => $complex_content,
            'excerpt' => 'Learn CSS Grid basics',
            'template' => $template,
        ));

        $this->assertIsInt($post_id);
        
        $post = get_post($post_id);
        // All artifacts should be removed
        $this->assertStringNotContainsString("Let's craft", $post->post_content);
        $this->assertStringNotContainsString("```", $post->post_content);
        $this->assertStringNotContainsString("---", $post->post_content);
        // Content should remain intact
        $this->assertStringContainsString("Mastering CSS Grid Layout", $post->post_content);
        $this->assertStringContainsString("<h2>Understanding the Basics</h2>", $post->post_content);
    }

    /**
     * Test that wp_kses_post is applied after cleaning.
     *
     * Ensures that XSS prevention happens after cleaning markdown artifacts.
     */
    public function test_sanitizes_after_cleaning() {
        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        // Content with potential XSS in markdown fence
        $content_with_xss = "```html\n<h2>Title</h2>\n<script>alert('XSS')</script>\n<p>Content</p>\n```";

        $post_id = $creator->create_post(array(
            'title' => 'Security Test',
            'content' => $content_with_xss,
            'excerpt' => 'Security testing',
            'template' => $template,
        ));

        $this->assertIsInt($post_id);
        
        $post = get_post($post_id);
        // Script tags should be stripped by wp_kses_post
        $this->assertStringNotContainsString("<script>", $post->post_content);
        $this->assertStringNotContainsString("alert", $post->post_content);
        // Safe content should remain
        $this->assertStringContainsString("<h2>Title</h2>", $post->post_content);
        $this->assertStringContainsString("<p>Content</p>", $post->post_content);
    }

    /**
     * Test that excerpt cleaning works the same as content cleaning.
     */
    public function test_cleans_excerpt_content() {
        $template = (object) array(
            'post_status' => 'draft',
            'post_author' => 1,
            'post_tags' => '',
        );

        $creator = new AIPS_Post_Creator();

        $excerpt_with_markdown = "Let's summarize this article.\n\n```\nThis is a summary.\n```";

        $post_id = $creator->create_post(array(
            'title' => 'Article with Excerpt',
            'content' => '<p>Main content here.</p>',
            'excerpt' => $excerpt_with_markdown,
            'template' => $template,
        ));

        $this->assertIsInt($post_id);
        
        $post = get_post($post_id);
        // Excerpt should be cleaned
        $this->assertStringNotContainsString("Let's", $post->post_excerpt);
        $this->assertStringNotContainsString("```", $post->post_excerpt);
    }
}
