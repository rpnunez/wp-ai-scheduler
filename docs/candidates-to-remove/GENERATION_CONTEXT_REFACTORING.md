# Generation Context Architecture Refactoring

## Overview
This document describes the refactoring of the Generator class to remove tight coupling to Templates and introduce a flexible Context-based architecture.

## Problem Statement
The `AIPS_Generator` class was tightly coupled to Template objects, requiring all generation sources to provide template-like structures. This led to:

1. **Template Mocking**: The `AIPS_Author_Post_Generator` had to create "mock" template objects using `build_template_from_author()` just to satisfy the Generator's requirements
2. **Poor Separation of Concerns**: The Generator couldn't handle different generation contexts (Topics, Research Results, etc.) without workarounds
3. **Limited Extensibility**: Adding new generation sources required creating fake template objects

## Solution: Generation Context Architecture

### Core Concept
Introduce an abstraction layer between the Generator and the source of generation configuration. This abstraction is represented by the `AIPS_Generation_Context` interface.

### Architecture Components

#### 1. AIPS_Generation_Context Interface
```php
interface AIPS_Generation_Context {
    public function get_type();              // 'template', 'topic', 'research_result'
    public function get_id();                 // Context identifier
    public function get_name();               // Display name
    public function get_content_prompt();     // AI content generation prompt
    public function get_title_prompt();       // AI title generation prompt
    public function get_image_prompt();       // Featured image prompt
    // ... additional methods for post settings, metadata, etc.
}
```

#### 2. Concrete Context Implementations

**AIPS_Template_Context**
- Wraps existing Template objects
- Maintains backward compatibility with all template-based generation
- Supports optional voice and topic parameters
- Ensures zero breaking changes to existing code

**AIPS_Topic_Context**
- Wraps Author + Topic pairs
- Eliminates the need for template mocking
- Builds content prompts from author's field/niche and topic title
- Supports expanded context from similar approved topics
- Uses author's post settings (status, category, tags, etc.)

#### 3. Updated Components

**AIPS_Generator**
- New `generate_post_from_context()` method handles context-based generation
- Existing `generate_post()` method maintains backward compatibility by converting templates to contexts
- All internal methods updated to work with contexts
- Context-aware helper methods: `generate_title_from_context()`, `generate_excerpt_from_context()`, etc.

**AIPS_Generation_Session**
- Updated `start()` method accepts both templates (legacy) and contexts
- Tracks context type and data generically
- Maintains backward compatibility with template-based tracking

**AIPS_Prompt_Builder**
- All prompt building methods updated to accept contexts
- Maintains legacy template-based method signatures
- Automatically detects and handles both approaches

**AIPS_Post_Creator**
- Updated `create_post()` to accept either template or context
- Extracts post settings from context interface
- Maintains backward compatibility with template-based post creation

**AIPS_Author_Post_Generator**
- **Removed** the `build_template_from_author()` hack
- Now creates `AIPS_Topic_Context` directly
- Cleaner, more maintainable code
- No more template mocking!

## Implementation Details

### Backward Compatibility Strategy
The refactoring maintains 100% backward compatibility through:

1. **Method Overloading**: Existing methods accept both old (template) and new (context) parameters
2. **Automatic Conversion**: Legacy template calls are automatically converted to contexts internally
3. **Legacy Support**: All existing template-based code paths continue to work unchanged
4. **Gradual Migration**: New code uses contexts, old code continues using templates

### Example: Before and After

**Before (Template Mocking)**:
```php
// AIPS_Author_Post_Generator
private function build_template_from_author($author, $topic) {
    return (object) array(
        'id' => null,
        'name' => "Author: {$author->name}",
        'prompt_template' => "Write about {$topic->topic_title}...",
        'title_prompt' => $topic->topic_title,
        // ... 15 more fields to mock
    );
}

$template = $this->build_template_from_author($author, $topic);
$post_id = $this->generator->generate_post($template, null, array('topic' => $topic->topic_title));
```

**After (Context-Based)**:
```php
// AIPS_Author_Post_Generator
$context = new AIPS_Topic_Context($author, $topic, $expanded_context);
$post_id = $this->generator->generate_post($context);
```

### Testing Strategy
Created comprehensive test suite (`Test_AIPS_Generation_Context`) covering:
- Template context wrapping
- Topic context functionality
- Generation session context support
- Backward compatibility with legacy templates
- Context serialization
- Voice integration

All 6 tests pass with 50 assertions.

## Benefits

### 1. Clean Architecture
- Clear separation between generation logic and configuration source
- Well-defined interface for all generation contexts
- Follows SOLID principles (especially Interface Segregation and Dependency Inversion)

### 2. No More Hacks
- Eliminated template mocking in Author Post Generator
- Removed artificial dependencies between components
- Cleaner, more maintainable code

### 3. Extensibility
- Easy to add new context types (Research Results, External APIs, etc.)
- Simply implement `AIPS_Generation_Context` interface
- No changes needed to Generator or related components

### 4. Backward Compatibility
- Zero breaking changes to existing code
- All template-based generation continues to work
- Gradual migration path for future development

### 5. Better Testability
- Contexts can be easily mocked for testing
- Clear boundaries make unit testing simpler
- Integration tests verify both legacy and new paths

## Migration Path

### For New Features
New generation sources should:
1. Implement `AIPS_Generation_Context` interface
2. Pass context directly to `$generator->generate_post($context)`
3. No template mocking required

### For Existing Code
Existing template-based code:
1. Continues to work without changes
2. Can be gradually migrated to contexts if desired
3. No immediate action required

## Future Enhancements

### Potential New Contexts
1. **AIPS_Research_Context** - Generate from research results
2. **AIPS_External_Context** - Generate from external API data
3. **AIPS_Curated_Context** - Generate from curated content sources
4. **AIPS_Hybrid_Context** - Combine multiple context types

### Advanced Features
1. Context chaining (combine multiple contexts)
2. Context validation and normalization
3. Context-specific optimization strategies
4. Context-aware caching

## Files Changed

### New Files
- `interface-aips-generation-context.php` - Core interface definition
- `class-aips-template-context.php` - Template wrapper implementation
- `class-aips-topic-context.php` - Topic context implementation
- `Test_AIPS_Generation_Context.php` - Test suite

### Modified Files
- `class-aips-generator.php` - Updated to use contexts
- `class-aips-generation-session.php` - Context-aware session tracking
- `class-aips-prompt-builder.php` - Context-aware prompt building
- `class-aips-post-creator.php` - Accepts contexts or templates
- `class-aips-author-post-generator.php` - Uses Topic Context, removed mocking
- `ai-post-scheduler.php` - Register new context classes
- `tests/bootstrap.php` - Load context classes for testing

## Conclusion

This refactoring successfully decouples the Generator from Templates while maintaining full backward compatibility. The new Context architecture provides a clean, extensible foundation for supporting multiple generation sources without hacks or workarounds.

The elimination of template mocking in the Author Post Generator demonstrates the immediate value of this approach. Future generation sources can be added simply by implementing the Context interface, with no changes needed to the Generator or related components.
