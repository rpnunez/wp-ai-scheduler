<?php
/**
 * Ability Catalog Controller
 *
 * Read-only AJAX endpoint for browsing available Abilities (used by the
 * workflow builder's step picker). Kept separate from the workflow CRUD
 * controllers since it has no workflow-specific state and is reusable
 * anywhere the plugin needs to surface the ability catalog.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ability_Catalog_Controller
 */
class AIPS_Ability_Catalog_Controller {

	/**
	 * @var AIPS_Ability_Catalog_Service
	 */
	private $catalog;

	/**
	 * Constructor. Registers all wp_ajax_* hooks owned by this controller.
	 *
	 * @param AIPS_Ability_Catalog_Service|null $catalog Catalog service.
	 */
	public function __construct( $catalog = null ) {
		$this->catalog = $catalog ?: new AIPS_Ability_Catalog_Service();

		add_action( 'wp_ajax_aips_list_abilities', array( $this, 'ajax_list_abilities' ) );
	}

	/**
	 * List available abilities for the step picker.
	 */
	public function ajax_list_abilities() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$args = array(
			'category' => isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '',
			'search'   => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
		);

		$abilities = $this->catalog->list_abilities( $args );

		if ( is_wp_error( $abilities ) ) {
			AIPS_Ajax_Response::error( $abilities->get_error_message() );
		}

		AIPS_Ajax_Response::success( array( 'abilities' => array_values( $abilities ) ) );
	}
}
