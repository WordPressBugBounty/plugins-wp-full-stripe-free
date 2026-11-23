<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\DonationFormViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\InvoiceViewConstants;

abstract class DonationFormModel extends FormModel implements InvoiceViewConstants {
	use InvoiceAddOn;

	protected $transactionDescription;
	protected $currency;
	protected $donationAmounts;
	protected $donationFrequencyOnetime;
	protected $donationFrequencyDaily;
	protected $donationFrequencyWeekly;
	protected $donationFrequencyMonthly;
	protected $donationFrequencyAnnual;
	protected $allowCustomDonationAmount;
	protected $minimumDonationAmount;
	protected $showDonationGoal;
	protected $donationGoal;

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->transactionDescription = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_TRANSACTION_DESCRIPTION );

		$this->currency = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_CURRENCY );

		$this->minimumDonationAmount = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_MINIMUM_DONATION_AMOUNT_HIDDEN );
		// The form editor posts a JSON list; anything else counts as no suggested amounts.
		$donationAmounts = isset( $postData[ DonationFormViewConstants::FIELD_FORM_DONATION_AMOUNTS ] ) ? $this->getJSONDecodedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_DONATION_AMOUNTS ) : null;
		$this->donationAmounts = is_array( $donationAmounts ) ? $donationAmounts : [];
		$this->allowCustomDonationAmount = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_ALLOW_CUSTOM_DONATION_AMOUNT, 0 );

		$this->donationFrequencyOnetime = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_DONATION_FREQUENCY_ONETIME, 0 );
		$this->donationFrequencyDaily = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_DONATION_FREQUENCY_DAILY, 0 );
		$this->donationFrequencyWeekly = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_DONATION_FREQUENCY_WEEKLY, 0 );
		$this->donationFrequencyMonthly = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_DONATION_FREQUENCY_MONTHLY, 0 );
		$this->donationFrequencyAnnual = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_DONATION_FREQUENCY_ANNUAL, 0 );
		$this->showDonationGoal   = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_SHOW_DONATION_GOAL, 0 );
		$this->donationGoal       = $this->getSanitizedArrayParam( $postData, DonationFormViewConstants::FIELD_FORM_DONATION_GOAL_HIDDEN );

		$this->bindInvoiceParameters( $postData );

		return $bindingResult;
	}

	public function getData() {

		$parentData = parent::getData();

		$data = [
			'stripeDescription' => $this->transactionDescription,
			'currency' => $this->currency,
			'minimumDonationAmount' => $this->minimumDonationAmount,
			'donationAmounts' => json_encode( $this->donationAmounts ),
			'allowCustomDonationAmount' => $this->allowCustomDonationAmount,
			'allowOneTimeDonation' => $this->donationFrequencyOnetime,
			'allowDailyRecurring' => $this->donationFrequencyDaily,
			'allowWeeklyRecurring' => $this->donationFrequencyWeekly,
			'allowMonthlyRecurring' => $this->donationFrequencyMonthly,
			'allowAnnualRecurring' => $this->donationFrequencyAnnual,
			'showDonationGoal' => $this->showDonationGoal,
			'donationGoal' => $this->donationGoal,
		];

		return array_merge( $data,
			$parentData,
			$this->getInvoiceData()
		);
	}

	/**
	 * @return mixed
	 */
	public function getTransactionDescription() {
		return $this->transactionDescription;
	}

	/**
	 * @return mixed
	 */
	public function getCurrency() {
		return $this->currency;
	}

	/**
	 * @return mixed
	 */
	public function getDonationAmounts() {
		return $this->donationAmounts;
	}

	/**
	 * @return mixed
	 */
	public function getDonationFrequencyOnetime() {
		return $this->donationFrequencyOnetime;
	}

	/**
	 * @return mixed
	 */
	public function getDonationFrequencyDaily() {
		return $this->donationFrequencyDaily;
	}

	/**
	 * @return mixed
	 */
	public function getDonationFrequencyWeekly() {
		return $this->donationFrequencyWeekly;
	}

	/**
	 * @return mixed
	 */
	public function getDonationFrequencyMonthly() {
		return $this->donationFrequencyMonthly;
	}

	/**
	 * @return mixed
	 */
	public function getDonationFrequencyAnnual() {
		return $this->donationFrequencyAnnual;
	}

	/**
	 * @return mixed
	 */
	public function getAllowCustomDonationAmount() {
		return $this->allowCustomDonationAmount;
	}

	/**
	 * @return mixed
	 */
	public function getMinimumDonationAmount() {
		return $this->minimumDonationAmount;
	}

	/**
	 * @return mixed
	 */
	public function getShowDonationGoal() {
		return $this->showDonationGoal;
	}

	/**
	 * @return mixed
	 */
	public function getDonationGoal() {
		return $this->donationGoal;
	}
}
