<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Locale\Localization;

trait CheckoutFormView {

	protected function getCheckoutFormAttributes( $form ) {
		$attributes = [];

		$attributes[ PopupFormViewConstants::ATTR_DATA_WPFS_COMPANY_NAME ] = $form->companyName;
		$attributes[ PopupFormViewConstants::ATTR_DATA_WPFS_PRODUCT_DESCRIPTION ] = Localization::translateLabel( $form->productDesc );
		$attributes[ PopupFormViewConstants::ATTR_DATA_WPFS_BUTTON_TITLE ] = Localization::translateLabel( $form->buttonTitle );
		$attributes[ PopupFormViewConstants::ATTR_DATA_WPFS_SHOW_REMEMBER_ME ] = 0;
		$attributes[ PopupFormViewConstants::ATTR_DATA_WPFS_IMAGE ] = $form->image;
		$attributes[ PopupFormViewConstants::ATTR_DATA_WPFS_USE_ALIPAY ] = 0; // tnagy remove Alipay support temporarily $form->useAlipay
		$attributes[ PopupFormViewConstants::ATTR_DATA_WPFS_PREFERRED_LANGUAGE ] = $form->preferredLanguage;

		return $attributes;
	}

}
