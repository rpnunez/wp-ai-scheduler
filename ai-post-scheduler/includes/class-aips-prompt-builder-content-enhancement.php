<?php
/**
 * Content Enhancement Prompt Builder
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Content_Enhancement
 */
class AIPS_Prompt_Builder_Content_Enhancement {

	private $repository;

	public function __construct( ?AIPS_Content_Enhancement_Repository $repository = null ) {
		$this->repository = $repository ?: new AIPS_Content_Enhancement_Repository();
	}

	public function build_content_enhancement_block( $topic, $template_context = null ): string {
		$enhancements = $this->repository->active();
		if ( empty( $enhancements ) ) {
			return '';
		}

		$lines = array(
			'Content Enhancements:',
			'Only include an enhancement when it is genuinely useful for the reader and relevant to the article topic. Do not force one into every article.',
			'Never output raw scripts, iframes, embeds, or shortcode execution markup. Use only the safe placeholder format {{aips_enhancement:slug}} on its own paragraph where the enhancement should appear.',
			'After the article, include an HTML comment named aips_enhancement_opportunities with a compact JSON array of selected slugs, reasons, and intended placements.',
			'Available Content Enhancements:',
		);

		foreach ( $enhancements as $enhancement ) {
			$lines[] = sprintf(
				'- %s (slug: %s, type: %s, provider: %s) Use when: %s',
				$enhancement['name'] ?? '',
				$enhancement['slug'] ?? '',
				$enhancement['type'] ?? 'embed',
				$enhancement['provider'] ?? 'custom',
				$enhancement['use_case'] ?? ''
			);
		}

		return implode( "\n", $lines );
	}
}
