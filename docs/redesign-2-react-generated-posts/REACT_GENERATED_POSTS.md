# React-based Generated Posts Page

This is a proof-of-concept implementation of a React-powered "Generated Posts" admin page that demonstrates the hybrid PHP-React approach for WordPress plugin development.

## Overview

The React-based Generated Posts page runs in parallel with the existing PHP-based Generated Posts page, demonstrating modern React + WordPress integration patterns without disrupting existing functionality.

## Architecture

### File Structure

```
ai-post-scheduler/
├── src/
│   └── generated-posts/
│       ├── index.js                          # Entry point
│       ├── components/
│       │   ├── GeneratedPostsApp.js          # Main app component
│       │   ├── PostFilters.js                # Status, search, template filters
│       │   ├── PostsList.js                  # Posts table with pagination
│       │   └── SessionModal.js               # View Session modal
│       └── style.scss                        # Component styles
├── includes/
│   └── api/
│       └── class-aips-generated-posts-api.php  # REST API endpoints
├── admin/
│   └── class-aips-generated-posts-react.php    # Admin page controller
├── build/                                     # Compiled assets (generated)
│   ├── generated-posts.js
│   ├── generated-posts.asset.php
│   └── style-generated-posts.css
├── package.json                               # npm dependencies
└── webpack.config.js                          # Build configuration
```

## Features

### Frontend (React)
- **Post List Display**: Shows generated posts with title, template, author, status, and date
- **Filters**: 
  - Status filter (All, Draft, Pending Review, Published)
  - Search by title
  - Filter by template
- **Pagination**: Navigate through multiple pages of results
- **View Session Modal**: Display generation session details, AI calls, and logs
- **Actions**: Edit, View, and Delete posts

### Backend (REST API)
- `GET /wp-json/aips/v1/generated-posts` - List posts with filters and pagination
- `GET /wp-json/aips/v1/generation-session/{id}` - Get session details
- `DELETE /wp-json/aips/v1/generated-posts/{id}` - Delete a post
- `GET /wp-json/aips/v1/templates` - Get templates for filter dropdown

All endpoints require `manage_options` capability and use WordPress REST API authentication.

## Development

### Prerequisites
- Node.js 14+ and npm
- PHP 8.2+
- WordPress 5.8+
- Meow Apps AI Engine plugin

### Setup

1. **Install npm dependencies:**
   ```bash
   cd ai-post-scheduler
   npm install
   ```

2. **Build for production:**
   ```bash
   npm run build
   ```

3. **Development mode (watch for changes):**
   ```bash
   npm start
   ```

### Build Scripts

- `npm run build` - Build production bundle (minified)
- `npm start` - Development mode with live reload
- `npm run lint:js` - Lint JavaScript files
- `npm run format` - Format code with Prettier

## Usage

1. Navigate to **AI Post Scheduler → Generated Posts (React)** in the WordPress admin menu
2. The page will display all generated posts from the database
3. Use filters to narrow down results by status, search term, or template
4. Click "View Session" to see AI generation details for any post
5. Use Edit, View, or Delete actions on each post

## Technical Implementation

### WordPress Integration

The React app integrates with WordPress using:

1. **REST API**: Custom endpoints under `/wp-json/aips/v1/`
2. **WordPress Components**: Uses `@wordpress/components` for UI consistency
3. **API Fetch**: Uses `@wordpress/api-fetch` with automatic nonce handling
4. **i18n**: Uses `@wordpress/i18n` for translations

### Security

- REST API endpoints require `manage_options` capability
- Nonce verification on all requests
- Permission checks on delete operations
- Input sanitization and output escaping

### Data Flow

1. React app mounts in `#aips-generated-posts-root` div
2. `wp_localize_script()` passes REST URL and nonce to JavaScript
3. React components fetch data from REST API endpoints
4. API controllers use existing repository classes for database access
5. Modal displays session data from history repository

## Comparison with PHP Version

| Feature | PHP Version | React Version |
|---------|-------------|---------------|
| Post List | ✅ | ✅ |
| Search | ✅ | ✅ |
| Status Filter | ✅ | ✅ |
| Template Filter | ✅ | ✅ |
| View Session | ✅ | ✅ |
| Pagination | ✅ | ✅ |
| Delete Post | ❌ | ✅ |
| UI Updates | Page reload | Live updates |
| Technology | PHP/jQuery | React/REST API |

## Future Enhancements

Potential improvements for production use:

1. **Bulk Actions**: Select multiple posts for batch operations
2. **Inline Editing**: Quick edit post details without leaving the page
3. **Advanced Filters**: Date range, author filter, custom fields
4. **Export**: Download post list as CSV
5. **Real-time Updates**: WebSocket or polling for live updates
6. **Optimistic UI**: Show changes before API confirms
7. **Error Retry**: Automatic retry on failed API calls
8. **Infinite Scroll**: Load more posts as you scroll

## Testing

The implementation includes:
- REST API endpoint validation
- Permission checks
- Error handling for network failures
- Loading states for better UX

To test the API endpoints directly:

```bash
# List posts
curl -X GET "https://your-site.com/wp-json/aips/v1/generated-posts" \
  -H "X-WP-Nonce: YOUR_NONCE"

# Get session details
curl -X GET "https://your-site.com/wp-json/aips/v1/generation-session/123" \
  -H "X-WP-Nonce: YOUR_NONCE"

# Delete post
curl -X DELETE "https://your-site.com/wp-json/aips/v1/generated-posts/456" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

## Troubleshooting

### "React app not built" error
Run `npm install && npm run build` in the plugin directory.

### 404 on REST API endpoints
Check that the plugin is activated and REST API is enabled in WordPress.

### Console errors
Check browser developer console for specific errors. Common issues:
- CORS errors (check REST API permissions)
- Nonce expiration (refresh the page)
- Network connectivity

### Empty post list
- Verify posts exist in database
- Check REST API returns data
- Verify filters aren't too restrictive

## Notes

- This is a proof-of-concept implementation
- The existing PHP-based Generated Posts page remains fully functional
- No data migration needed - both pages use the same database tables
- Can be extended or replaced based on testing feedback

## License

GPL v2 or later
