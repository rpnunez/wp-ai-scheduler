#!/bin/bash
echo "--- tab-generated-posts.php ---"
grep -in "generated_paged" ai-post-scheduler/templates/admin/tab-generated-posts.php
echo "--- tab-pending-review.php ---"
grep -in "review_paged" ai-post-scheduler/templates/admin/tab-pending-review.php
