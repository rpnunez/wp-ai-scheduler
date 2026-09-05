# Project: Unified Monetization, Ads & Referral Partner Hub

## Architecture
The Unified Monetization Hub consolidates four website revenue pillars (In-Content & Sticky Ad Units, Direct Brand Sponsors, Cloaked Affiliate Links, and Partner Referral Programs) into a single premier administration hub and automated in-content delivery engine.

### Data Flow & Architecture Layers:
1. **Storage & Configuration Layer**:
   - `wp_aips_referral_programs` table (managed via `AIPS_DB_Manager` and `AIPS_DB_Migrations::migrate_to_3_7_2()`).
   - `aips_affiliate_network_profiles` option in `AIPS_Config` defining network IDs and subID tracking templates for Amazon, ShareASale, CJ, Impact, Awin, Rakuten, and Direct.
2. **Persistence & Domain Layer**:
   - `AIPS_Referral_Programs_Repository`: CRUD, filtering, slug lookup, and keyword/category matching.
   - `AIPS_Link_Cloaking_Service`: Slug resolution (`/go/{slug}/`), parameter interpolation, HTTP 307 temporary redirects with `X-Robots-Tag: noindex, nofollow, noarchive`, and click telemetry.
   - `AIPS_Monetization_Telemetry_Repository`: Atomic event aggregation in `wp_aips_monetization_events`.
3. **Presentation & Admin Layer**:
   - `AIPS_Monetization_Controller` & `AIPS_Ajax_Registry`: Secure AJAX endpoints (`wp_ajax_aips_*`) with nonce and capability verification.
   - `templates/admin/monetization.php`: 5-tab interface (Slots, Sponsors, Affiliates, Referrals & Networks, Analytics).
   - `assets/js/admin-monetization.js`: Dynamic table rendering via `AIPS.Templates`, modal CRUD, and network profile configuration.
4. **Delivery & Runtime Layer**:
   - `AIPS_Referral_Delivery_Service` / `AIPS_Ad_Frontend`: In-content promo ribbon injection on `the_content` matching post tags/categories/keywords.
   - Shortcode `[aips_referral id="..."]` and Gutenberg block `aips/referral-card`.
   - `assets/js/monetization-frontend.js`: Interactive 1-click "Copy Code" with clipboard fallback, visual feedback, and `IntersectionObserver` impression telemetry.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | DB Schema `wp_aips_referral_programs` | DDL in `AIPS_DB_Manager::get_schema()` and registered in `$tables` | M1 | R5 |
| 2 | DB Migration `migrate_to_3_7_2()` | Upgrade migration in `AIPS_DB_Migrations` seeding network profiles and sample program | M1 | R5 |
| 3 | Version Bump to 3.7.2 | Update `AIPS_VERSION` in `ai-post-scheduler.php`, `CHANGELOG.md`, `AGENTS.md` | M1 | R5 |
| 4 | Network Profiles Config | Global options for Amazon, ShareASale, CJ, Impact, Awin, Rakuten, Direct with subID tokens | M1 | R3 |
| 5 | Referral Programs Repository | `AIPS_Referral_Programs_Repository` CRUD, slug lookup, status toggles, keyword/category matching | M1 | R2 |
| 6 | Container & Bootstrap Registration | Bind repository in `AIPS_Container` and instantiate in bootstrap | M1 | R2 |
| 7 | Cloaked Referral Redirection | Update `AIPS_Link_Cloaking_Service` to resolve referral slugs, decorate subIDs, issue 307 redirect with `X-Robots-Tag` | M1 | R4 |
| 8 | Telemetry Integration for Referrals | Record referral clicks in `wp_aips_monetization_events` on cloaked redirect | M1 | R4 |
| 9 | 5-Tab Monetization Hub View | Update `templates/admin/monetization.php` to 5 tabs (Slots, Sponsors, Affiliates, Referrals, Analytics) | M2 | R1 |
| 10 | Referral Manager Table & Filter UI | Tab 4 Referral program table, network filter, status badges, and network profiles settings form | M2 | R1, R2, R3 |
| 11 | Interactive Modal CRUD | `#aips-modal-referral` for add/edit referral programs without page reload | M2 | R2 |
| 12 | AJAX Actions & Registry Map | `AIPS_Monetization_Controller` and `AIPS_Ajax_Registry` endpoints for referral CRUD, toggle, and network profile saving | M2 | R2, R3 |
| 13 | Admin JavaScript & Micro-Templates | `admin-monetization.js` integration with `AIPS.Templates`, modal handlers, and AJAX CRUD | M2 | R1, R2 |
| 14 | Automated In-Content Ribbon Delivery | Inject stylized discount ribbon / promo box into `the_content` matching post tags/categories/keywords | M3 | R4 |
| 15 | Shortcode & Gutenberg Block | `[aips_referral id="..."]` shortcode and `aips/referral-card` block with inspector controls | M3 | R4 |
| 16 | Interactive "Copy Code" Button | 1-click clipboard copy with fallback and visual feedback in `monetization-frontend.js` | M3 | R4 |
| 17 | Frontend Telemetry Tracking | Track referral ribbon impressions via `IntersectionObserver` and clicks | M3 | R4 |
| 18 | E2E Testing & Acceptance Verification | Comprehensive verification of all R1-R5 acceptance criteria | M4 | AC 1-7 |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | M1: Backend Foundation (Schema, Migration, Config, Repository & Cloaking) | `AIPS_DB_Manager`, `AIPS_DB_Migrations::migrate_to_3_7_2()`, `AIPS_Config`, `AIPS_Referral_Programs_Repository`, `AIPS_Container`, `AIPS_Link_Cloaking_Service`, version bump 3.7.2 | none | COMPLETED |
| 2 | M2: Central Monetization Hub Admin UI & AJAX Controller | `monetization.php` 5 tabs, `AIPS_Monetization_Controller`, `AIPS_Ajax_Registry`, `admin-monetization.js`, modal CRUD, `AIPS.Templates` | M1 | COMPLETED |
| 3 | M3: In-Content Delivery Engine, Shortcode, Block & Copy Code | `the_content` injection, `[aips_referral]`, Gutenberg block, `monetization-frontend.js`, CSS ribbon, impression telemetry | M1 | COMPLETED |
| 4 | M4: E2E Integration & Acceptance Verification | Full verification against acceptance criteria, static syntax checks, audit pass | M2, M3 | COMPLETED |

## Interface Contracts

### `AIPS_Referral_Programs_Repository`
- `get_by_id( int $id ): ?array`
- `get_by_slug( string $slug ): ?array`
- `get_all( array $args = array() ): array`
- `save( array $data ): int|false`
- `delete( int $id ): bool`
- `toggle_status( int $id ): bool`
- `match_programs( array $categories, array $tags, string $content ): array`

### `AIPS_Link_Cloaking_Service`
- SubID token replacement: `{post_id}`, `{slug}`, `{date}`, `{author_id}`, `{category}`
- HTTP 307 temporary redirect with header `X-Robots-Tag: noindex, nofollow, noarchive`
- Click telemetry recording via `AIPS_Monetization_Telemetry_Repository::record_event(0, $program_id, $post_id, 'click', $device_type)`

### AJAX Endpoints
- `aips_get_referral_programs`: `manage_options` check, nonce `aips_monetization_nonce`, returns JSON list
- `aips_save_referral_program`: `manage_options`, nonce check, sanitization, returns saved program JSON
- `aips_delete_referral_program`: `manage_options`, nonce check, returns success boolean
- `aips_toggle_referral_program`: `manage_options`, nonce check, returns updated status
- `aips_save_affiliate_network_profiles`: `manage_options`, nonce check, saves profiles array to option

## Code Layout
- `ai-post-scheduler/includes/class-aips-db-manager.php`: Table schema definition
- `ai-post-scheduler/includes/class-aips-db-migrations.php`: `migrate_to_3_7_2()`
- `ai-post-scheduler/includes/class-aips-config.php`: Default options for network profiles
- `ai-post-scheduler/includes/class-aips-referral-programs-repository.php`: CRUD repository
- `ai-post-scheduler/includes/class-aips-referral-delivery-service.php`: Delivery and rendering logic
- `ai-post-scheduler/includes/class-aips-link-cloaking-service.php`: Cloaked redirection and telemetry
- `ai-post-scheduler/includes/class-aips-monetization-controller.php`: Admin AJAX handlers
- `ai-post-scheduler/includes/class-aips-ajax-registry.php`: AJAX action routing map
- `ai-post-scheduler/templates/admin/monetization.php`: 5-tab admin hub template
- `ai-post-scheduler/assets/js/admin-monetization.js`: Admin JS, modal, and `AIPS.Templates`
- `ai-post-scheduler/assets/js/monetization-frontend.js`: Frontend Copy Code, impression tracking
- `ai-post-scheduler/assets/css/monetization-frontend.css`: Promo ribbon & discount card styles
- `ai-post-scheduler/assets/js/blocks/referral-card-block.js`: Gutenberg block
- `ai-post-scheduler/ai-post-scheduler.php`: Plugin bootstrap & version bump
