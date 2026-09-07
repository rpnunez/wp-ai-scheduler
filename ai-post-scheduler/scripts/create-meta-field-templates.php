<?php
/**
 * Create two AIPS Templates wired to native WordPress custom (meta) fields.
 *
 * These are real, runnable Templates that exercise the third-party plugin
 * bridge with the built-in "WordPress Custom Fields" (native_meta) integration —
 * no ACF or any other plugin required. Each Template maps several custom fields
 * with per-field prompts, so generating from it produces a normal post AND
 * populates those custom fields through the Integration engine.
 *
 * Run it from the plugin root with WP-CLI:
 *
 *     wp eval-file ai-post-scheduler/scripts/create-meta-field-templates.php
 *
 * Re-running is safe: a Template whose name already exists is left untouched
 * and its mappings are re-synced rather than duplicated.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "This script must run inside WordPress. Use: wp eval-file " . basename(__FILE__) . "\n");
    exit(1);
}

if (!class_exists('AIPS_Template_Repository') || !class_exists('AIPS_Integration_Mappings_Repository')) {
    fwrite(STDERR, "AI Post Scheduler is not loaded. Activate the plugin first.\n");
    exit(1);
}

/**
 * Emit a line to stdout (WP-CLI captures this).
 *
 * @param string $line Message.
 * @return void
 */
$aips_out = static function ($line) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log($line);
    } else {
        echo $line . "\n";
    }
};

/**
 * The two Templates and their custom-field mappings. field_type uses the
 * native_meta adapter's "freeform" shapes: freeform_short_text (single line),
 * freeform_long_text (paragraph), freeform_html (rich text).
 */
$aips_specs = array(
    array(
        'name'            => 'Product Review (Custom Fields Demo)',
        'post_type'       => 'post',
        'prompt_template' => 'Write a balanced, practical product review of roughly 350 words about {{topic}}. Use two <h2> sections: one on strengths and one on trade-offs.',
        'title_prompt'    => 'Write a clear, specific product-review title. No colons, no clickbait.',
        'fields'          => array(
            array('key' => 'demo_rating',   'label' => 'Editor Rating',      'type' => 'freeform_short_text', 'prompt' => 'Give a single overall rating from 1 to 5 (one number only, e.g. "4").'),
            array('key' => 'demo_pros',     'label' => 'Pros',               'type' => 'freeform_long_text',  'prompt' => 'List the three biggest strengths as a short comma-separated phrase.'),
            array('key' => 'demo_cons',     'label' => 'Cons',               'type' => 'freeform_long_text',  'prompt' => 'List the two biggest drawbacks as a short comma-separated phrase.'),
            array('key' => 'demo_verdict',  'label' => 'One-line Verdict',   'type' => 'freeform_short_text', 'prompt' => 'Write a single punchy verdict sentence (max 15 words).'),
        ),
    ),
    array(
        'name'            => 'How-To Guide (Custom Fields Demo)',
        'post_type'       => 'post',
        'prompt_template' => 'Write a clear how-to guide of roughly 400 words about {{topic}}. Use numbered steps inside the body.',
        'title_prompt'    => 'Write an actionable how-to title starting with a verb.',
        'fields'          => array(
            array('key' => 'demo_summary',     'label' => 'Summary',        'type' => 'freeform_long_text',  'prompt' => 'Write a 1–2 sentence summary of what the reader will achieve.'),
            array('key' => 'demo_difficulty',  'label' => 'Difficulty',     'type' => 'freeform_short_text', 'prompt' => 'State the difficulty as one word: Beginner, Intermediate, or Advanced.'),
            array('key' => 'demo_time',        'label' => 'Time Required',  'type' => 'freeform_short_text', 'prompt' => 'State an approximate time to complete, e.g. "15 minutes".'),
            array('key' => 'demo_keywords',    'label' => 'Keywords',       'type' => 'freeform_short_text', 'prompt' => 'List three comma-separated keywords.'),
        ),
    ),
);

$template_repo = new AIPS_Template_Repository();
$mapping_repo  = new AIPS_Integration_Mappings_Repository();

foreach ($aips_specs as $spec) {
    // Reuse an existing Template of the same name rather than duplicating it.
    $existing_id = 0;

    if (method_exists($template_repo, 'name_exists') && $template_repo->name_exists($spec['name'])) {
        foreach ($template_repo->get_all(false) as $tpl) {
            if (isset($tpl->name) && $tpl->name === $spec['name']) {
                $existing_id = (int) $tpl->id;
                break;
            }
        }
    }

    if ($existing_id) {
        $template_id = $existing_id;
        $aips_out(sprintf('• Template "%s" already exists (#%d) — re-syncing its field mappings.', $spec['name'], $template_id));
    } else {
        $template_id = $template_repo->create(array(
            'name'            => $spec['name'],
            'prompt_template' => $spec['prompt_template'],
            'title_prompt'    => $spec['title_prompt'],
            'post_status'     => 'draft',
            'post_type'       => $spec['post_type'],
            'is_active'       => 1,
        ));

        if (!$template_id) {
            $aips_out(sprintf('✗ Failed to create Template "%s" — skipping.', $spec['name']));
            continue;
        }

        $aips_out(sprintf('✓ Created Template "%s" (#%d).', $spec['name'], $template_id));
    }

    foreach ($spec['fields'] as $field) {
        $mapping_id = $mapping_repo->save_mapping(array(
            'template_id'    => $template_id,
            'integration_id' => 'native_meta',
            'source_key'     => $spec['post_type'], // native meta groups by post type
            'field_key'      => $field['key'],
            'field_label'    => $field['label'],
            'field_type'     => $field['type'],
            'custom_prompt'  => $field['prompt'],
            'is_active'      => true,
        ));

        if ($mapping_id) {
            $aips_out(sprintf('    ↳ mapped custom field "%s" (%s).', $field['key'], $field['type']));
        } else {
            $aips_out(sprintf('    ↳ FAILED to map custom field "%s".', $field['key']));
        }
    }
}

$aips_out('');
$aips_out('Done. Open Templates in wp-admin, run "Generate" on either Template, then check the');
$aips_out('generated post\'s Custom Fields (and the History entry) to confirm the mapped meta was populated.');
