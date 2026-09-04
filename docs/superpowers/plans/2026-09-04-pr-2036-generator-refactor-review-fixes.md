# PR 2036 Generator Refactor Review Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Address every review finding in PR #2036 by making generator state flow explicit, separating metadata responsibilities, and adding regression coverage for orchestration behavior.

**Architecture:** Keep `generate_post_from_context()` as the visible lifecycle orchestrator. Content and metadata helpers return explicit values instead of mutating caller-owned content/status arrays, while focused title and excerpt helpers encapsulate their existing fallback behavior without changing generated-post semantics.

**Tech Stack:** PHP 8.2+, WordPress 5.8+, PHPUnit 9.6, Docker-backed WordPress test suite.

**Spec:** Approved design in the PR #2036 review conversation; source PR: https://github.com/rpnunez/wp-ai-scheduler/pull/2036/

## Global Constraints

- Work only on `refactor/atlas-extract-aips-generator-5153250400288804289`.
- Preserve existing generation behavior, hooks, logging, fallback titles, metadata-turn fallback, and post-status decisions.
- Use tabs and `array()` syntax for PHP.
- Use `AIPS_DateTime` for timestamp handling.
- Run Composer, PHPUnit, and plugin scripts from `ai-post-scheduler/` unless a repository script requires the repository root.

---

### Task 1: Protect refactored orchestration behavior

**Files:**
- Modify: `ai-post-scheduler/tests/Test_AIPS_Generator_Required_Content.php`

**Interfaces:**
- Consumes: `AIPS_Generator::generate_post(AIPS_Generation_Context $context)` and `AIPS_Test_Generator_Required_Content_Post_Manager::$created_post_data`.
- Produces: Regression coverage proving that post creation receives title-free content and that unresolved title placeholders retain the existing fallback/status contract.

- [x] **Step 1: Add the generated-content orchestration regression test**

```php
public function test_generated_leading_title_is_removed_before_post_creation() {
	$ai_service = new AIPS_Test_Generator_Required_Content_AI_Service(array(
		'<h1>Generated Title</h1><p>Generated body content.</p>',
		'Generated Title',
		'Generated excerpt.',
	));
	$post_manager = new AIPS_Test_Generator_Required_Content_Post_Manager();
	$generator = new AIPS_Generator(null, $ai_service, null, null, null, $post_manager, new AIPS_Test_Generator_Required_Content_History_Service());

	$generator->generate_post($this->make_context());

	$this->assertSame('<p>Generated body content.</p>', $post_manager->created_post_data['content']);
	$this->assertFalse($post_manager->created_post_data['generation_incomplete']);
}
```

- [x] **Step 2: Add the unresolved-title orchestration regression test**

```php
public function test_unresolved_title_placeholder_uses_fallback_and_marks_partial() {
	$ai_service = new AIPS_Test_Generator_Required_Content_AI_Service(array(
		'<p>Generated body content.</p>',
		'{{MissingTitle}}',
		'Generated excerpt.',
	));
	$post_manager = new AIPS_Test_Generator_Required_Content_Post_Manager();
	$generator = new AIPS_Generator(null, $ai_service, null, null, null, $post_manager, new AIPS_Test_Generator_Required_Content_History_Service());

	$generator->generate_post($this->make_context());

	$this->assertStringStartsWith('AIPS Generated Post: Test Topic - ', $post_manager->created_post_data['title']);
	$this->assertFalse($post_manager->created_post_data['component_statuses']['post_title']);
	$this->assertTrue($post_manager->created_post_data['generation_incomplete']);
}
```

- [x] **Step 3: Run the focused test file and validate the new cleanup test with a mutation check**

Run: `bash scripts/run-docker-test.sh --configuration phpunit.xml tests/Test_AIPS_Generator_Required_Content.php`

Expected: PASS on the existing behavior. Temporarily bypass `strip_leading_title_block_from_content()`, rerun the cleanup test, and confirm it FAILS because the `<h1>` reaches `created_post_data`; restore the production line before continuing.

### Task 2: Make generator helper outputs explicit and focused

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-generator.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Generator_Required_Content.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Generator_Conversational.php`

**Interfaces:**
- Consumes: Existing prompt builders, metadata turn, title/excerpt generators, history container, and component-status array.
- Produces: `generate_and_normalize_content($context, $component_statuses, $generation_start): string|WP_Error`, `generate_and_resolve_metadata($context, $content): array`, `resolve_generated_title($context, $content, $metadata): array`, and `resolve_generated_excerpt($title, $content, $context, $metadata): array`.

- [x] **Step 1: Move caller-owned state updates into the orchestrator**

After successful content generation, set `post_content` to `true`. Map `title_success` and `excerpt_success` from the metadata result into `component_statuses`, strip the leading title in the orchestrator, and calculate `pre_image_incomplete` and `generation_incomplete` once there.

- [x] **Step 2: Remove reference parameters from the extracted helpers**

Change the content helper to accept the status array by value for failure diagnostics. Change the metadata helper to accept content by value and return explicit success fields:

```php
return array(
	'title'                 => $title_result['title'],
	'title_success'         => $title_result['success'],
	'excerpt'               => $excerpt_result['excerpt'],
	'excerpt_success'       => $excerpt_result['success'],
	'resolved_image_prompt' => $resolved_image_prompt,
);
```

- [x] **Step 3: Extract focused title and excerpt resolution helpers**

Move title generation, placeholder detection, warning logging, and fallback construction into `resolve_generated_title()`. Move metadata-turn truncation and separate-call excerpt generation into `resolve_generated_excerpt()`. Each helper returns its generated value and a Boolean success flag.

- [x] **Step 4: Run focused generator tests**

Run: `bash scripts/run-docker-test.sh --configuration phpunit.xml --filter Test_AIPS_Generator`

Expected: All generator tests pass with zero failures.

- [x] **Step 5: Run syntax and whitespace checks**

Run: `php -l includes/class-aips-generator.php`

Expected: `No syntax errors detected`.

Run: `git diff --check`

Expected: no output and exit code 0.

- [x] **Step 6: Commit the implementation**

```bash
git add docs/superpowers/plans/2026-09-04-pr-2036-generator-refactor-review-fixes.md \
	includes/class-aips-generator.php \
	tests/Test_AIPS_Generator_Required_Content.php
git commit -m "refactor: address generator extraction review"
```
