1. **Apply inline Quick Schedule form to `templates.php`**
   - Use `run_in_bash_session` to execute a PHP script that patches `ai-post-scheduler/templates/admin/templates.php`.
   - The patch will insert an inline form (`#aips-quick-schedule-inline-form`) with a Cadence dropdown (Daily/Weekly/Monthly) and Cancel/Submit buttons right below the "Done" button in the post-save modal.
2. **Verify `templates.php` patch**
   - Use `run_in_bash_session` to check if `ai-post-scheduler/templates/admin/templates.php` contains the inserted form.
3. **Patch `admin.js` to handle the inline Quick Schedule form**
   - Use `run_in_bash_session` to execute a PHP script that patches `quickSchedule` in `ai-post-scheduler/assets/js/admin.js`.
   - The patch will change `quickSchedule` so that instead of redirecting the page to the schedule page, it toggles the display of `#aips-quick-schedule-inline-form`.
   - It will also bind an AJAX submission handler to `#aips-quick-schedule-submit-btn` that triggers the `aips_save_schedule` endpoint via `admin-ajax.php`, displays a success toast upon success, hides the inline form, and updates the Quick Schedule button to reflect the new state.
4. **Verify `admin.js` patch**
   - Use `run_in_bash_session` to check if `ai-post-scheduler/assets/js/admin.js` is patched correctly without syntax errors (`jshint ai-post-scheduler/assets/js/admin.js`).
5. **Run test suite**
   - Use `run_in_bash_session` to run the project's test suite (`cd ai-post-scheduler && WP_TESTS_DIR=/tmp/wordpress-tests-lib WP_CORE_DIR=/tmp/wordpress php vendor/bin/phpunit --configuration phpunit.xml`).
6. **Add Journal Entry**
   - Use `run_in_bash_session` to append a single-line session summary to `.build/nunezscheduler-agent-journal.md`. Format: `## YYYY-MM-DD - [Feature Name] Optimization \nTarget Feature: ... \nImprovement: ... \nFiles Modified: ... \nOutcome: ...`.
7. **Clean up temporary scripts**
   - Use `run_in_bash_session` to run `rm` followed by the names of the temporary scripts used for patching.
8. **Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.**
   - Run `pre_commit_instructions` and follow the provided instructions to ensure everything is ready for submission.
9. **Create pull request**
   - Use the `submit` tool to create a pull request.
   - PR Title: `NunezScheduler: Optimized Post Generator Flow`
   - PR Body should include What, Why, Before, After, and Testing notes.
