# AI Edit Feature - Quick Reference Card

## 📋 Overview
Custom modal editor for regenerating individual post components (title, excerpt, content, featured image) using original AI context.

## 📚 Documentation Suite

| Document | Purpose | Pages | Link |
|----------|---------|-------|------|
| **Executive Summary** | High-level overview for stakeholders | 15 | [View](./AI_EDIT_EXECUTIVE_SUMMARY.md) |
| **Technical Specification** | Complete architecture and design | 42 | [View](./AI_EDIT_FEATURE_SPECIFICATION.md) |
| **Implementation Roadmap** | Week-by-week development guide | 30 | [View](./AI_EDIT_IMPLEMENTATION_ROADMAP.md) |
| **This Document** | Quick reference for developers | 1 | You're here! |

## 🎯 Quick Facts

- **Estimated Time**: 5 weeks (120 hours)
- **New Files**: 6 (2 PHP, 1 JS, 1 CSS, 2 tests)
- **Modified Files**: 4 (template, assets loader, main plugin, readme)
- **AJAX Endpoints**: 3
- **Test Cases**: 20+ unit tests, 10+ integration tests

## 📁 File Structure

### Files to Create
```
includes/
├── class-aips-ai-edit-controller.php              [Week 1]
└── class-aips-component-regeneration-service.php  [Week 1]

assets/js/
└── admin-ai-edit.js                               [Week 2]

assets/css/
└── admin-ai-edit.css                              [Week 2]

tests/
├── test-ai-edit-controller.php                    [Week 1]
└── test-component-regeneration-service.php        [Week 1]
```

### Files to Modify
```
templates/admin/
└── generated-posts.php          → Add modal HTML + buttons [Week 2]

includes/
└── class-aips-admin-assets.php  → Enqueue assets         [Week 2]

ai-post-scheduler.php            → Register controller     [Week 1]
readme.txt                       → User documentation      [Week 5]
```

## 🔗 API Endpoints

### 1. Get Post Components
```
Action: aips_get_post_components
Input:  post_id, history_id
Output: {components, context}
```

### 2. Regenerate Component
```
Action: aips_regenerate_component
Input:  post_id, history_id, component
Output: {new_value, message}
```

### 3. Save Components
```
Action: aips_save_post_components
Input:  post_id, components{}
Output: {message, updated_components[]}
```

## 📅 5-Week Timeline

| Week | Focus | Deliverables |
|------|-------|--------------|
| **1** | Backend Infrastructure | Controller + Service + Tests |
| **2** | Frontend Modal | HTML + CSS + JS + Asset Loading |
| **3** | Regeneration Logic | All 4 component types working |
| **4** | Save & Integration | Persistence + End-to-end testing |
| **5** | Testing & Docs | QA + User guide + Dev guide |

## 🛠️ Development Commands

```bash
# Create feature branch
git checkout -b feature/ai-edit-implementation

# Run specific test
composer test ai-post-scheduler/tests/test-ai-edit-controller.php

# Run all tests
composer test

# Check syntax
find ai-post-scheduler/includes -name "*.php" -exec php -l {} \;
```

## ✅ Daily Checklist Template

```
[ ] Review spec section for today's tasks
[ ] Write code following WordPress standards
[ ] Add inline documentation
[ ] Write/update unit tests
[ ] Run tests (should pass)
[ ] Manual testing in browser
[ ] Commit with clear message
[ ] Update progress in tracker
```

## 🎨 UI Components in Modal

```
┌──────────────────────────────────────┐
│ AI Edit Post                      [×]│
├──────────────────────────────────────┤
│ Generation Context                   │
│ Template: Blog Post | Author: John   │
│ Topic: Technology Trends             │
├──────────────────────────────────────┤
│ [Title Section]    [Re-generate]     │
│ ┌──────────────────────────────────┐ │
│ │ Current title here...            │ │
│ └──────────────────────────────────┘ │
├──────────────────────────────────────┤
│ [Excerpt Section]  [Re-generate]     │
│ [Content Section]  [Re-generate]     │
│ [Image Section]    [Re-generate]     │
├──────────────────────────────────────┤
│              [Cancel] [Save Changes] │
└──────────────────────────────────────┘
```

## 🔒 Security Checklist

- [ ] Nonce verification on all AJAX endpoints
- [ ] Capability check: `current_user_can('edit_posts')`
- [ ] Post-specific permission: `current_user_can('edit_post', $id)`
- [ ] Input sanitization: `sanitize_text_field()`, `absint()`
- [ ] Output escaping: `esc_html()`, `esc_attr()`, `esc_url()`
- [ ] SQL injection prevention: Use repositories with prepared statements
- [ ] XSS prevention: `wp_kses_post()` for content

## 📊 Performance Targets

| Operation | Target | Acceptable |
|-----------|--------|------------|
| Load modal | <500ms | <1s |
| Get components | <500ms | <1s |
| Regenerate title | <3s | <5s |
| Regenerate excerpt | <5s | <8s |
| Regenerate content | <10s | <15s |
| Regenerate image | <15s | <20s |
| Save changes | <1s | <2s |

## 🧪 Testing Priority

### High Priority (Must Test)
1. ✅ Modal opens from both tabs
2. ✅ All 4 components regenerate
3. ✅ Save persists to WordPress
4. ✅ Permission checks work
5. ✅ Error handling (AI unavailable)

### Medium Priority (Should Test)
6. ⚠️ Cancel with unsaved changes
7. ⚠️ Multiple regenerations in sequence
8. ⚠️ Long content handling
9. ⚠️ Browser compatibility
10. ⚠️ Responsive design

### Low Priority (Nice to Test)
11. ℹ️ Rapid button clicking
12. ℹ️ Network timeout scenarios
13. ℹ️ Concurrent user editing
14. ℹ️ Screen reader accessibility

## 🐛 Common Issues & Solutions

### Modal doesn't open
→ Check console for JS errors, verify assets enqueued

### AJAX returns 403
→ Verify nonce, check user permissions

### Regeneration hangs
→ Check AI Engine status, add timeout handling

### Image doesn't display
→ Verify attachment exists, check URL

### Content truncated
→ Check textarea size, verify DB field capacity

## 📞 Quick Contact

- **Spec Questions**: Review [Technical Specification](./AI_EDIT_FEATURE_SPECIFICATION.md)
- **Implementation Questions**: Review [Implementation Roadmap](./AI_EDIT_IMPLEMENTATION_ROADMAP.md)
- **Blockers**: Escalate to project lead
- **Bugs**: Create GitHub issue with `ai-edit` label

## 🚀 Getting Started Right Now

1. **Read this card** (you're doing it! ✅)
2. **Skim executive summary** (15 pages, 20 minutes)
3. **Read Week 1 of roadmap** (detailed tasks for first week)
4. **Create feature branch**: `git checkout -b feature/ai-edit-implementation`
5. **Create controller file**: `includes/class-aips-ai-edit-controller.php`
6. **Start coding!** 🎉

## 💡 Key Success Factors

1. **Follow the spec closely** - It's comprehensive for a reason
2. **Test as you go** - Don't wait until the end
3. **Commit frequently** - Small, focused commits
4. **Ask early** - Don't get blocked unnecessarily
5. **Document decisions** - Update spec if things change

## 📈 Progress Tracking

Update this weekly:

```
Week 1: [    ] 0%  ← Update to [████] 100% when complete
Week 2: [    ] 0%
Week 3: [    ] 0%
Week 4: [    ] 0%
Week 5: [    ] 0%

Overall: ▱▱▱▱▱▱▱▱▱▱ 0%
```

## 🎓 Key WordPress Patterns Used

```php
// AJAX Handler Pattern
add_action('wp_ajax_action_name', array($this, 'handler'));
check_ajax_referer('aips_ajax_nonce', 'nonce');
wp_send_json_success($data);

// Repository Pattern
$repository = new AIPS_History_Repository();
$item = $repository->get_by_id($id);

// Service Pattern
$service = new AIPS_Component_Regeneration_Service();
$result = $service->regenerate_title($context);

// Asset Enqueuing
wp_enqueue_script('handle', $url, $deps, $ver, $in_footer);
wp_localize_script('handle', 'object', $data);
```

## 🔑 Key Classes to Understand

### Existing (Reuse These)
- `AIPS_Generator` - Core content generation
- `AIPS_Prompt_Builder` - Builds AI prompts
- `AIPS_Image_Service` - Image generation
- `AIPS_History_Repository` - Generation history
- `AIPS_Post_Review_Repository` - Draft posts

### New (You're Creating These)
- `AIPS_AI_Edit_Controller` - AJAX endpoints
- `AIPS_Component_Regeneration_Service` - Business logic

## 📝 Commit Message Template

```
[ai-edit] Brief description of change

- Detail 1
- Detail 2
- Detail 3

Relates to AI Edit feature implementation
Week X, Day Y
```

## ✨ After Completion

- [ ] Demo the feature to team
- [ ] Write user guide
- [ ] Create demo video
- [ ] Plan Phase 2 features
- [ ] Celebrate! 🎉

---

**Version**: 1.0  
**Last Updated**: 2026-02-09  
**Print This**: Keep handy during development!

