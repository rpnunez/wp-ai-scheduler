1. Extract the diagnostics methods (`check_*`, `count_*`, `get_cron_hook_timestamps`, `scan_file_for_errors`) from `ai-post-scheduler/includes/class-aips-system-status.php` and write them into a new file `ai-post-scheduler/includes/class-aips-system-diagnostics-service.php` under the `AIPS_System_Diagnostics_Service` class using `run_in_bash_session` with `cat`, `grep`, and `sed`.
2. Use `replace_with_git_merge_diff` to modify `ai-post-scheduler/includes/class-aips-system-status.php`, removing the extracted methods and modifying `get_system_info()` to instantiate `AIPS_System_Diagnostics_Service` and return the result of `$service->get_system_info()`.
3. Use `run_in_bash_session` with `ls ai-post-scheduler/includes/class-aips-system-diagnostics-service.php` to confirm the new file exists.
4. Use `run_in_bash_session` with `find ai-post-scheduler/includes -type f -name "*.php" -exec php -l {} \; | grep -v "No syntax errors detected"` to ensure no PHP syntax errors exist.
5. Use `replace_with_git_merge_diff` to add `'class-aips-system-diagnostics-service.php',` to the `$files = [` array in `ai-post-scheduler/tests/bootstrap.php`.
6. Use `replace_with_git_merge_diff` to add `'AIPS_System_Diagnostics_Service',` to the `$services` array in the `test_autoloader_loads_service_classes()` method of `ai-post-scheduler/tests/test-autoloader.php`.
7. Use `run_in_bash_session` with `cat << 'EOF' > ai-post-scheduler/tests/test-aips-system-diagnostics-service.php` to create a new PHPUnit test file that verifies the basic instantiation and output of `AIPS_System_Diagnostics_Service`.
8. Use `run_in_bash_session` with `cd ai-post-scheduler && composer test` to run the test suite and ensure no regressions occurred.
9. Use `run_in_bash_session` with `echo "## ... " >> .build/atlas-journal.md` to append the details of this specific extraction.
10. Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.
