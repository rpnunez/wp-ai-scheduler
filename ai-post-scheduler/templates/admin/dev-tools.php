<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Developer Tools', 'ai-post-scheduler'); ?></h1>
    <p><?php esc_html_e('Use these tools to generate test data and template scaffolds using AI.', 'ai-post-scheduler'); ?></p>

    <div class="card">
        <h2><?php esc_html_e('Generate Template Scaffold', 'ai-post-scheduler'); ?></h2>
        <p class="description"><?php esc_html_e('Create a complete template setup (Voice, Structure, Template) based on a topic.', 'ai-post-scheduler'); ?></p>

        <form id="aips-dev-scaffold-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="topic"><?php esc_html_e('Topic / Niche', 'ai-post-scheduler'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="topic" name="topic" class="regular-text" placeholder="e.g. Urban Gardening, SaaS Marketing" required>
                        <p class="description"><?php esc_html_e('The main topic to base the prompts and structure on.', 'ai-post-scheduler'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Options', 'ai-post-scheduler'); ?></th>
                    <td>
                        <fieldset>
                            <label for="include_voice">
                                <input type="checkbox" id="include_voice" name="include_voice" value="true">
                                <?php esc_html_e('Generate Voice/Persona', 'ai-post-scheduler'); ?>
                            </label><br>

                            <label for="include_structure">
                                <input type="checkbox" id="include_structure" name="include_structure" value="true">
                                <?php esc_html_e('Generate Article Structure', 'ai-post-scheduler'); ?>
                            </label><br>

                            <label for="include_title_prompt">
                                <input type="checkbox" id="include_title_prompt" name="include_title_prompt" value="true" checked>
                                <?php esc_html_e('Include Title Prompt', 'ai-post-scheduler'); ?>
                            </label><br>

                            <label for="include_content_prompt">
                                <input type="checkbox" id="include_content_prompt" name="include_content_prompt" value="true" checked>
                                <?php esc_html_e('Include Content Prompt', 'ai-post-scheduler'); ?>
                            </label><br>

                            <label for="include_image_prompt">
                                <input type="checkbox" id="include_image_prompt" name="include_image_prompt" value="true" checked>
                                <?php esc_html_e('Include Image Prompt', 'ai-post-scheduler'); ?>
                            </label><br>

                            <label for="include_ai_variables">
                                <input type="checkbox" id="include_ai_variables" name="include_ai_variables" value="true">
                                <?php esc_html_e('Use AI Variables {{Variable}}', 'ai-post-scheduler'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary" id="aips-generate-scaffold-btn">
                    <?php esc_html_e('Generate Scaffold', 'ai-post-scheduler'); ?>
                </button>
                <span class="spinner"></span>
            </p>
        </form>
    </div>

    <div id="aips-dev-output" class="notice notice-success is-dismissible" style="display:none; margin-top: 20px;">
        <p id="aips-dev-output-message"></p>
        <ul id="aips-dev-output-list"></ul>
    </div>

    <div id="aips-dev-error" class="notice notice-error is-dismissible" style="display:none; margin-top: 20px;">
        <p id="aips-dev-error-message"></p>
    </div>
</div>
