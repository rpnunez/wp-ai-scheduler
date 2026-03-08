# Trending Topics Research - User Guide

## Overview

The Trending Topics Research feature uses AI to automatically discover what's currently trending in your niche, helping you create timely and relevant content without the manual effort of topic brainstorming.

## What It Does

- **Discovers Trends**: Uses AI to analyze what's currently trending in any niche
- **Scores Topics**: Ranks each topic from 1-100 based on relevance and timeliness
- **Extracts Keywords**: Identifies related keywords for SEO optimization
- **Stores Research**: Saves all discovered topics for future reference
- **Bulk Scheduling**: Schedule multiple topics at once with templates
- **Automates Research**: Configure automatic daily research for your niches

## How To Use

### Manual Research

1. **Navigate to Trending Topics**
   - In WordPress admin, go to: `AI Post Scheduler → Trending Topics`

2. **Enter Your Niche**
   - In the "New Research" section, enter your niche (e.g., "Digital Marketing", "Health & Wellness", "AI Technology")
   - Choose how many topics to discover (1-50, default is 10)
   - Optionally add focus keywords (comma-separated)

3. **Research Trending Topics**
   - Click "Research Trending Topics"
   - The AI will analyze current trends, news, seasonal factors, and search patterns
   - Results appear immediately showing the top 5 topics
   - All topics are saved to your library for future use

4. **Review Research Results**
   - Each topic shows:
     - **Topic Title**: The suggested blog post title
     - **Score**: Relevance score (90+ = high, 70-89 = medium, <70 = low)
     - **Reason**: Why this topic is trending
     - **Keywords**: Related keywords for the topic

### Using the Topics Library

1. **Filter Topics**
   - **By Niche**: Select a specific niche from the dropdown
   - **By Score**: Show only high-scoring topics (80+ or 90+)
   - **By Freshness**: Show only topics researched in the last 7 days
   - Click "Load Topics" to apply filters

2. **Browse Topics**
   - View all stored research results in the table
   - Topics show score badges (color-coded for quick assessment)
   - Keywords displayed as tags
   - Research date shown for each topic

3. **Manage Topics**
   - **Delete**: Remove topics you don't want to use
   - **Select**: Check boxes next to topics you want to schedule

### Bulk Scheduling Topics

1. **Select Topics**
   - Check the boxes next to topics you want to schedule
   - Or use "Select All" to choose all visible topics

2. **Configure Schedule**
   - **Template**: Choose which template to use for generation
   - **Start Date**: When to start generating posts
   - **Frequency**: How often to generate (hourly, daily, weekly, etc.)

3. **Schedule**
   - Click "Schedule Topics"
   - The system creates a schedule for each selected topic
   - Topics will be generated at the specified times
   - View schedules in the "Schedule" page

### Automated Research

Configure automatic research to run on a schedule (requires editing WordPress options):

```php
// In wp-config.php or a custom plugin:
add_action('init', function() {
    update_option('aips_research_niches', array(
        array(
            'niche' => 'Digital Marketing',
            'count' => 10,
            'keywords' => array('SEO', 'content marketing', 'social media'),
        ),
        array(
            'niche' => 'AI Technology',
            'count' => 15,
            'keywords' => array('machine learning', 'automation', 'chatbots'),
        ),
    ));
});
```

The system will automatically research these niches daily and store results in your library.

## Understanding Topic Scores

Topics are scored from 1-100 based on multiple factors:

### Score Factors

1. **Temporal Relevance** (up to 20 points)
   - Mentions current year
   - Contains temporal words (now, today, latest, trending, new, current)

2. **Seasonal Relevance** (up to 15 points)
   - Matches current season/month
   - Holiday-related topics (if applicable)

3. **Search Volume Indicators** (AI analysis)
   - High search interest
   - Growing search trends

4. **Content Gap Opportunities** (AI analysis)
   - Topics with high demand but low competition
   - Unique angles on popular topics

5. **Evergreen Value** (AI analysis)
   - Long-term relevance
   - Not overly time-sensitive

### Score Ranges

- **90-100 (High)**: Highly trending, very timely, strong keywords
- **70-89 (Medium)**: Good relevance, decent timing, useful keywords  
- **Below 70 (Low)**: Less timely or relevant, may still be worth considering

## Best Practices

### 1. Regular Research

- Research your niches regularly (weekly or bi-weekly)
- Trends change quickly - fresh research ensures timely content
- Use the "Fresh Only" filter to focus on recent research

### 2. Niche Specificity

- **Good**: "Email Marketing for E-commerce"
- **Better**: "Email Marketing Automation for Shopify Stores"
- **Best**: "Email Marketing Automation for Fashion E-commerce in 2025"

More specific niches yield more targeted, relevant topics.

### 3. Keyword Combinations

- Use focus keywords to narrow research
- Combine broad + specific keywords
- Example: "content marketing, AI, automation, 2025"

### 4. Score Interpretation

- Don't ignore lower-scored topics entirely
- A 75-score topic might be perfect for your specific audience
- Consider your unique expertise and audience needs
- Scores are suggestions, not mandates

### 5. Bulk Scheduling Strategy

- Mix high and medium-scored topics
- Schedule high-priority topics first (earlier dates)
- Leave buffer time between scheduled posts
- Review generated drafts before publishing

### 6. Template Selection

- Create templates optimized for trending topics
- Use dynamic variables: `{{topic}}`, `{{date}}`, `{{keywords}}`
- Templates should adapt to any topic in your niche
- Test templates before bulk scheduling

## Examples

### Example 1: Blog Research

**Niche**: "Content Marketing"
**Keywords**: "AI", "automation", "2025"
**Results**:
1. "How AI is Revolutionizing Content Marketing in 2025" (Score: 95)
2. "Content Automation Tools Every Marketer Needs" (Score: 88)
3. "The Future of SEO: AI-Generated Content" (Score: 92)

**Action**: Select all three, schedule with "Blog Post" template, daily frequency starting tomorrow.

### Example 2: News Site Research

**Niche**: "Technology News"
**Keywords**: "AI", "startups", "funding"
**Results**:
1. "Top 10 AI Startups That Raised Series A in December 2025" (Score: 97)
2. "How AI is Disrupting the Healthcare Industry" (Score: 90)
3. "OpenAI's Latest Model: What It Means for Developers" (Score: 93)

**Action**: Schedule highest-scored topic immediately, others over next 3 days.

### Example 3: Seasonal Content

**Niche**: "E-commerce Marketing"
**Keywords**: "holiday", "sales", "conversion"
**Results** (researched in November):
1. "Black Friday Email Marketing Strategies That Convert" (Score: 98)
2. "How to Prepare Your E-commerce Store for Holiday Sales" (Score: 95)
3. "Post-Holiday Retention Strategies for E-commerce" (Score: 85)

**Action**: Schedule Black Friday content for mid-November, holiday prep for early November, retention for January.

## Troubleshooting

### No Results

**Problem**: Research returns no topics or error
**Solutions**:
- Check that Meow Apps AI Engine is installed and configured
- Verify AI Engine has API access
- Try a broader niche
- Check error logs in Settings → System Status

### Low-Quality Topics

**Problem**: Topics are generic or not relevant
**Solutions**:
- Make your niche more specific
- Add focus keywords to guide the AI
- Increase topic count to get more options to choose from
- Research multiple times and compare results

### Topics Not Relevant to Audience

**Problem**: Topics are trending but not right for your specific audience
**Solutions**:
- Refine your niche description
- Use very specific focus keywords
- Filter topics by keywords in the library
- Remember: you control which topics to schedule

### Scheduling Fails

**Problem**: Bulk scheduling doesn't work
**Solutions**:
- Verify you have active templates
- Check that start date is in the future
- Ensure you've selected at least one topic
- Check WordPress error logs

## Advanced Usage

### Custom Research Prompts

For developers: Extend the research functionality by filtering the AI prompt:

```php
add_filter('aips_research_prompt', function($prompt, $niche, $count, $keywords) {
    // Add custom instructions to the prompt
    $prompt .= "\n\nAdditional requirement: Focus on long-form content opportunities.";
    return $prompt;
}, 10, 4);
```

### Event Hooks

React to research events:

```php
// When automated research completes
add_action('aips_scheduled_research_completed', function($data) {
    $niche = $data['niche'];
    $topics_count = $data['topics_count'];
    
    // Send notification, log to external service, etc.
    wp_mail(
        get_option('admin_email'),
        "Research Completed: {$niche}",
        "Found {$topics_count} trending topics in {$niche}"
    );
});

// When a trending topic is scheduled
add_action('aips_trending_topic_scheduled', function($data) {
    // Track scheduled topics in analytics
    // data includes: schedule_id, topic, template_id, next_run
});
```

### Database Queries

Access trending topics programmatically:

```php
$repository = new AIPS_Trending_Topics_Repository();

// Get top 10 topics from last 7 days
$top_topics = $repository->get_top_topics(10, 7);

// Get topics for specific niche
$marketing_topics = $repository->get_by_niche('Digital Marketing', 20, 30);

// Search topics
$ai_topics = $repository->search('artificial intelligence', 15);

// Get statistics
$stats = $repository->get_stats();
// Returns: total_topics, niches_count, avg_score, recent_research_count
```

## FAQ

**Q: How often should I research topics?**
A: Weekly for fast-moving niches (tech, news), bi-weekly or monthly for slower niches (evergreen content).

**Q: Can I edit topics after research?**
A: Not directly, but you can delete unwanted topics and manually add topics to templates if needed.

**Q: Do researched topics expire?**
A: Topics remain in your library indefinitely, but older topics may become less relevant. Use the "Fresh Only" filter to focus on recent research.

**Q: Can I research multiple niches at once?**
A: Research one niche at a time manually, but automated research can handle multiple niches.

**Q: How many AI API calls does research use?**
A: One API call per research request (per niche). Each call can discover 1-50 topics.

**Q: Can I export research results?**
A: Not currently in the UI, but you can query the database directly for export.

## Tips for Content Creators

1. **Research Before Planning**: Do your topic research at the start of the week/month
2. **Combine with Calendar**: Align trending topics with your editorial calendar
3. **A/B Test Topics**: Try scheduling both high and medium-scored topics to see what performs
4. **Monitor Performance**: Track which topics get the most engagement
5. **Iterate**: Refine your niche and keywords based on what works
6. **Stay Current**: Even evergreen niches have trending angles - research regularly

## Support

For issues, feature requests, or questions:
- Check System Status page for diagnostics
- Review plugin logs for errors
- Contact plugin support with specific error messages
- Include niche, keywords, and any error messages when reporting issues
