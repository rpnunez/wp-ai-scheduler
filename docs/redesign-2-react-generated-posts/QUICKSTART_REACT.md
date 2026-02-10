# Quick Start Guide - React Generated Posts

## For Developers

### Prerequisites
```bash
# Check versions
node -v   # Should be 14+
npm -v    # Should be 6+
php -v    # Should be 8.2+
```

### Setup & Build

1. **Navigate to plugin directory:**
   ```bash
   cd wp-content/plugins/ai-post-scheduler
   ```

2. **Install dependencies:**
   ```bash
   npm install
   ```

3. **Build for production:**
   ```bash
   npm run build
   ```

4. **Development mode (optional):**
   ```bash
   npm start  # Watches for changes and rebuilds
   ```

### Quick Verification

```bash
# Verify build files exist
ls -lh build/
# Should show:
# - generated-posts.js (13KB)
# - generated-posts.asset.php
# - style-generated-posts.css (4KB)
# - style-generated-posts-rtl.css (4KB)

# Check PHP syntax
php -l includes/api/class-aips-generated-posts-api.php
php -l admin/class-aips-generated-posts-react.php

# Check JavaScript syntax
node -c build/generated-posts.js
```

## For Testers

### Access the Page

1. Log into WordPress admin
2. Go to: **AI Post Scheduler → Generated Posts (React)**
3. You should see a list of generated posts

### Test Checklist

#### Basic Functionality
- [ ] Page loads without errors (check browser console F12)
- [ ] Posts display in a table
- [ ] Post count shows at the top
- [ ] All columns visible: Title, Template, Author, Status, Date, Actions

#### Filters
- [ ] Click "All" tab - shows all posts
- [ ] Click "Draft" tab - shows only drafts
- [ ] Click "Published" tab - shows only published
- [ ] Type in search box and press "Search" - filters by title
- [ ] Select template from dropdown - filters by template
- [ ] Combine filters (e.g., Draft + Search) - works correctly

#### Pagination
- [ ] If >20 posts, pagination appears at bottom
- [ ] Click "Next" - loads next page
- [ ] Click "Previous" - goes back
- [ ] Click page number - jumps to that page
- [ ] Ellipsis appears for many pages
- [ ] Page scrolls to top on navigation

#### View Session Modal
- [ ] Click "View Session" button
- [ ] Modal opens with session details
- [ ] "AI Calls" tab shows prompts and responses
- [ ] "Logs" tab shows generation logs
- [ ] Logs can be expanded for more details
- [ ] Close button works (X in corner)
- [ ] Click outside modal - closes it

#### Actions
- [ ] Click "Edit" - opens WordPress post editor in new tab
- [ ] Click "View" (on published post) - opens post on site
- [ ] Click "Delete" - confirmation modal appears
- [ ] Click "Cancel" in delete modal - cancels operation
- [ ] Click "Delete" in modal - post is deleted
- [ ] Success message appears after delete
- [ ] Post removed from list immediately
- [ ] Total count decreases by 1

#### Error Handling
- [ ] Disconnect internet briefly - error message shows
- [ ] Reconnect - can refresh data
- [ ] Try to access API directly without auth - gets 401

#### Original Page Unchanged
- [ ] Go to "Generated Posts" (PHP version)
- [ ] Verify it still works correctly
- [ ] All features work as before
- [ ] No errors in console

### Performance Testing

If you have many posts (100+):

- [ ] Page loads in <3 seconds
- [ ] Filters respond quickly (<1 second)
- [ ] Pagination is smooth
- [ ] No UI lag when scrolling
- [ ] No memory leaks (check browser task manager)

### Browser Testing

Test in multiple browsers:

- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if on Mac)

## Troubleshooting

### "React app not built" Error

**Problem:** Error message on page load about missing build files.

**Solution:**
```bash
cd wp-content/plugins/ai-post-scheduler
npm install
npm run build
```

### Empty Post List

**Problem:** Page loads but shows "No posts found" even though posts exist.

**Possible Causes:**
1. REST API not working
2. Filters too restrictive
3. Permission issues

**Debug Steps:**
```bash
# Check if REST API works
curl https://your-site.local/wp-json/aips/v1/generated-posts

# Check browser console (F12) for errors
# Look for 404, 401, or 500 errors

# Try "All" filter with empty search
# Clear template filter
```

### Console Errors

**Problem:** JavaScript errors in browser console.

**Common Errors & Fixes:**

1. **"Nonce expired"**
   - Refresh the page to get new nonce

2. **"404 on REST API"**
   - Go to Settings → Permalinks
   - Click "Save Changes" to flush rewrite rules
   - Try again

3. **"Permission denied"**
   - Verify user has "manage_options" capability
   - Check if you're logged in as admin

4. **"Network error"**
   - Check internet connection
   - Check if WordPress site is accessible
   - Look for .htaccess issues

### API Testing

Test REST API endpoints directly:

```bash
# Get your nonce from browser console:
# Open page, press F12, type: console.log(window.aipsGeneratedPostsReact.nonce)

# List posts
curl -X GET "https://your-site.local/wp-json/aips/v1/generated-posts?page=1&per_page=20" \
  -H "X-WP-Nonce: YOUR_NONCE_HERE" \
  -H "Cookie: YOUR_COOKIE_STRING"

# Response should be JSON with posts array

# Get templates
curl -X GET "https://your-site.local/wp-json/aips/v1/templates" \
  -H "X-WP-Nonce: YOUR_NONCE_HERE" \
  -H "Cookie: YOUR_COOKIE_STRING"

# Get session (replace 123 with actual history_id)
curl -X GET "https://your-site.local/wp-json/aips/v1/generation-session/123" \
  -H "X-WP-Nonce: YOUR_NONCE_HERE" \
  -H "Cookie: YOUR_COOKIE_STRING"
```

## Deployment Checklist

Before deploying to production:

### Build
- [ ] Run `npm install` in plugin directory
- [ ] Run `npm run build` successfully
- [ ] Verify build/ directory contains 4 files
- [ ] Verify file sizes reasonable (~13KB JS, ~4KB CSS)

### Testing
- [ ] Test on staging environment first
- [ ] Run through full test checklist above
- [ ] Test with production data (not just sample data)
- [ ] Test with slow network (Chrome DevTools throttling)
- [ ] Test with large dataset (1000+ posts)

### Security
- [ ] Verify REST API requires authentication
- [ ] Test with non-admin user (should get permission denied)
- [ ] Check for XSS vulnerabilities
- [ ] Verify CSRF protection (nonces)
- [ ] Check input sanitization

### Performance
- [ ] Check page load time (<3 seconds)
- [ ] Monitor API response times (<1 second)
- [ ] Check bundle size (should be ~13KB)
- [ ] Verify no memory leaks (run for 5+ minutes)
- [ ] Test pagination with 100+ pages

### Compatibility
- [ ] Test on WordPress 5.8+
- [ ] Test with latest WordPress version
- [ ] Test with common plugins (Yoast, etc.)
- [ ] Test on PHP 8.2+
- [ ] Test in Chrome, Firefox, Safari

### Documentation
- [ ] Update main README with build instructions
- [ ] Document any environment-specific settings
- [ ] Add screenshots to documentation
- [ ] Update changelog

### Rollback Plan
- [ ] Keep old version available
- [ ] Document rollback steps
- [ ] Have database backup ready
- [ ] Test rollback procedure

## Common Issues & Solutions

### Issue: Node.js not installed

**Error:** `npm: command not found`

**Solution:**
```bash
# Ubuntu/Debian
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# macOS (with Homebrew)
brew install node

# Windows
# Download from: https://nodejs.org/
```

### Issue: Build fails with memory error

**Error:** `JavaScript heap out of memory`

**Solution:**
```bash
# Increase Node.js memory limit
export NODE_OPTIONS="--max-old-space-size=4096"
npm run build
```

### Issue: Permissions error on npm install

**Error:** `EACCES: permission denied`

**Solution:**
```bash
# Don't use sudo with npm
# Fix npm permissions:
mkdir ~/.npm-global
npm config set prefix '~/.npm-global'
echo 'export PATH=~/.npm-global/bin:$PATH' >> ~/.bashrc
source ~/.bashrc
```

### Issue: Old build cached

**Problem:** Changes not showing up after rebuild

**Solution:**
```bash
# Clear WordPress cache
# Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)

# Hard rebuild
rm -rf build/
rm -rf node_modules/
npm install
npm run build
```

## Success Indicators

You know it's working correctly when:

✅ Page loads with no console errors
✅ Posts display in table format
✅ Filters change the visible posts
✅ View Session modal shows generation details
✅ Delete confirmation modal appears
✅ Posts can be deleted successfully
✅ Original PHP page still works
✅ REST API endpoints return data
✅ All actions (Edit, View, Delete) work
✅ Pagination navigates correctly

## Getting Help

If you encounter issues:

1. **Check Documentation:**
   - REACT_GENERATED_POSTS.md - Full documentation
   - IMPLEMENTATION_SUMMARY.md - Technical details

2. **Check Browser Console:**
   - Press F12 in browser
   - Look for red errors
   - Note the error message

3. **Check Server Logs:**
   - WordPress debug.log
   - PHP error log
   - Web server error log

4. **Test REST API:**
   - Use curl commands above
   - Check if endpoints return data
   - Verify authentication works

5. **Create Issue:**
   - Include error messages
   - Include browser/OS version
   - Include WordPress version
   - Include steps to reproduce

## Next Steps

After successful testing:

1. **Gather Feedback:** Get user feedback on the new interface
2. **Monitor Performance:** Track page load times and API response times
3. **Plan Migration:** Decide if/when to replace PHP version
4. **Add Features:** Consider bulk actions, advanced filters, etc.
5. **Update Docs:** Add screenshots and video tutorials

---

**Version:** 1.0  
**Last Updated:** February 10, 2026  
**Compatibility:** WordPress 5.8+, PHP 8.2+, Node 14+
