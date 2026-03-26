1. **Move `log` method to `AIPS_Logger`**:
   - Instead of `AIPS_Generator::log`, move the enhanced logic into `AIPS_Logger::log` or create a new method in `AIPS_Logger`.
   - Actually, `AIPS_Logger` currently takes `($message, $level = 'info', $context = array())`.
   - If we update `AIPS_Logger::log` to accept `$ai_data` and `$history_container`, we can do everything there.
   - Wait, `AIPS_Logger` is a general text file logger. Does it make sense for it to know about `AIPS_History_Container`?
   - The user asked: "Move this new log method into the AIPS_Logger class, and instantiate it where necessary passing the $this->current_history object into it."
   - Okay, let's look at `AIPS_Logger` and see how to integrate this.

2. **Update `AIPS_Logger`**:
   - Add a property `private $history_container = null;` to `AIPS_Logger`.
   - Add a method `public function set_history_container($history_container) { $this->history_container = $history_container; }`
   - Update `AIPS_Logger::log($message, $level = 'info', $context = array(), $ai_data = array())`.
   - Wait, if `AIPS_Logger` handles the history recording, then `AIPS_Logger::log` will do the file write AND the history record.
   - Move the logic from `AIPS_Generator::log` into `AIPS_Logger::log`.

3. **Update `AIPS_Generator`**:
   - In `AIPS_Generator`, remove the private `log` method.
   - When `$this->current_history` is created or set in `AIPS_Generator`, pass it to `$this->logger->set_history_container($this->current_history)`.
   - Wait, `generate_post_from_context` creates `$this->current_history = $this->history_service->create(...);`
   - Immediately after, call `$this->logger->set_history_container($this->current_history);`.
   - Change all `$this->log(...)` calls in `AIPS_Generator` back to `$this->logger->log(...)`.

4. **Review other usages of `AIPS_Logger::log`**:
   - Does changing the signature of `AIPS_Logger::log` break other things?
   - Original: `public function log($message, $level = 'info', $context = array())`
   - New: `public function log($message, $level = 'info', $context = array(), $ai_data = array())`
   - This signature is backwards compatible since `$ai_data` is optional.
   - What about `AIPS_Logger::error` and `AIPS_Logger::warning`?
     `public function warning($message, $context = array(), $ai_data = array()) { $this->log($message, 'warning', $context, $ai_data); }`

5. **Let's review the code.**
