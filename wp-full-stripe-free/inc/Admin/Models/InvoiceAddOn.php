<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\InvoiceViewConstants;

trait InvoiceAddOn {
	protected $generateInvoice;

	protected function bindInvoiceParameters( $postData ) {
		$this->generateInvoice = $this->getSanitizedArrayParam( $postData, InvoiceViewConstants::FIELD_FORM_GENERATE_INVOICE, 0 );
	}

	protected function getInvoiceData() {
		return [
			'generateInvoice' => $this->generateInvoice,
		];
	}

	/**
	 * @return mixed
	 */
	public function getGenerateInvoice() {
		return $this->generateInvoice;
	}
}
