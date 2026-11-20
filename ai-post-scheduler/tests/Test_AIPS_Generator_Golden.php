<?php
/**
 * Golden Regression Test Suite for AIPS_Generator::generate_post
 *
 * Locks down prompts, options, context, and call sequence sent to AI providers.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/helpers/class-aips-fake-ai-provider.php';
require_once __DIR__ . '/helpers/class-aips-golden-helper.php';

class Test_AIPS_Generator_Golden extends WP_UnitTestCase {

    private $fake_provider;
    private $ai_service;
    private $generator;

    public function setUp(): void {
        parent::setUp();

        // Pin non-deterministic factors
        update_option('timezone_string', 'UTC');
        update_option('blogname', 'Golden Test Blog');
        update_option('blogdescription', 'A blog for regression tests');

        add_filter('aips_template_variables', array($this, 'filter_template_variables'));

        $this->fake_provider = new AIPS_Fake_AI_Provider();
        $this->ai_service    = new AIPS_AI_Service(null, null, null, $this->fake_provider);
        $this->generator     = new AIPS_Generator(null, $this->ai_service);
    }

    public function tearDown(): void {
        remove_filter('aips_template_variables', array($this, 'filter_template_variables'));
        parent::tearDown();
    }

    public function filter_template_variables($vars) {
        if (!is_array($vars)) {
            $vars = array();
        }
        $vars['date']             = '2026-01-01';
        $vars['year']             = '2026';
        $vars['month']            = '01';
        $vars['day']              = '01';
        $vars['time']             = '12:00:00';
        $vars['random_number']    = '42';
        $vars['site_name']        = 'Golden Test Blog';
        $vars['site_description'] = 'A blog for regression tests';
        return $vars;
    }

    /**
     * Helper to format captured calls into a snapshot array/string.
     */
    private function format_captured_calls(): string {
        $normalized_calls = AIPS_Golden_Helper::normalize($this->fake_provider->calls);
        return json_encode($normalized_calls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * Test Scenario 1: Standard template context generation pipeline.
     */
    public function test_golden_template_basic() {
        $template = (object) array(
            'id'                     => 1,
            'name'                   => 'Basic Article Template',
            'prompt_template'        => 'Write a comprehensive guide about {{topic}} for {{site_name}}.',
            'title_prompt'           => 'Generate an engaging title for {{topic}}.',
            'excerpt_prompt'         => 'Summarize {{topic}} in 2 sentences.',
            'post_type'              => 'post',
            'post_status'            => 'publish',
            'post_category'          => '1',
            'generate_featured_image'=> 1,
            'featured_image_source'  => 'ai_prompt',
            'image_prompt'           => 'A realistic photo representing {{topic}}.',
            'include_sources'        => 0,
        );

        $context = new AIPS_Template_Context($template, null, 'Artificial Intelligence in Healthcare');

        // Script canned responses
        $this->fake_provider->script_text_response('<h1>Artificial Intelligence in Healthcare</h1><p>AI is transforming medical diagnostics and patient care.</p>'); // content
        $this->fake_provider->script_text_response('"AI in Healthcare: Transforming Diagnostics"'); // title
        $this->fake_provider->script_text_response('AI is revolutionizing healthcare diagnostics and patient treatment protocols.'); // excerpt
        $this->fake_provider->script_image_response('https://example.com/ai-healthcare.jpg'); // image

        $post_id = $this->generator->generate_post($context);

        $this->assertNotWPError($post_id);
        $this->assertGreaterThan(0, $post_id);

        $actual = $this->format_captured_calls();
        $golden_path = __DIR__ . '/golden/generator-pipeline/template_basic.golden.json';

        AIPS_Golden_Helper::assert_golden($golden_path, $actual, $this);
    }

    /**
     * Test Scenario 2: Author Topic context generation pipeline.
     */
    public function test_golden_topic_basic() {
        $author = (object) array(
            'id'                       => 10,
            'name'                     => 'Dr. Jane Smith',
            'field_niche'              => 'Quantum Physics',
            'generate_featured_image'  => 0,
        );

        $topic_data = (object) array(
            'id'                       => 100,
            'topic_title'              => 'Quantum Computing Applications',
            'author_id'                => 10,
        );

        $context = new AIPS_Topic_Context($author, $topic_data);

        $this->fake_provider->script_text_response('<p>Quantum computing utilizes qubits for complex computations.</p>'); // content
        $this->fake_provider->script_text_response('"Quantum Computing Made Simple"'); // title
        $this->fake_provider->script_text_response('An overview of quantum computing and its real-world applications.'); // excerpt

        $post_id = $this->generator->generate_post($context);

        $this->assertNotWPError($post_id);
        $this->assertGreaterThan(0, $post_id);

        $actual = $this->format_captured_calls();
        $golden_path = __DIR__ . '/golden/generator-pipeline/topic_basic.golden.json';

        AIPS_Golden_Helper::assert_golden($golden_path, $actual, $this);
    }

    /**
     * Test Scenario 3: Template context with trusted sources block.
     */
    public function test_golden_template_sources() {
        $template = (object) array(
            'id'                     => 2,
            'name'                   => 'Sources Enabled Template',
            'prompt_template'        => 'Write an analysis on {{topic}} using the reference material.',
            'title_prompt'           => 'Title for {{topic}}',
            'excerpt_prompt'         => 'Excerpt for {{topic}}',
            'post_type'              => 'post',
            'post_status'            => 'draft',
            'post_category'          => '1',
            'generate_featured_image'=> 0,
            'include_sources'        => 1,
            'source_group_ids'       => json_encode(array(5)),
        );

        $context = new AIPS_Template_Context($template, null, 'Renewable Energy Trends');

        $this->fake_provider->script_text_response('<p>Renewable energy sources such as solar and wind continue to expand rapidly.</p>'); // content
        $this->fake_provider->script_text_response('"Renewable Energy Trends 2026"'); // title
        $this->fake_provider->script_text_response('Key trends shaping global renewable energy adoption.'); // excerpt

        $post_id = $this->generator->generate_post($context);

        $this->assertNotWPError($post_id);
        $this->assertGreaterThan(0, $post_id);

        $actual = $this->format_captured_calls();
        $golden_path = __DIR__ . '/golden/generator-pipeline/template_sources.golden.json';

        AIPS_Golden_Helper::assert_golden($golden_path, $actual, $this);
    }
}
