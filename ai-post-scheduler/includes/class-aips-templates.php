<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Templates {
    
    private $table_name;
    
    /**
     * @var AIPS_Template_Repository Repository for database operations
     */
    private $repository;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_templates';
        $this->repository = new AIPS_Template_Repository();
        
        add_action('wp_ajax_aips_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_aips_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_aips_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_aips_test_template', array($this, 'ajax_test_template'));
        add_action('wp_ajax_aips_get_template_posts', array($this, 'ajax_get_template_posts'));
    }
    
    public function get_all($active_only = false) {
        return $this->repository->get_all($active_only);
    }
    
    public function get($id) {
        return $this->repository->get_by_id($id);
    }
    
    public function save($data) {
        $template_data = array(
            'name' => sanitize_text_field($data['name']),
            'prompt_template' => wp_kses_post($data['prompt_template']),
            'title_prompt' => isset($data['title_prompt']) ? sanitize_text_field($data['title_prompt']) : '',
            'voice_id' => isset($data['voice_id']) ? absint($data['voice_id']) : null,
            'post_quantity' => isset($data['post_quantity']) ? absint($data['post_quantity']) : 1,
            'image_prompt' => isset($data['image_prompt']) ? wp_kses_post($data['image_prompt']) : '',
            'generate_featured_image' => isset($data['generate_featured_image']) ? 1 : 0,
            'post_status' => sanitize_text_field($data['post_status']),
            'post_category' => absint($data['post_category']),
            'post_tags' => isset($data['post_tags']) ? sanitize_text_field($data['post_tags']) : '',
            'post_author' => isset($data['post_author']) ? absint($data['post_author']) : get_current_user_id(),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        );
        
        if (!empty($data['id'])) {
            $this->repository->update(absint($data['id']), $template_data);
            return absint($data['id']);
        } else {
            return $this->repository->create($template_data);
        }
    }
    
    public function delete($id) {
        return $this->repository->delete($id);
    }
    
    public function ajax_save_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $data = array(
            'id' => isset($_POST['template_id']) ? absint($_POST['template_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'prompt_template' => isset($_POST['prompt_template']) ? wp_kses_post($_POST['prompt_template']) : '',
            'title_prompt' => isset($_POST['title_prompt']) ? sanitize_text_field($_POST['title_prompt']) : '',
            'voice_id' => isset($_POST['voice_id']) ? absint($_POST['voice_id']) : 0,
            'post_quantity' => isset($_POST['post_quantity']) ? absint($_POST['post_quantity']) : 1,
            'image_prompt' => isset($_POST['image_prompt']) ? wp_kses_post($_POST['image_prompt']) : '',
            'generate_featured_image' => isset($_POST['generate_featured_image']) ? 1 : 0,
            'post_status' => isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft',
            'post_category' => isset($_POST['post_category']) ? absint($_POST['post_category']) : 0,
            'post_tags' => isset($_POST['post_tags']) ? sanitize_text_field($_POST['post_tags']) : '',
            'post_author' => isset($_POST['post_author']) ? absint($_POST['post_author']) : get_current_user_id(),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );
        
        if (empty($data['name']) || empty($data['prompt_template'])) {
            wp_send_json_error(array('message' => __('Name and prompt template are required.', 'ai-post-scheduler')));
        }
        
        if ($data['post_quantity'] < 1 || $data['post_quantity'] > 20) {
            $data['post_quantity'] = 1;
        }
        
        $id = $this->save($data);
        
        if ($id) {
            wp_send_json_success(array(
                'message' => __('Template saved successfully.', 'ai-post-scheduler'),
                'template_id' => $id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save template.', 'ai-post-scheduler')));
        }
    }
    
    public function ajax_delete_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }
        
        if ($this->delete($id)) {
            wp_send_json_success(array('message' => __('Template deleted successfully.', 'ai-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete template.', 'ai-post-scheduler')));
        }
    }
    
    public function ajax_get_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }
        
        $template = $this->get($id);
        
        if ($template) {
            wp_send_json_success(array('template' => $template));
        } else {
            wp_send_json_error(array('message' => __('Template not found.', 'ai-post-scheduler')));
        }
    }
    
    public function ajax_test_template() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $prompt = isset($_POST['prompt_template']) ? wp_kses_post($_POST['prompt_template']) : '';
        
        if (empty($prompt)) {
            wp_send_json_error(array('message' => __('Prompt template is required.', 'ai-post-scheduler')));
        }
        
        $generator = new AIPS_Generator();
        $result = $generator->generate_content($prompt);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'content' => $result,
            'message' => __('Test generation successful.', 'ai-post-scheduler')
        ));
    }

    public function get_pending_stats($template_id) {
        global $wpdb;
        $table_schedule = $wpdb->prefix . 'aips_schedule';

        $schedules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_schedule WHERE template_id = %d AND is_active = 1",
            $template_id
        ));

        $stats = array(
            'today' => 0,
            'week' => 0,
            'month' => 0
        );

        if (empty($schedules)) {
            return $stats;
        }

        $now = current_time('timestamp');
        $today_end = strtotime('today 23:59:59', $now);
        $week_end = strtotime('+7 days', $now);
        $month_end = strtotime('+30 days', $now);

        $interval_calculator = new AIPS_Interval_Calculator();

        foreach ($schedules as $schedule) {
            $cursor = strtotime($schedule->next_run);
            $frequency = $schedule->frequency;

            // Limit iterations to prevent infinite loops or excessive processing
            $max_iterations = 100;
            $i = 0;

            while ($cursor <= $month_end && $i < $max_iterations) {
                if ($cursor < $now) {
                    // Skip past events that haven't run yet but update cursor?
                    // Actually if next_run is in past, it will run next cron.
                    // So count it as imminent.
                }

                if ($cursor <= $today_end) {
                    $stats['today']++;
                }

                if ($cursor <= $week_end) {
                    $stats['week']++;
                }

                if ($cursor <= $month_end) {
                    $stats['month']++;
                } else {
                    break;
                }

                if ($frequency === 'once') {
                    break;
                }

                // Calculate next run
                $cursor = $interval_calculator->calculate_next_timestamp($frequency, $cursor);
                $i++;
            }
        }

        return $stats;
    }

    public function get_all_pending_stats() {
        global $wpdb;
        $table_schedule = $wpdb->prefix . 'aips_schedule';

        // Get all active schedules ordered by template_id
        $schedules = $wpdb->get_results("SELECT * FROM $table_schedule WHERE is_active = 1 ORDER BY template_id");

        $stats = array();
        if (empty($schedules)) {
            return $stats;
        }

        $now = current_time('timestamp');
        $today_end = strtotime('today 23:59:59', $now);
        $week_end = strtotime('+7 days', $now);
        $month_end = strtotime('+30 days', $now);

        $interval_calculator = new AIPS_Interval_Calculator();

        foreach ($schedules as $schedule) {
            $tid = $schedule->template_id;
            if (!isset($stats[$tid])) {
                $stats[$tid] = array('today' => 0, 'week' => 0, 'month' => 0);
            }

            $cursor = strtotime($schedule->next_run);
            $frequency = $schedule->frequency;

            // Limit iterations to prevent infinite loops or excessive processing
            $max_iterations = 100;
            $i = 0;

            while ($cursor <= $month_end && $i < $max_iterations) {
                // If cursor is in the past, it's considered imminent (Today)
                if ($cursor <= $today_end) {
                    $stats[$tid]['today']++;
                }

                if ($cursor <= $week_end) {
                    $stats[$tid]['week']++;
                }

                if ($cursor <= $month_end) {
                    $stats[$tid]['month']++;
                } else {
                    break;
                }

                if ($frequency === 'once') {
                    break;
                }

                // Calculate next run
                $cursor = $interval_calculator->calculate_next_timestamp($frequency, $cursor);
                $i++;
            }
        }

        return $stats;
    }

    public function ajax_get_template_posts() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

        if (!$template_id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'ai-post-scheduler')));
        }

        $history = new AIPS_History();
        $data = $history->get_history(array(
            'template_id' => $template_id,
            'page' => $page,
            'per_page' => 10,
            'status' => 'completed'
        ));

        ob_start();
        if (!empty($data['items'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'ai-post-scheduler'); ?></th>
                        <th><?php esc_html_e('Date', 'ai-post-scheduler'); ?></th>
                        <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['items'] as $item): ?>
                    <tr>
                        <td>
                            <?php if ($item->post_id): ?>
                                <a href="<?php echo get_permalink($item->post_id); ?>" target="_blank">
                                    <?php echo esc_html($item->generated_title); ?>
                                </a>
                            <?php else: ?>
                                <?php echo esc_html($item->generated_title); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($item->created_at); ?></td>
                        <td>
                            <a href="<?php echo get_edit_post_link($item->post_id); ?>" class="button button-small" target="_blank">
                                <?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($data['pages'] > 1): ?>
            <div class="aips-pagination" style="margin-top: 10px; text-align: right;">
                <?php
                $current = $data['current_page'];
                $total = $data['pages'];

                if ($current > 1) {
                    echo '<button type="button" class="button aips-modal-page" data-page="' . ($current - 1) . '">&laquo; ' . esc_html__('Prev', 'ai-post-scheduler') . '</button> ';
                }

                printf(
                    '<span class="paging-input">%s %d %s %d</span> ',
                    esc_html__('Page', 'ai-post-scheduler'),
                    $current,
                    esc_html__('of', 'ai-post-scheduler'),
                    $total
                );

                if ($current < $total) {
                    echo '<button type="button" class="button aips-modal-page" data-page="' . ($current + 1) . '">' . esc_html__('Next', 'ai-post-scheduler') . ' &raquo;</button>';
                }
                ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <p><?php esc_html_e('No posts generated yet.', 'ai-post-scheduler'); ?></p>
        <?php endif;
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }
    
    public function render_page() {
        $templates = $this->get_all();
        $categories = get_categories(array('hide_empty' => false));
        $users = get_users(array('role__in' => array('administrator', 'editor', 'author')));
        
        include AIPS_PLUGIN_DIR . 'templates/admin/main.php';
    }
}
