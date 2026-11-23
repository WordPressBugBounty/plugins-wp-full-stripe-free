<?php

namespace ThemeIsle\WPFullPay\CustomerPortal;

use ThemeIsle\WPFullPay\Logging\Logger;
use ThemeIsle\WPFullPay\Stripe;

/**
 * Looks up the Stripe products of one customer portal page, fetching each product once.
 *
 * @phpstan-type ProductData object{id: string, name?: string, deleted?: bool}
 */
class ProductLookup
{
    /**
     * @var Stripe
     */
    private $stripe;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var array<string, ProductData|null> Products by id; null when Stripe couldn't return it.
     */
    private $products = [];

    /**
     * @param Stripe $stripe
     * @param Logger $logger
     */
    public function __construct($stripe, $logger)
    {
        $this->stripe = $stripe;
        $this->logger = $logger;
    }

    /**
     * @param string $productId
     *
     * @return ProductData|null The product, or null when it can't be loaded.
     */
    public function find($productId)
    {
        if (!array_key_exists($productId, $this->products)) {
            try {
                $this->products[$productId] = $this->stripe->retrieveProduct($productId);
            } catch (\Exception $ex) {
                $this->logger->error(__FUNCTION__, 'Cannot load product ' . $productId, $ex);
                $this->products[$productId] = null;
            }
        }

        return $this->products[$productId];
    }
}
