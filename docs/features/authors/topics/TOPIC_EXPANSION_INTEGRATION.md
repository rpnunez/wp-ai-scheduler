# Topic Expansion Integration for Post Generation

## Overview

This feature integrates the topic expansion service from PR #443 into the author post generation process. When generating a blog post from an approved author topic, the system now automatically includes context from semantically similar approved topics to improve the quality and relevance of generated content.

## What Was Changed

### Modified Files

1. **`ai-post-scheduler/includes/class-aips-author-post-generator.php`**
   - Added `AIPS_Topic_Expansion_Service` as a dependency
   - Modified `build_template_from_author()` to fetch and include expanded context
   - Added logging for when expanded context is added

### New Files

1. **`ai-post-scheduler/tests/test-author-post-generator-expansion.php`**
   - Unit tests for the expanded context feature
   - Tests both scenarios: with and without similar topics

## How It Works

### Step-by-Step Process

1. When a post is being generated from an author's topic:
   ```php
   $result = $this->generate_post_from_topic($topic, $author);
   ```

2. The `build_template_from_author()` method is called:
   - Builds the base prompt with the topic title and author's field/niche
   - Calls `$expansion_service->get_expanded_context($author_id, $topic_id, 5)`
   - If similar topics are found, appends them to the prompt

3. The enhanced prompt is passed to the AI generator:
   ```
   Write a comprehensive blog post about: [Topic Title]
   
   Field/Niche: [Author's Niche]
   
   Related approved topics:
   - [Similar Topic 1]
   - [Similar Topic 2]
   - [Similar Topic 3]
   ```

### Example Output

**Before Integration:**
```
Write a comprehensive blog post about: Clean Code Principles

Field/Niche: Software Development
```

**After Integration:**
```
Write a comprehensive blog post about: Clean Code Principles

Field/Niche: Software Development

Related approved topics:
- How to write better code
- Best practices for software development
- SOLID principles in practice
- Code review best practices
- Refactoring techniques
```

## Benefits

1. **Higher Quality Content**: The AI has more context about related topics the author has approved, leading to more relevant and consistent content
2. **Better Topic Understanding**: Similar topics help the AI understand the author's preferred writing style and content approach
3. **Semantic Consistency**: Using embeddings ensures that semantically similar topics are identified, not just keyword matches
4. **Backwards Compatible**: If no similar topics exist or if embeddings haven't been computed, the system gracefully falls back to the base prompt

## Technical Details

### Dependencies

- **AIPS_Topic_Expansion_Service**: Handles finding similar topics using embeddings
- **AIPS_Embeddings_Service**: Generates and compares embeddings (via Meow AI Engine)
- **AIPS_Author_Topics_Repository**: Retrieves approved topics for comparison

### Configuration

The system uses 5 similar topics by default:
```php
$expanded_context = $this->expansion_service->get_expanded_context($author->id, $topic->id, 5);
```

This can be adjusted by modifying the third parameter in the code.

### Performance Considerations

1. **Embeddings are cached** in the `metadata` column of `aips_author_topics` table
2. **Embeddings are computed on-demand** if not already available
3. **Only approved topics** are used for context expansion
4. **Similarity calculation** is done in-memory after fetching topics

## Testing

### Automated Tests

The file `test-author-post-generator-expansion.php` includes:

1. **test_expanded_context_added_to_prompt**: Verifies that expanded context is added when similar topics exist
2. **test_prompt_without_expanded_context**: Ensures the system works correctly when no similar topics are available

### Manual Verification

A verification script is provided at `/tmp/verify_topic_expansion.php` that demonstrates:
- Prompt generation with expanded context
- Prompt generation without expanded context
- Proper fallback behavior

Run it with:
```bash
php /tmp/verify_topic_expansion.php
```

## Future Enhancements

Potential improvements for future versions:

1. **Configurable limit**: Allow administrators to set the number of similar topics per author
2. **Context weighting**: Include similarity scores to help the AI understand topic relevance
3. **Cross-author learning**: Optionally include similar topics from other authors in the same niche
4. **Explicit context section**: Add a dedicated "Context from approved topics" section in the admin UI
5. **Performance optimization**: Pre-compute embeddings for all approved topics in batch

## References

- **PR #443**: Original implementation of topic expansion service and embeddings
- **AIPS_Topic_Expansion_Service**: Service class for topic similarity
- **AIPS_Embeddings_Service**: Service class for embedding generation and comparison
