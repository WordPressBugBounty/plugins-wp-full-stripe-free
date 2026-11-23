<?php

namespace ThemeIsle\WPFullPay\Pricing;

use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Exceptions\InvalidTaxIdException;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

abstract class PriceCalculator {
	use LoggerAddOn;
	use StaticContextAddOn;

	/**
	 * A Stripe invoice preview returns this many lines at most.
	 */
	const PREVIEW_LINE_LIMIT = 10;

	/**
	 * Previews take about a second each, so at most this many products are previewed one at a time.
	 */
	const SINGLE_PREVIEW_LIMIT = 10;

	/** @var Stripe $stripe */
	protected $stripe;
	/** @var Database $db */
	protected $db = null;
	/** @var Options $options */
	protected $options = null;
	protected $pricingData;

	public function __construct( $pricingData, $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_RUNTIME );
		$this->options = new Options();

		$this->initStaticContext();

		$this->pricingData = $pricingData;
		$this->stripe = new Stripe( Stripe::getStripeAuthenticationToken( $this->staticContext ), $loggerService );
		$this->db = new Database();
	}

	protected abstract function getFormFromDatabase();
	protected abstract function getApplicableTaxRates( $form );
	protected abstract function getProductBuckets( $form );
	protected function prepareStripeInvoiceParams( $pricingData, $products, $taxRateIds ) {
		$result = [];

		$address = [ 
			'country' => $pricingData->country,
		];
		if ( $pricingData->stripeTax ) {
			$result['automatic_tax'] = [ 
				'enabled' => true
			];
		}

		if ( ! empty( $pricingData->zip ) ) {
			$address['postal_code'] = $pricingData->zip;
		}
		if ( ! empty( $pricingData->city ) ) {
			$address['city'] = $pricingData->city;
		}
		if ( ! empty( $pricingData->line1 ) ) {
			$address['line1'] = $pricingData->line1;
		}
		if ( ! empty( $pricingData->line2 ) ) {
			$address['line2'] = $pricingData->line2;
		}
		if ( ! empty( $pricingData->state ) ) {
			$address['state'] = $pricingData->state;
		}

		$result['customer_details'] = [ 
			'address' => $address
		];

		if ( $pricingData->stripeTax && $pricingData->taxIdType && $pricingData->taxId ) {
			$result['customer_details']['tax_ids'] = [ 
				[ 
					'type' => $pricingData->taxIdType,
					'value' => $pricingData->taxId,
				]
			];
		}

		return $result;
	}
	protected abstract function extractPaymentDetailsFromInvoiceLineItems( $lineItems, $taxRates, $products );

	/**
	 * Whether the charge adds the recovery fee as an invoice line of its own, which a coupon
	 * discounts and Stripe Tax can tax, so the preview prices it the same way.
	 *
	 * @return bool
	 */
	protected function previewsRecoveryFeeLine() {
		return false;
	}

	/**
	 * The recovery fee of a product as an invoice item, untaxed like the line the charge adds.
	 *
	 * @param \stdClass $form
	 * @param int        $amount   The amount the fee is worked out on, in the smallest currency unit.
	 * @param string     $currency
	 * @param string     $priceId  The product's price, which the fee line is listed with.
	 * @return array{amount: int, currency: string, description: string, metadata: array{type: string, priceId: string}}|null
	 *         Null when the form has no fee, or the customer declined it.
	 */
	protected function prepareRecoveryFeeInvoiceItem( $form, $amount, $currency, $priceId ) {
		if ( ! $this->addsRecoveryFeeLine( $form ) || $amount <= 0 ) {
			return null;
		}

		$recoveryFeeData = Utils::getFeeRecoveryData( $form );
		return [
			'amount' => Utils::calculateRecoveryFee(
				$amount,
				$recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ],
				$recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ],
				$currency
			),
			'currency' => $currency,
			'description' => __( 'Transaction Fee', 'wp-full-stripe-free' ),
			'metadata' => [
				'type' => Main::LINE_ITEM_TYPE_FEE_RECOVERY,
				'priceId' => $priceId,
			],
		];
	}

	/**
	 * @param \stdClass $form
	 * @return bool Whether the previews add a recovery fee line for each product: the form has a fee and
	 *              the customer pays it.
	 */
	protected function addsRecoveryFeeLine( $form ) {
		$accepted = Utils::isFeeRecoveryAlwaysApplied( $form ) || ! empty( $this->pricingData->feeRecoveryAccepted );

		return $this->previewsRecoveryFeeLine() && ! empty( Utils::getFeeRecoveryData( $form ) ) && $accepted;
	}

	/**
	 * The recovery fee line of a preview as the payment details list it: what the fee adds to the
	 * total, after its discount and with its exclusive tax.
	 *
	 * @param \stdClass $lineItem A line of the previewed invoice, made from prepareRecoveryFeeInvoiceItem().
	 * @return object{type: 'fee-recovery', subType: 'fee-recovery', id: string, displayName: string, currency: string, amount: int, previewed: true}
	 */
	protected function createRecoveryFeeDisplayItem( $lineItem ) {
		$amount = $lineItem->amount;
		foreach ( $lineItem->discount_amounts as $discountAmount ) {
			$amount -= $discountAmount->amount;
		}
		foreach ( $lineItem->taxes as $taxItem ) {
			if ( ! Pricing::isTaxAmountInclusive( $taxItem ) ) {
				$amount += $taxItem->amount;
			}
		}

		$displayItem = new \StdClass;
		$displayItem->type = Main::LINE_ITEM_TYPE_FEE_RECOVERY;
		$displayItem->subType = Main::LINE_ITEM_TYPE_FEE_RECOVERY;
		$displayItem->id = $lineItem->metadata->priceId;
		$displayItem->displayName = __( 'Transaction Fee', 'wp-full-stripe-free' );
		$displayItem->currency = $lineItem->currency;
		$displayItem->amount = $amount;
		// The browser adds its own fee line where the preview has none.
		$displayItem->previewed = true;

		return $displayItem;
	}

	/**
	 * @param \stdClass $lineItem
	 * @return bool
	 */
	protected function isRecoveryFeeLineItem( $lineItem ) {
		return isset( $lineItem->metadata->type ) && Main::LINE_ITEM_TYPE_FEE_RECOVERY === $lineItem->metadata->type;
	}

	/**
	 * @param $form
	 * @param $pricingData
	 * @return array|mixed
	 */
	public static function getApplicableInlineTaxRatesStatic( $form, $pricingData ) {
		$taxRates = json_decode( $form->vatRates );

		$applicableTaxRates = [];
		if ( $form->vatRateType === Main::FIELD_VALUE_TAX_RATE_FIXED ) {
			$applicableTaxRates = $taxRates;
		} else if ( $form->vatRateType === Main::FIELD_VALUE_TAX_RATE_DYNAMIC ) {
			$applicableTaxRates = PriceCalculator::filterApplicableTaxRatesStatic(
				$pricingData->country,
				$pricingData->state,
				$taxRates,
				$pricingData->taxId
			);
		}

		return $applicableTaxRates;
	}

	/**
	 * @param $country
	 * @param $state
	 * @param $taxRates
	 * @param $taxId
	 *
	 * @return array
	 */
	public static function filterApplicableTaxRatesStatic( $country, $state, $taxRates, $taxId ) {
		$applicableTaxRates = [];
		if ( ! empty( $country ) ) {
			if ( $country === Main::COUNTRY_CODE_UNITED_STATES && ! empty( $state ) ) {
				foreach ( $taxRates as $taxRate ) {
					if (
						$taxRate->country === $country &&
						$taxRate->state === $state
					) {
						array_push( $applicableTaxRates, $taxRate );
					}
				}
			} else if ( $country !== Main::COUNTRY_CODE_UNITED_STATES ) {
				foreach ( $taxRates as $taxRate ) {
					if ( $taxRate->country === $country ) {
						array_push( $applicableTaxRates, $taxRate );
					}
				}
			}
		}

		$params = [ 
			'country' => $country,
			'state' => $state,
			'taxId' => $taxId
		];
		$filteredTaxRates = apply_filters( 'fullstripe_determine_tax_rates', $applicableTaxRates, $params );

		return $filteredTaxRates;
	}

	protected function extractCurrencyFromProducts( $products ) {
		$result = Main::CURRENCY_USD;

		if ( count( $products ) > 0 ) {
			$result = ( $products[0] )->currency;
		}

		return $result;
	}

	/**
	 * @param \StdClass $form
	 * @return mixed
	 */
	protected function extractCurrencyFromForm( $form ) {
		return $form->currency;
	}

	protected function generateTaxIdStripeErrorMessage() {
		$taxIdType = $this->pricingData->taxIdType ?? '';

		return "Invalid value for {$taxIdType}.";
	}

	/**
	 * @param \StdClass $product A product of the form.
	 * @return string The key of the product's pricing.
	 */
	protected abstract function getProductPriceId( $product );

	/**
	 * @param \StdClass $product A product of the form.
	 * @param \StdClass $form
	 * @return int The number of invoice lines the product adds to a preview.
	 */
	protected function countPreviewLines( $product, $form ) {
		return $this->addsRecoveryFeeLine( $form ) ? 2 : 1;
	}

	/**
	 * Split products into buckets whose previews return all their lines.
	 *
	 * @param array<int, \StdClass> $products
	 * @param \StdClass $form
	 * @return array<int, array<int, \StdClass>>
	 */
	protected function splitByPreviewLineLimit( $products, $form ) {
		$buckets = [];
		$bucket = [];
		$lineCount = 0;
		foreach ( $products as $product ) {
			$productLineCount = $this->countPreviewLines( $product, $form );
			if ( count( $bucket ) > 0 && $lineCount + $productLineCount > self::PREVIEW_LINE_LIMIT ) {
				$buckets[] = $bucket;
				$bucket = [];
				$lineCount = 0;
			}
			$bucket[] = $product;
			$lineCount += $productLineCount;
		}
		if ( count( $bucket ) > 0 ) {
			$buckets[] = $bucket;
		}

		return $buckets;
	}

	/**
	 * @param array<int, \StdClass> $products
	 * @return array<int, array<int, \StdClass>> A bucket for each product.
	 */
	protected function splitIntoSingleProducts( $products ) {
		return array_map(
			function ( $product ) {
				return [ $product ];
			},
			$products
		);
	}

	/**
	 * @param \StdClass $form
	 * @return bool Whether Stripe adds tax to the form's previews.
	 */
	protected function appliesTax( $form ) {
		return $this->pricingData->stripeTax || ! empty( $this->getApplicableTaxRates( $form ) );
	}

	/**
	 * Stripe rounds the tax, and the discount of a coupon on the invoice, over all lines of a preview, so a
	 * product in a shared preview can be a cent off what it is charged; previewed alone, it matches. An
	 * amount-off coupon on the invoice is shared out over every product of a preview, so it always previews
	 * each product alone.
	 *
	 * @param int $productCount
	 * @param \StdClass $form
	 * @param bool $couponOnInvoice Whether the previews put the coupon on the invoice instead of on each item.
	 * @return bool
	 */
	protected function previewsEachProductAlone( $productCount, $form, $couponOnInvoice ) {
		$hasInvoiceCoupon = $couponOnInvoice && ! empty( $this->pricingData->couponCode );
		if ( $hasInvoiceCoupon && ! empty( $this->pricingData->coupon->amount_off ) ) {
			return true;
		}

		return $productCount <= self::SINGLE_PREVIEW_LIMIT && ( $hasInvoiceCoupon || $this->appliesTax( $form ) );
	}

	protected function getProductPricesFromStripe( $form, $taxRates ) {
		$productPricing = [];

		$productBuckets = $this->getProductBuckets( $form );

		foreach ( $productBuckets as $productBucket ) {
			$bucketPricing = $this->previewProductPrices( $productBucket, $taxRates );

			$unpricedProducts = $this->findUnpricedProducts( $productBucket, $bucketPricing );
			if ( count( $productBucket ) > 1 ) {
				foreach ( $unpricedProducts as $unpricedProduct ) {
					$bucketPricing = array_merge( $bucketPricing, $this->previewProductPrices( [ $unpricedProduct ], $taxRates ) );
				}
				$unpricedProducts = $this->findUnpricedProducts( $productBucket, $bucketPricing );
			}

			// A product shown without its price would read as free.
			if ( count( $unpricedProducts ) > 0 ) {
				$this->logger->error(
					__FUNCTION__,
					'Stripe returned no price for ' . implode( ', ', array_map( [ $this, 'getProductPriceId' ], $unpricedProducts ) )
				);

				throw new \Exception( __( 'The prices of this form cannot be calculated. Please try again later.', 'wp-full-stripe-free' ) );
			}

			$productPricing = array_merge( $productPricing, $bucketPricing );
		}

		return $productPricing;
	}

	/**
	 * @param array<int, \StdClass> $productBucket
	 * @param array<int, \StdClass> $taxRates
	 * @return array<string, array<int, \StdClass>> The pricing of the products Stripe returned lines for.
	 * @throws InvalidTaxIdException
	 */
	private function previewProductPrices( $productBucket, $taxRates ) {
		try {
			$invoiceParams = $this->prepareStripeInvoiceParams(
				$this->pricingData,
				$productBucket,
				Pricing::extractTaxRateIdsStatic( $taxRates )
			);

			// We use this to get the actual prices for each of the prodcucts
			// including tax etc. An upcoming invoice is a "test" invoice and won't get saved
			// but we get all the information we need to display the price details
			// It wouldn't be enough to get get the price objects as they wouldn't have the tax
			// calculation
			$invoice = $this->stripe->getUpcomingInvoice( $invoiceParams );
		} catch (\Exception $ex) {
			if ( $ex->getMessage() === $this->generateTaxIdStripeErrorMessage() ) {
				throw new InvalidTaxIdException( $ex->getMessage() );
			} else {
				throw $ex;
			}
		}

		return $this->extractPaymentDetailsFromInvoiceLineItems( $invoice->lines->data, $taxRates, $productBucket );
	}

	/**
	 * @param array<int, \StdClass> $products
	 * @param array<string, array<int, \StdClass>> $productPricing
	 * @return array<int, \StdClass> The products without pricing.
	 */
	private function findUnpricedProducts( $products, $productPricing ) {
		$unpricedProducts = [];
		foreach ( $products as $product ) {
			if ( ! array_key_exists( $this->getProductPriceId( $product ), $productPricing ) ) {
				$unpricedProducts[] = $product;
			}
		}

		return $unpricedProducts;
	}

	public function getProductPrices() {
		$form = $this->getFormFromDatabase();
		$taxRates = $this->getApplicableTaxRates( $form );

		return $this->getProductPricesFromStripe( $form, $taxRates );
	}

	/**
	 * Extract the tax rate ID from a Stripe tax item.
	 * Handles both old and new Stripe API formats:
	 * - Old format: $taxItem->tax_rate contains the tax rate ID
	 * - New format: $taxItem->tax_rate_details->tax_rate contains the tax rate ID
	 * 
	 * @param object{
	 *     amount?: int,
	 *     tax_behavior?: string,
	 *     tax_rate_details?: object{tax_rate?: string},
	 *     tax_rate?: string,
	 *     taxability_reason?: string,
	 *     taxable_amount?: int,
	 *     type?: string
	 * } $taxItem
	 * @return string|null The tax rate ID, or null if not found.
	 */
	protected function extractTaxRateId( $taxItem ) {
		// Handle new Stripe API format where tax_rate_details contains the ID
		if ( isset( $taxItem->tax_rate_details ) && isset( $taxItem->tax_rate_details->tax_rate ) ) {
			return $taxItem->tax_rate_details->tax_rate;
		}

		// Handle old Stripe API format where tax_rate is directly available
		if ( isset( $taxItem->tax_rate ) && ! empty( $taxItem->tax_rate ) ) {
			return $taxItem->tax_rate;
		}

		return null;
	}

	/**
	 * @param $taxItem
	 * @return string|null
	 */
	protected function determineTaxLabel( $taxItem ) {
		$params = [ 
			'taxableAmount' => $taxItem->taxable_amount,
			'amount' => $taxItem->amount,
			'inclusive' => Pricing::isTaxAmountInclusive( $taxItem ),
			'taxabilityReason' => $taxItem->taxability_reason,
			'country' => $this->pricingData->country,
			'state' => $this->pricingData->state,
			'postalCode' => $this->pricingData->zip,
			'taxIdType' => $this->pricingData->taxIdType,
			'taxId' => $this->pricingData->taxId,
		];

		$result = __( 'Tax', 'wp-full-stripe-free' );
		try {
			$result = apply_filters( Main::FILTER_NAME_DETERMINE_TAX_LABEL, $result, $params );
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Cannot determine tax label', $ex );
		}

		return $result;
	}
}
