#!/bin/bash
# Search and replace in tab-generated-posts.php
sed -i "s/remove_query_arg(array('author_id', 'template_id', 'campaign_id', 'post_type'))/remove_query_arg(array('author_id', 'template_id', 'campaign_id', 'post_type', 'generated_paged'))/g" ai-post-scheduler/templates/admin/tab-generated-posts.php
sed -i "s/remove_query_arg('s')/remove_query_arg(array('s', 'generated_paged'))/g" ai-post-scheduler/templates/admin/tab-generated-posts.php

# Search and replace in tab-partial-generations.php
sed -i "s/remove_query_arg(array('author_id', 'template_id', 'post_type'))/remove_query_arg(array('author_id', 'template_id', 'post_type', 'partial_paged'))/g" ai-post-scheduler/templates/admin/tab-partial-generations.php
sed -i "s/remove_query_arg('s')/remove_query_arg(array('s', 'partial_paged'))/g" ai-post-scheduler/templates/admin/tab-partial-generations.php

# Search and replace in tab-pending-review.php
sed -i "s/remove_query_arg(array('template_id', 'post_type'))/remove_query_arg(array('template_id', 'post_type', 'review_paged'))/g" ai-post-scheduler/templates/admin/tab-pending-review.php
sed -i "s/remove_query_arg('s')/remove_query_arg(array('s', 'review_paged'))/g" ai-post-scheduler/templates/admin/tab-pending-review.php
