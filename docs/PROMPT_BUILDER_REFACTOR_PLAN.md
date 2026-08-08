# Prompt Builder Refactor Plan

## Purpose

The plugin already has a strong prompt-builder pattern for generated posts, author suggestions, taxonomy generation, article-structure sections, and shared diversity/source blocks. This plan identifies AI prompts that are still assembled inside controllers/services and proposes dedicated `AIPS_Prompt_Builder_*` classes for each prompt family.

## Existing prompt-builder baseline

Dedicated prompt-builder classes currently live in `ai-post-scheduler/includes/`:

- `AIPS_Prompt_Builder` orchestrates post content, title, excerpt, featured image, site context, and sources injection.
- `AIPS_Prompt_Builder_Post_Content`, `AIPS_Prompt_Builder_Post_Title`, `AIPS_Prompt_Builder_Post_Excerpt`, and `AIPS_Prompt_Builder_Post_Featured_Image` own post-generation prompts.
- `AIPS_Prompt_Builder_Authors`, `AIPS_Prompt_Builder_Topic`, `AIPS_Prompt_Builder_Taxonomy`, and `AIPS_Prompt_Builder_Article_Structure_Section` cover several specialized feature prompts.
- `AIPS_Prompt_Builder_Diversity_Injector` owns reusable diversity/uniqueness prompt blocks.

## Hardcoded prompt candidates outside dedicated builders

| Priority | Current owner | Prompt methods / call sites | Proposed builder class and file | Rationale |
| --- | --- | --- | --- | --- |
| P0 | `AIPS_Research_Service` | `build_research_prompt()` and `build_source_research_prompt()` | `AIPS_Prompt_Builder_Research` in `ai-post-scheduler/includes/class-aips-prompt-builder-research.php` | Research has two substantial, related prompts with date handling, keyword handling, JSON contract text, and source-grounding rules. This is the clearest extraction candidate. |
| P1 | `AIPS_Content_Auditor` | `generate_gap_analysis_prompt()` | `AIPS_Prompt_Builder_Content_Audit` in `ai-post-scheduler/includes/class-aips-prompt-builder-content-audit.php` | Gap analysis is an AI-facing SEO strategy prompt with a structured JSON schema. Moving it out of the auditor keeps the service focused on content collection, AI invocation, and response parsing. |
| P1 | `AIPS_Campaigns_Controller` | `build_ai_campaign_prompt()` | `AIPS_Prompt_Builder_Campaign` in `ai-post-scheduler/includes/class-aips-prompt-builder-campaign.php` | The campaign controller currently owns a large Guided AI Setup prompt and context assembly. Extraction reduces controller responsibility and makes the JSON contract testable. |
| P2 | `AIPS_AI_Assistance_Service` | `build_prompt()` | `AIPS_Prompt_Builder_AI_Assistance` in `ai-post-scheduler/includes/class-aips-prompt-builder-ai-assistance.php` | Field-suggestion prompts are compact, but extracting them keeps all AI instruction construction consistent and easier to extend per form context. |
| P2 | `AIPS_Internal_Link_Inserter_Service` | `build_prompt()` | `AIPS_Prompt_Builder_Internal_Link` in `ai-post-scheduler/includes/class-aips-prompt-builder-internal-link.php` | The insertion-location prompt has a detailed JSON schema and strict replacement rules; a builder makes the contract independently testable. |
| P2 | `AIPS_Planner` | inline topic-generation prompt in `ajax_generate_topics()` | `AIPS_Prompt_Builder_Planner` in `ai-post-scheduler/includes/class-aips-prompt-builder-planner.php` | The planner controller should not assemble AI prompts inline. The builder can own count/niche formatting and the JSON-array contract. |
| P3 | `AIPS_Seeder_Service` | `seed_voices()`, `seed_templates()`, and planner seed prompt construction | `AIPS_Prompt_Builder_Seeder` in `ai-post-scheduler/includes/class-aips-prompt-builder-seeder.php` | Seeder prompts are admin/dev utility prompts. Lower priority, but extraction removes repeated JSON instructions and examples from service methods. |
| P3 | `AIPS_Template_Processor` | `build_ai_variables_prompt()` | `AIPS_Prompt_Builder_AI_Variables` in `ai-post-scheduler/includes/class-aips-prompt-builder-ai-variables.php` | This is already a named prompt-building method, but it sits in a template-processing class. Extraction would separate variable prompt construction from variable parsing/substitution. |
| P3 | `AIPS_Dev_Tools` | inline diagnostic prompt | No new production builder unless diagnostics grow | Dev-tool smoke prompts are not part of regular content workflows. Keep as-is or fold into `AIPS_Prompt_Builder_Dev_Tools` only if more diagnostic prompts are added. |

## Recommended extraction order

1. **Research prompts first**
   - Add `AIPS_Prompt_Builder_Research` with `build_trending_topics_prompt($niche, $count, $keywords)` and `build_source_research_prompt($niche, $count, $keywords, $source_context)`.
   - Inject or instantiate the builder in `AIPS_Research_Service`.
   - Remove private prompt assembly methods from the service after updating call sites.
   - Preserve exact JSON output contracts and date behavior through `AIPS_DateTime`.

2. **Content audit and campaign prompts next**
   - Add `AIPS_Prompt_Builder_Content_Audit::build_gap_analysis_prompt($niche, $existing_content)`.
   - Add `AIPS_Prompt_Builder_Campaign::build_guided_setup_prompt($intake, $context)` or let the builder derive the context from collaborators passed in by the controller.
   - Keep controllers/services responsible for sanitization, permissions, AI calls, and response validation only.

3. **Smaller service/controller prompts**
   - Extract AI assistance, internal-link insertion, and planner prompts into builders.
   - Prefer constructor injection with fallback instantiation to keep existing tests and runtime behavior stable.

4. **Utility prompts**
   - Extract seeder and AI-variable prompts if the production prompt-builder convention should be comprehensive across all AI calls.
   - Leave dev-tool prompts inline unless diagnostics become a reusable feature.

## Implementation conventions for new builders

- Place every new builder in `ai-post-scheduler/includes/` using the existing filename pattern, for example `class-aips-prompt-builder-research.php` for `AIPS_Prompt_Builder_Research`.
- Include the standard `if (!defined('ABSPATH')) { exit; }` guard.
- Use `AIPS_`-prefixed class names, tabs, and `array()` syntax in PHP.
- Keep builders side-effect free: no AI calls, no database writes, no nonce/capability checks.
- Keep response parsing and validation in the current service/controller unless it is prompt-contract-specific enough to warrant a separate parser.
- Where prompts include dates, use `AIPS_DateTime` inside the builder or pass a date object/string into the builder for testability.
- Add focused tests around prompt output contracts where a test harness already covers the consuming service.

## Suggested first refactor shape: Research

```php
class AIPS_Prompt_Builder_Research {
	public function build_trending_topics_prompt($niche, $count, $keywords = array()) {
		// Current AIPS_Research_Service::build_research_prompt() logic.
	}

	public function build_source_research_prompt($niche, $count, $keywords, $source_context) {
		// Current AIPS_Research_Service::build_source_research_prompt() logic.
	}
}
```

`AIPS_Research_Service` would gain a `$prompt_builder` property, accept an optional builder in its constructor for tests, and replace both private method calls with builder calls. This keeps the research service focused on input validation, source gathering, AI execution, topic normalization, and logging.
