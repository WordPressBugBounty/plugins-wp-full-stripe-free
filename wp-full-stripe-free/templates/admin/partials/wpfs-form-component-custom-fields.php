<?php
	/** @var $view MM_WPFS_Admin_FormView */
	/** @var $form */
	/** @var $data */

	// Type label map shared by the field list and the add/edit dialog.
	$cfTypeLabels = [
		MM_WPFS_CustomFields::TYPE_TEXT        => __( 'Text field', 'wp-full-stripe-free' ),
		MM_WPFS_CustomFields::TYPE_TEXTAREA    => __( 'Text area', 'wp-full-stripe-free' ),
		MM_WPFS_CustomFields::TYPE_SELECT      => __( 'Dropdown', 'wp-full-stripe-free' ),
		MM_WPFS_CustomFields::TYPE_MULTISELECT => __( 'Multi-select', 'wp-full-stripe-free' ),
		MM_WPFS_CustomFields::TYPE_CHECKBOX    => __( 'Checkbox', 'wp-full-stripe-free' ),
		MM_WPFS_CustomFields::TYPE_DATE        => __( 'Date', 'wp-full-stripe-free' ),
		MM_WPFS_CustomFields::TYPE_NUMBER      => __( 'Number', 'wp-full-stripe-free' ),
		MM_WPFS_CustomFields::TYPE_PHONE       => __( 'Phone number', 'wp-full-stripe-free' ),
		MM_WPFS_CustomFields::TYPE_HTML        => __( 'HTML block', 'wp-full-stripe-free' ),
	];

	$cfBootstrapData = isset( $data->customFields ) ? json_decode( $data->customFields, true ) : null;
	if ( ! is_array( $cfBootstrapData ) ) {
		$cfBootstrapData = [ 'version' => 1, 'fields' => [] ];
	}
	$cfMaxCount = isset( $data->customFieldMaxCount ) ? (int) $data->customFieldMaxCount : MM_WPFS::DEFAULT_CUSTOM_INPUT_FIELD_MAX_COUNT;

	// Flags that make JSON safe to embed inside a <script> tag (escapes </script>, &, quotes).
	$cfJsonScriptFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

	$cfL10n = [
		'typeLabels'      => $cfTypeLabels,
		'maxCount'        => $cfMaxCount,
		'maxOptions'      => MM_WPFS_CustomFields::MAX_OPTIONS,
		'maxLabelLength'  => MM_WPFS_CustomFields::getMaxLabelLength(),
		'addTitle'        => __( 'Add custom field', 'wp-full-stripe-free' ),
		'editTitle'       => __( 'Edit custom field', 'wp-full-stripe-free' ),
		'deleteTitle'     => __( 'Delete custom field', 'wp-full-stripe-free' ),
		'labelRequired'   => __( 'Please enter field label', 'wp-full-stripe-free' ),
		'titleRequired'   => __( 'Please enter a title', 'wp-full-stripe-free' ),
		'contentRequired' => __( 'Please enter HTML content', 'wp-full-stripe-free' ),
		'labelTooLong'    => sprintf(
			/* translators: %d: maximum number of characters */
			__( 'Maximum custom field length is %d characters', 'wp-full-stripe-free' ),
			MM_WPFS_CustomFields::getMaxLabelLength()
		),
		'optionRequired'  => __( 'Please add at least one option', 'wp-full-stripe-free' ),
		'maxOptionsReached' => sprintf(
			/* translators: %d: maximum number of options */
			__( 'You can add at most %d options', 'wp-full-stripe-free' ),
			MM_WPFS_CustomFields::MAX_OPTIONS
		),
		'maxFieldsReached' => sprintf(
			/* translators: %d: maximum number of custom fields */
			__( 'You can add at most %d custom fields', 'wp-full-stripe-free' ),
			$cfMaxCount
		),
	];
?>
<div class="wpfs-form-group">
	<div class="wpfs-field-list">
		<div id="wpfs-custom-fields" class="wpfs-field-list__list js-sortable"></div>
	</div>
	<button type="button" class="wpfs-field-list__add js-add-custom-field" id="wpfs-add-custom-field">
		<div class="wpfs-icon-add-circle wpfs-field-list__icon"></div>
		<?php esc_html_e( 'Add field', 'wp-full-stripe-free' ); ?>
	</button>
</div>
<div class="wpfs-form-group" id="wpfs-custom-fields-required">
	<label class="wpfs-form-label"><?php $view->makeCustomFieldsRequired()->label(); ?></label>
	<p class="wpfs-form-text"><?php esc_html_e( 'Applies to legacy text fields. New typed fields use their own per-field "Required" toggle.', 'wp-full-stripe-free' ); ?></p>
	<div class="wpfs-form-check-list">
		<div class="wpfs-form-check">
			<?php $options = $view->makeCustomFieldsRequired()->options(); ?>
			<input id="<?php $options[0]->id(); ?>" name="<?php $options[0]->name(); ?>" <?php $options[0]->attributes(); ?> value="<?php $options[0]->value(); ?>" <?php echo $form->customInputRequired == $options[0]->value(false) ? 'checked' : ''; ?>>
			<label class="wpfs-form-check-label" for="<?php $options[0]->id(); ?>"><?php $options[0]->label(); ?></label>
		</div>
		<div class="wpfs-form-check">
			<input id="<?php $options[1]->id(); ?>" name="<?php $options[1]->name(); ?>" <?php $options[1]->attributes(); ?> value="<?php $options[1]->value(); ?>" <?php echo $form->customInputRequired == $options[1]->value(false) ? 'checked' : ''; ?>>
			<label class="wpfs-form-check-label" for="<?php $options[1]->id(); ?>"><?php $options[1]->label(); ?></label>
		</div>
	</div>
</div>
<input id="<?php $view->customFields()->id(); ?>" name="<?php $view->customFields()->name(); ?>" value="" <?php $view->customFields()->attributes(); ?>>

<script type="application/json" id="wpfs-custom-fields-bootstrap"><?php echo wp_json_encode( $cfBootstrapData, $cfJsonScriptFlags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_* flags is safe inside a script tag. ?></script>
<script type="application/json" id="wpfs-custom-fields-l10n"><?php echo wp_json_encode( $cfL10n, $cfJsonScriptFlags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_* flags is safe inside a script tag. ?></script>

<script type="text/template" id="wpfs-custom-field-template">
	<div class="wpfs-icon-expand-vertical-left-right wpfs-field-list__icon js-custom-field-handle"></div>
	<div class="wpfs-field-list__info js-edit-custom-field">
		<div class="wpfs-field-list__title"><%- title %><% if ( required ) { %><span class="wpfs-field-list__badge"><?php esc_html_e( 'Required', 'wp-full-stripe-free' ); ?></span><% } %></div>
		<div class="wpfs-field-list__meta"><%- typeLabel %></div>
	</div>
	<div class="wpfs-field-list__actions">
		<button type="button" class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-edit-custom-field" title="<?php esc_attr_e( 'Edit', 'wp-full-stripe-free' ); ?>">
			<span class="wpfs-icon-edit"></span>
		</button>
		<button type="button" class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-delete-custom-field" title="<?php esc_attr_e( 'Delete', 'wp-full-stripe-free' ); ?>">
			<span class="wpfs-icon-trash"></span>
		</button>
	</div>
</script>

<script type="text/template" id="wpfs-custom-field-option-row">
	<div class="wpfs-custom-field-option js-custom-field-option">
		<div class="wpfs-icon-expand-vertical-left-right wpfs-field-list__icon js-option-handle"></div>
		<input type="text" class="wpfs-form-control js-option-label" value="<%- label %>" placeholder="<?php esc_attr_e( 'Option label', 'wp-full-stripe-free' ); ?>">
		<input type="text" class="wpfs-form-control js-option-value" value="<%- value %>" placeholder="<?php esc_attr_e( 'Value', 'wp-full-stripe-free' ); ?>">
		<button type="button" class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-remove-option" title="<?php esc_attr_e( 'Remove option', 'wp-full-stripe-free' ); ?>">
			<span class="wpfs-icon-trash"></span>
		</button>
	</div>
</script>

<script type="text/template" id="wpfs-modal-custom-field">
	<div id="wpfs-custom-field-dialog" class="wpfs-dialog-content wpfs-custom-field-form">
		<form onsubmit="return false;">
			<div class="wpfs-dialog-scrollable">
				<div class="wpfs-form-group">
					<label class="wpfs-form-label" for="wpfs-cf-type"><?php esc_html_e( 'Field type', 'wp-full-stripe-free' ); ?></label>
					<select id="wpfs-cf-type" class="wpfs-form-control js-cf-type">
						<?php foreach ( $cfTypeLabels as $cfTypeValue => $cfTypeLabel ) : ?>
							<option value="<?php echo esc_attr( $cfTypeValue ); ?>"><?php echo esc_html( $cfTypeLabel ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="label">
					<label class="wpfs-form-label" for="wpfs-cf-label"><?php esc_html_e( 'Field label', 'wp-full-stripe-free' ); ?></label>
					<input id="wpfs-cf-label" class="wpfs-form-control js-cf-label" type="text" maxlength="<?php echo esc_attr( MM_WPFS_CustomFields::getMaxLabelLength() ); ?>">
					<div class="wpfs-form-field-error js-cf-label-error"></div>
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="title">
					<label class="wpfs-form-label" for="wpfs-cf-title"><?php esc_html_e( 'Title (admin only)', 'wp-full-stripe-free' ); ?></label>
					<input id="wpfs-cf-title" class="wpfs-form-control js-cf-title" type="text" maxlength="<?php echo esc_attr( MM_WPFS_CustomFields::getMaxLabelLength() ); ?>">
					<div class="wpfs-form-field-error js-cf-title-error"></div>
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="content">
					<label class="wpfs-form-label" for="wpfs-cf-content"><?php esc_html_e( 'HTML content', 'wp-full-stripe-free' ); ?></label>
					<textarea id="wpfs-cf-content" class="wpfs-form-control js-cf-content" rows="5"></textarea>
					<p class="wpfs-form-text"><?php esc_html_e( 'Display-only content. Never submitted and never sent to Stripe.', 'wp-full-stripe-free' ); ?></p>
					<div class="wpfs-form-field-error js-cf-content-error"></div>
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="placeholder">
					<label class="wpfs-form-label" for="wpfs-cf-placeholder"><?php esc_html_e( 'Placeholder', 'wp-full-stripe-free' ); ?></label>
					<input id="wpfs-cf-placeholder" class="wpfs-form-control js-cf-placeholder" type="text">
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="description">
					<label class="wpfs-form-label" for="wpfs-cf-description"><?php esc_html_e( 'Description', 'wp-full-stripe-free' ); ?></label>
					<input id="wpfs-cf-description" class="wpfs-form-control js-cf-description" type="text">
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="options">
					<label class="wpfs-form-label"><?php esc_html_e( 'Options', 'wp-full-stripe-free' ); ?></label>
					<div class="wpfs-custom-field-options js-custom-field-options"></div>
					<div class="wpfs-form-check">
						<input id="wpfs-cf-show-values" type="checkbox" class="wpfs-form-check-input js-cf-show-values">
						<label class="wpfs-form-check-label" for="wpfs-cf-show-values"><?php esc_html_e( 'Show values (advanced)', 'wp-full-stripe-free' ); ?></label>
					</div>
					<button type="button" class="wpfs-btn wpfs-btn-text js-add-option"><?php esc_html_e( 'Add option', 'wp-full-stripe-free' ); ?></button>
					<div class="wpfs-form-field-error js-cf-options-error"></div>
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="rows">
					<label class="wpfs-form-label" for="wpfs-cf-rows"><?php esc_html_e( 'Rows', 'wp-full-stripe-free' ); ?></label>
					<input id="wpfs-cf-rows" class="wpfs-form-control js-cf-rows" type="number" min="1" step="1">
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="maxLength">
					<label class="wpfs-form-label" for="wpfs-cf-maxlength"><?php esc_html_e( 'Maximum length', 'wp-full-stripe-free' ); ?></label>
					<input id="wpfs-cf-maxlength" class="wpfs-form-control js-cf-maxlength" type="number" min="1" step="1" max="<?php echo esc_attr( MM_WPFS_Utils::STRIPE_METADATA_VALUE_MAX_LENGTH ); ?>">
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="number">
					<label class="wpfs-form-label"><?php esc_html_e( 'Number constraints', 'wp-full-stripe-free' ); ?></label>
					<div class="wpfs-form-row">
						<input class="wpfs-form-control js-cf-min" type="number" placeholder="<?php esc_attr_e( 'Min', 'wp-full-stripe-free' ); ?>">
						<input class="wpfs-form-control js-cf-max" type="number" placeholder="<?php esc_attr_e( 'Max', 'wp-full-stripe-free' ); ?>">
						<input class="wpfs-form-control js-cf-step" type="number" placeholder="<?php esc_attr_e( 'Step', 'wp-full-stripe-free' ); ?>">
					</div>
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="date">
					<label class="wpfs-form-label"><?php esc_html_e( 'Date constraints', 'wp-full-stripe-free' ); ?></label>
					<div class="wpfs-form-row">
						<input class="wpfs-form-control js-cf-mindate" type="date" placeholder="<?php esc_attr_e( 'Earliest date', 'wp-full-stripe-free' ); ?>">
						<input class="wpfs-form-control js-cf-maxdate" type="date" placeholder="<?php esc_attr_e( 'Latest date', 'wp-full-stripe-free' ); ?>">
					</div>
				</div>

				<div class="wpfs-form-group js-cf-section" data-cf-section="required">
					<div class="wpfs-form-check">
						<input id="wpfs-cf-required" type="checkbox" class="wpfs-form-check-input js-cf-required">
						<label class="wpfs-form-check-label" for="wpfs-cf-required"><?php esc_html_e( 'Required', 'wp-full-stripe-free' ); ?></label>
					</div>
				</div>
			</div>
			<div class="wpfs-dialog-content-actions">
				<button type="button" class="wpfs-btn wpfs-btn-primary js-save-custom-field-dialog"><?php esc_html_e( 'Save changes', 'wp-full-stripe-free' ); ?></button>
				<button type="button" class="wpfs-btn wpfs-btn-text js-close-this-dialog"><?php esc_html_e( 'Discard', 'wp-full-stripe-free' ); ?></button>
			</div>
		</form>
	</div>
</script>

<script type="text/template" id="wpfs-modal-delete-custom-field">
	<div id="wpfs-delete-custom-field-dialog" class="wpfs-dialog-content">
		<div class="wpfs-dialog-scrollable">
			<p class="wpfs-dialog-content-text"><?php esc_html_e( 'Are you sure you would like to delete this custom field?', 'wp-full-stripe-free' ); ?></p>
		</div>
		<div class="wpfs-dialog-content-actions">
			<button type="button" class="wpfs-btn wpfs-btn-danger js-delete-custom-field-dialog"><?php esc_html_e( 'Delete field', 'wp-full-stripe-free' ); ?></button>
			<button type="button" class="wpfs-btn wpfs-btn-text js-close-this-dialog"><?php esc_html_e( 'Keep field', 'wp-full-stripe-free' ); ?></button>
		</div>
	</div>
</script>
