<?php
/**
 * Legacy class names kept for code outside this plugin.
 *
 * The members addon and site-owner snippets reference these global
 * MM_WPFS_* / WPFS_* names directly. Each entry maps the legacy name to its
 * namespaced replacement. Internal code must use the namespaced class; do not
 * add entries for names only this plugin uses.
 *
 * @package ThemeIsle\WPFullPay
 */

return [
	'MM_WPFS'                    => \ThemeIsle\WPFullPay\Main::class,
	'MM_WPFS_API_v1'             => \ThemeIsle\WPFullPay\Api\ApiV1::class,
	'MM_WPFS_Admin_Menu'         => \ThemeIsle\WPFullPay\Admin\Menu::class,
	'MM_WPFS_Assets'             => \ThemeIsle\WPFullPay\Assets::class,
	'MM_WPFS_Binder'             => \ThemeIsle\WPFullPay\Forms\Models\Binder::class,
	'MM_WPFS_BindingResult'      => \ThemeIsle\WPFullPay\Forms\Models\BindingResult::class,
	'MM_WPFS_Control'            => \ThemeIsle\WPFullPay\Forms\Views\Control::class,
	'MM_WPFS_ControlUtils'       => \ThemeIsle\WPFullPay\Forms\Views\ControlUtils::class,
	'MM_WPFS_Currencies'         => \ThemeIsle\WPFullPay\Locale\Currencies::class,
	'MM_WPFS_FormViewConstants'  => \ThemeIsle\WPFullPay\Forms\Views\FormViewConstants::class,
	'MM_WPFS_LoggerService'      => \ThemeIsle\WPFullPay\Logging\LoggerService::class,
	'MM_WPFS_Model'              => \ThemeIsle\WPFullPay\Forms\Models\Model::class,
	'MM_WPFS_Options'            => \ThemeIsle\WPFullPay\Options::class,
	'MM_WPFS_PrivateAPI_v1'      => \ThemeIsle\WPFullPay\Api\PrivateApiV1::class,
	'MM_WPFS_Stripe'             => \ThemeIsle\WPFullPay\Stripe::class,
	'MM_WPFS_Utils'              => \ThemeIsle\WPFullPay\Utils::class,
	'MM_WPFS_Validator'          => \ThemeIsle\WPFullPay\Forms\Validators\Validator::class,
	'WPFS_Base_Table'            => \ThemeIsle\WPFullPay\Admin\Tables\BaseTable::class,
	'WPFS_UserFriendlyException' => \ThemeIsle\WPFullPay\Exceptions\UserFriendlyException::class,
];
