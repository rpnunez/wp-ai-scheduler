1. **Optimize `get_setting` in `AIPS_Site_Context`**
   - Use `run_in_bash_session` with a PHP patch script to update `ai-post-scheduler/includes/class-aips-site-context.php`.
   - The script will replace the linear search loop inside `get_setting()` with a statically cached inverted index mapping `key` -> `option_name` to achieve O(1) lookup.
   - Command:
```bash
cat << 'EOF' > patch.php
<?php
$file = 'ai-post-scheduler/includes/class-aips-site-context.php';
$content = file_get_contents($file);
$content = str_replace("\r\n", "\n", $content);

$search = <<<'CODE'
	public static function get_setting($key, $default = null) {
		$options = AIPS_Settings::get_content_strategy_options();
		$config  = AIPS_Config::get_instance();

		foreach ($options as $option_name => $meta) {
			if ($meta['key'] === $key) {
				return $config->get_option($option_name, $default);
			}
		}

		return $default !== null ? $default : '';
	}
CODE;

$replace = <<<'CODE'
	public static function get_setting($key, $default = null) {
		static $key_map = null;

		if ($key_map === null) {
			$options = AIPS_Settings::get_content_strategy_options();
			$key_map = array();
			foreach ($options as $option_name => $meta) {
				if (isset($meta['key'])) {
					$key_map[$meta['key']] = $option_name;
				}
			}
		}

		if (isset($key_map[$key])) {
			return AIPS_Config::get_instance()->get_option($key_map[$key], $default);
		}

		return $default !== null ? $default : '';
	}
CODE;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
unlink(__FILE__);
