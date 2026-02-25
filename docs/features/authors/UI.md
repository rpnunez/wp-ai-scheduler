# Authors Feature User Interface

## Overview

The Authors feature provides a user-friendly interface for managing authors and topics.

## Admin Screens

### Authors List

The main admin page (`templates/admin/authors.php`) displays a list of all authors.

- **Columns**:
  - Name
  - Field/Niche
  - Topic Generation Frequency
  - Post Generation Frequency
  - Status (Active/Inactive)
  - Actions (Edit, Delete, View Topics)

### Author Modal

The author modal allows you to create or edit an author.

- **Fields**:
  - Name
  - Field/Niche
  - Topic Generation Frequency
  - Post Generation Frequency
  - Topic Generation Quantity
  - Article Structure (Optional)
  - Active Status

### Topics Modal

The topics modal displays a list of topics for a specific author.

- **Tabs**:
  - Pending
  - Approved
  - Rejected

- **Columns**:
  - Topic Title
  - Status
  - Actions (Approve, Reject, Edit, Delete, Generate Post Now)

### Topic Posts Modal

The topic posts modal displays a list of posts generated from a specific topic.

- **Columns**:
  - Post ID
  - Post Title
  - Date Generated
  - Date Published
  - Actions (Edit, View)

## Interactions

- **Clicking "View Topics"**: Opens the topics modal for the selected author.
- **Clicking "Approve"**: Marks a topic as approved and ready for post generation.
- **Clicking "Reject"**: Marks a topic as rejected.
- **Clicking "Generate Post Now"**: Immediately generates a post from the selected topic.
- **Clicking Post Count Badge**: Opens the topic posts modal to view generated posts.

## Styling

The UI uses standard WordPress admin styles and custom CSS (`assets/css/authors.css`) for a consistent look and feel.
