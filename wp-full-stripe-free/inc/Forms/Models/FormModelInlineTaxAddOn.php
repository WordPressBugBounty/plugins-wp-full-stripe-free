<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Forms\Views\FormViewInlineTaxAddOnConstants;

trait FormModelInlineTaxAddOn
{
	protected $buyingAsBusiness;
	protected $businessName;
	protected $taxIdType;
	protected $taxId;
	protected $taxCountry;
	protected $taxState;
	protected $taxZip;

	protected function bindTaxDataByArray($postData)
	{
		$this->buyingAsBusiness = $this->getSanitizedArrayParam($postData, FormViewInlineTaxAddOnConstants::FIELD_BUYING_AS_BUSINESS, 0);
		$this->businessName = $this->getSanitizedArrayParam($postData, FormViewInlineTaxAddOnConstants::FIELD_BUSINESS_NAME);
		$this->taxIdType = $this->getSanitizedArrayParam($postData, FormViewInlineTaxAddOnConstants::FIELD_TAX_ID_TYPE);
		$this->taxId = $this->getSanitizedArrayParam($postData, FormViewInlineTaxAddOnConstants::FIELD_TAX_ID);
		$this->taxZip = $this->getSanitizedArrayParam($postData, FormViewInlineTaxAddOnConstants::FIELD_TAX_ZIP);

		$this->taxCountry = $this->getSanitizedArrayParam($postData, FormViewInlineTaxAddOnConstants::FIELD_TAX_COUNTRY);
		if (empty($this->taxCountry)) {
			$this->taxCountry = $this->getBillingAddressCountry();
			$this->taxState = $this->getBillingAddressState();
		} else {
			$this->taxState = $this->getSanitizedArrayParam($postData, FormViewInlineTaxAddOnConstants::FIELD_TAX_STATE);
		}
	}

	/**
	 * @return mixed
	 */
	public function getBuyingAsBusiness()
	{
		return $this->buyingAsBusiness;
	}

	/**
	 * @return mixed
	 */
	public function getBusinessName()
	{
		return $this->businessName;
	}

	/**
	 * @param mixed $businessName
	 */
	public function setBusinessName($businessName)
	{
		$this->businessName = $businessName;
	}

	/**
	 * @return mixed
	 */
	public function getTaxIdType()
	{
		return $this->taxIdType;
	}

	/**
	 * Set the tax ID type.
	 *
	 * @param string|null $taxIdType
	 * @return void
	 */
	public function setTaxIdType(?string $taxIdType): void
	{
		$this->taxIdType = $taxIdType;
	}

	/**
	 * @return mixed
	 */
	public function getTaxId()
	{
		return $this->taxId;
	}

	/**
	 * Set the tax ID.
	 *
	 * @param string|null $taxId
	 * @return void
	 */
	public function setTaxId(?string $taxId): void
	{
		$this->taxId = $taxId;
	}

	/**
	 * @return mixed
	 */
	public function getTaxCountry()
	{
		return $this->taxCountry;
	}

	/**
	 * @return mixed
	 */
	public function getTaxState()
	{
		return $this->taxState;
	}

	/**
	 * @return mixed
	 */
	public function getTaxZip()
	{
		return $this->taxZip;
	}

	/**
	 * Sets the location the payment is taxed at.
	 *
	 * @param string|null $country Two-letter country code.
	 * @param string|null $state
	 * @param string|null $zip
	 * @return void
	 */
	public function setTaxLocation(?string $country, ?string $state, ?string $zip): void
	{
		$this->taxCountry = $country;
		$this->taxState = $state;
		$this->taxZip = $zip;
	}

}
