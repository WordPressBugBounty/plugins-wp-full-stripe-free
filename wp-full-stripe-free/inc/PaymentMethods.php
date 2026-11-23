<?php

namespace ThemeIsle\WPFullPay;

class PaymentMethods
{
    public static function get_payment_methods(): array
    {
        return register_payment_methods();
    }

    public static function get_payment_method_with_name($name): ?array
    {
        return get_payment_method_with_name($name);
    }

    public static function support_recurring($name): bool
    {
        $paymentMethod = get_payment_method_with_name($name);
        if ($paymentMethod === null) {
            return true; // default to support recurring as forms without payment methods are currently mostly recurring
        }

        return $paymentMethod['recurring'];
    }

    public static function is_supported_currency($payment_method, $currency): bool
    {
        $paymentMethod = get_payment_method_with_name($payment_method);
        if ($paymentMethod === null) {
            return false;
        }

        $supported_currencies = $paymentMethod['currencies'];

        if (empty($supported_currencies)) {
            return true;
        }

        return in_array($currency, $supported_currencies);
    }

    /**
     * Whether a form's payment method list leaves Stripe Link out. A card-only list doesn't hide Link,
     * because Stripe offers Link inside card payments, so the Payment Element must hide it explicitly.
     * Forms without a list (or with an unreadable one) keep Link.
     *
     * @param string|array<string>|null $paymentMethods The form's stored payment methods.
     */
    public static function is_link_disabled($paymentMethods): bool
    {
        if (is_string($paymentMethods)) {
            $paymentMethods = json_decode($paymentMethods, true);
        }

        return is_array($paymentMethods) && ! empty($paymentMethods) && ! in_array(Main::PAYMENT_METHOD_LINK, $paymentMethods, true);
    }
}
