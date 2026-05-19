<?php
/**
 * Architectural test for container instantiation policy.
 *
 * @package AI_Post_Scheduler
 */

class Test_Container_Instantiation_Policy extends WP_UnitTestCase {

	/**
	 * Ensure core cross-cutting services are not directly instantiated in includes/.
	 */
	public function test_no_direct_core_service_instantiation_in_includes() {
		$root = dirname(__DIR__);
		$includes_dir = $root . '/includes';
		$whitelist_file = $root . '/config/container-instantiation-whitelist.txt';

		$patterns = array(
			'AIPS_Logger' => '/new\s+AIPS_Logger\s*\(/',
			'AIPS_History_Service' => '/new\s+AIPS_History_Service\s*\(/',
			'AIPS_AI_Service' => '/new\s+AIPS_AI_Service\s*\(/',
			'AIPS_Resilience_Service' => '/new\s+AIPS_Resilience_Service\s*\(/',
		);

		$whitelist = array();
		if (file_exists($whitelist_file)) {
			$lines = file($whitelist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (is_array($lines)) {
				foreach ($lines as $line) {
					$line = trim($line);
					if ('' === $line || '#' === substr($line, 0, 1)) {
						continue;
					}
					$whitelist[str_replace('\\', '/', $line)] = true;
				}
			}
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($includes_dir, FilesystemIterator::SKIP_DOTS)
		);

		$violations = array();

		foreach ($iterator as $file_info) {
			if (!$file_info->isFile()) {
				continue;
			}

			$path = $file_info->getPathname();
			if ('php' !== strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
				continue;
			}

			$relative = str_replace('\\', '/', ltrim(str_replace($root, '', $path), '/'));
			if (isset($whitelist[$relative])) {
				continue;
			}

			$content = file_get_contents($path);
			if (false === $content) {
				continue;
			}

			foreach ($patterns as $class => $regex) {
				if (!preg_match_all($regex, $content, $matches, PREG_OFFSET_CAPTURE)) {
					continue;
				}

				foreach ($matches[0] as $match) {
					$offset = $match[1];
					$line_no = substr_count(substr($content, 0, $offset), "\n") + 1;
					$violations[] = sprintf('%s:%d (%s)', $relative, $line_no, $class);
				}
			}
		}

		$this->assertSame(
			array(),
			$violations,
			"Direct instantiation policy violations found:\n" . implode("\n", $violations)
		);
	}
}
