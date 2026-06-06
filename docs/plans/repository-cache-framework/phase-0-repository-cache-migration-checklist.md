# Phase 0 Repository Cache Migration Checklist

This checklist is the Phase 0 tracking baseline for the repository cache framework rollout.

## Rules frozen by Phase 0

- No new repository usage of `AIPS_Cache_Policy`.
- No new repository usage of `AIPS_Cache_Invalidation_Bus`.
- New repository caching work must use explicit operation IDs with `AIPS_Cacheable_Repository`.
- The temporary legacy baseline is enforced by `composer lint:repository-boundary`.

## Current repository migration status

### Migrated to the new framework

- [x] `AIPS_Authors_Repository`
- [x] `AIPS_Author_Topics_Repository`

### Legacy repository cache baseline

- [ ] `AIPS_Article_Structure_Repository`
- [ ] `AIPS_Post_Slices_Repository`
- [ ] `AIPS_Prompt_Section_Repository`
- [ ] `AIPS_Schedule_Repository`

### Not yet using repository-level caching

- [ ] `AIPS_AI_Assistance_Repository`
- [ ] `AIPS_Author_Topic_Logs_Repository`
- [ ] `AIPS_Campaigns_Repository`
- [ ] `AIPS_Data_Management_Repository`
- [ ] `AIPS_Feedback_Repository`
- [ ] `AIPS_History_Repository`
- [ ] `AIPS_Internal_Links_Repository`
- [ ] `AIPS_Metrics_Repository`
- [ ] `AIPS_Notifications_Repository`
- [ ] `AIPS_Post_Embeddings_Repository`
- [ ] `AIPS_Post_Review_Repository`
- [ ] `AIPS_Sources_Data_Repository`
- [ ] `AIPS_Sources_Repository`
- [ ] `AIPS_Taxonomy_Repository`
- [ ] `AIPS_Telemetry_Repository`
- [ ] `AIPS_Template_Repository`
- [ ] `AIPS_Trending_Topics_Repository`
- [ ] `AIPS_Voices_Repository`

## Validation

- `composer lint:repository-boundary`
- Targeted PHPUnit: `Test_AIPS_Repository_Boundary_Check`

## Exit signal for Phase 0

Phase 0 is complete when the boundary lint blocks any new repository-level legacy cache usage and this checklist stays aligned with the temporary legacy baseline file.
