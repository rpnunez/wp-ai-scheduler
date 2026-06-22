<?php
/**
 * Content Enhancement Inserter
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Enhancement_Inserter
 */
class AIPS_Content_Enhancement_Inserter {

	private $repository;
	private $renderer;

	public function __construct( ?AIPS_Content_Enhancement_Repository $repository = null, ?AIPS_Content_Enhancement_Renderer $renderer = null ) {
		$this->repository = $repository ?: new AIPS_Content_Enhancement_Repository();
		$this->renderer   = $renderer ?: new AIPS_Content_Enhancement_Renderer();
	}

	public function replace_placeholders( string $content ): string {
		return preg_replace_callback( '/\{\{aips_enhancement:([a-z0-9_-]+)\}\}/i', function( $matches ) {
			$enhancement = $this->repository->find_by_slug( $matches[1] );
			if ( ! $enhancement || empty( $enhancement['is_active'] ) ) {
				return '';
			}

			return $this->renderer->render( $enhancement );
		}, $content );
	}
}
