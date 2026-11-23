<?php

namespace ThemeIsle\WPFullPay;

class Shortcode {
    const SHORTCODE_PATTERN = '[fullstripe_form name="%s" type="%s"]';

    /**
     * @param $form
     *
     * @return bool|string
     */
    public static function createShortCodeByForm($form) {
        if (Main::FORM_TYPE_PAYMENT === $form->type &&
            Main::FORM_LAYOUT_INLINE === $form->layout) {
            return sprintf(self::SHORTCODE_PATTERN, $form->name, Main::FORM_TYPE_INLINE_PAYMENT);
        } elseif (Main::FORM_TYPE_SAVE_CARD === $form->type &&
            Main::FORM_LAYOUT_INLINE === $form->layout) {
            return sprintf(self::SHORTCODE_PATTERN, $form->name, Main::FORM_TYPE_INLINE_SAVE_CARD);
        } elseif (Main::FORM_TYPE_SUBSCRIPTION === $form->type &&
            Main::FORM_LAYOUT_INLINE === $form->layout) {
            return sprintf(self::SHORTCODE_PATTERN, $form->name, Main::FORM_TYPE_INLINE_SUBSCRIPTION);
        } elseif (Main::FORM_TYPE_DONATION === $form->type &&
            Main::FORM_LAYOUT_INLINE === $form->layout) {
            return sprintf(self::SHORTCODE_PATTERN, $form->name, Main::FORM_TYPE_INLINE_DONATION);
        } elseif (Main::FORM_TYPE_PAYMENT === $form->type &&
            Main::FORM_LAYOUT_CHECKOUT === $form->layout) {
            return sprintf(self::SHORTCODE_PATTERN, $form->name, Main::FORM_TYPE_CHECKOUT_PAYMENT);
        } elseif (Main::FORM_TYPE_SAVE_CARD === $form->type &&
            Main::FORM_LAYOUT_CHECKOUT === $form->layout) {
            return sprintf(self::SHORTCODE_PATTERN, $form->name, Main::FORM_TYPE_CHECKOUT_SAVE_CARD);
        } elseif (Main::FORM_TYPE_SUBSCRIPTION === $form->type &&
            Main::FORM_LAYOUT_CHECKOUT === $form->layout) {
            return sprintf(self::SHORTCODE_PATTERN, $form->name, Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION);
        } elseif (Main::FORM_TYPE_DONATION === $form->type &&
            Main::FORM_LAYOUT_CHECKOUT == $form->layout) {
            return sprintf(self::SHORTCODE_PATTERN, $form->name, Main::FORM_TYPE_CHECKOUT_DONATION);
        }

        return false;
    }

    /**
     * @param string $type
     * @return string
     */
    public static function normalizeShortCodeFormType( $type ) {
        $result = $type;

        switch ($type) {
            case Main::FORM_TYPE_PAYMENT:
                $result = Main::FORM_TYPE_INLINE_PAYMENT;
                break;

            case Main::FORM_TYPE_SUBSCRIPTION:
                $result = Main::FORM_TYPE_INLINE_SUBSCRIPTION;
                break;

            case Main::FORM_TYPE_POPUP_PAYMENT:
                $result = Main::FORM_TYPE_CHECKOUT_PAYMENT;
                break;

            case Main::FORM_TYPE_POPUP_SUBSCRIPTION:
                $result = Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION;
                break;

            case Main::FORM_TYPE_POPUP_SAVE_CARD:
                $result = Main::FORM_TYPE_CHECKOUT_SAVE_CARD;
                break;

            case Main::FORM_TYPE_POPUP_DONATION:
                $result = Main::FORM_TYPE_CHECKOUT_DONATION;
                break;
        }

        return $result;
    }
}
