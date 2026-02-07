# JSON Query Integration with Meow Apps AI Engine

## Overview

This document describes the integration of Meow Apps AI Engine's `simpleJsonQuery` method into the AI Post Scheduler plugin for improved structured data generation.

## Problem Statement

Previously, the plugin generated structured data (topics, research results, author topics) using plain text queries and then parsing the responses. This approach had several limitations:

1. **Unreliable Parsing**: Text responses often contained markdown formatting, numbering, or other artifacts that complicated parsing
2. **Limited Metadata**: Plain text responses couldn't easily include structured metadata like scores, keywords, or reasons
3. **Error-Prone**: Parsing failures could occur when AI responses didn't match expected formats
4. **Manual JSON Extraction**: Had to manually strip markdown code blocks and handle JSON decoding errors

## Solution

The plugin now leverages AI Engine's `simpleJsonQuery` method which:

- Returns structured JSON data directly
- Eliminates need for manual JSON extraction
- Provides more reliable and consistent responses
- Enables richer metadata in responses

## Implementation

### New Method: `AIPS_AI_Service::generate_json()`

```php
public function generate_json($prompt, $options = array())
```

**Features:**
- Uses `simpleJsonQuery` via global `$mwai` when available
- Falls back to text-based JSON parsing for backward compatibility
- Includes full resilience patterns (circuit breaker, rate limiting, retry)
- Returns parsed array data directly
- Comprehensive error handling

**Example Usage:**

```php
$ai_service = new AIPS_AI_Service();

$prompt = "Generate 5 blog topics about WordPress:\n\n";
$prompt .= "Return a JSON array with:\n";
$prompt .= "- \"title\": topic title\n";
$prompt .= "- \"score\": relevance 1-100\n";
$prompt .= "- \"keywords\": array of keywords\n";

$topics = $ai_service->generate_json($prompt, array(
    'temperature' => 0.7,
    'max_tokens' => 2000,
));

if (is_wp_error($topics)) {
    // Handle error
    error_log($topics->get_error_message());
} else {
    // Use structured data
    foreach ($topics as $topic) {
        echo "Topic: " . $topic['title'] . "\n";
        echo "Score: " . $topic['score'] . "\n";
        echo "Keywords: " . implode(', ', $topic['keywords']) . "\n";
    }
}
```

### Updated Services

#### 1. AIPS_Research_Service

**Before:**
```php
$result = $this->ai_service->generate_text($prompt, $options);
$topics = $this->parse_research_response($result, $count);
```

**After:**
```php
$result = $this->ai_service->generate_json($prompt, $options);
$topics = $this->validate_and_normalize_topics($result, $count);
```

**Benefits:**
- More reliable topic parsing
- Includes relevance scores (1-100)
- Includes trending reasons
- Includes related keywords (3-5 per topic)

**Response Structure:**
```json
[
  {
    "topic": "How AI is Transforming Content Creation in 2025",
    "score": 95,
    "reason": "High search volume, current AI adoption surge",
    "keywords": ["AI content", "automation", "GPT-4", "content marketing", "2025 trends"]
  }
]
```

#### 2. AIPS_Author_Topics_Generator

**Before:**
```php
$response = $this->ai_service->generate_text($prompt, $options);
$topics = $this->parse_topics_from_response($response, $author);
```

**After:**
```php
$response = $this->ai_service->generate_json($prompt, $options);
$topics = $this->parse_json_topics($response, $author);
```

**Benefits:**
- More reliable topic generation
- Includes engagement scores
- Includes relevant keywords
- Better metadata tracking

**Response Structure:**
```json
[
  {
    "title": "10 Best Practices for WordPress SEO in 2025",
    "score": 85,
    "keywords": ["WordPress", "SEO", "best practices", "2025", "optimization"]
  }
]
```

## Fallback Mechanism

When `simpleJsonQuery` is not available (older AI Engine versions or specific configurations), the plugin automatically falls back to text-based JSON parsing:

1. Calls `generate_text()` with JSON-requesting prompt
2. Strips markdown code blocks (`\`\`\`json` / `\`\`\``)
3. Parses JSON manually with error handling
4. Returns structured data or WP_Error

This ensures **100% backward compatibility** while taking advantage of new features when available.

## Benefits

### 1. More Reliable Parsing
- No markdown artifacts to strip
- Consistent JSON structure
- Reduced parsing errors

### 2. Richer Metadata
- Relevance/engagement scores
- Related keywords
- Contextual reasons
- Generation metadata

### 3. Better Error Handling
- Structured validation
- Type checking
- Clear error messages
- Graceful degradation

### 4. Improved Developer Experience
- Direct array access
- No manual JSON decoding
- Type-safe data structures
- Easier testing

### 5. Future-Ready Architecture
- Foundation for advanced AI features
- Easier to extend with new fields
- Supports complex nested structures
- Ready for AI Engine updates

## When to Use `generate_json()` vs `generate_text()`

### Use `generate_json()` when:
- Generating lists or collections (topics, items, options)
- Need structured data with multiple fields
- Want metadata (scores, keywords, categories)
- Parsing reliability is critical
- Data will be programmatically processed

### Use `generate_text()` when:
- Generating prose content (blog posts, descriptions)
- Free-form text output is desired
- Simple string result is sufficient
- Text formatting (paragraphs, headings) is needed
- Content is for direct display to users

## Testing

The implementation includes comprehensive tests:

```php
// Test unavailability handling
test_generate_json_unavailable()

// Test fallback logs as json type for accurate statistics
test_generate_json_fallback_logs_as_json_type()

// Test options passing
test_generate_json_accepts_options()

// Test call statistics include json type properly
test_call_statistics_include_json_calls()

// Test simpleJsonQuery success path with mocked $mwai
test_generate_json_with_simpleJsonQuery_success()
```

All tests validate both the `simpleJsonQuery` success path and fallback behavior when AI Engine is not available.

## Migration Guide

Existing code continues to work without changes. To adopt JSON queries:

1. **Identify structured data generation** in your code
2. **Update prompts** to request JSON format explicitly
3. **Replace `generate_text()`** with `generate_json()`
4. **Update parsing logic** to work with array data
5. **Test fallback behavior** in environments without `simpleJsonQuery`

Example migration:

```php
// Old approach
$prompt = "Generate 5 topics, one per line";
$text = $ai_service->generate_text($prompt);
$topics = explode("\n", $text);

// New approach
$prompt = "Generate 5 topics as JSON array with title, score, keywords";
$topics = $ai_service->generate_json($prompt);
foreach ($topics as $topic) {
    // Direct access to structured fields
    $title = $topic['title'];
    $score = $topic['score'];
    $keywords = $topic['keywords'];
}
```

## Performance Considerations

- **Network**: Similar to text queries (same API calls)
- **Parsing**: Faster than text parsing (native JSON handling)
- **Memory**: Slightly higher (structured data in memory)
- **Reliability**: Significant improvement (fewer parse failures)

Overall, JSON queries provide better performance and reliability with minimal overhead.

## Conclusion

The JSON query integration represents a significant improvement in how the plugin interacts with AI Engine for structured data generation. It provides:

- ✅ More reliable parsing
- ✅ Richer metadata
- ✅ Better error handling
- ✅ Full backward compatibility
- ✅ Future-ready architecture

This feature is production-ready and recommended for all new structured data generation use cases.

## References

- [Meow Apps AI Engine Documentation](https://ai.thehiddendocs.com/)
- [AI Engine Public REST API](https://ai.thehiddendocs.com/public-rest-api/)
- [AIPS_AI_Service Class](../ai-post-scheduler/includes/class-aips-ai-service.php)
- [AIPS_Research_Service Class](../ai-post-scheduler/includes/class-aips-research-service.php)
- [AIPS_Author_Topics_Generator Class](../ai-post-scheduler/includes/class-aips-author-topics-generator.php)
