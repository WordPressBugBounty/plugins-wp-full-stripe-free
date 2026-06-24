<?php
	/**
	 * Renders a single typed custom field on the public form.
	 *
	 * Expects $input (a MM_WPFS_Control) in scope, populated by
	 * MM_WPFS_FormView::prepareCustomInputs().
	 *
	 * @var MM_WPFS_Control $input
	 */

	$cfMeta     = $input->metadata();
	$cfType     = isset( $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_TYPE ] ) ? $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_TYPE ] : MM_WPFS_CustomFields::TYPE_TEXT;
	$cfRequired = ! empty( $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_REQUIRED ] );
	$cfDesc     = isset( $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_DESCRIPTION ] ) ? $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_DESCRIPTION ] : '';
	$cfOptions  = isset( $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_OPTIONS ] ) ? $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_OPTIONS ] : [];
	$cfSettings = isset( $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_SETTINGS ] ) ? $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_SETTINGS ] : [];
	$cfFieldId  = isset( $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_FIELD_ID ] ) ? $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_FIELD_ID ] : '';

	// Display-only content block: never an input, never submitted.
	if ( MM_WPFS_CustomFields::TYPE_HTML === $cfType ) {
		$cfContent = isset( $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_CONTENT ] ) ? $cfMeta[ MM_WPFS_FormView::CUSTOM_FIELD_META_CONTENT ] : '';
		?>
		<div class="wpfs-form-group wpfs-custom-field wpfs-custom-field--html">
			<?php echo MM_WPFS_CustomFields::sanitizeHtmlContent( $cfContent ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses_post() inside sanitizeHtmlContent(). ?>
		</div>
		<?php
		return;
	}

	$cfId       = $input->id( false );
	$cfDataAttr = sprintf(
		' data-wpfs-cf-type="%s" data-wpfs-cf-id="%s" data-wpfs-cf-required="%s"',
		esc_attr( $cfType ),
		esc_attr( $cfFieldId ),
		$cfRequired ? '1' : '0'
	);

	$cfGetSetting = static function ( $key ) use ( $cfSettings ) {
		return isset( $cfSettings[ $key ] ) ? $cfSettings[ $key ] : null;
	};
?>
<div class="wpfs-form-group wpfs-custom-field wpfs-custom-field--<?php echo esc_attr( $cfType ); ?>"<?php echo $cfDataAttr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr() above. ?>>
	<?php if ( MM_WPFS_CustomFields::TYPE_CHECKBOX === $cfType ) : ?>
		<div class="wpfs-form-check">
			<input type="hidden" name="<?php $input->name(); ?>" value="<?php echo esc_attr( MM_WPFS_CustomFields::CHECKBOX_VALUE_NO ); ?>">
			<input id="<?php echo esc_attr( $cfId ); ?>" name="<?php $input->name(); ?>" type="checkbox" class="wpfs-form-check-input" value="<?php echo esc_attr( MM_WPFS_CustomFields::CHECKBOX_VALUE_YES ); ?>" <?php $input->attributes(); ?>>
			<label class="wpfs-form-check-label" for="<?php echo esc_attr( $cfId ); ?>"><?php $input->label(); ?><?php if ( $cfRequired ) : ?> <span class="wpfs-required-mark" aria-hidden="true">*</span><?php endif; ?></label>
		</div>
	<?php else : ?>
		<label class="wpfs-form-label" for="<?php echo esc_attr( $cfId ); ?>"><?php $input->label(); ?><?php if ( $cfRequired ) : ?> <span class="wpfs-required-mark" aria-hidden="true">*</span><?php endif; ?></label>
		<?php
		switch ( $cfType ) {
			case MM_WPFS_CustomFields::TYPE_TEXTAREA:
				$cfRows      = $cfGetSetting( 'rows' );
				$cfMaxLength = $cfGetSetting( 'maxLength' );
				?>
				<textarea id="<?php echo esc_attr( $cfId ); ?>" name="<?php $input->name(); ?>" class="wpfs-form-control"
					rows="<?php echo esc_attr( $cfRows ? $cfRows : MM_WPFS_CustomFields::DEFAULT_TEXTAREA_ROWS ); ?>"
					<?php if ( $cfMaxLength ) : ?>maxlength="<?php echo esc_attr( $cfMaxLength ); ?>"<?php endif; ?>
					placeholder="<?php $input->placeholder(); ?>" <?php $input->attributes(); ?>><?php echo esc_textarea( $input->value( false ) ); ?></textarea>
				<?php
				break;

			case MM_WPFS_CustomFields::TYPE_SELECT:
				?>
				<?php
					$cfSelectPlaceholder = $input->placeholder( false );
					if ( '' === $cfSelectPlaceholder ) {
						$cfSelectPlaceholder = __( 'Select an option', 'wp-full-stripe-free' );
					}
					$cfCurrentValue = (string) $input->value( false );
				?>
				<select id="<?php echo esc_attr( $cfId ); ?>" name="<?php $input->name(); ?>" class="wpfs-form-control" <?php $input->attributes(); ?>>
					<option value=""><?php echo esc_html( $cfSelectPlaceholder ); ?></option>
					<?php foreach ( $cfOptions as $cfOption ) : ?>
						<option value="<?php echo esc_attr( $cfOption['value'] ); ?>" <?php selected( $cfCurrentValue, (string) $cfOption['value'] ); ?>><?php echo esc_html( $cfOption['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
				break;

			case MM_WPFS_CustomFields::TYPE_MULTISELECT:
				$cfCurrentRaw    = $input->value( false );
				$cfCurrentValues = is_array( $cfCurrentRaw )
					? array_map( 'strval', $cfCurrentRaw )
					: ( '' === (string) $cfCurrentRaw ? [] : [ (string) $cfCurrentRaw ] );
				?>
				<div class="wpfs-form-check-list" <?php $input->attributes(); ?>>
					<?php foreach ( $cfOptions as $cfOptionIndex => $cfOption ) : ?>
						<?php $cfOptionId = $cfId . '-' . $cfOptionIndex; ?>
						<div class="wpfs-form-check">
							<input type="checkbox" class="wpfs-form-check-input" id="<?php echo esc_attr( $cfOptionId ); ?>" name="<?php $input->name(); ?>" value="<?php echo esc_attr( $cfOption['value'] ); ?>" <?php checked( in_array( (string) $cfOption['value'], $cfCurrentValues, true ) ); ?>>
							<label class="wpfs-form-check-label" for="<?php echo esc_attr( $cfOptionId ); ?>"><?php echo esc_html( $cfOption['label'] ); ?></label>
						</div>
					<?php endforeach; ?>
				</div>
				<?php
				break;

			case MM_WPFS_CustomFields::TYPE_DATE:
				$cfMinDate = $cfGetSetting( 'minDate' );
				$cfMaxDate = $cfGetSetting( 'maxDate' );
				?>
				<input id="<?php echo esc_attr( $cfId ); ?>" name="<?php $input->name(); ?>" type="date" class="wpfs-form-control"
					value="<?php $input->value(); ?>"
					<?php if ( $cfMinDate ) : ?>min="<?php echo esc_attr( $cfMinDate ); ?>"<?php endif; ?>
					<?php if ( $cfMaxDate ) : ?>max="<?php echo esc_attr( $cfMaxDate ); ?>"<?php endif; ?>
					placeholder="<?php $input->placeholder(); ?>" <?php $input->attributes(); ?>>
				<?php
				break;

			case MM_WPFS_CustomFields::TYPE_NUMBER:
				$cfMin  = $cfGetSetting( 'min' );
				$cfMax  = $cfGetSetting( 'max' );
				$cfStep = $cfGetSetting( 'step' );
				?>
				<input id="<?php echo esc_attr( $cfId ); ?>" name="<?php $input->name(); ?>" type="number" class="wpfs-form-control"
					value="<?php $input->value(); ?>"
					<?php if ( null !== $cfMin ) : ?>min="<?php echo esc_attr( $cfMin ); ?>"<?php endif; ?>
					<?php if ( null !== $cfMax ) : ?>max="<?php echo esc_attr( $cfMax ); ?>"<?php endif; ?>
					<?php if ( null !== $cfStep ) : ?>step="<?php echo esc_attr( $cfStep ); ?>"<?php endif; ?>
					placeholder="<?php $input->placeholder(); ?>" <?php $input->attributes(); ?>>
				<?php
				break;

			case MM_WPFS_CustomFields::TYPE_PHONE:
				?>
				<input id="<?php echo esc_attr( $cfId ); ?>" name="<?php $input->name(); ?>" type="tel" class="wpfs-form-control"
					value="<?php $input->value(); ?>" maxlength="<?php echo esc_attr( MM_WPFS_CustomFields::PHONE_MAX_LENGTH ); ?>"
					placeholder="<?php $input->placeholder(); ?>" <?php $input->attributes(); ?>>
				<?php
				break;

			case MM_WPFS_CustomFields::TYPE_TEXT:
			default:
				?>
				<input id="<?php echo esc_attr( $cfId ); ?>" name="<?php $input->name(); ?>" type="text" class="wpfs-form-control"
					value="<?php $input->value(); ?>" placeholder="<?php $input->placeholder(); ?>" <?php $input->attributes(); ?>>
				<?php
				break;
		}
		?>
	<?php endif; ?>
	<?php if ( '' !== $cfDesc ) : ?>
		<p class="wpfs-form-field-description"><?php echo esc_html( $cfDesc ); ?></p>
	<?php endif; ?>
</div>
