<?php
namespace AIPS\Services\Content;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateHelper {
    /**
     * Render a frequency dropdown using interval calculator labels.
     *
     * @param string $field_id   HTML id attribute.
     * @param string $field_name HTML name attribute.
     * @param string $selected   Selected frequency key.
     * @param string $label_text Label text.
     * @param array  $allowed    Optional list of allowed frequency keys.
     */
    public static function render_frequency_dropdown($field_id, $field_name, $selected, $label_text, $allowed = array()) {
        $calculator = new AIPS_Interval_Calculator();
        $options = $calculator->get_all_interval_displays($allowed);
        ?>
        <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label_text); ?></label>
        <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>">
            <?php foreach ($options as $frequency_key => $display) : ?>
                <option value="<?php echo esc_attr($frequency_key); ?>" <?php selected($frequency_key, $selected); ?>>
                    <?php echo esc_html($display); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
