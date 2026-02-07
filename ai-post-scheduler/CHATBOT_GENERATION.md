# Chatbot-Based Post Generation

## Overview

As of version 2.0, AI Post Scheduler uses the Meow Apps AI Engine's **Chatbot feature** for post generation. This provides superior coherence between post components (content, title, and excerpt) by maintaining conversational context throughout the generation process.

## How It Works

### Previous Approach (Disconnected)
Previously, each post component was generated independently:
1. Generate content → AI has no context
2. Generate title → AI only sees the content prompt
3. Generate excerpt → AI only sees title and partial content

This resulted in components that sometimes didn't align well with each other.

### New Approach (Conversational)
Now, all components are generated in a single conversation:
1. **Step 1**: Generate content (establishes a `chatId`)
2. **Step 2**: Generate title - AI "remembers" the content it just created
3. **Step 3**: Generate excerpt - AI "remembers" both content and title

The chatbot maintains full context across all three steps, resulting in much better coherence.

## Configuration

### Chatbot ID Setting
You can configure which AI Engine chatbot to use:

**Settings Location**: AI Post Scheduler → Settings → General Settings → Chatbot ID

**Default Value**: `default` (uses AI Engine's default chatbot)

**Usage**: Set this to the ID of any chatbot you've configured in AI Engine. This allows you to:
- Use custom system prompts for post generation
- Apply specific chatbot personalities to your content
- Use different chatbots for different use cases

## Technical Implementation

### AIPS_AI_Service
The `AIPS_AI_Service` class now includes:

```php
public function generate_with_chatbot($chatbot_id, $message, $options = array(), $log_type = 'chatbot')
```

This method:
- Uses `$mwai_core->simpleChatbotQuery()` from AI Engine
- Maintains conversation via `chatId` parameter
- Includes full resilience (circuit breaker, rate limiting, retries)
- Logs all interactions for debugging

### AIPS_Generator
The `AIPS_Generator` class now uses:

```php
private function generate_post_components_with_chatbot($context)
```

This method orchestrates the three-step conversation:
1. Sends content prompt as first message
2. Sends title request referencing the generated content
3. Sends excerpt request referencing both content and title

All three use the same `chatId` to maintain context.

## History and Logging

All chatbot interactions are logged in the generation history:
- Each step records the prompt sent
- Responses are captured with metadata including `chatId`
- Component type is tracked (`content`, `title`, `excerpt`)
- Method is marked as `chatbot` for distinction from legacy approach

## Backward Compatibility

The refactoring maintains backward compatibility:
- Old template-based API still works (converted to context internally)
- Hooks receive both `context` and `template` keys
- Existing integrations continue to function

## Benefits

1. **Better Coherence**: Title and excerpt directly reference the generated content
2. **Context Awareness**: Each component builds on previous ones
3. **More Natural Output**: AI produces more cohesive posts
4. **Flexibility**: Configure different chatbots for different needs
5. **Observable**: Full logging of each step for debugging

## Example Flow

```php
// Step 1: Generate content
$content_response = $ai->simpleChatbotQuery('default', $content_prompt, []);
// Returns: ['reply' => '...content...', 'chatId' => 'abc123']

// Step 2: Generate title (with context)
$title_response = $ai->simpleChatbotQuery('default', 
    "Based on the article you just generated, create a title...",
    ['chatId' => 'abc123']
);
// AI "remembers" the content it just created

// Step 3: Generate excerpt (with full context)
$excerpt_response = $ai->simpleChatbotQuery('default',
    "Based on the article and title you created, write an excerpt...",
    ['chatId' => 'abc123']
);
// AI "remembers" both content and title
```

## Requirements

- **AI Engine Plugin**: Must be installed and activated
- **AI Engine Version**: Must support `simpleChatbotQuery()` method
- **Chatbot Configuration**: At minimum, the 'default' chatbot should exist in AI Engine

## Troubleshooting

### Chatbot Not Available
If you see errors about chatbot being unavailable:
1. Verify AI Engine is installed and activated
2. Update AI Engine to the latest version
3. Check that at least one chatbot exists in AI Engine settings

### Poor Results
If generated content isn't coherent:
1. Check your chatbot configuration in AI Engine
2. Verify the chatbot has appropriate system prompts
3. Try using a different chatbot ID in settings
4. Check generation history logs for prompts and responses

## Migration Notes

Existing installations will automatically use the new chatbot-based generation. No migration is required. The setting defaults to 'default' which should work with standard AI Engine installations.
