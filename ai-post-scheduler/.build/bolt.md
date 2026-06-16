## 2024-06-08 - Hoist get_option in Generated Posts Controller
**Area:** ai-post-scheduler/includes/class-aips-generated-posts-controller.php
**Status:** opened PR
**PR:** ⚡ Bolt: Hoist date and time format options outside of loops in generated posts controller
**Learning:** Hoisting get_option('date_format') and get_option('time_format') out of loops reduces redundant DB queries and function calls.
**Action:** Always check loops for repeated WP option calls and extract them into variables.

## 2026-10-14 - Hoist get_option in Campaigns Admin Templates
**Area:** ai-post-scheduler/templates/admin/campaigns.php, campaign-detail.php, campaign-wizard.php
**Status:** opened PR
**PR:** ⚡ Bolt: Hoist repeated get_option calls outside loops in campaigns admin templates
**Learning:** Hoisting get_option calls out of foreach loops and rendering closures reduces redundant database queries and function calls.
**Action:** Always extract get_option and config queries outside loops and pass them to variables.
