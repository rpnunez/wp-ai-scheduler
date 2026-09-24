<?php
/**
 * Contract tests for AIPS_Prompt_Builder_Section / AIPS_Prompt_Builder_Section_Base.
 *
 * Verifies that:
 *   1. Every prompt-builder section class carries the shared marker interface
 *      and extends the shared base.
 *   2. Every section still honours the informal `build()` convention. The
 *      interface cannot declare `build()` -- the nine implementors have
 *      mutually incompatible required parameters -- so the convention is
 *      enforced here by reflection instead.
 *   3. The base resolves collaborators lazily, so sections that never use the
 *      diversity injector do not pay to construct it (and its two repositories).
 *   4. Injected collaborators are handed straight back, unchanged.
 *   5. AIPS_Prompt_Builder_Diversity_Injector stays a collaborator, not a section.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.5
 */

class Test_Prompt_Builder_Section_Contract extends WP_UnitTestCase {

	/**
	 * Every prompt-builder section class.
	 *
	 * @var string[]
	 */
	private static $section_classes = array(
		'AIPS_Prompt_Builder_Article_Structure_Section',
		'AIPS_Prompt_Builder_Authors',
		'AIPS_Prompt_Builder_Post_Content',
		'AIPS_Prompt_Builder_Post_Excerpt',
		'AIPS_Prompt_Builder_Post_Featured_Image',
		'AIPS_Prompt_Builder_Post_Metadata',
		'AIPS_Prompt_Builder_Post_Title',
		'AIPS_Prompt_Builder_Taxonomy',
		'AIPS_Prompt_Builder_Topic',
	);

	/**
	 * Data provider yielding one section class per case.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function section_class_provider() {
		$cases = array();

		foreach (self::$section_classes as $class_name) {
			$cases[$class_name] = array($class_name);
		}

		return $cases;
	}

	/**
	 * Guards against a section class being added to includes/ without being
	 * registered here, which would let it skip every other assertion below.
	 */
	public function test_provider_covers_every_section_subclass() {
		$found = array();

		foreach (glob(dirname(__DIR__) . '/includes/class-aips-prompt-builder-*.php') as $file) {
			$source = file_get_contents($file);

			if (preg_match('/^class\s+(\w+)\s+extends\s+AIPS_Prompt_Builder_Section_Base\b/m', $source, $matches)) {
				$found[] = $matches[1];
			}
		}

		sort($found);
		$expected = self::$section_classes;
		sort($expected);

		$this->assertSame(
			$expected,
			$found,
			'Every AIPS_Prompt_Builder_Section_Base subclass must be listed in Test_Prompt_Builder_Section_Contract::$section_classes.'
		);
	}

	/**
	 * @dataProvider section_class_provider
	 *
	 * @param string $class_name Section class under test.
	 */
	public function test_section_implements_interface_and_extends_base($class_name) {
		$this->assertTrue(class_exists($class_name), "Class {$class_name} should exist.");

		$this->assertArrayHasKey(
			'AIPS_Prompt_Builder_Section',
			class_implements($class_name),
			"{$class_name} should implement AIPS_Prompt_Builder_Section."
		);

		$this->assertArrayHasKey(
			'AIPS_Prompt_Builder_Section_Base',
			class_parents($class_name),
			"{$class_name} should extend AIPS_Prompt_Builder_Section_Base."
		);
	}

	/**
	 * The `build()` convention the marker interface documents but cannot declare.
	 *
	 * @dataProvider section_class_provider
	 *
	 * @param string $class_name Section class under test.
	 */
	public function test_section_declares_public_build_method($class_name) {
		$reflection = new ReflectionClass($class_name);

		$this->assertTrue(
			$reflection->hasMethod('build'),
			"{$class_name} should expose a build() method."
		);

		$build = $reflection->getMethod('build');

		$this->assertTrue($build->isPublic(), "{$class_name}::build() should be public.");
		$this->assertFalse($build->isStatic(), "{$class_name}::build() should not be static.");
		$this->assertFalse($build->isAbstract(), "{$class_name}::build() should be concrete.");
	}

	/**
	 * Constructing with no arguments must keep working -- callers across the
	 * generation pipeline rely on the zero-argument form.
	 *
	 * @dataProvider section_class_provider
	 *
	 * @param string $class_name Section class under test.
	 */
	public function test_section_constructs_without_arguments($class_name) {
		$instance = new $class_name();

		$this->assertInstanceOf('AIPS_Prompt_Builder_Section', $instance);
		$this->assertInstanceOf('AIPS_Prompt_Builder_Section_Base', $instance);
	}

	/**
	 * Sections that never touch the diversity injector must not construct one.
	 */
	public function test_unused_collaborators_are_not_constructed() {
		foreach (array('AIPS_Prompt_Builder_Authors', 'AIPS_Prompt_Builder_Taxonomy') as $class_name) {
			$instance = new $class_name();

			$this->assertNull(
				$this->read_base_property($instance, 'diversity_injector'),
				"{$class_name} should not eagerly construct a diversity injector."
			);
			$this->assertNull(
				$this->read_base_property($instance, 'template_processor'),
				"{$class_name} should not eagerly construct a template processor."
			);
		}
	}

	/**
	 * Lazy resolution still yields a usable collaborator on first access.
	 */
	public function test_collaborators_resolve_on_first_access() {
		$instance = new AIPS_Prompt_Builder_Taxonomy();

		$this->assertInstanceOf(
			'AIPS_Template_Processor',
			$this->call_accessor($instance, 'get_template_processor')
		);
		$this->assertInstanceOf(
			'AIPS_Prompt_Builder_Diversity_Injector',
			$this->call_accessor($instance, 'get_diversity_injector')
		);
	}

	/**
	 * Repeated access returns the same instance rather than rebuilding it.
	 */
	public function test_lazy_collaborators_are_memoized() {
		$instance = new AIPS_Prompt_Builder_Taxonomy();

		$this->assertSame(
			$this->call_accessor($instance, 'get_template_processor'),
			$this->call_accessor($instance, 'get_template_processor')
		);
	}

	/**
	 * Explicit injection still wins over container/`new` resolution.
	 */
	public function test_injected_collaborators_are_used_as_is() {
		$template_processor = new AIPS_Template_Processor();
		$diversity_injector = new AIPS_Prompt_Builder_Diversity_Injector();

		$instance = new AIPS_Prompt_Builder_Post_Title($template_processor, $diversity_injector);

		$this->assertSame($template_processor, $this->call_accessor($instance, 'get_template_processor'));
		$this->assertSame($diversity_injector, $this->call_accessor($instance, 'get_diversity_injector'));
	}

	/**
	 * The diversity injector is a collaborator the sections consume, not a section.
	 */
	public function test_diversity_injector_is_not_a_section() {
		$this->assertArrayNotHasKey(
			'AIPS_Prompt_Builder_Section',
			class_implements('AIPS_Prompt_Builder_Diversity_Injector'),
			'AIPS_Prompt_Builder_Diversity_Injector should not implement AIPS_Prompt_Builder_Section.'
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Read a private property declared on AIPS_Prompt_Builder_Section_Base.
	 *
	 * @param object $instance      Section instance.
	 * @param string $property_name Property to read.
	 * @return mixed
	 */
	private function read_base_property($instance, $property_name) {
		$property = new ReflectionProperty('AIPS_Prompt_Builder_Section_Base', $property_name);
		$property->setAccessible(true);

		return $property->getValue($instance);
	}

	/**
	 * Invoke a protected accessor on AIPS_Prompt_Builder_Section_Base.
	 *
	 * @param object $instance    Section instance.
	 * @param string $method_name Accessor to invoke.
	 * @return mixed
	 */
	private function call_accessor($instance, $method_name) {
		$method = new ReflectionMethod('AIPS_Prompt_Builder_Section_Base', $method_name);
		$method->setAccessible(true);

		return $method->invoke($instance);
	}
}
