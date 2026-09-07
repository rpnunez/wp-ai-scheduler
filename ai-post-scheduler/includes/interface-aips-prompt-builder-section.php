<?php
/**
 * Prompt Builder Section Interface
 *
 * Shared type for the prompt-builder section classes that assemble individual
 * components of an AI prompt (title, content, excerpt, metadata, taxonomy, ...).
 *
 * Deliberately a marker interface: every section exposes a public `build()`
 * method, but their signatures are genuinely heterogeneous and each declares
 * required parameters of its own -- `build($structure_id, $topic = null)`,
 * `build(array $inputs, $count)`, `build($title, $content, ...)` and so on.
 * PHP has no signature that all of those are compatible with. A variadic
 * `build(...$args)` declaration on the interface is *not* a valid common
 * ancestor: PHP rejects any implementor that requires parameters, so declaring
 * one here would fatal every section class at load time.
 *
 * The `build()` convention is therefore enforced by
 * Test_Prompt_Builder_Section_Contract, which reflects over every implementor
 * and asserts it declares a public `build()` method. That keeps the convention
 * guarded without forcing a lossy variadic signature onto nine call sites.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.5
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Prompt_Builder_Section
 *
 * Marks a class as a prompt-builder section so callers and aggregators can
 * type-hint against the family. Implementors are expected to expose a public
 * `build()` method returning the assembled prompt string (or WP_Error).
 */
interface AIPS_Prompt_Builder_Section {
}
