<?php

/**
 * Focused tests for DI behavior in the regeneration stack.
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_Regeneration_DI extends WP_UnitTestCase {

	public function tearDown(): void {
		AIPS_Container::get_instance()->clear();
		parent::tearDown();
	}

	/**
	 * Test that the generation context factory can reconstruct template context
	 * using injected repository dependencies.
	 *
	 * @return void
	 */
	public function test_generation_context_factory_uses_injected_repositories() {
		$history_repository = new class() {
			public function get_by_id($history_id) {
				return (object) array(
					'id' => $history_id,
					'post_id' => 42,
					'template_id' => 10,
					'topic_id' => 0,
					'author_id' => 0,
				);
			}
		};

		$template_repository = new class() {
			public function get_by_id($template_id) {
				return (object) array(
					'id' => $template_id,
					'name' => 'Injected Template',
					'prompt_template' => 'Write about {{topic}}',
					'title_prompt' => 'Generate a title',
					'image_prompt' => 'Generate an image',
					'generate_featured_image' => 0,
					'featured_image_source' => 'ai_prompt',
					'post_status' => 'draft',
					'post_category' => 1,
					'post_tags' => 'test',
					'post_author' => 1,
				);
			}
		};

		$factory = new AIPS_Generation_Context_Factory(
			$history_repository,
			$template_repository,
			new class() {
				public function get_by_id($topic_id) {
					return null;
				}
			},
			new class() {
				public function get_by_id($author_id) {
					return null;
				}
			},
			new class() {
				public function get_by_id($voice_id) {
					return null;
				}
			}
		);

		$context = $factory->create_from_history_id(99);

		$this->assertIsArray($context);
		$this->assertSame(99, $context['history_id']);
		$this->assertSame(42, $context['post_id']);
		$this->assertSame('template', $context['context_type']);
		$this->assertSame('Injected Template', $context['context_name']);
		$this->assertInstanceOf('AIPS_Template_Context', $context['generation_context']);
	}

	/**
	 * Test that component regeneration service delegates context loading to the
	 * injected generation context factory.
	 *
	 * @return void
	 */
	public function test_component_regeneration_service_uses_injected_factory() {
		$factory = new class() {
			public $requested_history_id = null;

			public function create_from_history_id($history_id) {
				$this->requested_history_id = $history_id;

				return array(
					'history_id' => $history_id,
					'post_id' => 321,
					'context_type' => 'template',
					'context_name' => 'Delegated Context',
					'generation_context' => new AIPS_Template_Context((object) array(
						'id' => 3,
						'name' => 'Delegated Template',
						'prompt_template' => 'Prompt',
						'title_prompt' => 'Title',
						'image_prompt' => 'Image',
						'generate_featured_image' => 0,
						'featured_image_source' => 'ai_prompt',
						'post_status' => 'draft',
						'post_category' => 1,
						'post_tags' => '',
						'post_author' => 1,
					)),
				);
			}
		};

		$service = new AIPS_Component_Regeneration_Service(new AIPS_History_Repository(), $factory);

		$context = $service->get_generation_context(123);

		$this->assertSame(123, $factory->requested_history_id);
		$this->assertSame('Delegated Context', $context['context_name']);
		$this->assertSame(321, $context['post_id']);
	}

	/**
	 * Test that AI edit controller resolves the regeneration service from the
	 * container when no explicit service is provided.
	 *
	 * @return void
	 */
	public function test_ai_edit_controller_uses_container_service_binding() {
		$container = AIPS_Container::get_instance();
		$stub_service = new stdClass();

		$container->bind(AIPS_Component_Regeneration_Service::class, function() use ($stub_service) {
			return $stub_service;
		});

		$controller = new AIPS_AI_Edit_Controller(null, new AIPS_History_Repository());

		$reflection = new ReflectionClass($controller);
		$property = $reflection->getProperty('service');
		$property->setAccessible(true);

		$this->assertSame($stub_service, $property->getValue($controller));
	}
}