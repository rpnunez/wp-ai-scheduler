<?php
/**
 * Tests for natural-language schedule parsing.
 */
class Test_AIPS_Natural_Schedule_Parser extends WP_UnitTestCase {

    /** @var AIPS_Natural_Schedule_Parser */
    private $parser;

    public function setUp(): void {
        parent::setUp();
        $this->parser = new AIPS_Natural_Schedule_Parser();
    }

    public function test_parse_every_weekday_at_eight_am() {
        $reference = strtotime('2030-06-14 10:00:00'); // Friday
        $parsed = $this->parser->parse('every weekday at 8 AM', $reference);

        $this->assertIsArray($parsed);
        $this->assertEquals('weekdays', $parsed['frequency']);
        $this->assertEquals('08:00:00', date('H:i:s', strtotime($parsed['start_time'])));
        $this->assertEquals(1, (int) date('N', strtotime($parsed['start_time']))); // Monday
    }

    public function test_parse_every_monday_at_930() {
        $reference = strtotime('2030-06-12 10:00:00'); // Wednesday
        $parsed = $this->parser->parse('every monday at 9:30 AM', $reference);

        $this->assertIsArray($parsed);
        $this->assertEquals('every_monday', $parsed['frequency']);
        $this->assertEquals('09:30:00', date('H:i:s', strtotime($parsed['start_time'])));
        $this->assertEquals(1, (int) date('N', strtotime($parsed['start_time'])));
    }

    public function test_parse_daily_at_8pm() {
        $reference = strtotime('2030-06-12 10:00:00');
        $parsed = $this->parser->parse('daily at 8 PM', $reference);

        $this->assertIsArray($parsed);
        $this->assertEquals('daily', $parsed['frequency']);
        $this->assertEquals('20:00:00', date('H:i:s', strtotime($parsed['start_time'])));
        $this->assertEquals('2030-06-12', date('Y-m-d', strtotime($parsed['start_time'])));
    }

    public function test_parse_unsupported_phrase_returns_error() {
        $parsed = $this->parser->parse('sometime maybe next-ish');

        $this->assertInstanceOf('WP_Error', $parsed);
        $this->assertEquals('unsupported_schedule_text', $parsed->get_error_code());
    }
}
