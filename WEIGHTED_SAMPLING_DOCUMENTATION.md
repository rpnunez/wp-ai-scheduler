# Weighted Topic Sampling & Scheduling Feature

## Overview

This feature extends the Authors functionality with intelligent topic selection using weighted sampling and scheduling priority adjustments. Topics are scored based on feedback history and recency, with higher-scored topics having a better chance of selection and earlier scheduling.

## Implementation Details

### 1. Scoring Algorithm

Topics are scored using the formula:
```
topic_score = base + alpha * approved_count - beta * rejected_count - gamma * recency_penalty
```

Where:
- **base**: Base score for all topics (default: 50)
- **alpha**: Weight multiplier for approved feedback (default: 10)
- **beta**: Weight multiplier for rejected feedback (default: 15)
- **gamma**: Weight multiplier for recency penalty (default: 5)
- **approved_count**: Number of approvals in topic feedback
- **rejected_count**: Number of rejections in topic feedback
- **recency_penalty**: Days since reviewed / 30 (normalized to months)

A minimum score of 1 is enforced to ensure all topics maintain a chance of selection.

### 2. Weighted Sampling

Instead of simple sequential selection, topics are selected using weighted random sampling where:
1. Each topic's score is normalized to a probability
2. Higher-scored topics have proportionally higher selection probability
3. Selection is randomized but biased toward higher-scored topics

Example probabilities for 4 topics:
- Topic with score 80: ~35% selection chance
- Topic with score 70: ~30% selection chance
- Topic with score 50: ~22% selection chance
- Topic with score 30: ~13% selection chance

### 3. Scheduling Priority Bump

Posts generated from highly-scored topics receive a scheduling adjustment:
1. Only applies to posts with 'future' status (scheduled posts)
2. Calculates bump based on score difference from base: `(score - base) / base`
3. Multiplies by configurable bump time (default: 3600 seconds = 1 hour)
4. Adjusts post date earlier for positive scores, later for negative scores
5. Never schedules in the past (minimum 1 minute in future)

## Configuration

New options in `AIPS_Config`:

```php
'aips_topic_scoring_base' => 50,              // Base score
'aips_topic_scoring_alpha' => 10,             // Approval weight
'aips_topic_scoring_beta' => 15,              // Rejection weight
'aips_topic_scoring_gamma' => 5,              // Recency weight
'aips_topic_scheduling_priority_bump' => 3600 // Scheduling adjustment (seconds)
```

Access via:
```php
$config = AIPS_Config::get_instance();
$scoring_config = $config->get_topic_scoring_config();
```

## New Methods

### AIPS_Author_Topics_Repository

```php
/**
 * Get approved topics with weighted sampling.
 *
 * @param int   $author_id Author ID
 * @param int   $limit     Maximum topics to return
 * @param array $config    Optional scoring config
 * @return array Array of topic objects with computed_score property
 */
public function get_approved_for_generation_weighted($author_id, $limit = 1, $config = array())
```

### AIPS_Config

```php
/**
 * Get topic scoring configuration.
 *
 * @return array Configuration with keys: base, alpha, beta, gamma, scheduling_priority_bump
 */
public function get_topic_scoring_config()
```

### AIPS_Author_Post_Generator (Private)

```php
/**
 * Apply scheduling priority bump to a post.
 *
 * @param int    $post_id Post ID
 * @param object $topic   Topic object with computed_score
 */
private function apply_scheduling_priority_bump($post_id, $topic)
```

## Usage

The feature is automatically integrated into the post generation flow:

```php
// Old behavior (sequential selection)
$topics = $this->topics_repository->get_approved_for_generation($author->id, 1);

// New behavior (weighted sampling)
$topics = $this->topics_repository->get_approved_for_generation_weighted($author->id, 1);
```

When `generate_post_for_author()` is called, it:
1. Uses weighted sampling to select a topic
2. Generates the post from that topic
3. Applies scheduling priority bump if applicable
4. Logs the topic score in metadata

## Testing

### Test Files

1. **test-weighted-topic-sampling.php**: Tests weighted sampling algorithm
   - Score calculation with various feedback scenarios
   - Weighted selection behavior
   - Minimum score enforcement
   - Recency penalty application
   - Multiple topic selection

2. **test-config-topic-scoring.php**: Tests configuration
   - Default values
   - Custom value retrieval
   - Config method structure

### Verification Script

Run `php verification/verify_weighted_sampling.php` to see:
- Score calculations for different scenarios
- Probability distribution visualization
- 1000-sample simulation results

## Example Scenarios

### Scenario 1: Popular Topic
- Approved: 5, Rejected: 0, Days old: 7
- Score: 50 + (10×5) - (15×0) - (5×0.23) ≈ 98.83
- High selection probability, scheduled earlier

### Scenario 2: Topic with Rejections
- Approved: 1, Rejected: 3, Days old: 14
- Score: 50 + (10×1) - (15×3) - (5×0.47) ≈ 12.67
- Low selection probability, scheduled later

### Scenario 3: Old Topic
- Approved: 2, Rejected: 0, Days old: 90
- Score: 50 + (10×2) - (15×0) - (5×3) = 55
- Moderate selection probability despite age

### Scenario 4: Heavily Rejected
- Approved: 0, Rejected: 10, Days old: 5
- Score: max(1, 50 + 0 - 150 - 0.83) = 1
- Minimum score ensures still has a chance

## Performance Considerations

1. **Database Query**: Single query joins topics with feedback counts
2. **Scoring Calculation**: O(n) where n = number of approved topics
3. **Sampling**: O(n×limit) for selecting multiple topics
4. **Memory**: Maintains topic list in memory during sampling

For typical use cases (<100 approved topics per author), performance is negligible.

## Backward Compatibility

The original `get_approved_for_generation()` method remains unchanged and functional. Only `generate_post_for_author()` was updated to use weighted sampling. Manual topic generation via `generate_now()` continues to work without changes.

## Future Enhancements

Potential improvements:
1. Admin UI to configure scoring parameters
2. Topic score display in admin interface
3. Historical score tracking over time
4. A/B testing framework for scoring algorithms
5. Machine learning integration for adaptive weights
