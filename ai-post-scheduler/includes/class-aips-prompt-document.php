<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Prompt_Document
 *
 * A structured, provider-agnostic object model for a single AI prompt.
 * Replaces ad hoc string concatenation with named slots that can be
 * populated independently (e.g. by AIPS_Prompt_Composer middlewares) and
 * compiled into either a single completion string or a chat message array.
 *
 * This class has no WordPress dependency so it can be unit tested in
 * isolation, mirroring the existing plain-object context classes
 * (AIPS_Template_Context, AIPS_Topic_Context).
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Prompt_Document {

    /** @var string System prompt / persona directives. */
    private $system_instructions = '';

    /** @var string Core objective, e.g. "generate title", "draft section". */
    private $task_directive = '';

    /** @var array<int,string> Topic, template variables, keywords, author profile, etc. */
    private $context_payload = array();

    /** @var array<int,string> Word count, tone, forbidden terms, format constraints. */
    private $constraints = array();

    /** @var array<int,array{input:string,output:string}> Optional input/output demonstration pairs. */
    private $few_shot_examples = array();

    /** @var array|string|null JSON schema or structural delimiter rules. */
    private $output_schema = null;

    /**
     * @return string
     */
    public function get_system_instructions() {
        return $this->system_instructions;
    }

    /**
     * @param string $system_instructions
     * @return $this
     */
    public function set_system_instructions($system_instructions) {
        $this->system_instructions = (string) $system_instructions;
        return $this;
    }

    /**
     * @return string
     */
    public function get_task_directive() {
        return $this->task_directive;
    }

    /**
     * @param string $task_directive
     * @return $this
     */
    public function set_task_directive($task_directive) {
        $this->task_directive = (string) $task_directive;
        return $this;
    }

    /**
     * @return array<int,string>
     */
    public function get_context_payload() {
        return $this->context_payload;
    }

    /**
     * Replace the context payload entries.
     *
     * @param array<int,string> $context_payload
     * @return $this
     */
    public function set_context_payload(array $context_payload) {
        $this->context_payload = array_values($context_payload);
        return $this;
    }

    /**
     * Append a single context payload entry.
     *
     * @param string $entry
     * @return $this
     */
    public function add_context_payload($entry) {
        $entry = trim((string) $entry);
        if ('' !== $entry) {
            $this->context_payload[] = $entry;
        }
        return $this;
    }

    /**
     * @return array<int,string>
     */
    public function get_constraints() {
        return $this->constraints;
    }

    /**
     * @param array<int,string> $constraints
     * @return $this
     */
    public function set_constraints(array $constraints) {
        $this->constraints = array_values($constraints);
        return $this;
    }

    /**
     * Append a single constraint.
     *
     * @param string $constraint
     * @return $this
     */
    public function add_constraint($constraint) {
        $constraint = trim((string) $constraint);
        if ('' !== $constraint) {
            $this->constraints[] = $constraint;
        }
        return $this;
    }

    /**
     * @return array<int,array{input:string,output:string}>
     */
    public function get_few_shot_examples() {
        return $this->few_shot_examples;
    }

    /**
     * @param array<int,array{input:string,output:string}> $few_shot_examples
     * @return $this
     */
    public function set_few_shot_examples(array $few_shot_examples) {
        $this->few_shot_examples = array_values($few_shot_examples);
        return $this;
    }

    /**
     * Append a single few-shot demonstration pair.
     *
     * @param string $input
     * @param string $output
     * @return $this
     */
    public function add_few_shot_example($input, $output) {
        $this->few_shot_examples[] = array(
            'input'  => (string) $input,
            'output' => (string) $output,
        );
        return $this;
    }

    /**
     * @return array|string|null
     */
    public function get_output_schema() {
        return $this->output_schema;
    }

    /**
     * @param array|string|null $output_schema
     * @return $this
     */
    public function set_output_schema($output_schema) {
        $this->output_schema = $output_schema;
        return $this;
    }

    /**
     * Compile the document into a single completion-style prompt string.
     *
     * Sections are emitted in a fixed order (system instructions, task
     * directive, context payload, few-shot examples, constraints, output
     * schema) and empty sections are omitted entirely so the result never
     * carries stray whitespace from unset slots.
     *
     * @return string
     */
    public function compile_text() {
        $parts = array();

        if ('' !== trim($this->system_instructions)) {
            $parts[] = trim($this->system_instructions);
        }

        if ('' !== trim($this->task_directive)) {
            $parts[] = trim($this->task_directive);
        }

        if (!empty($this->context_payload)) {
            $parts[] = implode("\n", $this->context_payload);
        }

        if (!empty($this->few_shot_examples)) {
            $parts[] = $this->format_few_shot_examples();
        }

        if (!empty($this->constraints)) {
            $parts[] = "Constraints:\n" . implode("\n", array_map(function ($constraint) {
                return '- ' . $constraint;
            }, $this->constraints));
        }

        $schema_text = $this->format_output_schema();
        if ('' !== $schema_text) {
            $parts[] = $schema_text;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Compile the document into a chat-style message array.
     *
     * The system instructions (if any) become a single 'system' message;
     * everything else is concatenated into one 'user' message, in the same
     * section order as compile_text(). A document with no system
     * instructions produces only a 'user' message.
     *
     * @return array<int,array{role:string,content:string}>
     */
    public function compile_messages() {
        $messages = array();

        if ('' !== trim($this->system_instructions)) {
            $messages[] = array(
                'role'    => 'system',
                'content' => trim($this->system_instructions),
            );
        }

        $user_parts = array();

        if ('' !== trim($this->task_directive)) {
            $user_parts[] = trim($this->task_directive);
        }

        if (!empty($this->context_payload)) {
            $user_parts[] = implode("\n", $this->context_payload);
        }

        if (!empty($this->few_shot_examples)) {
            $user_parts[] = $this->format_few_shot_examples();
        }

        if (!empty($this->constraints)) {
            $user_parts[] = "Constraints:\n" . implode("\n", array_map(function ($constraint) {
                return '- ' . $constraint;
            }, $this->constraints));
        }

        $schema_text = $this->format_output_schema();
        if ('' !== $schema_text) {
            $user_parts[] = $schema_text;
        }

        if (!empty($user_parts)) {
            $messages[] = array(
                'role'    => 'user',
                'content' => implode("\n\n", $user_parts),
            );
        }

        return $messages;
    }

    /**
     * @return string
     */
    private function format_few_shot_examples() {
        $blocks = array();

        foreach ($this->few_shot_examples as $example) {
            $blocks[] = "Example input:\n" . $example['input'] . "\nExample output:\n" . $example['output'];
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @return string
     */
    private function format_output_schema() {
        if (null === $this->output_schema || '' === $this->output_schema) {
            return '';
        }

        if (is_array($this->output_schema)) {
            return "Output schema:\n" . wp_json_encode($this->output_schema);
        }

        return "Output schema:\n" . (string) $this->output_schema;
    }
}
