# AI Post Scheduler - Feature Flowcharts

**Version:** 1.7.0  
**Last Updated:** 2026-01-23

This document provides visual flowcharts for each major feature in the AI Post Scheduler plugin using Mermaid syntax.

---

## Table of Contents

1. [Template System](#1-template-system)
2. [Scheduling System](#2-scheduling-system)
3. [Authors Feature](#3-authors-feature)
4. [Voices Feature](#4-voices-feature)
5. [Article Structures](#5-article-structures)
6. [Trending Topics Research](#6-trending-topics-research)
7. [Planner](#7-planner-bulk-topic-scheduling)
8. [AI Content Generation](#8-ai-content-generation-pipeline)
9. [History & Activity](#9-history--activity-tracking)
10. [Data Management](#10-data-management)

---

## 1. Template System

### Template Creation & Management Flow

```mermaid
flowchart TD
    A[Admin Opens Templates Page] --> B[Click 'Add New Template']
    B --> C[Enter Template Details]
    C --> D{Configure Prompts}
    D --> E[Content Prompt]
    D --> F[Title Prompt]
    D --> G[Excerpt Prompt]
    D --> H[Image Prompt - Optional]
    E --> I[Add Template Variables]
    F --> I
    G --> I
    H --> I
    I --> J{Assign Voice?}
    J -->|Yes| K[Select Voice from Dropdown]
    J -->|No| L[Continue]
    K --> L
    L --> M[Configure Post Settings]
    M --> N[Status: Draft/Publish/Private/Pending]
    M --> O[Select Categories]
    M --> P[Add Tags]
    M --> Q[Select Author]
    M --> R[Featured Image Settings]
    N --> S{Test Generate?}
    O --> S
    P --> S
    Q --> S
    R --> S
    S -->|Yes| T[Click 'Test Generate']
    T --> U[AI Generates Preview]
    U --> V{Preview Satisfactory?}
    V -->|No| C
    V -->|Yes| W[Save Template]
    S -->|No| W
    W --> X[Template Saved to Database]
    X --> Y[Available for Scheduling]
```

### Template Variables Processing

```mermaid
flowchart LR
    A[Template with Variables] --> B{Process Variables}
    B --> C[{{date}}] --> C1[Replace with Current Date]
    B --> D[{{year}}] --> D1[Replace with Current Year]
    B --> E[{{month}}] --> E1[Replace with Current Month]
    B --> F[{{day}}] --> F1[Replace with Day of Week]
    B --> G[{{time}}] --> G1[Replace with Current Time]
    B --> H[{{site_name}}] --> H1[Replace with Site Name]
    B --> I[{{topic}}] --> I1[Replace with Schedule Topic]
    B --> J[{{random_number}}] --> J1[Replace with Random 1-1000]
    C1 --> K[Final Processed Prompt]
    D1 --> K
    E1 --> K
    F1 --> K
    G1 --> K
    H1 --> K
    I1 --> K
    J1 --> K
    K --> L[Send to AI Engine]
```

---

## 2. Scheduling System

### Schedule Creation & Execution Flow

```mermaid
flowchart TD
    A[Admin Opens Schedule Page] --> B[Click 'Add New Schedule']
    B --> C[Select Template]
    C --> D[Choose Frequency]
    D --> E{Frequency Type}
    E -->|Hourly| F[Every 1 Hour]
    E -->|Every 4h| G[Every 4 Hours]
    E -->|Daily| H[Every 24 Hours]
    E -->|Weekly| I[Every 7 Days]
    E -->|Monthly| J[Every 30 Days]
    E -->|Once| K[One-Time Only]
    E -->|Weekdays| L[Specific Days]
    F --> M[Set Start Date/Time]
    G --> M
    H --> M
    I --> M
    J --> M
    K --> M
    L --> M
    M --> N[Set Post Quantity]
    N --> O{Article Structure?}
    O -->|Yes| P[Select Structure]
    O -->|No| Q[Use Default]
    P --> R[Choose Rotation Pattern]
    Q --> R
    R --> S{Rotation Type}
    S -->|Sequential| T[Cycle Through All]
    S -->|Random| U[Random Selection]
    S -->|Weighted| V[Favor Default 2x]
    S -->|Alternating| W[Alternate Top 2]
    T --> X[Save Schedule]
    U --> X
    V --> X
    W --> X
    X --> Y[Schedule Active in Database]
    Y --> Z[WordPress Cron Picks Up]
    Z --> AA[Generate Post at Scheduled Time]
```

### Cron Execution Flow

```mermaid
flowchart TD
    A[WordPress Cron Runs Hourly] --> B[aips_generate_scheduled_posts Hook]
    B --> C[Scheduler Service Activated]
    C --> D{Find Active Schedules}
    D --> E[Query: is_active=1 AND next_run <= NOW]
    E --> F{Schedules Found?}
    F -->|No| G[Log: No schedules due]
    F -->|Yes| H[Loop Through Each Schedule]
    H --> I[Get Template for Schedule]
    I --> J{Article Structure?}
    J -->|Yes| K[Select Structure via Rotation]
    J -->|No| L[Use Default Structure]
    K --> M[Build Content Prompt]
    L --> M
    M --> N[Call Generator Service]
    N --> O[Generate Post]
    O --> P{Generation Success?}
    P -->|Yes| Q[Save to wp_posts]
    P -->|No| R[Log Error to History]
    Q --> S[Update Schedule next_run]
    R --> S
    S --> T{More Schedules?}
    T -->|Yes| H
    T -->|No| U[Cron Execution Complete]
```

---

## 3. Authors Feature

### Authors Workflow - Complete System

```mermaid
flowchart TD
    A[Admin Creates Author] --> B[Configure Author]
    B --> C[Enter Name & Niche]
    C --> D[Set Topic Generation Frequency]
    C --> E[Set Post Generation Frequency]
    C --> F[Set Topic Quantity]
    D --> G[Save Author to Database]
    E --> G
    F --> G
    G --> H[Activate Author]
    H --> I[Topic Generation Cron Starts]
    
    I --> J{Topic Gen Due?}
    J -->|Yes| K[Generate Topics via AI]
    J -->|No| L[Wait for Next Run]
    K --> M[Feedback Loop Context]
    M --> N[Include Approved Topics Summary]
    M --> O[Include Rejected Topics Summary]
    N --> P[AI Creates Diverse Topics]
    O --> P
    P --> Q[Save Topics with status=pending]
    Q --> R[Update topic_generation_next_run]
    R --> L
    
    L --> S[Admin Reviews Topics]
    S --> T{Topic Decision}
    T -->|Approve| U[Status = approved]
    T -->|Reject| V[Status = rejected]
    T -->|Edit| W[Update Topic Title]
    T -->|Delete| X[Remove Topic]
    T -->|Generate Now| Y[Manual Post Generation]
    
    U --> Z[Log Approval Action]
    V --> AA[Log Rejection Action]
    W --> AB[Log Edit Action]
    Y --> AC[Generate Post Immediately]
    
    Z --> AD[Topic in Approved Pool]
    AD --> AE{Post Gen Due?}
    AE -->|Yes| AF[Get Next Approved Topic FIFO]
    AE -->|No| AG[Wait for Next Run]
    AF --> AH[Generate Post from Topic]
    AH --> AI[Save Post to wp_posts]
    AI --> AJ[Link Post to Topic in Logs]
    AJ --> AK[Update post_generation_next_run]
    AK --> AG
    AG --> AE
    
    AA --> AL[Improve Future Suggestions]
    AL --> M
```

### Topic Approval Flow - Detailed

```mermaid
flowchart TD
    A[Pending Topics List] --> B{Admin Action}
    B -->|Approve| C[AJAX: aips_approve_topic]
    B -->|Reject| D[AJAX: aips_reject_topic]
    B -->|Edit| E[AJAX: aips_edit_topic]
    B -->|Delete| F[AJAX: aips_delete_topic]
    B -->|Generate Post| G[AJAX: aips_generate_post_from_topic]
    
    C --> H[Update status to 'approved']
    D --> I[Update status to 'rejected']
    E --> J[Update topic_title]
    F --> K[DELETE from database]
    G --> L[Call Post Generator]
    
    H --> M[Log to author_topic_logs]
    I --> M
    J --> M
    K --> M
    L --> N[Generate WordPress Post]
    
    M --> O[Apply Feedback]
    O --> P{Approval?}
    P -->|Yes| Q[Reward Score +10]
    P -->|No| R[Penalty Score -5]
    
    Q --> S[Update Feedback Table]
    R --> S
    S --> T[Feedback Used in Next Generation]
    
    N --> U[Post Created Successfully]
    U --> V[Link Post ID to Topic]
    V --> M
```

---

## 4. Voices Feature

### Voice Creation & Usage Flow

```mermaid
flowchart TD
    A[Admin Opens Voices Page] --> B[Click 'Add New Voice']
    B --> C[Enter Voice Details]
    C --> D[Voice Name]
    C --> E[Voice Description]
    C --> F[Title Guidance]
    C --> G[Content Guidance]
    C --> H[Excerpt Guidance]
    D --> I[Save Voice]
    E --> I
    F --> I
    G --> I
    H --> I
    I --> J[Voice Available in Database]
    J --> K[Admin Creates Template]
    K --> L[Select Voice from Dropdown]
    L --> M[Voice Assigned to Template]
    M --> N[Generation Time]
    N --> O{Voice Assigned?}
    O -->|Yes| P[Append Voice Guidance to Prompts]
    O -->|No| Q[Use Prompts Only]
    P --> R[Title Prompt + Voice Title Guidance]
    P --> S[Content Prompt + Voice Content Guidance]
    P --> T[Excerpt Prompt + Voice Excerpt Guidance]
    R --> U[Combined Prompt to AI]
    S --> U
    T --> U
    Q --> V[Original Prompts to AI]
    U --> W[Generate Content]
    V --> W
```

---

## 5. Article Structures

### Structure Selection & Rotation Flow

```mermaid
flowchart TD
    A[Schedule Execution] --> B{Article Structure Set?}
    B -->|No| C[Use Default Structure]
    B -->|Yes| D{Rotation Pattern Set?}
    D -->|No| E[Use Specified Structure]
    D -->|Yes| F{Pattern Type}
    
    F -->|Sequential| G[Get Next Structure in Order]
    F -->|Random| H[Randomly Select Structure]
    F -->|Weighted| I[Weighted Random - Default 2x]
    F -->|Alternating| J[Alternate Between Top 2]
    
    G --> K[Increment Rotation Counter]
    H --> K
    I --> K
    J --> K
    
    C --> L[Load Default Structure]
    E --> L
    K --> L
    
    L --> M[Get Structure Data]
    M --> N[Load Prompt Sections]
    N --> O{Structure Has Sections?}
    O -->|Yes| P[Build Prompt from Sections]
    O -->|No| Q[Use Structure Template]
    
    P --> R[Replace Section Placeholders]
    R --> S[Process Template Variables]
    Q --> S
    
    S --> T[Final Structured Prompt]
    T --> U[Send to AI Engine]
    U --> V[Generate Structured Content]
```

### Structure Builder Flow

```mermaid
flowchart LR
    A[Admin Creates Structure] --> B[Define Structure Name]
    B --> C[Select Prompt Sections]
    C --> D{Section Types}
    D --> E[Introduction]
    D --> F[Prerequisites]
    D --> G[Steps]
    D --> H[Examples]
    D --> I[Tips]
    D --> J[Troubleshooting]
    D --> K[Conclusion]
    D --> L[Resources]
    E --> M[Build Structure Template]
    F --> M
    G --> M
    H --> M
    I --> M
    J --> M
    K --> M
    L --> M
    M --> N[Set as Default?]
    N -->|Yes| O[Mark is_default=1]
    N -->|No| P[Save Structure]
    O --> P
    P --> Q[Available for Schedules]
```

---

## 6. Trending Topics Research

### Research & Discovery Flow

```mermaid
flowchart TD
    A[Admin Opens Trending Topics] --> B[Enter Niche]
    B --> C[Optional: Add Focus Keywords]
    C --> D[Set Topic Count 1-50]
    D --> E[Click 'Research Trending Topics']
    E --> F[AJAX: aips_research_topics]
    F --> G[Build Research Prompt]
    G --> H[Include Niche]
    G --> I[Include Keywords]
    G --> J[Include Current Date/Season]
    H --> K[AI Engine Call]
    I --> K
    J --> K
    K --> L[AI Analyzes Trends]
    L --> M[Returns Topics Array]
    M --> N{For Each Topic}
    N --> O[Calculate Relevance Score]
    O --> P{Score Factors}
    P --> Q[Temporal: Current Year/Trending 20pts]
    P --> R[Seasonal: Month/Holiday 15pts]
    P --> S[Search Volume: AI Analysis]
    P --> T[Content Gaps: AI Analysis]
    P --> U[Evergreen Value: AI Analysis]
    Q --> V[Total Score 1-100]
    R --> V
    S --> V
    T --> V
    U --> V
    V --> W[Extract Keywords]
    W --> X[Generate Reason String]
    X --> Y[Save to trending_topics Table]
    Y --> Z{More Topics?}
    Z -->|Yes| N
    Z -->|No| AA[Display Results]
    AA --> AB[Show Top 5 in Results Box]
    AB --> AC[All Saved to Library]
```

### Topics Library & Scheduling Flow

```mermaid
flowchart TD
    A[Admin Views Topics Library] --> B{Apply Filters}
    B --> C[Filter by Niche]
    B --> D[Filter by Score 80+/90+]
    B --> E[Filter by Freshness 7 days]
    C --> F[Load Filtered Topics]
    D --> F
    E --> F
    F --> G[Display Topics Table]
    G --> H[Admin Selects Topics]
    H --> I{Bulk Actions}
    I -->|Schedule| J[Click 'Schedule Topics']
    I -->|Delete| K[Delete Selected]
    J --> L[Bulk Schedule Modal]
    L --> M[Select Template]
    L --> N[Set Start Date]
    L --> O[Choose Frequency]
    M --> P[Click 'Schedule']
    N --> P
    O --> P
    P --> Q{For Each Selected Topic}
    Q --> R[Create Schedule Entry]
    R --> S[topic field = topic_title]
    S --> T[template_id = selected template]
    T --> U[frequency = chosen frequency]
    U --> V[next_run = calculated from start_date]
    V --> W[is_active = 1]
    W --> X[Save to schedule table]
    X --> Y{More Topics?}
    Y -->|Yes| Q
    Y -->|No| Z[All Topics Scheduled]
    Z --> AA[Cron Will Pick Up]
```

### Automated Research Flow

```mermaid
flowchart TD
    A[Daily Cron: aips_scheduled_research] --> B[Get Research Configuration]
    B --> C[Load Niches from Options]
    C --> D{Niches Configured?}
    D -->|No| E[Skip Automated Research]
    D -->|Yes| F{For Each Niche}
    F --> G[Research Niche]
    G --> H[AI Discovers Topics]
    H --> I[Score and Save Topics]
    I --> J{More Niches?}
    J -->|Yes| F
    J -->|No| K[Fire Hook: aips_scheduled_research_completed]
    K --> L[Log Research Stats]
    L --> M[Email Admin - Optional]
```

---

## 7. Planner (Bulk Topic Scheduling)

### Planner Workflow

```mermaid
flowchart TD
    A[Admin Opens Planner] --> B{Topic Source}
    B -->|AI Generate| C[Enter Niche]
    B -->|Manual Entry| D[Paste Topics List]
    
    C --> E[Set Topic Count 1-50]
    E --> F[Click 'Generate Topics']
    F --> G[AJAX: aips_generate_planner_topics]
    G --> H[AI Brainstorms Topics]
    H --> I[Display Topics in Grid]
    
    D --> J[Click 'Add to List']
    J --> K[Parse Line-by-Line]
    K --> I
    
    I --> L[Topics Displayed]
    L --> M{Admin Actions}
    M -->|Edit| N[Inline Edit Topic Text]
    M -->|Select| O[Check Topic Boxes]
    M -->|Select All| P[Check All Boxes]
    M -->|Clear| Q[Remove All Topics]
    M -->|Copy| R[Copy Selected to Clipboard]
    
    N --> S[Topics Ready]
    O --> S
    P --> S
    
    S --> T[Configure Bulk Schedule]
    T --> U[Select Template]
    T --> V[Set Start Date]
    T --> W[Choose Frequency]
    
    U --> X[Click 'Schedule Selected Topics']
    V --> X
    W --> X
    
    X --> Y[AJAX: aips_bulk_schedule_planner]
    Y --> Z{For Each Selected}
    Z --> AA[Create Schedule]
    AA --> AB[topic = topic text]
    AB --> AC[Use {{topic}} variable in template]
    AC --> AD[Calculate staggered next_run]
    AD --> AE[Save Schedule]
    AE --> AF{More Topics?}
    AF -->|Yes| Z
    AF -->|No| AG[Success Message]
    AG --> AH[Schedules Created]
```

---

## 8. AI Content Generation Pipeline

### Complete Generation Process

```mermaid
flowchart TD
    A[Generation Triggered] --> B{Trigger Source}
    B -->|Schedule| C[From Schedule Table]
    B -->|Authors| D[From Approved Topic]
    B -->|Planner| E[From Planner Topic]
    B -->|Manual| F[Test Generate / Run Now]
    
    C --> G[Load Template]
    D --> G
    E --> G
    F --> G
    
    G --> H[Load Template Config]
    H --> I{Voice Assigned?}
    I -->|Yes| J[Load Voice Guidance]
    I -->|No| K[Voice = null]
    J --> L[Process Template Variables]
    K --> L
    
    L --> M{Article Structure?}
    M -->|Yes| N[Load Structure & Sections]
    M -->|No| O[Use Template Prompts Only]
    N --> P[Build Structured Prompt]
    O --> P
    
    P --> Q[Start Generation Session]
    Q --> R[Log to History Table]
    R --> S[Generate Title]
    S --> T[AI Service Call - Title]
    T --> U{Title Success?}
    U -->|No| V[Retry with Backoff]
    V --> W{Max Retries?}
    W -->|Yes| X[Circuit Breaker Open]
    W -->|No| T
    U -->|Yes| Y[Title Generated]
    X --> Z[Log Error & Fail]
    Y --> AA[Generate Content]
    AA --> AB[AI Service Call - Content]
    AB --> AC{Content Success?}
    AC -->|No| V
    AC -->|Yes| AD[Content Generated]
    AD --> AE[Generate Excerpt]
    AE --> AF[AI Service Call - Excerpt]
    AF --> AG{Excerpt Success?}
    AG -->|No| AH[Use Content Substring]
    AG -->|Yes| AI[Excerpt Generated]
    AH --> AI
    AI --> AJ{Featured Image?}
    AJ -->|Enabled| AK[Generate Image]
    AJ -->|Disabled| AL[Skip Image]
    AK --> AM{Image Source}
    AM -->|AI-Generated| AN[AI Service Call - Image]
    AM -->|Unsplash| AO[Unsplash API Call]
    AM -->|Media Library| AP[Use Existing Image]
    AN --> AQ{Image Success?}
    AO --> AQ
    AP --> AQ
    AQ -->|Yes| AR[Image URL Obtained]
    AQ -->|No| AL
    AR --> AS[Create WordPress Post]
    AL --> AS
    AS --> AT[Set Post Title]
    AT --> AU[Set Post Content]
    AU --> AV[Set Post Excerpt]
    AV --> AW[Set Post Status]
    AW --> AX[Set Post Author]
    AX --> AY[Set Categories]
    AY --> AZ[Set Tags]
    AZ --> BA{Image Available?}
    BA -->|Yes| BB[Set Featured Image]
    BA -->|No| BC[Skip Featured Image]
    BB --> BD[wp_insert_post]
    BC --> BD
    BD --> BE{Post Created?}
    BE -->|Yes| BF[Update History: Success]
    BE -->|No| BG[Update History: Failed]
    BF --> BH[Fire Hook: aips_post_generated]
    BG --> BI[Fire Hook: aips_post_generation_failed]
    BH --> BJ[Log Success Details]
    BI --> BK[Log Error Details]
    BJ --> BL[Return Post ID]
    BK --> BL
```

### Resilience & Error Handling

```mermaid
flowchart TD
    A[AI Service Call] --> B{Call Succeeds?}
    B -->|Yes| C[Return Response]
    B -->|No| D[Error Occurred]
    D --> E{Error Type}
    E -->|Network| F[Network Error]
    E -->|Rate Limit| G[Rate Limit Hit]
    E -->|Timeout| H[Request Timeout]
    E -->|API Error| I[API Returned Error]
    
    F --> J[Retry Counter]
    G --> J
    H --> J
    I --> J
    
    J --> K{Retry < Max?}
    K -->|Yes| L[Calculate Backoff]
    K -->|No| M[Circuit Breaker Check]
    
    L --> N[Wait: retry^2 seconds]
    N --> O[Retry++]
    O --> A
    
    M --> P{Failure Rate High?}
    P -->|Yes| Q[Open Circuit Breaker]
    P -->|No| R[Record Failure]
    
    Q --> S[Block All Calls for 5 min]
    S --> T[Return Error]
    R --> T
    
    C --> U{Circuit Open?}
    U -->|Yes| V[Half-Open State]
    U -->|No| W[Return Success]
    V --> X[Test Call]
    X --> Y{Success?}
    Y -->|Yes| Z[Close Circuit]
    Y -->|No| S
    Z --> W
```

---

## 9. History & Activity Tracking

### History Logging Flow

```mermaid
flowchart TD
    A[Generation Started] --> B[Create History Entry]
    B --> C[Insert into wp_aips_history]
    C --> D[Fields: template_id, status=pending, created_at]
    D --> E[Get history_id]
    E --> F[Generation Process Runs]
    F --> G{Logs During Process}
    G --> H[Title Generated - Log]
    G --> I[Content Generated - Log]
    G --> J[Excerpt Generated - Log]
    G --> K[Image Generated - Log]
    G --> L[Post Created - Log]
    H --> M[Insert wp_aips_history_log]
    I --> M
    J --> M
    K --> M
    L --> M
    M --> N{Generation Complete}
    N -->|Success| O[Update History: status=success]
    N -->|Failed| P[Update History: status=failed]
    O --> Q[Set post_id field]
    P --> R[Set error_message field]
    Q --> S[Update updated_at timestamp]
    R --> S
    S --> T[History Record Complete]
```

### Activity Tracking Flow

```mermaid
flowchart TD
    A[User Action] --> B{Action Type}
    B --> C[Template Saved]
    B --> D[Schedule Created]
    B --> E[Topic Approved]
    B --> F[Post Generated]
    B --> G[Settings Changed]
    C --> H[Log Activity]
    D --> H
    E --> H
    F --> H
    G --> H
    H --> I[Insert wp_aips_activity]
    I --> J[Fields: action_type, user_id, details JSON, created_at]
    J --> K[Activity Logged]
    K --> L[Activity Page Shows Entry]
    L --> M{Admin Views Details}
    M --> N[Click 'View Details']
    N --> O[Load Full Activity Data]
    O --> P[Display Modal with JSON]
```

---

## 10. Data Management

### Export Flow

```mermaid
flowchart TD
    A[Admin Opens Data Management] --> B[Select Export]
    B --> C{Export Format}
    C -->|MySQL| D[Select Tables]
    C -->|JSON| E[Select Tables]
    D --> F[MySQL Exporter]
    E --> G[JSON Exporter]
    F --> H[For Each Table]
    H --> I[Generate CREATE TABLE statement]
    I --> J[Generate INSERT statements]
    J --> K{More Tables?}
    K -->|Yes| H
    K -->|No| L[Combine into .sql file]
    L --> M[Download File]
    G --> N[For Each Table]
    N --> O[Query all rows]
    O --> P[Convert to JSON array]
    P --> Q{More Tables?}
    Q -->|Yes| N
    Q -->|No| R[Combine into JSON object]
    R --> S[Download .json file]
```

### Import Flow

```mermaid
flowchart TD
    A[Admin Selects Import] --> B{Import Format}
    B -->|MySQL| C[Upload .sql file]
    B -->|JSON| D[Upload .json file]
    C --> E[MySQL Importer]
    D --> F[JSON Importer]
    E --> G[Parse SQL file]
    G --> H{Validate SQL}
    H -->|Invalid| I[Error: Invalid Format]
    H -->|Valid| J[Backup Existing Tables]
    J --> K[Execute CREATE TABLE]
    K --> L[Execute INSERT statements]
    L --> M{All Successful?}
    M -->|No| N[Rollback - Restore Backup]
    M -->|Yes| O[Import Complete]
    F --> P[Parse JSON file]
    P --> Q{Validate JSON}
    Q -->|Invalid| R[Error: Invalid Format]
    Q -->|Valid| S[Backup Existing Tables]
    S --> T[For Each Table in JSON]
    T --> U[Truncate Target Table]
    U --> V[Insert Rows]
    V --> W{More Tables?}
    W -->|Yes| T
    W -->|No| X{All Successful?}
    X -->|No| Y[Rollback - Restore Backup]
    X -->|Yes| Z[Import Complete]
```

### Database Repair Flow

```mermaid
flowchart TD
    A[Admin Clicks 'Repair Database'] --> B[Run Database Checks]
    B --> C[Check Table Existence]
    C --> D{All Tables Exist?}
    D -->|No| E[Create Missing Tables]
    D -->|Yes| F[Check Table Structure]
    E --> F
    F --> G{Columns Correct?}
    G -->|No| H[Alter Tables - Add Missing Columns]
    G -->|Yes| I[Check Indexes]
    H --> I
    I --> J{Indexes Correct?}
    J -->|No| K[Create Missing Indexes]
    J -->|Yes| L[Run Table Optimization]
    K --> L
    L --> M[OPTIMIZE TABLE for each]
    M --> N[Check Foreign Keys]
    N --> O{Constraints Correct?}
    O -->|No| P[Add Missing Constraints]
    O -->|Yes| Q[Repair Complete]
    P --> Q
    Q --> R[Display Success Message]
```

---

## System Architecture Overview

### High-Level Plugin Architecture

```mermaid
flowchart TD
    A[WordPress Core] --> B[AI Post Scheduler Plugin]
    B --> C[Admin Menu Pages]
    B --> D[AJAX Controllers]
    B --> E[Service Layer]
    B --> F[Repository Layer]
    B --> G[Database Tables]
    B --> H[WordPress Cron]
    B --> I[Meow Apps AI Engine]
    
    C --> J[Dashboard]
    C --> K[Templates]
    C --> L[Schedules]
    C --> M[Authors]
    C --> N[Voices]
    C --> O[Research]
    C --> P[Planner]
    C --> Q[History]
    C --> R[Activity]
    C --> S[Settings]
    C --> T[System Status]
    
    D --> U[Template Controller]
    D --> V[Schedule Controller]
    D --> W[Authors Controller]
    D --> X[Research Controller]
    D --> Y[Activity Controller]
    
    E --> Z[Generator Service]
    E --> AA[AI Service]
    E --> AB[Research Service]
    E --> AC[Resilience Service]
    E --> AD[Image Service]
    
    F --> AE[Template Repository]
    F --> AF[Schedule Repository]
    F --> AG[Authors Repository]
    F --> AH[History Repository]
    F --> AI[Trending Topics Repository]
    
    G --> AJ[13 Custom Tables]
    
    H --> AK[4 Cron Jobs]
    AK --> AL[Generate Posts]
    AK --> AM[Generate Topics]
    AK --> AN[Generate Author Posts]
    AK --> AO[Automated Research]
    
    I --> AP[AI Engine API]
    AP --> AQ[OpenAI]
    AP --> AR[Other AI Providers]
```

---

## Conclusion

These flowcharts provide visual representations of how each feature works in the AI Post Scheduler plugin. They show:

- User interactions and decision points
- Data flow through the system
- Integration between features
- Error handling and resilience
- Cron automation processes
- Database operations

Use these diagrams to:
- Understand feature workflows
- Debug issues
- Plan new features
- Onboard new developers
- Document integrations

All diagrams use Mermaid syntax and can be rendered in GitHub, GitLab, VS Code, and many documentation platforms.
