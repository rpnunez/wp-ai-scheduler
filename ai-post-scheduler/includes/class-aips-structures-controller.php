<?php
/**
 * Structures Controller
 *
 * Handles AJAX requests for Article Structures, Pattern Archetypes,
 * Component associations, Live Testing modal, and default restorations.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Structures_Controller {

    private $repo;

    public function __construct($repo = null) {
        $this->repo = $repo ?: new AIPS_Article_Structure_Repository();

        add_action('wp_ajax_aips_get_structures', array($this, 'ajax_get_structures'));
        add_action('wp_ajax_aips_get_structure', array($this, 'ajax_get_structure'));
        add_action('wp_ajax_aips_save_structure', array($this, 'ajax_save_structure'));
        add_action('wp_ajax_aips_delete_structure', array($this, 'ajax_delete_structure'));
        add_action('wp_ajax_aips_toggle_structure_active', array($this, 'ajax_toggle_structure_active'));
        add_action('wp_ajax_aips_clone_structure', array($this, 'ajax_clone_structure'));
        add_action('wp_ajax_aips_get_components', array($this, 'ajax_get_components'));
        add_action('wp_ajax_aips_test_structure', array($this, 'ajax_test_structure'));
        add_action('wp_ajax_aips_restore_default_structures', array($this, 'ajax_restore_default_structures'));
    }

    public function ajax_get_structures() {
        if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $structures = $this->repo->get_all(false);
        AIPS_Ajax_Response::success(array('structures' => $structures));
    }

    public function ajax_get_structure() {
        if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid structure ID.', 'ai-post-scheduler'));
        }

        $manager = new AIPS_Article_Structure_Manager();
        $structure = $manager->get_structure($id);

        if (is_wp_error($structure) || !$structure) {
            AIPS_Ajax_Response::error(__('Structure not found.', 'ai-post-scheduler'));
        }

        AIPS_Ajax_Response::success(array('structure' => $structure));
    }

    public function ajax_save_structure() {
        if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id              = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        $name            = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $description     = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $archetype       = isset($_POST['archetype']) ? sanitize_key(wp_unslash($_POST['archetype'])) : 'custom';
        $editor_mode     = isset($_POST['editor_mode']) ? sanitize_key(wp_unslash($_POST['editor_mode'])) : 'auto';
        $enable_schema   = isset($_POST['enable_schema']) ? 1 : 0;
        $prompt_template = isset($_POST['prompt_template']) ? wp_kses_post(wp_unslash($_POST['prompt_template'])) : '';
        $is_active       = isset($_POST['is_active']) ? 1 : 0;

        $sections = isset($_POST['sections']) && is_array($_POST['sections'])
            ? AIPS_Utilities::sanitize_string_array(wp_unslash($_POST['sections']))
            : array();

        // Relational sections parsing
        $relational_sections = null;

        if (isset($_POST['relational_sections'])) {
            $raw_rel = wp_unslash($_POST['relational_sections']);

            if (is_string($raw_rel)) {
                $raw_rel = json_decode($raw_rel, true);
            }

            if (is_array($raw_rel)) {
                $relational_sections = array();

                foreach ($raw_rel as $idx => $r) {
                    $relational_sections[] = array(
                        'id'                => isset($r['id']) ? absint($r['id']) : 0,
                        'component_id'      => !empty($r['component_id']) ? absint($r['component_id']) : null,
                        'section_key'       => sanitize_key(isset($r['section_key']) ? $r['section_key'] : ('section_' . ($idx + 1))),
                        'heading_text'      => sanitize_text_field(isset($r['heading_text']) ? $r['heading_text'] : ''),
                        'heading_level'     => in_array(isset($r['heading_level']) ? $r['heading_level'] : 'h2', array('h2', 'h3', 'h4', 'none'), true) ? $r['heading_level'] : 'h2',
                        'target_word_count' => isset($r['target_word_count']) ? absint($r['target_word_count']) : 0,
                        'is_required'       => !isset($r['is_required']) || !empty($r['is_required']) ? 1 : 0,
                        'sort_order'        => (int) $idx,
                        'custom_prompt'     => isset($r['custom_prompt']) ? wp_kses_post($r['custom_prompt']) : '',
                        'component_config'  => isset($r['component_config']) ? (is_array($r['component_config']) ? wp_json_encode($r['component_config']) : (string) $r['component_config']) : '{}',
                    );
                }
            }
        }

        if (empty($name)) {
            AIPS_Ajax_Response::error(__('Structure name is required.', 'ai-post-scheduler'));
        }

        // Auto-generate prompt template if empty and relational sections provided
        if (empty($prompt_template) && !empty($relational_sections)) {
            $prompt_template = sprintf("Write a comprehensive article about {{topic}}.");
        }

        $manager = new AIPS_Article_Structure_Manager();

        if ($id) {
            $result = $manager->update_structure(
                $id,
                $name,
                $sections,
                $prompt_template,
                $description,
                $is_active == 1,
                $archetype,
                $editor_mode,
                $enable_schema,
                $relational_sections
            );

            if (is_wp_error($result)) {
                AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
            }

            $structure = $manager->get_structure($id);

            AIPS_Ajax_Response::success(array('message' => __('Structure updated.', 'ai-post-scheduler'), 'structure_id' => $id, 'structure' => $structure));
        } else {
            $new_id = $manager->create_structure(
                $name,
                $sections,
                $prompt_template,
                $description,
                $is_active == 1,
                $archetype,
                $editor_mode,
                $enable_schema,
                $relational_sections
            );

            if (is_wp_error($new_id)) {
                AIPS_Ajax_Response::error(array('message' => $new_id->get_error_message()));
            }

            $structure = $manager->get_structure($new_id);

            AIPS_Ajax_Response::success(array('message' => __('Structure created.', 'ai-post-scheduler'), 'structure_id' => $new_id, 'structure' => $structure));
        }
    }

    public function ajax_clone_structure() {
        if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;

        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid structure ID.', 'ai-post-scheduler'));
        }

        $cloned_id = $this->repo->clone($id);
        
        if (!$cloned_id) {
            AIPS_Ajax_Response::error(__('Failed to clone structure.', 'ai-post-scheduler'));
        }

        $manager = new AIPS_Article_Structure_Manager();
        $cloned_structure = $manager->get_structure($cloned_id);

        AIPS_Ajax_Response::success(array(
            'message'      => __('Structure cloned successfully.', 'ai-post-scheduler'),
            'structure_id' => $cloned_id,
            'structure'    => $cloned_structure,
        ));
    }

    public function ajax_get_components() {
        if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $components = class_exists('AIPS_Component_Repository')
            ? AIPS_Component_Repository::instance()->get_all(true)
            : array();

        AIPS_Ajax_Response::success(array('components' => $components));
    }

    public function ajax_test_structure() {
        if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $structure_id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        $sample_topic = isset($_POST['sample_topic']) && trim($_POST['sample_topic']) !== ''
            ? sanitize_text_field(wp_unslash($_POST['sample_topic']))
            : 'How to Build an Efficient Home Office Workflow';

        $prompt_builder = new AIPS_Prompt_Builder_Article_Structure_Section();
        $assembled_prompt = $prompt_builder->build($structure_id, $sample_topic);

        if (is_wp_error($assembled_prompt)) {
            AIPS_Ajax_Response::error($assembled_prompt->get_error_message());
        }

        // Simulated or AI-generated output for testing
        $manager = new AIPS_Article_Structure_Manager();
        $structure = $manager->get_structure($structure_id);
        $archetype = !empty($structure['archetype']) ? $structure['archetype'] : 'how_to';

        $simulated_output = $this->build_sample_ai_output($sample_topic, $archetype);

        // Parse through delimiter parser
        $parser = new AIPS_Structure_Delimiter_Parser();
        $editor_mode = !empty($structure['editor_mode']) ? $structure['editor_mode'] : 'gutenberg';
        $parse_res = $parser->parse($simulated_output, $editor_mode);

        // Build schema
        $schema_builder = new AIPS_Structure_Schema_Builder();
        $schema_graph = $schema_builder->build_schema($parse_res['extracted_data'], array(
            'title'       => $sample_topic,
            'description' => 'A structured guide about ' . $sample_topic,
            'topic'       => $sample_topic,
            'url'         => home_url('/sample-post'),
        ));

        AIPS_Ajax_Response::success(array(
            'prompt'           => $assembled_prompt,
            'raw_ai_output'    => $simulated_output,
            'visual_preview'   => $parse_res['content'],
            'schema_json_ld'   => $schema_graph,
            'extracted_data'   => $parse_res['extracted_data'],
        ));
    }

    public function ajax_restore_default_structures() {
        if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $restored_components = 0;
        if (class_exists('AIPS_Component_Repository')) {
            $restored_components = AIPS_Component_Repository::instance()->restore_defaults();
        }

        AIPS_DB_Manager::install_tables();

        AIPS_Ajax_Response::success(array(
            'message'             => __('Default archetypes and components restored successfully.', 'ai-post-scheduler'),
            'restored_components' => $restored_components,
        ));
    }

    public function ajax_delete_structure() {
        if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid structure ID.', 'ai-post-scheduler'));
        }

        $manager = new AIPS_Article_Structure_Manager();
        $result = $manager->delete_structure($id);
        if (is_wp_error($result)) {
            AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
        }

        AIPS_Ajax_Response::success(array(), __('Structure deleted.', 'ai-post-scheduler'));
    }

    public function ajax_toggle_structure_active() {
        if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $id = isset($_POST['structure_id']) ? absint($_POST['structure_id']) : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!$id) {
            AIPS_Ajax_Response::error(__('Invalid structure ID.', 'ai-post-scheduler'));
        }

        $result = $this->repo->set_active($id, $is_active);
        if (!$result) {
            AIPS_Ajax_Response::error(__('Failed to update active status.', 'ai-post-scheduler'));
        }

        AIPS_Ajax_Response::success(array(), __('Structure status updated.', 'ai-post-scheduler'));
    }

    /**
     * Build realistic sample AI output with delimiters for testing.
     *
     * @param string $topic Topic string.
     * @param string $archetype Pattern archetype.
     * @return string Markdown output with delimiters.
     */
    private function build_sample_ai_output(string $topic, string $archetype): string {
        $out = "Creating an optimal setup for **{$topic}** requires strategic planning and consistent execution.\n\n";

        if ($archetype === 'review') {
            $out .= "[PROS]\n+ Excellent performance and responsiveness\n+ Clean, ergonomic build quality\n+ Outstanding battery efficiency\n[/PROS]\n\n";
            $out .= "[CONS]\n- Premium pricing compared to previous generations\n- Limited port selection\n[/CONS]\n\n";
            $out .= "[RATING value=\"4.8\" max=\"5\" verdict=\"A stellar choice that delivers unmatched efficiency for professionals.\"]\nOverall, this solution delivers exceptional reliability and power, comfortably justifying the investment.\n[/RATING]\n\n";
        } elseif ($archetype === 'faq') {
            $out .= "[FAQ q=\"What is the primary benefit of {$topic}?\" a=\"It significantly boosts productivity while minimizing daily friction and repetitive tasks.\"]\n";
            $out .= "[FAQ q=\"How long does it take to implement?\" a=\"Most users get fully up and running within 1 to 2 hours of initial configuration.\"]\n";
            $out .= "[FAQ q=\"Is prior technical experience necessary?\" a=\"No, intuitive step-by-step documentation makes it accessible for beginners.\"]\n\n";
        } elseif ($archetype === 'comparison') {
            $out .= "[TABLE]\n| Feature | Option A | Option B |\n| --- | --- | --- |\n| Ease of Setup | 5/5 | 3/5 |\n| Price | \$29/mo | \$49/mo |\n| Speed | Instant | 2-3 sec |\n[/TABLE]\n\n";
            $out .= "[PROS]\n+ Highly competitive pricing\n+ Zero-maintenance setup\n[/PROS]\n\n";
            $out .= "[CONS]\n- Lacks enterprise SAML SSO\n[/CONS]\n\n";
        } else {
            // Default how_to / steps
            $out .= "[CALLOUT type=\"tip\"]Make sure you have administrative privileges and a verified backup before starting this setup.[/CALLOUT]\n\n";
            $out .= "[STEP_LIST]\n";
            $out .= "[STEP title=\"Initial Environment Assessment\"]Examine current workflow bottlenecks and identify areas requiring optimization.[/STEP]\n";
            $out .= "[STEP title=\"Tool Configuration\"]Install and configure the necessary tools with recommended baseline settings.[/STEP]\n";
            $out .= "[STEP title=\"Testing and Verification\"]Run verification benchmarks to confirm everything functions smoothly under load.[/STEP]\n";
            $out .= "[/STEP_LIST]\n\n";
            $out .= "[FAQ q=\"What if a step fails?\" a=\"Review the system logs and verify prerequisite permissions before re-running the setup.\"]\n\n";
        }

        $out .= "### Conclusion\nBy adhering to these structured best practices, you ensure sustainable, high-impact results for {$topic}.";

        return $out;
    }
}
