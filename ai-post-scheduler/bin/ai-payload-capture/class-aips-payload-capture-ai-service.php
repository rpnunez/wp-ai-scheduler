<?php
/**
 * AI payload capture: AI service subclass.
 *
 * The real AIPS_AI_Service with its public entry points wrapped so the prompt
 * and options are recorded exactly as the caller passed them. Every call still
 * runs the parent implementation (token budgeting, resilience, logging, JSON
 * fallback), so the captured run behaves like production.
 *
 * Dev tooling only: loaded by bin/ai-payload-capture.php, never by the plugin.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Payload_Capture_AI_Service extends AIPS_AI_Service {

	/** @var AIPS_Payload_Capture_Recorder */
	private $capture_recorder;

	public function __construct(AIPS_Payload_Capture_Recorder $recorder, AIPS_AI_Provider_Interface $provider) {
		parent::__construct(null, null, null, $provider);

		$this->capture_recorder = $recorder;
	}

	public function generate_text($prompt, $options = array()) {
		$handle = $this->capture_recorder->open_service_call('generate_text', $prompt, $options);
		$result = parent::generate_text($prompt, $options);
		$this->capture_recorder->close_service_call($handle, $result);

		return $result;
	}

	public function generate_json($prompt, $options = array()) {
		$handle = $this->capture_recorder->open_service_call('generate_json', $prompt, $options);
		$result = parent::generate_json($prompt, $options);
		$this->capture_recorder->close_service_call($handle, $result);

		return $result;
	}

	public function generate_json_from_text($prompt, $options = array()) {
		$handle = $this->capture_recorder->open_service_call('generate_json_from_text', $prompt, $options);
		$result = parent::generate_json_from_text($prompt, $options);
		$this->capture_recorder->close_service_call($handle, $result);

		return $result;
	}

	public function generate_image($prompt, $options = array()) {
		$handle = $this->capture_recorder->open_service_call('generate_image', $prompt, $options);
		$result = parent::generate_image($prompt, $options);
		$this->capture_recorder->close_service_call($handle, $result);

		return $result;
	}

	public function generate_embedding($text, $options = array()) {
		$handle = $this->capture_recorder->open_service_call('generate_embedding', $text, $options);
		$result = parent::generate_embedding($text, $options);
		$this->capture_recorder->close_service_call($handle, $result);

		return $result;
	}
}
