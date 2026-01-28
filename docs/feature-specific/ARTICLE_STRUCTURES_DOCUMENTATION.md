# Dynamic Article Structure/Template-Type Selector

## Overview

The Dynamic Article Structure/Template-Type Selector feature allows users to create diverse, non-repetitive content by selecting different article structures for their scheduled posts. This feature provides flexibility in post generation and makes automated content feel more natural and varied.

## Features

### 1. **Predefined Article Structures**

Six built-in article structures are provided:

- **How-To Guide**: Step-by-step instructions for accomplishing tasks
- **Tutorial**: In-depth educational content with practical examples
- **Library Reference**: Technical documentation for libraries/APIs
- **Listicle**: List-based articles with multiple items or tips
- **Case Study**: Real-world examples with analysis
- **Opinion/Editorial**: Thought leadership content

### 2. **Modular Prompt Sections**

Eight reusable prompt sections that can be combined:

- **Introduction**: Opening paragraph that hooks the reader
- **Prerequisites**: Required knowledge or tools
- **Step-by-Step Instructions**: Detailed procedural steps
- **Code Examples**: Practical code samples
- **Tips and Best Practices**: Expert advice and recommendations
- **Troubleshooting**: Common issues and solutions
- **Conclusion**: Wrap-up and next steps
- **Resources**: Additional learning materials

### 3. **Rotation Patterns**

Four automated rotation patterns for batch schedules:

- **Sequential**: Cycles through all structures in order
- **Random**: Randomly selects a structure each time
- **Weighted**: Favors default structure (2x weight)
- **Alternating**: Alternates between top 2 structures

### 4. **Flexible Selection**

- Select a specific structure for each schedule
- Use automatic rotation for variety
- Fall back to default structure when none is specified
- Full backward compatibility with existing templates

## Usage

### Creating a Schedule with Article Structure

1. Navigate to **AI Post Scheduler → Schedules**
2. Click **Add New Schedule**
3. Select your template
4. Choose your frequency
5. **NEW:** Select an Article Structure:
   - Leave as "Use Default" for the default structure
   - Select a specific structure to always use it
   - Or set a Rotation Pattern to automatically alternate

### Using Rotation Patterns

When creating a schedule, select a rotation pattern to automatically cycle through different article structures:

- **Sequential**: Posts will use Structure 1, then Structure 2, then Structure 3, etc.
- **Random**: Each post randomly selects from available structures
- **Weighted**: More likely to use the default structure
- **Alternating**: Alternates between the top 2 most-used structures

## Technical Details

### Database Schema

Two new tables are added in version 1.5.0:

#### `wp_aips_article_structures`

Stores article structure definitions:

```sql
- id: Primary key
- name: Structure name (e.g., "How-To Guide")
- description: Structure description
- structure_data: JSON data with sections and prompt template
- is_active: Active status flag
- is_default: Default structure flag
- created_at, updated_at: Timestamps
```

#### `wp_aips_prompt_sections`

Stores reusable prompt sections:

```sql
- id: Primary key
- name: Section name (e.g., "Introduction")
- description: Section description
- section_key: Unique key (e.g., "introduction")
- content: Section prompt content
- is_active: Active status flag
- created_at, updated_at: Timestamps
```

#### Updated: `wp_aips_schedule`

Two new columns added:

```sql
- article_structure_id: FK to article structure (nullable)
- rotation_pattern: Rotation pattern key (nullable)
```

### Architecture

The feature follows WordPress coding standards and the plugin's architecture patterns:

- **Repository Pattern**: `AIPS_Article_Structure_Repository`, `AIPS_Prompt_Section_Repository`
- **Service Classes**: `AIPS_Article_Structure_Manager`, `AIPS_Template_Type_Selector`
- **Integration**: Updated `AIPS_Generator`, `AIPS_Scheduler`, `AIPS_Schedule_Controller`

### Key Classes

#### `AIPS_Article_Structure_Manager`

Manages article structures and builds prompts from sections.

```php
$manager = new AIPS_Article_Structure_Manager();
$structures = $manager->get_active_structures();
$prompt = $manager->build_prompt($structure_id, $topic);
```

#### `AIPS_Template_Type_Selector`

Selects article structures based on rotation patterns.

```php
$selector = new AIPS_Template_Type_Selector();
$structure_id = $selector->select_structure($schedule);
$patterns = $selector->get_rotation_patterns();
```

### Template Variables

Article structures support all existing template variables plus section placeholders:

- `{{section:introduction}}` - Replaced with introduction section content
- `{{section:steps}}` - Replaced with step-by-step instructions
- `{{section:examples}}` - Replaced with code examples
- `{{date}}`, `{{topic}}`, `{{site_name}}` - Standard variables still work

### Example Structure Data

```json
{
  "sections": ["introduction", "prerequisites", "steps", "tips", "conclusion"],
  "prompt_template": "Write a how-to guide about {{topic}}.\n\n{{section:introduction}}\n\n{{section:prerequisites}}\n\n{{section:steps}}\n\n{{section:tips}}\n\n{{section:conclusion}}"
}
```

## Backward Compatibility

The feature is 100% backward compatible:

- Existing templates continue to work without modification
- Existing schedules run normally without article structures
- If no structure is specified, the default is used
- Template processor falls back to standard processing if structure fails

## Extension Points

### Filters

```php
// Modify available rotation patterns
add_filter('aips_rotation_patterns', function($patterns) {
    $patterns['custom'] = __('My Custom Pattern', 'textdomain');
    return $patterns;
});

// Modify article structure before use
add_filter('aips_article_structure', function($structure, $structure_id) {
    // Modify structure
    return $structure;
}, 10, 2);
```

### Actions

```php
// When a structure is selected for a schedule
do_action('aips_structure_selected', $schedule_id, $structure_id);

// Before building prompt from structure
do_action('aips_before_build_prompt', $structure_id, $topic);

// After building prompt from structure
do_action('aips_after_build_prompt', $prompt, $structure_id, $topic);
```

## Testing

Comprehensive test coverage is provided:

- `test-article-structure-repository.php`: Repository CRUD operations
- `test-template-type-selector.php`: Rotation pattern logic

Run tests:

```bash
composer test
```

## Migration

The migration runs automatically on plugin update:

1. Creates new database tables
2. Seeds default article structures
3. Seeds default prompt sections
4. Adds new columns to schedule table
5. Maintains all existing data

Migration file: `migrations/migration-1.5-add-article-structures.php`

## Future Enhancements

Possible future additions:

- UI for creating custom article structures
- UI for editing prompt sections
- Import/export of article structures
- Per-template default structures
- Structure usage analytics
- AI-powered structure suggestions based on topic

## Support

For issues or questions:

- Check the plugin documentation
- Review the test files for usage examples
- Consult the inline code documentation
- Submit issues on GitHub

## Version History

- **1.5.0**: Initial release of dynamic article structure feature
  - 6 predefined structures
  - 8 modular sections
  - 4 rotation patterns
  - Full UI integration
  - Comprehensive tests
