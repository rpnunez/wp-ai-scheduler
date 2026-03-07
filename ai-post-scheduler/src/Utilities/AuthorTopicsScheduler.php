<?php

namespace AIPS\Utilities;

use AIPS\Repositories\AuthorsRepository;
use AIPS\Generators\AuthorTopicsGenerator;
use AIPS\Services\Logger;
use AIPS\Services\HistoryService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Author Topics Scheduler
 *
 * Handles scheduled generation of topics for authors.
 * Separate from post generation scheduling.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */
class AuthorTopicsScheduler {

    private $authors_repository;
    private $topics_generator;
    private $logger;
    private $interval_calculator;
    private $history_service;

    public function __construct() {
        $this->authors_repository = new AuthorsRepository();
        $this->topics_generator = new AuthorTopicsGenerator();
        $this->logger = new Logger();
        $this->interval_calculator = new IntervalCalculator();
        $this->history_service = new HistoryService();

        add_action('aips_generate_author_topics', array($this, 'process_topic_generation'));
    }

    public function process_topic_generation() {
        $this->logger->log('Starting scheduled topic generation', 'info');

        $due_authors = $this->authors_repository->get_due_for_topic_generation();

        if (empty($due_authors)) {
            $this->logger->log('No authors due for topic generation', 'info');
            return;
        }

        $this->logger->log('Found ' . count($due_authors) . ' authors due for topic generation', 'info');

        foreach ($due_authors as $author) {
            $this->generate_topics_for_author($author);
        }

        $this->logger->log('Completed scheduled topic generation', 'info');
    }

    public function generate_topics_for_author($author) {
        $this->logger->log("Generating topics for author: {$author->name} (ID: {$author->id})", 'info');

        $result = $this->topics_generator->generate_topics($author);

        if (is_wp_error($result)) {
            $this->logger->log("Failed to generate topics for author {$author->id}: " . $result->get_error_message(), 'error');

            $fail_history = $this->history_service->create('author_topic_generation', array(
                'author_id' => $author->id,
            ));
            $fail_history->record(
                'activity',
                sprintf(
                    __('Failed to generate topics for author "%s": %s', 'ai-post-scheduler'),
                    $author->name,
                    $result->get_error_message()
                ),
                array(
                    'event_type' => 'author_topic_generation',
                    'event_status' => 'failed',
                ),
                null,
                array(
                    'author_id' => $author->id,
                    'author_name' => $author->name,
                    'field_niche' => $author->field_niche,
                    'requested_quantity' => $author->topic_generation_quantity,
                    'error' => $result->get_error_message(),
                )
            );

            $this->update_author_schedule($author);
            return false;
        }

        $this->update_author_schedule($author);

        $topic_count = is_array($result) ? count($result) : 0;
        $success_history = $this->history_service->create('author_topic_generation', array(
            'author_id' => $author->id,
        ));
        $success_history->record(
            'activity',
            sprintf(
                __('Generated %d topics for author "%s"', 'ai-post-scheduler'),
                $topic_count,
                $author->name
            ),
            array(
                'event_type' => 'author_topic_generation',
                'event_status' => 'success',
            ),
            null,
            array(
                'author_id' => $author->id,
                'author_name' => $author->name,
                'field_niche' => $author->field_niche,
                'topics_generated' => $topic_count,
                'requested_quantity' => $author->topic_generation_quantity,
            )
        );

        $this->logger->log("Successfully generated topics for author {$author->id}", 'info');
        return true;
    }

    private function update_author_schedule($author) {
        $next_run = $this->interval_calculator->calculate_next_run($author->topic_generation_frequency);

        $this->authors_repository->update_topic_generation_schedule($author->id, $next_run);

        $this->logger->log("Updated topic generation schedule for author {$author->id}. Next run: {$next_run}", 'info');
    }

    public function generate_now($author_id) {
        $author = $this->authors_repository->get_by_id($author_id);

        if (!$author) {
            return new \WP_Error('invalid_author', 'Author not found');
        }

        return $this->topics_generator->generate_topics($author);
    }
}
