# Authors Feature

## Overview

The **Authors Feature** introduces a new workflow for generating blog posts that solves the problem of duplicate content topics. Instead of generating posts directly, it generates topic ideas first, allows admin review, and then generates posts from approved topics. This creates better content diversity and gives editors control over what gets published.

## Problem Statement

When using the standard Templates feature with prompts like "write about a popular PHP framework", the AI might generate 10 different posts all about Laravel (the most popular framework), resulting in duplicate content. The Authors feature solves this by:

1. **Generating diverse topics first** - AI creates multiple topic ideas
2. **Admin review** - Editors approve/reject topics before post generation
3. **Feedback loop** - The system learns from approved/rejected topics to improve future suggestions
4. **Scheduled generation** - Topics and posts are generated on separate schedules

## Key Features

### 1. Author Personas
Define "Authors" with specific niches (e.g., "PHP Expert", "Marketing Guru"). Each author has their own schedule for generating topics and posts.

### 2. Two-Stage Generation
- **Stage 1: Topic Generation**: The system generates a list of potential blog post titles.
- **Stage 2: Post Generation**: The system picks an *approved* topic and generates a full blog post.

### 3. Feedback Loop
The system learns from your actions. When generating new topics, it looks at:
- **Approved Topics**: To avoid duplicates and understand what you like.
- **Rejected Topics**: To avoid similar ideas or bad suggestions.

### 4. Audit Trail
Every action is logged. You can see who approved a topic, when a post was generated, and link back to the original AI request.

## Usage Example

### Creating an Author for PHP Content

1. Go to **AI Post Scheduler → Authors**
2. Click **Add New Author**
3. Fill in:
   - Name: "PHP Expert"
   - Field/Niche: "PHP Programming"
   - Topic Generation: Weekly, 10 topics
   - Post Generation: Daily
4. Save and activate

### Workflow After Creation

- **Week 1**: System generates 10 PHP topic ideas (e.g., "Best PHP Frameworks in 2024", "PHP 8.3 New Features", etc.)
- **Admin reviews**: Approves 8, rejects 2 (too similar to existing content)
- **Daily**: System generates one post per day from approved topics
- **Week 2**: System generates 10 NEW topics, using approved/rejected history to avoid duplicates
- **Result**: Diverse content covering different aspects of PHP

## Documentation Index

- [Architecture](ARCHITECTURE.md) - Code structure and technical design
- [Database Schema](DATABASE.md) - Database tables and relationships
- [Workflow](WORKFLOW.md) - Detailed step-by-step process flow
- [API Reference](API.md) - AJAX endpoints and repository methods
- [Feedback Loop](FEEDBACK_LOOP.md) - How the AI learning system works
- [User Interface](UI.md) - Admin screens and interactions
- [Topic Posts View](TOPIC_POSTS_VIEW.md) - Viewing posts generated from topics
- [Topic Expansion](TOPIC_EXPANSION.md) - Generating new topics from existing ones
