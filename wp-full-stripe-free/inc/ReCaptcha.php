<?php

namespace ThemeIsle\WPFullPay;

use ThemeIsle\WPFullPay\Context\StaticContext;

class ReCaptcha {
    const URL_RECAPTCHA_API_SITEVERIFY = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * @param $googleReCAPTCHAResponse
     *
     * @return array|bool|mixed|object|\WP_Error
     */
    public static function verifyReCAPTCHA( $context, $googleReCAPTCHAResponse ) {
        $googleReCAPTCHASecretKey = self::getSecretKey( $context );

        if (!is_null($googleReCAPTCHASecretKey) && !is_null($googleReCAPTCHAResponse)) {
            $inputArray = [
                'secret' => $googleReCAPTCHASecretKey,
                'response' => $googleReCAPTCHAResponse,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];
            $request = wp_remote_post(
                self::URL_RECAPTCHA_API_SITEVERIFY,
                [
                    'timeout' => 10,
                    'sslverify' => true,
                    'body' => $inputArray
                ]
            );
            if (!is_wp_error($request)) {
                $request = json_decode(wp_remote_retrieve_body($request));

                return $request;
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * @param StaticContext $context
     * @return mixed|null
     */
    public static function getSecretKey( $context ) {
        return $context->getOptions()->get( Options::OPTION_GOOGLE_RE_CAPTCHA_SECRET_KEY );
    }

    /**
     * @param StaticContext $context
     * @return mixed|null
     */
    public static function getSiteKey( $context ) {
        return $context->getOptions()->get( Options::OPTION_GOOGLE_RE_CAPTCHA_SITE_KEY );
    }

    /**
     * @param StaticContext $context
     * @return mixed|null
     */
    public static function getSecureInlineForms( $context ) {
        return $context->getOptions()->get( Options::OPTION_SECURE_INLINE_FORMS_WITH_GOOGLE_RE_CAPTCHA ) == '1';
    }

    /**
     * @param StaticContext $context
     * @return mixed|null
     */
    public static function getSecureCheckoutForms( $context ) {
        return $context->getOptions()->get( Options::OPTION_SECURE_CHECKOUT_FORMS_WITH_GOOGLE_RE_CAPTCHA ) == '1';
    }

    /**
     * @param StaticContext $context
     * @return mixed|null
     */
    public static function getSecureCustomerPortal( $context ) {
        return $context->getOptions()->get( Options::OPTION_SECURE_CUSTOMER_PORTAL_WITH_GOOGLE_RE_CAPTCHA ) == '1';
    }
}
