<?php
/**
 * Linking Pipeline Stage
 *
 * Injects internal and affiliate links into formatted content.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Linking_Stage implements AIPS_Generation_Stage_Interface {

	/**
	 * @var AIPS_Affiliate_Link_Inserter_Service|null
	 */
	private $affiliate_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Affiliate_Link_Inserter_Service|null $affiliate_service Link inserter service.
	 */
	public function __construct(?AIPS_Affiliate_Link_Inserter_Service $affiliate_service = null) {
		$container = AIPS_Container::get_instance();
		$this->affiliate_service = $affiliate_service ?: ($container->has(AIPS_Affiliate_Link_Inserter_Service::class) ? $container->make(AIPS_Affiliate_Link_Inserter_Service::class) : null);
	}

	public function get_id(): string {
		return 'linking';
	}

	public function get_label(): string {
		return __('Internal & Affiliate Link Placement', 'ai-post-scheduler');
	}

	public function get_priority(): int {
		return 60;
	}

	public function should_run(AIPS_Generation_Context $context, AIPS_Generation_Pipeline_Payload $payload): bool {
		return !empty($payload->formatted_content);
	}

	public function process(
		AIPS_Generation_Context $context,
		AIPS_Generation_Pipeline_Payload $payload,
		callable $next
	): AIPS_Generation_Pipeline_Payload {
		$content = $payload->formatted_content;

		// Inject affiliate links if enabled.
		if ($context->get_affiliate_links_enabled() && class_exists('AIPS_Affiliate_Link_Inserter_Service')) {
			if ($this->affiliate_service === null) {
				$this->affiliate_service = new AIPS_Affiliate_Link_Inserter_Service();
			}

			if (method_exists($this->affiliate_service, 'insert_links')) {
				$content = $this->affiliate_service->insert_links($content);
			}
		}

		$payload->formatted_content = $content;

		return $next($payload);
	}
}
