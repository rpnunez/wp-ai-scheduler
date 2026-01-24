# Email Notification Settings - UI Mockup

## Settings Page Location
Navigate to: **AI Post Scheduler → Settings**

## New Settings Section

The notification settings are added at the bottom of the "General Settings" section:

```
┌────────────────────────────────────────────────────────────────────────────┐
│ General Settings                                                            │
│ ────────────────────────────────────────────────────────────────────────── │
│                                                                             │
│ Configure default settings for AI-generated posts.                         │
│                                                                             │
│ Default Post Status         [Draft ▼]                                      │
│                            Default status for newly generated posts.        │
│                                                                             │
│ Default Category           [Uncategorized ▼]                               │
│                            Default category for newly generated posts.      │
│                                                                             │
│ AI Model                   [                                    ]          │
│                            AI model to use for content generation.          │
│                                                                             │
│ Unsplash Access Key        [                                    ]          │
│                            API key for Unsplash image integration.          │
│                                                                             │
│ Max Retries on Failure     [3]                                             │
│                            Number of retry attempts if generation fails.    │
│                                                                             │
│ Enable Logging             [✓] Enable detailed logging for debugging       │
│                                                                             │
│ Developer Mode             [ ] Enable developer tools and features         │
│                                                                             │
│ ═══════════════════════════════════════════════════════════════════════════ │
│                          NEW SETTINGS BELOW                                 │
│ ═══════════════════════════════════════════════════════════════════════════ │
│                                                                             │
│ Send Email Notifications   [✓] Send daily email notifications when posts   │
│ for Posts Awaiting Review      are awaiting review                         │
│                            A daily email will be sent with a list of draft  │
│                            posts pending review.                            │
│                                                                             │
│ Notifications Email        [admin@example.com                ]             │
│ Address                    Email address to receive notifications about     │
│                            posts awaiting review.                           │
│                                                                             │
│                                                [Save Changes]               │
└────────────────────────────────────────────────────────────────────────────┘
```

## Email Notification Preview

When enabled, users receive an email that looks like this:

```
┌────────────────────────────────────────────────────────────────────────────┐
│                         Posts Awaiting Review                               │
│                     (Blue header with white text)                           │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│ Hello,                                                                      │
│                                                                             │
│ You have AI-generated posts waiting for review before publication.         │
│                                                                             │
│ ┌─────────────────────────────────────────────────────────────────────────┐
│ │  5 Posts Awaiting Review                                                │
│ │  (Blue highlight box)                                                   │
│ └─────────────────────────────────────────────────────────────────────────┘
│                                                                             │
│ Recent Draft Posts:                                                         │
│                                                                             │
│ ┌─────────────────────────────────────────────────────────────────────────┐
│ │ How to Start a Blog in 2024                                             │
│ │ Template: Blog Posts | Created: January 23, 2026                        │
│ └─────────────────────────────────────────────────────────────────────────┘
│                                                                             │
│ ┌─────────────────────────────────────────────────────────────────────────┐
│ │ Top 10 WordPress Tips for Beginners                                     │
│ │ Template: Blog Posts | Created: January 22, 2026                        │
│ └─────────────────────────────────────────────────────────────────────────┘
│                                                                             │
│ ┌─────────────────────────────────────────────────────────────────────────┐
│ │ SEO Best Practices for 2026                                             │
│ │ Template: SEO Articles | Created: January 21, 2026                      │
│ └─────────────────────────────────────────────────────────────────────────┘
│                                                                             │
│ ┌─────────────────────────────────────────────────────────────────────────┐
│ │ AI in Content Creation: A Complete Guide                                │
│ │ Template: Tech News | Created: January 20, 2026                         │
│ └─────────────────────────────────────────────────────────────────────────┘
│                                                                             │
│ ┌─────────────────────────────────────────────────────────────────────────┐
│ │ WordPress Performance Optimization Tips                                 │
│ │ Template: Blog Posts | Created: January 19, 2026                        │
│ └─────────────────────────────────────────────────────────────────────────┘
│                                                                             │
│                          [Review Posts]                                     │
│                      (Blue button, centered)                                │
│                                                                             │
│ Click the button above to review and publish your posts.                   │
│                                                                             │
├────────────────────────────────────────────────────────────────────────────┤
│ This email was sent by AI Post Scheduler on YourSite.com                  │
│ To disable these notifications, visit the plugin settings page.            │
└────────────────────────────────────────────────────────────────────────────┘
```

## Email Features

### Subject Line
```
[YourSite.com] 5 Posts Awaiting Review
```

### When Email is Sent
- **Frequency**: Once per day
- **Time**: Once per day at approximately the same time the schedule was activated or last updated (based on WordPress cron)
- **Condition**: Only when draft posts exist AND notifications are enabled
- **Maximum posts shown**: 10 (with indication of more if applicable)

### Email Actions
- Clicking **"Review Posts"** button takes user directly to the Post Review page
- Clean, professional HTML email template
- Mobile-responsive design

## Configuration Steps

1. Navigate to **AI Post Scheduler → Settings**
2. Scroll to the bottom of General Settings
3. Check **"Send Email Notifications for Posts Awaiting Review"**
4. Enter or verify the **"Notifications Email Address"**
5. Click **"Save Changes"**

The first notification will be sent the next day at 9:00 AM (if draft posts exist).

## Notification Logic

```
┌─────────────────────────────────────────────────┐
│ Daily at 9:00 AM (WP Cron)                      │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
         ┌────────────────────┐
         │ Are notifications  │───No───► Exit (no email sent)
         │ enabled?           │
         └────────┬───────────┘
                  │ Yes
                  ▼
         ┌────────────────────┐
         │ Are there draft    │───No───► Exit (no email sent)
         │ posts?             │
         └────────┬───────────┘
                  │ Yes
                  ▼
         ┌────────────────────┐
         │ Is email address   │───No───► Exit (no email sent)
         │ valid?             │
         └────────┬───────────┘
                  │ Yes
                  ▼
         ┌────────────────────┐
         │ Get up to 10 draft │
         │ posts              │
         └────────┬───────────┘
                  │
                  ▼
         ┌────────────────────┐
         │ Build HTML email   │
         │ with post list     │
         └────────┬───────────┘
                  │
                  ▼
         ┌────────────────────┐
         │ Send email via     │
         │ wp_mail()          │
         └────────┬───────────┘
                  │
                  ▼
         ┌────────────────────┐
         │ Log to Activity    │
         │ table              │
         └────────────────────┘
```

## Activity Log Entry

When an email is sent, an entry is added to the Activity log:

```
Event Type: review_notification_sent
Status: success
Message: Review notification email sent to admin@example.com (5 posts)
```

## Disabling Notifications

To stop receiving emails:
1. Go to **AI Post Scheduler → Settings**
2. Uncheck **"Send Email Notifications for Posts Awaiting Review"**
3. Click **"Save Changes"**

The cron job will continue to run but no emails will be sent when disabled.

## Testing the Feature

### Manual Test
1. Enable notifications in settings
2. Generate some posts with "Draft" status
3. Trigger the cron manually via WP-CLI:
   ```bash
   wp cron event run aips_send_review_notifications
   ```
4. Check your inbox for the notification email

### Verifying Cron Schedule
```bash
wp cron event list --search=aips_send_review_notifications
```

Should show:
```
hook                           next_run_gmt         next_run_relative  recurrence
aips_send_review_notifications 2026-01-24 14:00:00  in 17 hours        daily
```
