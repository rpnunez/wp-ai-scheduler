# Research Features Architecture

## Overview

The Research features are built on a modular architecture that separates data access, business logic, and user interface.

## Components

### 1. Database Layer
- **`wp_aips_trending_topics`**: Stores researched topics.
  - `id`: Primary key
  - `niche`: The niche used for research
  - `topic`: The topic title
  - `score`: Relevance score (0-100)
  - `reason`: Why it's trending
  - `keywords`: JSON array of keywords
  - `researched_at`: Timestamp

### 2. Repository Layer
- **`AIPS_Trending_Topics_Repository`**: Handles CRUD operations for the `wp_aips_trending_topics` table.
  - `create()`: Save a new topic
  - `get_by_niche()`: Retrieve topics for a niche
  - `get_top_topics()`: Get highest-scoring topics
  - `delete()`: Remove a topic

### 3. Service Layer
- **`AIPS_Research_Service`**: Manages the interaction with the AI.
  - `research_niche()`: Constructs the prompt, calls the AI service, parses the response, and saves topics via the repository.
  - `get_research_prompt()`: Builds the prompt for the AI.

### 4. Controller Layer
- **`AIPS_Research_Controller`**: Handles AJAX requests from the admin UI.
  - `ajax_research_topics()`: Triggers a research session.
  - `ajax_get_topics()`: Retrieves topics for the library view.
  - `ajax_delete_topic()`: Deletes a topic.
  - `ajax_schedule_topics()`: Adds topics to the schedule.

### 5. UI Layer
- **`templates/admin/trending-topics.php`**: The main admin page.
- **`assets/js/trending-topics.js`**: JavaScript for handling UI interactions (research, filtering, scheduling).

## Data Flow

1. **User Action**: User clicks "Research" in the admin UI.
2. **AJAX Request**: `AIPS_Research_Controller::ajax_research_topics` is called.
3. **Service Call**: Controller calls `AIPS_Research_Service::research_niche`.
4. **AI Interaction**: Service calls `AIPS_AI_Service` to get topic ideas.
5. **Data Storage**: Service parses the AI response and saves topics using `AIPS_Trending_Topics_Repository`.
6. **Response**: Controller returns the new topics to the UI.
7. **Update**: UI updates to display the results.

## Integration Points

- **AI Engine**: Used for generating topic ideas and scores.
- **Scheduler**: The Planner integrates with `AIPS_Schedule_Repository` to create schedule entries.
- **Templates**: Topics are linked to `AIPS_Templates` when scheduled.
