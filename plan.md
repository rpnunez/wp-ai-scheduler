1. **Fix `isset()` checks for `$wpdb->get_row()` returns in Repositories (Hunter Mode)**
   - Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/includes/class-aips-history-repository.php`:
     ```
     <<<<<<< SEARCH
        $stats = array(
            'total' => isset($results->total) ? (int) $results->total : 0,
            'completed' => isset($results->completed) ? (int) $results->completed : 0,
            'failed' => isset($results->failed) ? (int) $results->failed : 0,
            'processing' => isset($results->processing) ? (int) $results->processing : 0,
            'partial' => isset($results->partial) ? (int) $results->partial : 0,
        );
     =======
        $stats = array(
            'total' => ($results && isset($results->total)) ? (int) $results->total : 0,
            'completed' => ($results && isset($results->completed)) ? (int) $results->completed : 0,
            'failed' => ($results && isset($results->failed)) ? (int) $results->failed : 0,
            'processing' => ($results && isset($results->processing)) ? (int) $results->processing : 0,
            'partial' => ($results && isset($results->partial)) ? (int) $results->partial : 0,
        );
     >>>>>>> REPLACE
     ```
   - Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/includes/class-aips-template-repository.php`:
     ```
     <<<<<<< SEARCH
        return array(
            'total' => isset($results->total) ? (int) $results->total : 0,
            'active' => isset($results->active) ? (int) $results->active : 0,
        );
     =======
        return array(
            'total' => ($results && isset($results->total)) ? (int) $results->total : 0,
            'active' => ($results && isset($results->active)) ? (int) $results->active : 0,
        );
     >>>>>>> REPLACE
     ```
   - Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/includes/class-aips-schedule-repository.php`:
     ```
     <<<<<<< SEARCH
        return array(
            'total' => isset($results->total) ? (int) $results->total : 0,
            'active' => isset($results->active) ? (int) $results->active : 0,
        );
     =======
        return array(
            'total' => ($results && isset($results->total)) ? (int) $results->total : 0,
            'active' => ($results && isset($results->active)) ? (int) $results->active : 0,
        );
     >>>>>>> REPLACE
     ```
   - Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/includes/class-aips-prompt-section-repository.php`:
     ```
     <<<<<<< SEARCH
		return array(
			'total' => isset($results->total) ? (int) $results->total : 0,
			'active' => isset($results->active) ? (int) $results->active : 0,
		);
     =======
		return array(
			'total' => ($results && isset($results->total)) ? (int) $results->total : 0,
			'active' => ($results && isset($results->active)) ? (int) $results->active : 0,
		);
     >>>>>>> REPLACE
     ```
   - Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/includes/class-aips-article-structure-repository.php`:
     ```
     <<<<<<< SEARCH
		return array(
			'total' => isset($results->total) ? (int) $results->total : 0,
			'active' => isset($results->active) ? (int) $results->active : 0,
		);
     =======
		return array(
			'total' => ($results && isset($results->total)) ? (int) $results->total : 0,
			'active' => ($results && isset($results->active)) ? (int) $results->active : 0,
		);
     >>>>>>> REPLACE
     ```
   - Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/includes/class-aips-feedback-repository.php`:
     ```
     <<<<<<< SEARCH
		return array(
			'total' => isset($results->total) ? (int) $results->total : 0,
			'approved' => isset($results->approved) ? (int) $results->approved : 0,
			'rejected' => isset($results->rejected) ? (int) $results->rejected : 0
		);
     =======
		return array(
			'total' => ($results && isset($results->total)) ? (int) $results->total : 0,
			'approved' => ($results && isset($results->approved)) ? (int) $results->approved : 0,
			'rejected' => ($results && isset($results->rejected)) ? (int) $results->rejected : 0
		);
     >>>>>>> REPLACE
     ```

2. **Standardize `AIPS_Author_Topics_Scheduler` to implement `AIPS_Cron_Generation_Handler` (Atlas Mode)**
   - Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/includes/class-aips-author-topics-scheduler.php`:
     ```
     <<<<<<< SEARCH
class AIPS_Author_Topics_Scheduler {
     =======
class AIPS_Author_Topics_Scheduler implements AIPS_Cron_Generation_Handler {
     >>>>>>> REPLACE
     ```
   - Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/includes/class-aips-author-topics-scheduler.php`:
     ```
     <<<<<<< SEARCH
	public function process_topic_generation() {
		$this->logger->log('Starting scheduled topic generation', 'info');
     =======
	/**
	 * Process any pending generation work for this handler.
	 *
	 * Implements AIPS_Cron_Generation_Handler::process().
	 */
	public function process(): void {
		$this->process_topic_generation();
	}

	/**
	 * Process topic generation for all due authors.
	 *
	 * This is called by WordPress cron on the scheduled interval.
	 */
	public function process_topic_generation() {
		$this->logger->log('Starting scheduled topic generation', 'info');
     >>>>>>> REPLACE
     ```
   - Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/ai-post-scheduler.php`:
     ```
     <<<<<<< SEARCH
        // Lazy-resolve the author-topics scheduler only when its hook fires.
        add_action('aips_generate_author_topics', function() {
            AIPS_Author_Topics_Scheduler::instance()->process_topic_generation();
        });
     =======
        // Lazy-resolve the author-topics scheduler only when its hook fires.
        add_action('aips_generate_author_topics', function() {
            AIPS_Author_Topics_Scheduler::instance()->process();
        });
     >>>>>>> REPLACE
     ```
   - Verify the changes using `cat` and check syntax via `run_in_bash_session`: `find . -type f -name "*.php" -exec php -l {} \; | grep -v "No syntax errors detected"`.

3. **Testing and Journals**
   - Update `.jules/hunter.md` and `.build/atlas-journal.md`.

4. **Pre-commit Steps**
   - Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.

5. **Submit**
   - Submit the PR with branch name `atlas-hunter-improvements` and descriptive commit message.
