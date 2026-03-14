<?php
/**
 * Audit Subscriber
 *
 * Listens for specific actions within the plugin and creates history log
 * entries for them, providing a detailed audit trail.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Audit_Subscriber {

    /**
     * @var AIPS_History_Service
     */
    private $history_service;

    public function __construct() {
        $this->history_service = new AIPS_History_Service();
        add_action('aips_template_created', array($this, 'handle_template_created'), 10, 1);
        add_action('aips_template_updated', array($this, 'handle_template_updated'), 10, 2);
    }

    /**
     * Handle the creation of a new template.
     *
     * @param array $new_data The data for the new template.
     */
    public function handle_template_created($new_data) {
        $history = $this->history_service->create('template_audit', [
            'template_id' => $new_data['id'],
            'user_id' => get_current_user_id(),
        ]);

        $history->record(
            'activity',
            sprintf(__('Template "%s" was created.', 'ai-post-scheduler'), $new_data['name']),
            $new_data,
            null,
            ['user_id' => get_current_user_id()]
        );
        
        $history->complete_success();
    }

    /**
     * Handle the updating of an existing template.
     *
     * @param array $new_data The new data for the template.
     * @param object $old_data The old template object from the database.
     */
    public function handle_template_updated($new_data, $old_data) {
        $changes = $this->compare_template_data($new_data, (array) $old_data);

        if (empty($changes)) {
            return; // No changes to log
        }
        
        // A template does not have a lifecycle history container like an author,
        // so we create a new one for each audit event.
        $history = $this->history_service->create('template_audit', [
            'template_id' => $old_data->id,
            'user_id' => get_current_user_id(),
        ]);

        $change_log = '';
        foreach ($changes as $change) {
            $change_log .= sprintf(
                __('%s changed from "%s" to "%s".', 'ai-post-scheduler'),
                $change['field'],
                $change['from'],
                $change['to']
            ) . "
";
        }
        
        $history->record(
            'activity',
            sprintf(__('Template "%s" was updated.', 'ai-post-scheduler'), $old_data->name),
            $changes,
            null,
            ['user_id' => get_current_user_id()]
        );

        $history->complete_success(['summary' => $change_log]);
    }

    /**
     * Compare the new and old template data to find what changed.
     *
     * @param array $new The new data.
     * @param array $old The old data.
     * @return array A list of changes.
     */
    private function compare_template_data($new, $old) {
        $changes = [];
        $field_map = [
            'name' => __('Name', 'ai-post-scheduler'),
            'description' => __('Description', 'ai-post-scheduler'),
            'prompt_template' => __('Prompt Template', 'ai-post-scheduler'),
            'title_prompt' => __('Title Prompt', 'ai-post-scheduler'),
            'post_quantity' => __('Post Quantity', 'ai-post-scheduler'),
            'image_prompt' => __('Image Prompt', 'ai-post-scheduler'),
            'generate_featured_image' => __('Generate Featured Image', 'ai-post-scheduler'),
            'featured_image_source' => __('Featured Image Source', 'ai-post-scheduler'),
            'featured_image_unsplash_keywords' => __('Unsplash Keywords', 'ai-post-scheduler'),
            'post_status' => __('Post Status', 'ai-post-scheduler'),
            'post_tags' => __('Tags', 'ai-post-scheduler'),
            'is_active' => __('Is Active', 'ai-post-scheduler'),
        ];

        foreach ($field_map as $key => $label) {
            $old_value = isset($old[$key]) ? $old[$key] : '';
            $new_value = isset($new[$key]) ? $new[$key] : '';

            // Normalize boolean values for comparison
            if ($key === 'generate_featured_image' || $key === 'is_active') {
                $old_value = $old_value ? __('Enabled', 'ai-post-scheduler') : __('Disabled', 'ai-post-scheduler');
                $new_value = $new_value ? __('Enabled', 'ai-post-scheduler') : __('Disabled', 'ai-post-scheduler');
            }

            if ((string)$old_value !== (string)$new_value) {
                $changes[] = [
                    'field' => $label,
                    'from' => (string)$old_value,
                    'to' => (string)$new_value,
                ];
            }
        }
        
        // Special handling for category and author as they are IDs
        if (isset($old['post_category']) && isset($new['post_category']) && $old['post_category'] != $new['post_category']) {
            $old_cat = get_the_category_by_ID($old['post_category']);
            $new_cat = get_the_category_by_ID($new['post_category']);
            $changes[] = [
                'field' => __('Category', 'ai-post-scheduler'),
                'from' => $old_cat ?: __('None', 'ai-post-scheduler'),
                'to' => $new_cat ?: __('None', 'ai-post-scheduler'),
            ];
        }

        if (isset($old['post_author']) && isset($new['post_author']) && $old['post_author'] != $new['post_author']) {
            $old_author = get_the_author_meta('display_name', $old['post_author']);
            $new_author = get_the_author_meta('display_name', $new['post_author']);
            $changes[] = [
                'field' => __('Author', 'ai-post-scheduler'),
                'from' => $old_author ?: __('None', 'ai-post-scheduler'),
                'to' => $new_author ?: __('None', 'ai-post-scheduler'),
            ];
        }

        return $changes;
    }
}
