# Research Features API Reference

## AJAX Endpoints

### `wp_ajax_aips_research_topics`
Triggers a new research session.

- **Parameters**:
  - `niche` (string): The niche to research.
  - `count` (int): Number of topics to generate (default: 10).
  - `keywords` (string): Comma-separated focus keywords.
  - `nonce`: Security nonce.
- **Response**: JSON object containing the list of generated topics.

### `wp_ajax_aips_get_trending_topics`
Retrieves topics from the library.

- **Parameters**:
  - `niche` (string, optional): Filter by niche.
  - `min_score` (int, optional): Minimum score filter.
  - `days` (int, optional): Freshness filter (days since research).
  - `nonce`: Security nonce.
- **Response**: JSON object containing the list of topics.

### `wp_ajax_aips_delete_trending_topic`
Deletes a specific topic.

- **Parameters**:
  - `id` (int): Topic ID.
  - `nonce`: Security nonce.
- **Response**: Success/error message.

### `wp_ajax_aips_schedule_trending_topics`
Schedules selected topics.

- **Parameters**:
  - `topic_ids` (array): List of topic IDs.
  - `template_id` (int): ID of the template to use.
  - `start_date` (string): Start date (Y-m-d).
  - `frequency` (string): Schedule frequency (e.g., 'daily').
  - `nonce`: Security nonce.
- **Response**: Success/error message.

## Repository Methods

### `AIPS_Trending_Topics_Repository`

```php
/**
 * Create a new topic.
 * @param array $data Topic data.
 * @return int|false Insert ID or false.
 */
public function create($data);

/**
 * Get topics by niche.
 * @param string $niche Niche name.
 * @param int $limit Limit results.
 * @param int $offset Offset results.
 * @return array List of objects.
 */
public function get_by_niche($niche, $limit = 20, $offset = 0);

/**
 * Get top scoring topics.
 * @param int $limit Limit results.
 * @param int $days_fresh Days to look back.
 * @return array List of objects.
 */
public function get_top_topics($limit = 10, $days_fresh = 30);

/**
 * Delete a topic.
 * @param int $id Topic ID.
 * @return bool Success.
 */
public function delete($id);
```

## Service Methods

### `AIPS_Research_Service`

```php
/**
 * Research a niche.
 * @param string $niche Niche name.
 * @param int $count Number of topics.
 * @param array $keywords Focus keywords.
 * @return array|WP_Error List of topics or error.
 */
public function research_niche($niche, $count = 10, $keywords = array());
```
