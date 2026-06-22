<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<?php
$config = AIPS_Config::get_instance();
$content_enhancements_repository = new AIPS_Content_Enhancement_Repository();
$content_enhancements = $content_enhancements_repository->all();
$content_enhancement_allowlist = $config->get_option('aips_content_enhancement_provider_allowlist', array());
$content_enhancement_default_disclosure = $config->get_option('aips_content_enhancement_default_disclosure_text');
$content_enhancement_default_cta = $config->get_option('aips_content_enhancement_default_cta_text');
?>
<?php if (empty($embedded)) : ?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Content Enhancements', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Generate test data and template scaffolds using AI to quickly prototype and test your workflow.', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
        </div>
<?php endif; ?>

        <!-- Content Panel -->
        <div class="aips-content-panel">
            <div class="aips-panel-body">
                <form id="aips-dev-scaffold-form">
                    <div class="aips-form-section">
                        <h3 class="aips-form-section-title">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e('Generate Template Scaffold', 'ai-post-scheduler'); ?>
                        </h3>
                        <p class="aips-field-description" style="margin-bottom: 20px;">
                            <?php esc_html_e('Create a complete template setup (Voice, Structure, Template) based on a topic.', 'ai-post-scheduler'); ?>
                        </p>

                        <div class="aips-form-row">
                            <label for="topic"><?php esc_html_e('Topic / Niche', 'ai-post-scheduler'); ?></label>
                            <input type="text" id="topic" name="topic" class="aips-form-input" placeholder="<?php esc_attr_e('e.g. Urban Gardening, SaaS Marketing', 'ai-post-scheduler'); ?>" required>
                            <p class="aips-field-description"><?php esc_html_e('The main topic to base the prompts and structure on.', 'ai-post-scheduler'); ?></p>
                        </div>

                        <div class="aips-form-row">
                            <label><?php esc_html_e('Options', 'ai-post-scheduler'); ?></label>
                            <div class="aips-checkbox-group">
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_voice" name="include_voice" value="true">
                                    <?php esc_html_e('Generate Voice/Persona', 'ai-post-scheduler'); ?>
                                </label>
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_structure" name="include_structure" value="true">
                                    <?php esc_html_e('Generate Article Structure', 'ai-post-scheduler'); ?>
                                </label>
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_title_prompt" name="include_title_prompt" value="true" checked>
                                    <?php esc_html_e('Include Title Prompt', 'ai-post-scheduler'); ?>
                                </label>
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_content_prompt" name="include_content_prompt" value="true" checked>
                                    <?php esc_html_e('Include Content Prompt', 'ai-post-scheduler'); ?>
                                </label>
                                <label class="aips-checkbox-label">
                                    <input type="checkbox" id="include_image_prompt" name="include_image_prompt" value="true" checked>
                                    <?php esc_html_e('Include Image Prompt', 'ai-post-scheduler'); ?>
                                </label>
                            </div>
                        </div>

                        <div class="aips-form-actions">
                            <button type="submit" id="aips-dev-scaffold-submit" class="aips-btn aips-btn-primary aips-btn-lg">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php esc_html_e('Generate Scaffold', 'ai-post-scheduler'); ?>
                            </button>
                            <span class="spinner" style="float: none; margin-top: 4px;"></span>
                        </div>
                    </div>
                </form>
            </div>
        </div>


        <!-- Content Enhancements Panel -->
        <div class="aips-content-panel" style="margin-top: 20px;">
            <div class="aips-panel-header">
                <h3><?php esc_html_e('Content Enhancements', 'ai-post-scheduler'); ?></h3>
            </div>
            <div class="aips-panel-body">
                <p class="aips-field-description"><?php esc_html_e('Manage low-volume content enhancements used by custom workflows, disclosures, and calls to action.', 'ai-post-scheduler'); ?></p>
                <form id="aips-content-enhancement-form">
                    <input type="hidden" id="content_enhancement_id" name="enhancement_id" value="">
                    <div class="aips-form-row">
                        <label for="content_enhancement_name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="content_enhancement_name" name="name" class="aips-form-input" required>
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_slug"><?php esc_html_e('Slug', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="content_enhancement_slug" name="slug" class="aips-form-input" placeholder="mortgage-calculator">
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_type"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></label>
                        <select id="content_enhancement_type" name="type" class="aips-form-input">
                            <?php foreach ( AIPS_Content_Enhancement::get_types() as $type => $label ) : ?>
                                <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_provider"><?php esc_html_e('Provider', 'ai-post-scheduler'); ?></label>
                        <select id="content_enhancement_provider" name="provider" class="aips-form-input" required>
                            <?php foreach ($content_enhancement_allowlist as $provider) : ?>
                                <option value="<?php echo esc_attr($provider); ?>"><?php echo esc_html(ucwords(str_replace(array('-', '_'), ' ', $provider))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_use_case"><?php esc_html_e('Use Case', 'ai-post-scheduler'); ?></label>
                        <textarea id="content_enhancement_use_case" name="use_case" class="aips-form-input" rows="3" placeholder="<?php esc_attr_e('When should the AI suggest this enhancement?', 'ai-post-scheduler'); ?>"></textarea>
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_endpoint_url"><?php esc_html_e('Endpoint URL', 'ai-post-scheduler'); ?></label>
                        <input type="url" id="content_enhancement_endpoint_url" name="endpoint_url" class="aips-form-input" placeholder="<?php esc_attr_e('https://example.com/embed', 'ai-post-scheduler'); ?>">
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_referral_url"><?php esc_html_e('Referral URL', 'ai-post-scheduler'); ?></label>
                        <input type="url" id="content_enhancement_referral_url" name="referral_url" class="aips-form-input" placeholder="<?php esc_attr_e('https://example.com/referral', 'ai-post-scheduler'); ?>">
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_utm_campaign"><?php esc_html_e('UTM Campaign', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="content_enhancement_utm_campaign" name="utm_campaign" class="aips-form-input">
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_utm_source"><?php esc_html_e('UTM Source', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="content_enhancement_utm_source" name="utm_source" class="aips-form-input">
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_utm_medium"><?php esc_html_e('UTM Medium', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="content_enhancement_utm_medium" name="utm_medium" class="aips-form-input">
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_rel_attributes"><?php esc_html_e('Rel Attributes', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="content_enhancement_rel_attributes" name="rel_attributes" class="aips-form-input" value="sponsored nofollow noopener noreferrer">
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_disclosure_text"><?php esc_html_e('Disclosure Text', 'ai-post-scheduler'); ?></label>
                        <textarea id="content_enhancement_disclosure_text" name="disclosure_text" class="aips-form-input" rows="3"><?php echo esc_textarea($content_enhancement_default_disclosure); ?></textarea>
                    </div>
                    <div class="aips-form-row">
                        <label for="content_enhancement_cta_text"><?php esc_html_e('CTA Text', 'ai-post-scheduler'); ?></label>
                        <input type="text" id="content_enhancement_cta_text" name="cta_label" class="aips-form-input" value="<?php echo esc_attr($content_enhancement_default_cta); ?>">
                    </div>
                    <label class="aips-checkbox-label">
                        <input type="checkbox" id="content_enhancement_is_active" name="is_active" value="1">
                        <?php esc_html_e('Active', 'ai-post-scheduler'); ?>
                    </label>
                    <div class="aips-form-actions" style="margin-top: 16px;">
                        <button type="submit" class="aips-btn aips-btn-primary"><?php esc_html_e('Save Enhancement', 'ai-post-scheduler'); ?></button>
                        <button type="button" id="aips-content-enhancement-reset" class="aips-btn"><?php esc_html_e('Reset', 'ai-post-scheduler'); ?></button>
                        <span class="spinner" style="float: none; margin-top: 4px;"></span>
                    </div>
                </form>

                <table class="widefat striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Slug', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Provider', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="aips-content-enhancements-list">
                        <?php foreach ($content_enhancements as $enhancement) : ?>
                            <tr data-enhancement='<?php echo esc_attr(wp_json_encode($enhancement)); ?>'>
                                <td><?php echo esc_html($enhancement['name'] ?? ''); ?></td>
                                <td><code>{{aips_enhancement:<?php echo esc_html($enhancement['slug'] ?? ''); ?>}}</code></td>
                                <td><?php echo esc_html($enhancement['type'] ?? ''); ?></td>
                                <td><?php echo esc_html($enhancement['provider'] ?? ''); ?></td>
                                <td><?php echo ! empty($enhancement['is_active']) ? esc_html__('Active', 'ai-post-scheduler') : esc_html__('Inactive', 'ai-post-scheduler'); ?></td>
                                <td>
                                    <button type="button" class="button aips-edit-content-enhancement"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></button>
                                    <button type="button" class="button aips-toggle-content-enhancement"><?php echo ! empty($enhancement['is_active']) ? esc_html__('Disable', 'ai-post-scheduler') : esc_html__('Enable', 'ai-post-scheduler'); ?></button>
                                    <button type="button" class="button aips-delete-content-enhancement"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Results Panel -->
        <div id="aips-dev-scaffold-results" class="aips-content-panel" style="margin-top: 20px; display: none;">
            <div class="aips-panel-header">
                <h3><?php esc_html_e('Generated Scaffold', 'ai-post-scheduler'); ?></h3>
            </div>
            <div class="aips-panel-body">
                <div id="aips-dev-scaffold-log" style="background: #f0f0f1; padding: 15px; border: 1px solid #c3c4c7; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px; line-height: 1.6;"></div>
            </div>
        </div>
<?php if (empty($embedded)) : ?>
    </div>
</div>
<?php endif; ?>
