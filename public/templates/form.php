<?php
/** @var object $form */
/** @var array  $fields */
?>
<form class="wpns-form" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="wpns_form_id" value="<?php echo esc_attr( $form->id ); ?>">

    <?php foreach ( $fields as $field ) :
        $name      = $field->field_name;
        $label     = $field->field_label;
        $type      = $field->field_type;
        $required  = ! empty( $field->is_required );
        $ph        = $field->placeholder ?? '';
        $default   = $field->default_val ?? '';
        $css_class = $field->css_class   ?? '';
        $options   = [];
        if ( ! empty( $field->options_json ) ) {
            $decoded = json_decode( $field->options_json, true );
            if ( is_array( $decoded ) ) {
                $options = $decoded;
            }
        }
    ?>

        <?php if ( $type === 'hidden' ) : ?>
            <input type="hidden"
                   name="<?php echo esc_attr( $name ); ?>"
                   value="<?php echo esc_attr( $default ); ?>">
            <?php continue; ?>
        <?php endif; ?>

        <div class="wpns-field<?php echo $css_class ? ' ' . esc_attr( $css_class ) : ''; ?>"
             data-field-name="<?php echo esc_attr( $name ); ?>">

            <label for="wpns-field-<?php echo esc_attr( $name ); ?>">
                <?php echo esc_html( $label ); ?>
                <?php if ( $required ) : ?>
                    <span class="wpns-required-star" aria-hidden="true">*</span>
                <?php endif; ?>
            </label>

            <?php if ( in_array( $type, [ 'text', 'email', 'tel', 'number' ], true ) ) : ?>

                <input type="<?php echo esc_attr( $type ); ?>"
                       id="wpns-field-<?php echo esc_attr( $name ); ?>"
                       name="<?php echo esc_attr( $name ); ?>"
                       placeholder="<?php echo esc_attr( $ph ); ?>"
                       value="<?php echo esc_attr( $default ); ?>"
                       data-label="<?php echo esc_attr( $label ); ?>"
                       <?php echo $required ? 'required aria-required="true"' : ''; ?>>

            <?php elseif ( $type === 'textarea' ) : ?>

                <textarea id="wpns-field-<?php echo esc_attr( $name ); ?>"
                          name="<?php echo esc_attr( $name ); ?>"
                          placeholder="<?php echo esc_attr( $ph ); ?>"
                          data-label="<?php echo esc_attr( $label ); ?>"
                          <?php echo $required ? 'required aria-required="true"' : ''; ?>><?php echo esc_textarea( $default ); ?></textarea>

            <?php elseif ( $type === 'file' ) : ?>

                <input type="file"
                       id="wpns-field-<?php echo esc_attr( $name ); ?>"
                       name="<?php echo esc_attr( $name ); ?>"
                       data-label="<?php echo esc_attr( $label ); ?>"
                       <?php echo $required ? 'required aria-required="true"' : ''; ?>>

            <?php elseif ( $type === 'select' ) : ?>

                <select id="wpns-field-<?php echo esc_attr( $name ); ?>"
                        name="<?php echo esc_attr( $name ); ?>"
                        data-label="<?php echo esc_attr( $label ); ?>"
                        <?php echo $required ? 'required aria-required="true"' : ''; ?>>
                    <option value=""><?php esc_html_e( 'Select…', 'wp-netsuite-forms' ); ?></option>
                    <?php foreach ( $options as $opt ) :
                        $opt_label = $opt['label'] ?? '';
                        $opt_value = $opt['value'] ?? '';
                    ?>
                        <option value="<?php echo esc_attr( $opt_value ); ?>"
                            <?php selected( $default, $opt_value ); ?>>
                            <?php echo esc_html( $opt_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ( $type === 'radio' ) : ?>

                <div class="wpns-options"
                     role="radiogroup"
                     aria-label="<?php echo esc_attr( $label ); ?>">
                    <?php foreach ( $options as $opt ) :
                        $opt_label = $opt['label'] ?? '';
                        $opt_value = $opt['value'] ?? '';
                    ?>
                        <label>
                            <input type="radio"
                                   name="<?php echo esc_attr( $name ); ?>"
                                   value="<?php echo esc_attr( $opt_value ); ?>"
                                   data-label="<?php echo esc_attr( $label ); ?>"
                                   <?php checked( $default, $opt_value ); ?>
                                   <?php echo $required ? 'required' : ''; ?>>
                            <?php echo esc_html( $opt_label ); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

            <?php elseif ( $type === 'checkbox' ) : ?>

                <div class="wpns-options">
                    <?php $idx = 0; foreach ( $options as $opt ) :
                        $opt_label = $opt['label'] ?? '';
                        $opt_value = $opt['value'] ?? '';
                    ?>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( $name ); ?>[]"
                                   value="<?php echo esc_attr( $opt_value ); ?>"
                                   data-label="<?php echo esc_attr( $label ); ?>"
                                   <?php echo ( $required && $idx === 0 ) ? 'required' : ''; ?>>
                            <?php echo esc_html( $opt_label ); ?>
                        </label>
                        <?php $idx++; ?>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

            <span class="wpns-field-error" role="alert" aria-live="polite"></span>

        </div>

    <?php endforeach; ?>

    <div class="wpns-submit-wrap">
        <button type="submit" class="wpns-submit">
            <?php esc_html_e( 'Submit', 'wp-netsuite-forms' ); ?>
        </button>
    </div>

    <div class="wpns-response" aria-live="polite"></div>
</form>
