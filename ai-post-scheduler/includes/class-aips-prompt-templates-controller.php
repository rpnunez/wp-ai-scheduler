<?php
/**
 * Prompt Templates Controller
 *
 * Handles AJAX actions for managing prompt template groups and their
 * per-component prompt items.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
exit;
}

/**
 * Class AIPS_Prompt_Templates_Controller
 *
 * Registers wp_ajax_* hooks for all prompt-template AJAX actions and
 * delegates persistence to AIPS_Prompt_Template_Group_Repository (groups) and
 * AIPS_Prompt_Template_Item_Repository (items).
 */
class AIPS_Prompt_Templates_Controller {

/**
 * @var AIPS_Prompt_Template_Group_Repository
 */
private $group_repo;

/**
 * @var AIPS_Prompt_Template_Item_Repository
 */
private $item_repo;

/**
 * Register AJAX hooks.
 */
public function __construct() {
$this->group_repo = AIPS_Prompt_Template_Group_Repository::instance();
$this->item_repo  = AIPS_Prompt_Template_Item_Repository::instance();

add_action( 'wp_ajax_aips_get_prompt_template_groups',        array( $this, 'ajax_get_groups' ) );
add_action( 'wp_ajax_aips_get_prompt_template_group',         array( $this, 'ajax_get_group' ) );
add_action( 'wp_ajax_aips_save_prompt_template_group',        array( $this, 'ajax_save_group' ) );
add_action( 'wp_ajax_aips_delete_prompt_template_group',      array( $this, 'ajax_delete_group' ) );
add_action( 'wp_ajax_aips_set_default_prompt_template_group', array( $this, 'ajax_set_default_group' ) );
add_action( 'wp_ajax_aips_save_prompt_template_items',        array( $this, 'ajax_save_items' ) );
}

// -------------------------------------------------------------------------
// AJAX handlers
// -------------------------------------------------------------------------

/**
 * Return all prompt template groups as JSON.
 *
 * @return void
 */
public function ajax_get_groups() {
check_ajax_referer( 'aips_prompt_templates_nonce', 'nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-post-scheduler' ) ), 403 );
}

$this->group_repo->ensure_default_group_exists();

$groups = $this->group_repo->get_all_groups();

wp_send_json_success( array( 'groups' => $groups ) );
}

/**
 * Return a single group with its component items.
 *
 * @return void
 */
public function ajax_get_group() {
check_ajax_referer( 'aips_prompt_templates_nonce', 'nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-post-scheduler' ) ), 403 );
}

$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
if ( ! $id ) {
wp_send_json_error( array( 'message' => __( 'Invalid group ID.', 'ai-post-scheduler' ) ), 400 );
}

$group = $this->group_repo->get_group( $id );
if ( ! $group ) {
wp_send_json_error( array( 'message' => __( 'Group not found.', 'ai-post-scheduler' ) ), 404 );
}

$items      = $this->item_repo->get_items_for_group( $id );
$components = $this->item_repo->get_component_definitions();

wp_send_json_success( array(
'group'      => $group,
'items'      => array_values( $items ),
'components' => array_values( $components ),
) );
}

/**
 * Create or update a prompt template group.
 *
 * When an `id` is provided the existing group is updated; otherwise a new
 * group is created and seeded with built-in default items.
 *
 * @return void
 */
public function ajax_save_group() {
check_ajax_referer( 'aips_prompt_templates_nonce', 'nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-post-scheduler' ) ), 403 );
}

$id          = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
$is_default  = isset( $_POST['is_default'] ) ? (int) $_POST['is_default'] : 0;

if ( $name === '' ) {
wp_send_json_error( array( 'message' => __( 'Group name is required.', 'ai-post-scheduler' ) ), 400 );
}

if ( $id ) {
// Update.
$ok = $this->group_repo->update_group( $id, array(
'name'        => $name,
'description' => $description,
'is_default'  => $is_default,
) );

if ( ! $ok ) {
wp_send_json_error( array( 'message' => __( 'Failed to update group.', 'ai-post-scheduler' ) ), 500 );
}

$group = $this->group_repo->get_group( $id );
wp_send_json_success( array(
'group'   => $group,
'message' => __( 'Group updated successfully.', 'ai-post-scheduler' ),
) );
} else {
// Create.
$new_id = $this->group_repo->create_group( array(
'name'        => $name,
'description' => $description,
'is_default'  => $is_default,
) );

if ( ! $new_id ) {
wp_send_json_error( array( 'message' => __( 'Failed to create group.', 'ai-post-scheduler' ) ), 500 );
}

$group = $this->group_repo->get_group( $new_id );
wp_send_json_success( array(
'group'   => $group,
'message' => __( 'Group created successfully.', 'ai-post-scheduler' ),
) );
}
}

/**
 * Delete a prompt template group.
 *
 * Prevents deletion of the last remaining group.
 *
 * @return void
 */
public function ajax_delete_group() {
check_ajax_referer( 'aips_prompt_templates_nonce', 'nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-post-scheduler' ) ), 403 );
}

$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
if ( ! $id ) {
wp_send_json_error( array( 'message' => __( 'Invalid group ID.', 'ai-post-scheduler' ) ), 400 );
}

if ( $this->group_repo->count_groups() <= 1 ) {
wp_send_json_error( array( 'message' => __( 'Cannot delete the last remaining group.', 'ai-post-scheduler' ) ), 400 );
}

$group = $this->group_repo->get_group( $id );
if ( ! $group ) {
wp_send_json_error( array( 'message' => __( 'Group not found.', 'ai-post-scheduler' ) ), 404 );
}

// If deleting the default group, promote another one first.
if ( (int) $group->is_default === 1 ) {
$others = $this->group_repo->get_all_groups();
foreach ( $others as $other ) {
if ( (int) $other->id !== $id ) {
$this->group_repo->set_default_group( (int) $other->id );
break;
}
}
}

$ok = $this->group_repo->delete_group( $id );
if ( ! $ok ) {
wp_send_json_error( array( 'message' => __( 'Failed to delete group.', 'ai-post-scheduler' ) ), 500 );
}

wp_send_json_success( array( 'message' => __( 'Group deleted.', 'ai-post-scheduler' ) ) );
}

/**
 * Set a group as the active default.
 *
 * @return void
 */
public function ajax_set_default_group() {
check_ajax_referer( 'aips_prompt_templates_nonce', 'nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-post-scheduler' ) ), 403 );
}

$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
if ( ! $id ) {
wp_send_json_error( array( 'message' => __( 'Invalid group ID.', 'ai-post-scheduler' ) ), 400 );
}

$ok = $this->group_repo->set_default_group( $id );
if ( ! $ok ) {
wp_send_json_error( array( 'message' => __( 'Failed to set default group.', 'ai-post-scheduler' ) ), 500 );
}

wp_send_json_success( array( 'message' => __( 'Default group updated.', 'ai-post-scheduler' ) ) );
}

/**
 * Save component items for a group.
 *
 * Expects $_POST['group_id'] and $_POST['items'] (JSON-encoded map of
 * component_key => prompt_text).  After saving, the group repository's prompt
 * cache is flushed so that the next generation request picks up fresh values.
 *
 * @return void
 */
public function ajax_save_items() {
check_ajax_referer( 'aips_prompt_templates_nonce', 'nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-post-scheduler' ) ), 403 );
}

$group_id   = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
$items_json = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '';

if ( ! $group_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid group ID.', 'ai-post-scheduler' ) ), 400 );
}

if ( ! $this->group_repo->get_group( $group_id ) ) {
wp_send_json_error( array( 'message' => __( 'Group not found.', 'ai-post-scheduler' ) ), 404 );
}

$items = json_decode( $items_json, true );
if ( ! is_array( $items ) ) {
wp_send_json_error( array( 'message' => __( 'Invalid items data.', 'ai-post-scheduler' ) ), 400 );
}

// Validate each component key and sanitize values.
$definitions = $this->item_repo->get_component_definitions();
$clean_items = array();
foreach ( $items as $key => $text ) {
$key = sanitize_key( $key );
if ( isset( $definitions[ $key ] ) ) {
$clean_items[ $key ] = sanitize_textarea_field( (string) $text );
}
}

$ok = $this->item_repo->save_items( $group_id, $clean_items );
if ( ! $ok ) {
wp_send_json_error( array( 'message' => __( 'Failed to save some items.', 'ai-post-scheduler' ) ), 500 );
}

// Flush the group repository's prompt cache so generation picks up fresh values.
$this->group_repo->flush_prompt_cache();

wp_send_json_success( array( 'message' => __( 'Prompt templates saved.', 'ai-post-scheduler' ) ) );
}

// -------------------------------------------------------------------------
// Page render
// -------------------------------------------------------------------------

/**
 * Render the Prompt Templates admin page.
 *
 * Ensures the default group exists (lazy-create on first visit), then
 * loads all groups and component definitions for the template.
 *
 * @return void
 */
public function render_page() {
$this->group_repo->ensure_default_group_exists();

$groups     = $this->group_repo->get_all_groups();
$components = AIPS_Prompt_Template_Defaults::get_components();

include AIPS_PLUGIN_DIR . 'templates/admin/prompt-templates.php';
}
}
