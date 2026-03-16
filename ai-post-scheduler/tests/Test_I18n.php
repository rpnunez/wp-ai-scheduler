<?php
class Test_I18n extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        require_once dirname(__DIR__) . '/includes/class-aips-i18n.php';
    }

    public function test_get_utilities_strings() {
        $strings = AIPS_i18n::get_utilities_strings();
        $this->assertIsArray($strings);
        $this->assertArrayHasKey('closeLabel', $strings);
    }

    public function test_get_admin_strings() {
        $strings = AIPS_i18n::get_admin_strings();
        $this->assertIsArray($strings);
        $this->assertArrayHasKey('errorOccurred', $strings);
    }

    public function test_get_authors_strings() {
        $strings = AIPS_i18n::get_authors_strings();
        $this->assertIsArray($strings);
        $this->assertArrayHasKey('addNewAuthor', $strings);
    }

    public function test_get_research_strings() {
        $strings = AIPS_i18n::get_research_strings();
        $this->assertIsArray($strings);
        $this->assertArrayHasKey('topicsSaved', $strings);
    }

    public function test_get_activity_strings() {
        $strings = AIPS_i18n::get_activity_strings();
        $this->assertIsArray($strings);
        $this->assertArrayHasKey('confirmPublish', $strings);
    }

    public function test_get_post_review_strings() {
        $strings = AIPS_i18n::get_post_review_strings();
        $this->assertIsArray($strings);
        $this->assertArrayHasKey('confirmDelete', $strings);
    }

    public function test_get_ai_edit_strings() {
        $strings = AIPS_i18n::get_ai_edit_strings();
        $this->assertIsArray($strings);
        $this->assertArrayHasKey('regenerate', $strings);
    }

    public function test_get_history_strings() {
        $strings = AIPS_i18n::get_history_strings();
        $this->assertIsArray($strings);
        $this->assertArrayHasKey('loading', $strings);
    }
}
