<?php
/**
 * Verification Script: Reproduce Excerpt Generation Logic Flaw
 *
 * This script simulates the logic in AIPS_Generator::generate_post regarding
 * excerpt generation. It demonstrates that the prompt instructions ($base_processed_prompt)
 * are passed to generate_excerpt instead of the actual generated content ($content).
 */

class Mock_AIPS_Generator {

    public function simulate_generate_post() {
        // Step 1: Define inputs
        $topic = "Space Exploration";
        $template_prompt = "Write a comprehensive article about recent Mars missions.";
        $base_processed_prompt = "Topic: $topic\nInstructions: $template_prompt"; // Simplified prompt construction

        echo "Input Prompt (base_processed_prompt): \n$base_processed_prompt\n\n";

        // Step 2: Simulate Content Generation
        // This is what the AI returns based on the prompt
        $generated_content = "Mars missions have seen significant activity in recent years. NASA's Perseverance rover continues to explore Jezero Crater... (Imagine 2000 words here)";
        echo "Generated Content (\$content): \n$generated_content\n\n";

        // Step 3: Simulate Title Generation
        $title = "The New Era of Martian Exploration";

        // Step 4: Excerpt Generation (The Bug)
        // In the actual code: $excerpt = $this->generate_excerpt($title, $base_processed_prompt, $voice_excerpt_instructions);
        echo "--- Calling generate_excerpt ---\n";
        $this->generate_excerpt($title, $base_processed_prompt); // PASSING PROMPT, NOT CONTENT
    }

    public function generate_excerpt($title, $content_argument) {
        echo "Inside generate_excerpt:\n";
        echo "Argument received as \$content: \n'$content_argument'\n\n";

        // Check if the argument matches the prompt or the content
        if (strpos($content_argument, "Instructions:") !== false) {
            echo "❌ BUG CONFIRMED: The argument contains instructions/prompt, not the article body.\n";
            echo "The excerpt will be generated based on instructions like 'Write an article...', rather than the article itself.\n";
        } else {
            echo "✅ Correct: The argument appears to be the article content.\n";
        }

        // Simulate prompt construction for excerpt
        $excerpt_prompt = "ARTICLE BODY:\n" . $content_argument . "\n\n";
        echo "Prompt sent to AI for excerpt:\n$excerpt_prompt\n";
    }
}

$generator = new Mock_AIPS_Generator();
$generator->simulate_generate_post();
