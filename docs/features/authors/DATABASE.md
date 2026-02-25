# Authors Feature Database Schema

## Overview

The Authors feature introduces three new database tables to manage author configurations, topic ideas, and audit logs.

## Tables

### 1. `wp_aips_authors`

Stores author configurations for different content verticals.

| Column | Type | Description |
|---|---|---|
| `id` | `bigint(20) unsigned` | Primary key |
| `name` | `varchar(255)` | Display name (e.g., "PHP Expert") |
| `field_niche` | `text` | The topic domain (e.g., "PHP Programming") |
| `topic_generation_frequency` | `varchar(50)` | How often to generate new topics (daily, weekly, etc.) |
| `post_generation_frequency` | `varchar(50)` | How often to generate posts from approved topics |
| `topic_generation_quantity` | `int(11)` | Number of topics to generate per run |
| `article_structure_id` | `bigint(20) unsigned` | Optional article structure to use for posts |
| `is_active` | `tinyint(1)` | Whether this author is currently active |
| `topic_generation_next_run` | `datetime` | Next scheduled topic generation time |
| `post_generation_next_run` | `datetime` | Next scheduled post generation time |
| `created_at` | `datetime` | Creation timestamp |
| `updated_at` | `datetime` | Last update timestamp |

### 2. `wp_aips_author_topics`

Stores generated topic ideas awaiting review or approved for post generation.

| Column | Type | Description |
|---|---|---|
| `id` | `bigint(20) unsigned` | Primary key |
| `author_id` | `bigint(20) unsigned` | Foreign key to `wp_aips_authors` |
| `topic_title` | `text` | The suggested blog post title |
| `status` | `varchar(50)` | Current state: `pending`, `approved`, or `rejected` |
| `reviewed_at` | `datetime` | When the topic was reviewed |
| `reviewed_by` | `bigint(20) unsigned` | User ID who reviewed it |
| `created_at` | `datetime` | Creation timestamp |
| `updated_at` | `datetime` | Last update timestamp |

### 3. `wp_aips_author_topic_logs`

Audit trail for all actions on topics and generated posts.

| Column | Type | Description |
|---|---|---|
| `id` | `bigint(20) unsigned` | Primary key |
| `author_topic_id` | `bigint(20) unsigned` | Foreign key to `wp_aips_author_topics` |
| `post_id` | `bigint(20) unsigned` | WordPress post ID (if a post was generated) |
| `action` | `varchar(50)` | Type of action: `approved`, `rejected`, `edited`, `post_generated` |
| `user_id` | `bigint(20) unsigned` | Who performed the action |
| `notes` | `text` | Additional context (e.g., old title for edits) |
| `metadata` | `longtext` | JSON data for AI calls, etc. |
| `created_at` | `datetime` | Creation timestamp |

## Relationships

- **One Author** can have **Many Topics** (`wp_aips_authors.id` -> `wp_aips_author_topics.author_id`)
- **One Topic** can have **Many Logs** (`wp_aips_author_topics.id` -> `wp_aips_author_topic_logs.author_topic_id`)
- **One Log** can link to **One Post** (`wp_aips_author_topic_logs.post_id` -> `wp_posts.ID`)

## Indexes

- `wp_aips_authors`: `is_active`, `topic_generation_next_run`, `post_generation_next_run`
- `wp_aips_author_topics`: `author_id`, `status`
- `wp_aips_author_topic_logs`: `author_topic_id`, `post_id`, `action`
