<?php
use PHPUnit\Framework\TestCase;

class Test_Author_Topics_Generator_Fix extends TestCase {

    public function setUp(): void {
        parent::setUp();
        // Load necessary classes if not already loaded
        if (!class_exists('AIPS_Author_Topics_Generator')) {
            require_once dirname(dirname(__FILE__)) . '/includes/class-aips-author-topics-generator.php';
        }
        if (!class_exists('AIPS_Author_Topics_Repository')) {
            require_once dirname(dirname(__FILE__)) . '/includes/class-aips-author-topics-repository.php';
        }
        if (!class_exists('AIPS_Author_Topic_Logs_Repository')) {
            require_once dirname(dirname(__FILE__)) . '/includes/class-aips-author-topic-logs-repository.php';
        }
    }

    public function test_generate_topics_uses_timestamp_filter() {
        // Mock dependencies
        $ai_service = $this->createMock('AIPS_AI_Service');
        $logger = $this->createMock('AIPS_Logger');
        $topics_repo = $this->createMock('AIPS_Author_Topics_Repository');
        $logs_repo = $this->createMock('AIPS_Author_Topic_Logs_Repository');

        // Setup AI Service mock to return valid JSON
        $ai_service->method('generate_json')
            ->willReturn(array(
                array(
                    'title' => 'Test Topic 1',
                    'score' => 80,
                    'keywords' => array('test', 'topic')
                ),
                array(
                    'title' => 'Test Topic 2',
                    'score' => 70,
                    'keywords' => array('test', 'topic2')
                )
            ));

        // Setup Topics Repository mock
        $topics_repo->method('create_bulk')->willReturn(true);
        $topics_repo->method('get_approved_summary')->willReturn(array());
        $topics_repo->method('get_rejected_summary')->willReturn(array());

        // Expect get_latest_by_author to be called with a timestamp (3rd argument)
        // Currently it is called with 2 arguments, so this expectation should fail
        $topics_repo->expects($this->once())
            ->method('get_latest_by_author')
            ->with(
                $this->anything(), // author_id
                $this->anything(), // limit
                $this->callback(function($timestamp) {
                    // Fail if timestamp is null (which is the default if 2 args are passed)
                    return $timestamp !== null;
                })
            )
            ->willReturn(array());

        $generator = new AIPS_Author_Topics_Generator($ai_service, $logger, $topics_repo, $logs_repo);

        $author = (object) array(
            'id' => 123,
            'name' => 'Test Author',
            'field_niche' => 'Testing',
            'topic_generation_quantity' => 2,
            'keywords' => '',
            'details' => '',
            'topic_generation_prompt' => ''
        );

        $generator->generate_topics($author);
    }
}
