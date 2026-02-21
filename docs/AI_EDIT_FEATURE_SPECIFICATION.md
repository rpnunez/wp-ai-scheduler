# AI Edit Feature - Technical Specification

## Overview

The AI Edit feature introduces a new custom post editor modal that allows administrators to regenerate individual components of AI-generated posts without using the default WordPress post editor. This provides granular control over post generation and allows iterative refinement of content.

**Version:** 1.0  
**Status:** Draft Specification  
**Created:** 2026-02-09  
**Author:** AI Post Scheduler Team

---

## Table of Contents

1. [Business Requirements](#business-requirements)
2. [User Stories](#user-stories)
3. [System Architecture](#system-architecture)
4. [Data Model](#data-model)
5. [API Specifications](#api-specifications)
6. [UI/UX Design](#uiux-design)
7. [Implementation Phases](#implementation-phases)
8. [Testing Strategy](#testing-strategy)
9. [Security Considerations](#security-considerations)
10. [Performance Considerations](#performance-considerations)
11. [Future Enhancements](#future-enhancements)

---

## Business Requirements

### Primary Goals

1. **Granular Post Control**: Allow admins to regenerate individual post components (title, excerpt, content, featured image) without regenerating the entire post
2. **Preserve Context**: Use the original generation context (template, author, topic) to maintain consistency
3. **Non-Destructive Editing**: Allow iterative refinement while preserving the original post structure
4. **Accessibility**: Provide access from both Generated Posts and Pending Review tabs

### Success Criteria

- Admins can open the AI Edit modal from any post in the Generated Posts or Pending Review tabs
- All post components are visible and can be regenerated individually
- Regeneration uses the original context (template, author, topic) stored in the history record
- The UI provides clear feedback during regeneration (loading states, success/error messages)
- The modal can be closed without saving changes (preview mode)

### Out of Scope (Phase 1)

- Prompt customization before regeneration
- Article structure section-level regeneration
- Component regeneration history tracking
- Undo/redo functionality
- Live preview of regenerated content

---

## User Stories

### Story 1: Access AI Edit from Generated Posts
**As an** admin  
**I want to** click an "AI Edit" button next to a generated post  
**So that** I can access the custom post editor modal

**Acceptance Criteria:**
- "AI Edit" button appears in the Actions column of Generated Posts table
- Button is clearly labeled and visually distinct from other buttons
- Clicking the button opens the AI Edit modal
- Modal loads the current post data

### Story 2: Access AI Edit from Pending Review
**As an** admin  
**I want to** click an "AI Edit" button next to a draft post in Pending Review  
**So that** I can refine the post before publishing

**Acceptance Criteria:**
- "AI Edit" button appears in the Actions column of Pending Review table
- Button follows the same visual design as in Generated Posts tab
- Clicking the button opens the AI Edit modal with draft post data

### Story 3: View Post Components in Modal
**As an** admin  
**I want to** see all post components (title, excerpt, content, featured image) in the modal  
**So that** I can review the current state before regenerating

**Acceptance Criteria:**
- Modal displays the current title in an editable text field
- Modal displays the current excerpt in a textarea
- Modal displays the current content in a textarea or rich text preview
- Modal displays the current featured image (if exists)
- Each component section is clearly labeled

### Story 4: Regenerate Individual Components
**As an** admin  
**I want to** click a "Re-generate" button next to each component  
**So that** I can regenerate just that component using the original context

**Acceptance Criteria:**
- Each component has a "Re-generate" button
- Clicking the button triggers regeneration for that component only
- Original template, author, and topic data are used for regeneration
- Loading indicator appears during regeneration
- Success/error feedback is displayed
- Regenerated content replaces the existing component in the modal

### Story 5: Save or Cancel Changes
**As an** admin  
**I want to** save my regenerated changes or cancel the modal  
**So that** I have control over whether changes are applied to the post

**Acceptance Criteria:**
- Modal has a "Save Changes" button to update the post
- Modal has a "Cancel" or "Close" button to discard changes
- Closing the modal without saving does not update the post
- Saving updates the WordPress post with the new component values

---

## System Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    WordPress Admin UI                   │
│  ┌───────────────┐            ┌───────────────┐        │
│  │Generated Posts│            │Pending Review │        │
│  │     Tab       │            │     Tab       │        │
│  └───────┬───────┘            └───────┬───────┘        │
│          │                            │                 │
│          │   [AI Edit Button]         │                 │
│          └────────────┬───────────────┘                 │
│                       ▼                                  │
│            ┌─────────────────────┐                      │
│            │  AI Edit Modal      │                      │
│            │  - Title            │                      │
│            │  - Excerpt          │                      │
│            │  - Content          │                      │
│            │  - Featured Image   │                      │
│            └──────────┬──────────┘                      │
│                       │                                  │
└───────────────────────┼──────────────────────────────────┘
                        │
                        ▼ AJAX
┌─────────────────────────────────────────────────────────┐
│                   Backend (PHP)                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │     AIPS_AI_Edit_Controller                      │  │
│  │  - ajax_get_post_components()                    │  │
│  │  - ajax_regenerate_component()                   │  │
│  │  - ajax_save_post_components()                   │  │
│  └───────────────┬──────────────────────────────────┘  │
│                  │                                       │
│                  ▼                                       │
│  ┌──────────────────────────────────────────────────┐  │
│  │     AIPS_Component_Regeneration_Service          │  │
│  │  - regenerate_title()                            │  │
│  │  - regenerate_excerpt()                          │  │
│  │  - regenerate_content()                          │  │
│  │  - regenerate_featured_image()                   │  │
│  └───────────────┬──────────────────────────────────┘  │
│                  │                                       │
│                  ▼                                       │
│  ┌──────────────────────────────────────────────────┐  │
│  │     AIPS_Generator (existing)                    │  │
│  │  - generate() for content                        │  │
│  │  - AIPS_Image_Service for images                 │  │
│  └───────────────┬──────────────────────────────────┘  │
│                  │                                       │
│                  ▼                                       │
│  ┌──────────────────────────────────────────────────┐  │
│  │     AIPS_History_Repository (existing)           │  │
│  │  - get_by_id() to fetch original context        │  │
│  │  - get_by_post_id() to fetch history            │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### Component Diagram

```
Frontend (JavaScript)
├── admin-ai-edit.js (new)
│   ├── Modal initialization
│   ├── Component display
│   ├── Regeneration handlers
│   └── Save/Cancel logic
│
Backend (PHP)
├── class-aips-ai-edit-controller.php (new)
│   ├── AJAX endpoint registration
│   ├── Permission checks
│   └── Response formatting
│
├── class-aips-component-regeneration-service.php (new)
│   ├── Component regeneration logic
│   ├── Context reconstruction
│   └── Error handling
│
└── Existing Classes (reused)
    ├── AIPS_Generator
    ├── AIPS_Prompt_Builder
    ├── AIPS_Image_Service
    ├── AIPS_History_Repository
    └── AIPS_Post_Review_Repository
```

### Class Responsibilities

#### AIPS_AI_Edit_Controller
- **Purpose**: Handle AJAX requests for the AI Edit modal
- **Responsibilities**:
  - Fetch post component data with original generation context
  - Trigger component regeneration via service class
  - Update WordPress posts with new component values
  - Validate permissions and nonces
  - Format responses for frontend consumption

#### AIPS_Component_Regeneration_Service
- **Purpose**: Business logic for regenerating individual post components
- **Responsibilities**:
  - Reconstruct generation context from history record
  - Build appropriate prompts for each component type
  - Invoke AI services for regeneration
  - Handle errors and fallbacks
  - Log regeneration activities

---

## Data Model

### Existing Data Sources

#### wp_aips_history table (existing)
This table already stores the complete generation context. We'll query it to retrieve:

```php
// Fields we need from history:
- id                  // History record ID
- post_id            // WordPress post ID
- template_id        // Template used for generation
- author_id          // Author used for generation (if applicable)
- topic_id           // Topic used for generation (if applicable)
- structure_id       // Article structure used (if applicable)
- generated_title    // Original generated title
- generation_log     // JSON with prompts and AI calls
```

#### wp_posts table (existing, WordPress core)
Current post data:
```php
- ID
- post_title
- post_excerpt
- post_content
- post_status
```

#### wp_postmeta table (existing, WordPress core)
Featured image:
```php
- _thumbnail_id      // Featured image attachment ID
```

### New Data Structures (In-Memory Only, Phase 1)

For Phase 1, we don't add new database tables. Component regeneration history will be tracked in the existing `wp_aips_history_log` table.

#### Component Regeneration Context (PHP Array)
```php
array(
    'post_id' => 123,
    'history_id' => 456,
    'component' => 'title', // or 'excerpt', 'content', 'featured_image'
    'template_id' => 10,
    'author_id' => 5,
    'topic_id' => 20,
    'structure_id' => 3,
    'original_value' => 'Current Title',
    'template_data' => array(...), // Full template object
    'author_data' => array(...),   // Full author object (if exists)
    'topic_data' => array(...),    // Full topic object (if exists)
)
```

---

## API Specifications

### AJAX Endpoints

#### 1. Get Post Components
**Endpoint**: `wp-admin/admin-ajax.php?action=aips_get_post_components`

**Method**: POST

**Request Parameters**:
```javascript
{
  action: 'aips_get_post_components',
  post_id: 123,           // WordPress post ID
  history_id: 456,        // Optional: History ID for faster lookup
  nonce: '...'
}
```

**Response (Success)**:
```javascript
{
  success: true,
  data: {
    post_id: 123,
    history_id: 456,
    components: {
      title: {
        value: "Current Post Title",
        can_regenerate: true
      },
      excerpt: {
        value: "Current post excerpt...",
        can_regenerate: true
      },
      content: {
        value: "Full post content...",
        can_regenerate: true
      },
      featured_image: {
        url: "https://example.com/image.jpg",
        attachment_id: 789,
        can_regenerate: true
      }
    },
    context: {
      template_id: 10,
      template_name: "Blog Post Template",
      author_id: 5,
      author_name: "John Doe",
      topic_id: 20,
      topic_title: "Technology Trends",
      structure_id: 3,
      structure_name: "Standard Article"
    }
  }
}
```

**Response (Error)**:
```javascript
{
  success: false,
  data: {
    message: "Post not found or no history available."
  }
}
```

**PHP Handler**:
```php
public function ajax_get_post_components() {
    check_ajax_referer('aips_ajax_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Permission denied.'));
    }
    
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
    
    // Fetch post and history
    // Build response
    // wp_send_json_success($data);
}
```

---

#### 2. Regenerate Component
**Endpoint**: `wp-admin/admin-ajax.php?action=aips_regenerate_component`

**Method**: POST

**Request Parameters**:
```javascript
{
  action: 'aips_regenerate_component',
  post_id: 123,
  history_id: 456,
  component: 'title',     // One of: title, excerpt, content, featured_image
  nonce: '...'
}
```

**Response (Success)**:
```javascript
{
  success: true,
  data: {
    component: 'title',
    new_value: "Regenerated Post Title",
    message: "Title regenerated successfully."
  }
}
```

**Response (Error)**:
```javascript
{
  success: false,
  data: {
    component: 'title',
    message: "Failed to regenerate title: AI service unavailable."
  }
}
```

**PHP Handler**:
```php
public function ajax_regenerate_component() {
    check_ajax_referer('aips_ajax_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Permission denied.'));
    }
    
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
    $component = isset($_POST['component']) ? sanitize_text_field($_POST['component']) : '';
    
    // Validate component type
    $valid_components = array('title', 'excerpt', 'content', 'featured_image');
    if (!in_array($component, $valid_components)) {
        wp_send_json_error(array('message' => 'Invalid component type.'));
    }
    
    // Regenerate via service
    // Return new value
    // wp_send_json_success($data);
}
```

---

#### 3. Save Post Components
**Endpoint**: `wp-admin/admin-ajax.php?action=aips_save_post_components`

**Method**: POST

**Request Parameters**:
```javascript
{
  action: 'aips_save_post_components',
  post_id: 123,
  components: {
    title: "Updated Title",
    excerpt: "Updated excerpt...",
    content: "Updated content...",
    featured_image_id: 789  // Attachment ID or 0 to remove
  },
  nonce: '...'
}
```

**Response (Success)**:
```javascript
{
  success: true,
  data: {
    message: "Post updated successfully.",
    updated_components: ['title', 'excerpt']
  }
}
```

**Response (Error)**:
```javascript
{
  success: false,
  data: {
    message: "Failed to update post."
  }
}
```

---

## UI/UX Design

### Modal Structure

#### HTML Template (in generated-posts.php)
```html
<!-- AI Edit Modal -->
<div id="aips-ai-edit-modal" class="aips-modal" style="display: none;">
    <div class="aips-modal-overlay"></div>
    <div class="aips-modal-content aips-modal-large">
        <div class="aips-modal-header">
            <h2><?php esc_html_e('AI Edit Post', 'ai-post-scheduler'); ?></h2>
            <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">×</button>
        </div>
        <div class="aips-modal-body">
            <!-- Loading State -->
            <div class="aips-ai-edit-loading">
                <span class="spinner is-active"></span>
                <p><?php esc_html_e('Loading post data...', 'ai-post-scheduler'); ?></p>
            </div>
            
            <!-- Main Content (hidden until loaded) -->
            <div class="aips-ai-edit-content" style="display: none;">
                <!-- Context Info -->
                <div class="aips-ai-edit-context">
                    <h3><?php esc_html_e('Generation Context', 'ai-post-scheduler'); ?></h3>
                    <div class="aips-context-info">
                        <span class="aips-context-item">
                            <strong><?php esc_html_e('Template:', 'ai-post-scheduler'); ?></strong>
                            <span id="aips-context-template"></span>
                        </span>
                        <span class="aips-context-item">
                            <strong><?php esc_html_e('Author:', 'ai-post-scheduler'); ?></strong>
                            <span id="aips-context-author"></span>
                        </span>
                        <span class="aips-context-item">
                            <strong><?php esc_html_e('Topic:', 'ai-post-scheduler'); ?></strong>
                            <span id="aips-context-topic"></span>
                        </span>
                    </div>
                </div>
                
                <!-- Title Component -->
                <div class="aips-component-section">
                    <div class="aips-component-header">
                        <h3><?php esc_html_e('Title', 'ai-post-scheduler'); ?></h3>
                        <button class="button aips-regenerate-btn" data-component="title">
                            <?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                    <div class="aips-component-body">
                        <input type="text" id="aips-component-title" class="large-text" readonly>
                        <span class="aips-component-status"></span>
                    </div>
                </div>
                
                <!-- Excerpt Component -->
                <div class="aips-component-section">
                    <div class="aips-component-header">
                        <h3><?php esc_html_e('Excerpt', 'ai-post-scheduler'); ?></h3>
                        <button class="button aips-regenerate-btn" data-component="excerpt">
                            <?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                    <div class="aips-component-body">
                        <textarea id="aips-component-excerpt" rows="3" readonly></textarea>
                        <span class="aips-component-status"></span>
                    </div>
                </div>
                
                <!-- Content Component -->
                <div class="aips-component-section">
                    <div class="aips-component-header">
                        <h3><?php esc_html_e('Content', 'ai-post-scheduler'); ?></h3>
                        <button class="button aips-regenerate-btn" data-component="content">
                            <?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                    <div class="aips-component-body">
                        <textarea id="aips-component-content" rows="15" readonly></textarea>
                        <span class="aips-component-status"></span>
                    </div>
                </div>
                
                <!-- Featured Image Component -->
                <div class="aips-component-section">
                    <div class="aips-component-header">
                        <h3><?php esc_html_e('Featured Image', 'ai-post-scheduler'); ?></h3>
                        <button class="button aips-regenerate-btn" data-component="featured_image">
                            <?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                    <div class="aips-component-body">
                        <div id="aips-component-image-container">
                            <img id="aips-component-image" src="" alt="" style="max-width: 300px; display: none;">
                            <p id="aips-component-image-none"><?php esc_html_e('No featured image', 'ai-post-scheduler'); ?></p>
                        </div>
                        <span class="aips-component-status"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="aips-modal-footer">
            <button class="button button-large" id="aips-ai-edit-cancel">
                <?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
            </button>
            <button class="button button-primary button-large" id="aips-ai-edit-save">
                <?php esc_html_e('Save Changes', 'ai-post-scheduler'); ?>
            </button>
        </div>
    </div>
</div>
```

### CSS Styles

```css
/* AI Edit Modal Specific Styles */
.aips-ai-edit-content {
    max-height: 70vh;
    overflow-y: auto;
}

.aips-ai-edit-context {
    background: #f0f0f1;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.aips-context-info {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.aips-context-item {
    font-size: 13px;
}

.aips-component-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
}

.aips-component-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    border-bottom: 1px solid #dcdcde;
    padding-bottom: 10px;
}

.aips-component-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.aips-component-body {
    position: relative;
}

.aips-component-body input,
.aips-component-body textarea {
    width: 100%;
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
}

.aips-component-status {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    font-style: italic;
}

.aips-component-status.success {
    color: #00a32a;
}

.aips-component-status.error {
    color: #d63638;
}

.aips-regenerate-btn.regenerating {
    cursor: not-allowed;
    opacity: 0.6;
}

.aips-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px;
    border-top: 1px solid #dcdcde;
}
```

### JavaScript Module Structure

```javascript
// admin-ai-edit.js
(function($) {
    'use strict';
    
    // Module state
    const state = {
        postId: null,
        historyId: null,
        components: {},
        changedComponents: new Set()
    };
    
    // Initialize module
    function init() {
        bindEvents();
    }
    
    // Bind UI events
    function bindEvents() {
        $(document).on('click', '.aips-ai-edit-btn', openModal);
        $(document).on('click', '#aips-ai-edit-cancel, .aips-modal-close', closeModal);
        $(document).on('click', '#aips-ai-edit-save', saveChanges);
        $(document).on('click', '.aips-regenerate-btn', regenerateComponent);
    }
    
    // Open modal and load post data
    function openModal(e) {
        e.preventDefault();
        const $btn = $(this);
        state.postId = $btn.data('post-id');
        state.historyId = $btn.data('history-id');
        
        $('#aips-ai-edit-modal').show();
        loadPostComponents();
    }
    
    // Load post component data via AJAX
    function loadPostComponents() {
        // Show loading state
        // AJAX call to aips_get_post_components
        // Populate modal with data
        // Hide loading, show content
    }
    
    // Regenerate a single component
    function regenerateComponent(e) {
        e.preventDefault();
        const $btn = $(this);
        const component = $btn.data('component');
        
        // Disable button
        $btn.prop('disabled', true).text('Regenerating...');
        
        // AJAX call to aips_regenerate_component
        // Update component value in modal
        // Mark component as changed
        // Re-enable button
    }
    
    // Save all changed components
    function saveChanges() {
        // Collect changed components
        // AJAX call to aips_save_post_components
        // Show success message
        // Close modal
        // Refresh page/row
    }
    
    // Close modal
    function closeModal() {
        if (state.changedComponents.size > 0) {
            if (!confirm('You have unsaved changes. Are you sure?')) {
                return;
            }
        }
        $('#aips-ai-edit-modal').hide();
        resetState();
    }
    
    // Initialize on document ready
    $(document).ready(init);
    
})(jQuery);
```

### User Flow

1. **Admin views Generated Posts or Pending Review tab**
   - Each row has an "AI Edit" button next to existing action buttons
   
2. **Admin clicks "AI Edit" button**
   - Modal opens with loading spinner
   - AJAX request fetches post data and generation context
   - Modal displays all components and context info
   
3. **Admin reviews components**
   - All components are visible but read-only
   - "Re-generate" button next to each component
   - Context info shows template, author, topic used
   
4. **Admin clicks "Re-generate" for a component** (e.g., Title)
   - Button shows "Regenerating..." state
   - AJAX request triggers regeneration on server
   - Server reconstructs original context and generates new value
   - New value replaces old value in modal
   - Component marked as changed (visual indicator)
   - Success/error message displayed
   
5. **Admin regenerates other components as needed**
   - Process repeats for excerpt, content, featured image
   - Each regeneration is independent
   
6. **Admin saves or cancels**
   - **Save**: Updates WordPress post with all changed components
   - **Cancel**: Discards all changes, closes modal
   - If changes exist and Cancel clicked, confirmation prompt appears

---

## Implementation Phases

### Phase 1: Backend Infrastructure (Week 1)
**Goal**: Create server-side logic for component fetching and regeneration

**Tasks**:
1. Create `class-aips-ai-edit-controller.php`
   - Add constructor with AJAX action registration
   - Implement `ajax_get_post_components()` method
   - Implement `ajax_regenerate_component()` method
   - Implement `ajax_save_post_components()` method
   - Add permission checks and nonce validation
   
2. Create `class-aips-component-regeneration-service.php`
   - Implement `get_generation_context($history_id)` method
   - Implement `regenerate_title($context)` method
   - Implement `regenerate_excerpt($context)` method
   - Implement `regenerate_content($context)` method
   - Implement `regenerate_featured_image($context)` method
   - Add error handling and logging
   
3. Register controller in `ai-post-scheduler.php`
   - Instantiate controller in initialization
   
**Deliverables**:
- Working AJAX endpoints
- Unit tests for service methods
- Backend logging of regeneration activities

---

### Phase 2: Frontend Modal Structure (Week 2)
**Goal**: Create modal UI and component display logic

**Tasks**:
1. Update `templates/admin/generated-posts.php`
   - Add AI Edit modal HTML structure
   - Add "AI Edit" button to Generated Posts table
   - Add "AI Edit" button to Pending Review table
   
2. Create `assets/js/admin-ai-edit.js`
   - Initialize modal open/close handlers
   - Implement `loadPostComponents()` function
   - Implement component display logic
   - Add loading states and error handling
   
3. Create `assets/css/admin-ai-edit.css`
   - Style modal layout
   - Style component sections
   - Add responsive design
   - Add loading and status indicators
   
4. Update `class-aips-admin-assets.php`
   - Enqueue new JS file on generated-posts page
   - Enqueue new CSS file
   - Localize script with translations and AJAX URL
   
**Deliverables**:
- Functional modal that opens and displays post data
- Styled UI matching WordPress admin design
- Loading states for async operations

---

### Phase 3: Component Regeneration Logic (Week 3)
**Goal**: Implement regeneration for each component type

**Tasks**:
1. Implement title regeneration
   - Reuse existing title prompt building
   - Call AI service
   - Return new title
   
2. Implement excerpt regeneration
   - Reuse existing excerpt prompt building
   - Call AI service
   - Return new excerpt
   
3. Implement content regeneration
   - Reuse existing content prompt building
   - Handle article structures if present
   - Call AI service
   - Return new content
   
4. Implement featured image regeneration
   - Reuse existing image prompt building
   - Call image generation service
   - Upload image to media library
   - Return attachment ID and URL
   
5. Update `admin-ai-edit.js`
   - Implement `regenerateComponent()` function
   - Handle loading states per component
   - Display success/error messages
   - Mark components as changed
   
**Deliverables**:
- All four component types can be regenerated
- Real-time feedback in modal
- Error handling for failed generations

---

### Phase 4: Save and Update Logic (Week 4)
**Goal**: Persist regenerated components to WordPress posts

**Tasks**:
1. Implement save logic in controller
   - Validate changed components
   - Update post title, excerpt, content via `wp_update_post()`
   - Update featured image via `set_post_thumbnail()`
   - Return success/error response
   
2. Update `admin-ai-edit.js`
   - Implement `saveChanges()` function
   - Collect all changed component values
   - Send AJAX save request
   - Handle success: close modal, refresh row, show notice
   - Handle error: display error message, keep modal open
   
3. Add change tracking
   - Track which components have been regenerated
   - Show visual indicator for changed components
   - Confirm before closing if unsaved changes exist
   
**Deliverables**:
- Regenerated components persist to WordPress database
- User feedback on successful save
- Unsaved changes confirmation

---

### Phase 5: Testing and Polish (Week 5)
**Goal**: Test all functionality and refine UX

**Tasks**:
1. Manual testing
   - Test on Generated Posts tab
   - Test on Pending Review tab
   - Test all component regenerations
   - Test save and cancel flows
   - Test error scenarios (AI unavailable, network errors)
   
2. Cross-browser testing
   - Chrome, Firefox, Safari, Edge
   - Test responsive design on smaller screens
   
3. Unit and integration tests
   - Test controller AJAX methods
   - Test service regeneration methods
   - Test context reconstruction
   
4. UX refinements
   - Improve loading indicators
   - Refine error messages
   - Add tooltips/help text
   - Optimize modal performance
   
5. Documentation
   - Update user guide
   - Document API endpoints
   - Add code comments
   
**Deliverables**:
- Fully tested feature
- Updated documentation
- Bug fixes from testing

---

## Testing Strategy

### Unit Tests (PHPUnit)

#### Test: Controller Permission Checks
```php
public function test_ajax_get_post_components_requires_permission() {
    // Create user without edit_posts capability
    $user_id = $this->factory->user->create(array('role' => 'subscriber'));
    wp_set_current_user($user_id);
    
    // Attempt to call endpoint
    $result = $this->controller->ajax_get_post_components();
    
    // Assert error response
    $this->assertInstanceOf(WP_Error::class, $result);
}
```

#### Test: Service Context Reconstruction
```php
public function test_get_generation_context_returns_complete_context() {
    // Create history record with template, author, topic
    $history_id = $this->create_test_history();
    
    // Get context
    $context = $this->service->get_generation_context($history_id);
    
    // Assert context has required fields
    $this->assertArrayHasKey('template_id', $context);
    $this->assertArrayHasKey('author_id', $context);
    $this->assertArrayHasKey('topic_id', $context);
}
```

#### Test: Title Regeneration
```php
public function test_regenerate_title_returns_new_title() {
    // Mock context
    $context = $this->get_mock_context();
    
    // Mock AI service to return specific title
    $mock_ai_service = $this->createMock(AIPS_AI_Service::class);
    $mock_ai_service->expects($this->once())
        ->method('generate')
        ->willReturn('New Generated Title');
    
    // Inject mock
    $service = new AIPS_Component_Regeneration_Service($mock_ai_service);
    
    // Regenerate
    $result = $service->regenerate_title($context);
    
    // Assert
    $this->assertEquals('New Generated Title', $result);
}
```

### Integration Tests

#### Test: End-to-End Component Regeneration
```php
public function test_complete_regeneration_flow() {
    // Create post with history
    $post_id = $this->factory->post->create();
    $history_id = $this->create_history_for_post($post_id);
    
    // Call regenerate endpoint
    $_POST = array(
        'post_id' => $post_id,
        'history_id' => $history_id,
        'component' => 'title',
        'nonce' => wp_create_nonce('aips_ajax_nonce'),
    );
    
    $response = $this->controller->ajax_regenerate_component();
    
    // Assert success
    $this->assertTrue($response['success']);
    $this->assertArrayHasKey('new_value', $response['data']);
}
```

### Manual Test Cases

#### Test Case 1: Open Modal from Generated Posts
**Steps**:
1. Navigate to Generated Posts tab
2. Click "AI Edit" button on any post
3. Observe modal opens
4. Observe loading indicator appears
5. Observe post data loads after brief delay

**Expected Result**: Modal opens and displays post components

---

#### Test Case 2: Regenerate Title
**Steps**:
1. Open AI Edit modal
2. Click "Re-generate" button next to Title
3. Observe button changes to "Regenerating..."
4. Wait for response

**Expected Result**: 
- New title appears in title field
- Button returns to "Re-generate"
- Success message displays

---

#### Test Case 3: Save Changes
**Steps**:
1. Open AI Edit modal
2. Regenerate title
3. Click "Save Changes"
4. Observe modal closes
5. Check post in WordPress editor

**Expected Result**: 
- Post title updated to new value
- Success notice appears
- Modal closes automatically

---

#### Test Case 4: Cancel with Unsaved Changes
**Steps**:
1. Open AI Edit modal
2. Regenerate title (don't save)
3. Click "Cancel"

**Expected Result**: 
- Confirmation prompt appears: "You have unsaved changes. Are you sure?"
- If confirmed, modal closes without saving
- If cancelled, modal remains open

---

#### Test Case 5: Error Handling - AI Unavailable
**Steps**:
1. Disable AI Engine plugin
2. Open AI Edit modal
3. Click "Re-generate" on any component

**Expected Result**: 
- Error message: "AI service unavailable"
- Component value unchanged
- Button returns to enabled state

---

## Security Considerations

### Authentication & Authorization
- All AJAX endpoints must verify nonces: `check_ajax_referer('aips_ajax_nonce', 'nonce')`
- All endpoints must check `current_user_can('edit_posts')` capability
- Post ID and History ID must be validated and sanitized
- Verify user has permission to edit the specific post

### Input Validation
- Sanitize all user inputs: `sanitize_text_field()`, `absint()`, etc.
- Validate component types against whitelist: `array('title', 'excerpt', 'content', 'featured_image')`
- Escape all outputs: `esc_html()`, `esc_attr()`, `esc_url()`

### SQL Injection Prevention
- Use repository pattern with prepared statements (existing pattern)
- Never concatenate user input into SQL queries
- Use `$wpdb->prepare()` for all dynamic queries

### XSS Prevention
- Escape all dynamic content in templates
- Use `wp_kses_post()` for content that may contain HTML
- Sanitize data before storing in database

### CSRF Protection
- Use WordPress nonces for all AJAX requests
- Verify nonces on server side before processing
- Generate new nonce for each request

### Rate Limiting
- Consider implementing rate limiting for regeneration requests
- Track regeneration attempts per user/session
- Prevent abuse of AI service calls

---

## Performance Considerations

### Optimization Strategies

#### 1. Lazy Load Modal Content
- Modal HTML is in DOM but hidden
- Component data loaded only when modal opens
- Reduces initial page load time

#### 2. Caching Generation Context
- Cache template, author, topic objects for request duration
- Avoid N+1 queries when fetching context
- Use existing repository caching mechanisms

#### 3. Async Regeneration
- Each component regeneration is independent
- Allow parallel regeneration of multiple components in future
- Use JavaScript Promises for better UX

#### 4. Progressive Enhancement
- Modal works without JavaScript (fallback to edit post page)
- Loading states prevent duplicate submissions
- Graceful degradation for older browsers

#### 5. Image Optimization
- Featured image regeneration can be slow
- Show progress indicator specific to image generation
- Consider background job for large images (future)

### Performance Metrics

**Target Response Times**:
- Load post components: < 500ms
- Regenerate title: < 3s
- Regenerate excerpt: < 5s
- Regenerate content: < 10s
- Regenerate featured image: < 15s
- Save changes: < 1s

**Resource Limits**:
- Modal JavaScript bundle: < 50KB minified
- Modal CSS: < 10KB
- AJAX response size: < 100KB per request
- Maximum concurrent regenerations: 1 per component

---

## Future Enhancements

### Phase 2 Features (Not in Scope for v1)

#### 1. Prompt Customization
**Description**: Allow admins to modify prompts before regenerating components

**User Story**: As an admin, I want to edit the prompt before regenerating so that I can fine-tune the output

**Implementation**:
- Add "Edit Prompt" button next to each component
- Show expandable textarea with current prompt
- Allow editing before clicking "Re-generate"
- Store custom prompts in history log

---

#### 2. Article Structure Section Regeneration
**Description**: For posts with article structures, allow regeneration of individual sections

**User Story**: As an admin, I want to regenerate just the "Introduction" section without regenerating the entire article

**Implementation**:
- Detect if post has article structure
- Display structure sections in modal
- Add "Re-generate" button per section
- Reconstruct prompt for specific section

---

#### 3. Component Regeneration History
**Description**: Track history of component regenerations with ability to revert

**User Story**: As an admin, I want to see previous versions of a component and restore an older version

**Implementation**:
- Store each regenerated value in history table
- Add "View History" button per component
- Show dropdown/modal with previous versions
- Allow selection and restoration

---

#### 4. Undo/Redo Functionality
**Description**: Allow undo and redo of regeneration actions within modal

**User Story**: As an admin, I want to undo a regeneration if I don't like the result

**Implementation**:
- Maintain in-memory undo stack
- Add Undo/Redo buttons to modal header
- Store up to 10 previous states per session
- Clear stack on modal close

---

#### 5. Live Preview
**Description**: Preview how the post will look before saving

**User Story**: As an admin, I want to preview the post with my changes before saving

**Implementation**:
- Add "Preview" button to modal footer
- Open WordPress preview in new tab/iframe
- Pass temporary content via session storage
- Render without saving to database

---

#### 6. Bulk Component Regeneration
**Description**: Regenerate the same component across multiple posts

**User Story**: As an admin, I want to regenerate excerpts for 10 posts at once

**Implementation**:
- Add bulk action in Generated Posts table
- Select multiple posts via checkboxes
- Choose component to regenerate
- Process in background with progress indicator

---

## Appendix

### Glossary

| Term | Definition |
|------|------------|
| Component | A discrete part of a post: title, excerpt, content, or featured image |
| Generation Context | The template, author, topic, and structure data used to generate a post |
| History Record | Database entry tracking a post generation session |
| Regeneration | Creating a new version of a component using AI |
| AI Edit Modal | Custom modal UI for editing and regenerating post components |

### Related Documentation

- [Post Review Feature](./POST_REVIEW_FEATURE.md) - Draft post workflow
- [Hooks Documentation](./HOOKS.md) - Event system for extensibility
- [Architecture Improvements](./ARCHITECTURAL_IMPROVEMENTS.md) - Overall system design
- [Testing Guide](../TESTING.md) - How to run tests

### Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-02-09 | AI Post Scheduler Team | Initial specification |

---

**Document Status**: Draft  
**Next Review Date**: After Phase 1 completion  
**Approvers**: Product Owner, Lead Developer
