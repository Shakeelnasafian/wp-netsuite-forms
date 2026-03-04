<?php
/** @var object $form */
/** @var array $fields */
?>
<form class="wpns-form" method="post" enctype="multipart/form-data">
    <input type="hidden" name="wpns_form_id" value="<?php echo esc_attr($form->id); ?>">
    <?php foreach ($fields as $field) :
        $name = $field->field_name;
        $label = $field->field_label;
        $type = $field->field_type;
        $required = !empty($field->is_required);
        $placeholder = $field->placeholder ?? '';
        $default = $field->default_val ?? '';
        $css_class = $field->css_class ?? '';
        $options = [];
        if (!empty($field->options_json)) {
            $decoded = json_decode($field->options_json, true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }
    ?>
        <?php if ($type === 'hidden') : ?>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($default); ?>">
            <?php continue; ?>
        <?php endif; ?>

        <div class="wpns-field <?php echo esc_attr($css_class); ?>">
            <label><?php echo esc_html($label); ?><?php if ($required) : ?> <span>*</span><?php endif; ?></label>

            <?php if (in_array($type, ['text','email','tel','number'], true)) : ?>
                <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" value="<?php echo esc_attr($default); ?>" <?php echo $required ? 'required' : ''; ?> data-label="<?php echo esc_attr($label); ?>">
            <?php elseif ($type === 'textarea') : ?>
                <textarea name="<?php echo esc_attr($name); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" <?php echo $required ? 'required' : ''; ?> data-label="<?php echo esc_attr($label); ?>"><?php echo esc_textarea($default); ?></textarea>
            <?php elseif ($type === 'file') : ?>
                <input type="file" name="<?php echo esc_attr($name); ?>" <?php echo $required ? 'required' : ''; ?> data-label="<?php echo esc_attr($label); ?>">
            <?php elseif ($type === 'select') : ?>
                <select name="<?php echo esc_attr($name); ?>" <?php echo $required ? 'required' : ''; ?> data-label="<?php echo esc_attr($label); ?>">
                    <option value=""><?php echo esc_html__('Select', 'wp-netsuite-forms'); ?></option>
                    <?php foreach ($options as $opt) :
                        $opt_label = $opt['label'] ?? '';
                        $opt_value = $opt['value'] ?? '';
                    ?>
                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($default, $opt_value); ?>><?php echo esc_html($opt_label); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'radio') : ?>
                <div class="wpns-options">
                    <?php foreach ($options as $opt) :
                        $opt_label = $opt['label'] ?? '';
                        $opt_value = $opt['value'] ?? '';
                    ?>
                        <label><input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($opt_value); ?>" <?php checked($default, $opt_value); ?> <?php echo $required ? 'required' : ''; ?> data-label="<?php echo esc_attr($label); ?>"> <?php echo esc_html($opt_label); ?></label>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($type === 'checkbox') : ?>
                <div class="wpns-options">
                    <?php $idx = 0; foreach ($options as $opt) :
                        $opt_label = $opt['label'] ?? '';
                        $opt_value = $opt['value'] ?? '';
                    ?>
                        <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($opt_value); ?>" data-label="<?php echo esc_attr($label); ?>" <?php echo ($required && $idx === 0) ? 'required' : ''; ?>> <?php echo esc_html($opt_label); ?></label>
                        <?php $idx++; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="wpns-submit"><?php echo esc_html__('Submit', 'wp-netsuite-forms'); ?></button>
    <div class="wpns-response"></div>
</form>
