<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Seeder_Service {

    private $generator;
    private $voices;
    private $templates;
    private $scheduler;

    public function __construct() {
        $this->generator = new AIPS_Generator();
        $this->voices = new AIPS_Voices();
        $this->templates = new AIPS_Template_Service();
        $this->scheduler = new AIPS_Scheduler();
    }

    /**
     * Generate seed data based on type and count.
     *
     * @param string $type The type of data to seed (voices, templates, schedule, planner).
     * @param int $count The number of items to create.
     * @param string $keywords Optional keywords to guide generation.
     * @return array Result with success count and message.
     */
    public function seed($type, $count, $keywords = '') {
        if ($count <= 0) {
            return array('success' => true, 'count' => 0, 'message' => 'Count is 0, skipping.');
        }

        switch ($type) {
            case 'voices':
                return $this->seed_voices($count, $keywords);
            case 'templates':
                return $this->seed_templates($count, $keywords);
            case 'schedule':
                return $this->seed_scheduled_templates($count, $keywords);
            case 'planner':
                return $this->seed_planner_entries($count, $keywords);
            default:
                return array('success' => false, 'message' => 'Invalid type.');
        }
    }

    private function seed_voices($count, $keywords = '') {
        if (!$this->generator->is_available()) {
            return array('success' => false, 'message' => 'AI Engine not available.');
        }

        $prompt = "Generate a list of {$count} unique personas for blog writing. \n";
        if (!empty($keywords)) {
            $prompt .= "Use the following keywords to inspire the personas: {$keywords}. \n";
        }
        $prompt .= "Each persona must have a 'name', 'content_instructions' (writing style description), and 'title_prompt' (instructions for writing titles). \n";
        $prompt .= "Return ONLY a valid JSON array of objects. Example: [{\"name\": \"Tech Guru\", \"content_instructions\": \"...\", \"title_prompt\": \"...\"}]";

        $data = $this->generate_json($prompt);

        if (empty($data) || !is_array($data)) {
            return array('success' => false, 'message' => 'Failed to generate voice data from AI.');
        }

        $created = 0;
        $failed = 0;
        foreach ($data as $item) {
            if (isset($item->name)) {
                $result = $this->voices->save(array(
                    'name' => sanitize_text_field($item->name),
                    'title_prompt' => isset($item->title_prompt) ? wp_kses_post($item->title_prompt) : 'Generate a catchy title.',
                    'content_instructions' => isset($item->content_instructions) ? wp_kses_post($item->content_instructions) : 'Write in a professional tone.',
                    'excerpt_instructions' => '',
                    'is_active' => 1
                ));

                if ($result) {
                    $created++;
                } else {
                    $failed++;
                }
            }
        }

        $message = "Created {$created} voices.";
        if ($failed > 0) {
            $message .= " Failed to create {$failed}.";
        }

        return array('success' => true, 'count' => $created, 'message' => $message);
    }

    private function seed_templates($count, $keywords = '') {
        if (!$this->generator->is_available()) {
            return array('success' => false, 'message' => 'AI Engine not available.');
        }

        $prompt = "Generate a list of {$count} blog post templates. \n";
        if (!empty($keywords)) {
            $prompt .= "The templates should be relevant to these keywords/niche: {$keywords}. \n";
        }
        $prompt .= "Each template needs a 'name', 'prompt_template' (e.g., 'Write a blog post about {{topic}}...'), and 'image_prompt'. \n";
        $prompt .= "Return ONLY a valid JSON array of objects. Example: [{\"name\": \"How-to Guide\", \"prompt_template\": \"...\", \"image_prompt\": \"...\"}]";

        $data = $this->generate_json($prompt);

        if (empty($data) || !is_array($data)) {
            return array('success' => false, 'message' => 'Failed to generate template data from AI.');
        }

        $created = 0;
        foreach ($data as $item) {
            if (isset($item->name)) {
                $result = $this->templates->save(array(
                    'name' => sanitize_text_field($item->name),
                    'prompt_template' => isset($item->prompt_template) ? wp_kses_post($item->prompt_template) : 'Write about {{topic}}',
                    'image_prompt' => isset($item->image_prompt) ? wp_kses_post($item->image_prompt) : 'An abstract image representing {{topic}}',
                    'post_quantity' => 1,
                    'generate_featured_image' => 1,
                    'post_status' => 'draft',
                    'post_category' => 0,
                    'is_active' => 1
                ));

                if ($result) {
                    $created++;
                }
            }
        }

        return array('success' => true, 'count' => $created, 'message' => "Created {$created} templates.");
    }

    private function seed_scheduled_templates($count, $keywords = '') {
        $all_templates = $this->templates->get_all(true); // Active only

        if (empty($all_templates)) {
            return array('success' => false, 'message' => 'No active templates found. Please seed templates first.');
        }

        $schedules = array();
        $frequencies = array('daily', 'weekly', 'hourly', 'every_12_hours');

        for ($i = 0; $i < $count; $i++) {
            $template = $all_templates[array_rand($all_templates)];
            $freq = $frequencies[array_rand($frequencies)];
            // Random start time within next 24 hours
            $next_run = date('Y-m-d H:i:s', time() + rand(60, 86400));

            $schedules[] = array(
                'template_id' => $template->id,
                'frequency' => $freq,
                'next_run' => $next_run,
                'is_active' => 1,
                'topic' => '' // Recurring usually generic
            );
        }

        $saved_count = $this->scheduler->save_schedule_bulk($schedules);

        if ($saved_count === false) {
            return array('success' => false, 'message' => 'Failed to save schedules to database.');
        }

        return array('success' => true, 'count' => $saved_count, 'message' => "Scheduled {$saved_count} recurring templates.");
    }

    private function seed_planner_entries($count, $keywords = '') {
        if (!$this->generator->is_available()) {
            return array('success' => false, 'message' => 'AI Engine not available.');
        }

        $all_templates = $this->templates->get_all(true);
        if (empty($all_templates)) {
            return array('success' => false, 'message' => 'No active templates found. Please seed templates first.');
        }

        $prompt = "Generate a list of {$count} interesting blog post topics/titles. \n";
        if (!empty($keywords)) {
            $prompt .= "The topics MUST be related to these keywords: {$keywords}. \n";
        } else {
            $prompt .= "Topics should be about Technology, Lifestyle, or Business. \n";
        }
        $prompt .= "Return ONLY a valid JSON array of strings. Example: [\"Topic 1\", \"Topic 2\"]";

        $topics = $this->generate_json($prompt);

        if (empty($topics) || !is_array($topics)) {
            return array('success' => false, 'message' => 'Failed to generate topics from AI.');
        }

        $schedules = array();
        $base_time = time() + 3600; // Start 1 hour from now

        foreach ($topics as $index => $topic) {
            if (!is_string($topic)) continue;

            $template = $all_templates[array_rand($all_templates)];
            $next_run = date('Y-m-d H:i:s', $base_time + ($index * 3600)); // Spread out by hour

            $schedules[] = array(
                'template_id' => $template->id,
                'frequency' => 'once',
                'next_run' => $next_run,
                'is_active' => 1,
                'topic' => sanitize_text_field($topic)
            );
        }

        $saved_count = $this->scheduler->save_schedule_bulk($schedules);

        if ($saved_count === false) {
            return array('success' => false, 'message' => 'Failed to save planner entries to database.');
        }

        return array('success' => true, 'count' => $saved_count, 'message' => "Created {$saved_count} planner entries (scheduled once).");
    }

    private function generate_json($prompt) {
        $result = $this->generator->generate_content($prompt, array('temperature' => 0.7, 'max_tokens' => 2000), 'seeder_json');

        if (is_wp_error($result)) {
            return null;
        }

        $json_str = trim($result);
        $json_str = preg_replace('/^```json/', '', $json_str);
        $json_str = preg_replace('/^```/', '', $json_str);
        $json_str = preg_replace('/```$/', '', $json_str);
        $json_str = trim($json_str);

        $data = json_decode($json_str);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }
}
