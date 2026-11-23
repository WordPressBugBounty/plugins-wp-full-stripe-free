<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;

class DonationFormView extends FormView implements DonationFormViewConstants, InvoiceViewConstants {
    use FormViewInvoiceAddOn;

    /** @var Control */
    protected $transactionDescription;
    /** @var Control */
    protected $currency;
    /** @var Control */
    protected $donationAmounts;
    /** @var Control */
    protected $donationFrequencies;
    /** @var Control */
    protected $donationFrequencyOnetime;
    /** @var Control */
    protected $donationFrequencyDaily;
    /** @var Control */
    protected $donationFrequencyWeekly;
    /** @var Control */
    protected $donationFrequencyMonthly;
    /** @var Control */
    protected $donationFrequencyAnnual;
    /** @var Control */
    protected $addAmountButton;
    /** @var Control */
    protected $allowCustomDonationAmount;
    /** @var Control */
    protected $minimumDonationAmount;
    /** @var Control */
    protected $minimumDonationAmountHidden;
    /** @var Control */
    protected $showDonationGoal;
    /** @var Control */
    protected $donationGoal;
    /** @var Control */
    protected $donationGoalHidden;

    public function __construct() {
        parent::__construct();
        $this->initFields();
    }

    protected function createDonationFrequencies() {
        $this->donationFrequencies = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DONATION_FREQUENCIES, null, null,
            __( 'Donation frequencies', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;

        $this->donationFrequencyOnetime = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DONATION_FREQUENCY_ONETIME, null, null,
            /* translators: The 'one-time' donation frequency label */
            __( 'One-time', 'wp-full-stripe-free' ), $optionIndex );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
            'value'     => 1
        ];
        $this->donationFrequencyOnetime->setAttributes( $optionAttributes );
        $optionIndex++;

        $this->donationFrequencyDaily = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DONATION_FREQUENCY_DAILY, null, null,
            /* translators: The 'daily' donation frequency label */
            __( 'Daily', 'wp-full-stripe-free' ), $optionIndex );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
            'value'     => 1
        ];
        $this->donationFrequencyDaily->setAttributes( $optionAttributes );
        $optionIndex++;

        $this->donationFrequencyWeekly = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DONATION_FREQUENCY_WEEKLY, null, null,
            /* translators: The 'weekly' donation frequency label */
            __( 'Weekly', 'wp-full-stripe-free' ), $optionIndex );
        $this->donationFrequencyWeekly->setValue( '1' );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
            'value'     => 1
        ];
        $this->donationFrequencyWeekly->setAttributes( $optionAttributes );
        $optionIndex++;

        $this->donationFrequencyMonthly = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DONATION_FREQUENCY_MONTHLY, null, null,
            /* translators: The 'monthly' donation frequency label */
            __( 'Monthly', 'wp-full-stripe-free' ), $optionIndex );
        $this->donationFrequencyMonthly->setValue( '1' );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
            'value'     => 1
        ];
        $this->donationFrequencyMonthly->setAttributes( $optionAttributes );
        $optionIndex++;

        $this->donationFrequencyAnnual = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DONATION_FREQUENCY_ANNUAL, null, null,
            /* translators: The 'annual' donation frequency label */
            __( 'Annual', 'wp-full-stripe-free' ), $optionIndex );
        $this->donationFrequencyAnnual->setValue( '1' );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
            'value'     => 1
        ];
        $this->donationFrequencyAnnual->setAttributes( $optionAttributes );
    }

    protected function initFields() {
        $this->transactionDescription = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_TRANSACTION_DESCRIPTION, null, null,
            __( 'Transaction description', 'wp-full-stripe-free' ), null );
        $this->transactionDescription->setAttributes( [
            'class'     => 'wpfs-form-control js-token-target-transaction-description js-position-tracking-transaction-description',
            'rows'      => '3'
        ]);

        $this->currency = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CURRENCY, null, null,
            __( 'Currency', 'wp-full-stripe-free' ), null );
        $this->currency->setAttributes( [
            'class'     => 'js-combobox'
        ]);

        $this->donationAmounts = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DONATION_AMOUNTS, null, null,
            __( 'Suggested donation amounts', 'wp-full-stripe-free' ), null );
        $this->donationAmounts->setAttributes( [
            'type'      => 'hidden',
        ]);

        $this->addAmountButton = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_ADD_AMOUNT, null, null,
            /* translators: Label for the button which opens the "Add amount" dialog */
            __( 'Add suggested donation amount', 'wp-full-stripe-free' ), null );

        $this->allowCustomDonationAmount = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_ALLOW_CUSTOM_DONATION_AMOUNT, null, null,
            __( 'Allow custom amount to be entered', 'wp-full-stripe-free' ), null );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
            'value'     => 1
        ];
        $this->allowCustomDonationAmount->setAttributes( $optionAttributes );

        $this->minimumDonationAmount = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_MINIMUM_DONATION_AMOUNT, null, null,
            /* translators: Form field label for the minimum donation amount */
            __( 'Minimum donation amount', 'wp-full-stripe-free' ), null );

        $this->minimumDonationAmountHidden = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_MINIMUM_DONATION_AMOUNT_HIDDEN, null, null,
            __( 'One-time products', 'wp-full-stripe-free' ), null );
        $this->minimumDonationAmountHidden->setAttributes( [
            'type'      => 'hidden',
        ]);

        $this->showDonationGoal = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_SHOW_DONATION_GOAL, null, null,
            __( 'Show donation goal', 'wp-full-stripe-free' ), null );

        $this->showDonationGoal->setAttributes( [
            'type'      => 'checkbox',
            'value'     => 1,
            'class'     => 'wpfs-form-check-input'
        ]);

        $this->donationGoal = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DONATION_GOAL, null, null,
            __( 'Donation goal', 'wp-full-stripe-free' ), null );
        $this->donationGoal->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-form-control'
        ]);

        $this->donationGoalHidden = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DONATION_GOAL_HIDDEN, null, null,
            __( 'Donation goal', 'wp-full-stripe-free' ), null );
        $this->donationGoalHidden->setAttributes( [
            'type'      => 'hidden',
        ]);

        $this->initGenerateInvoice();
        $this->createDonationFrequencies();
    }

    public static function getFields() {
        $addOns = new FormViewAddOns();

        $fields = [
            self::FIELD_FORM_TRANSACTION_DESCRIPTION        => ControlUtils::input( self::FIELD_FORM_TRANSACTION_DESCRIPTION ),
            self::FIELD_FORM_CURRENCY                       => ControlUtils::input( self::FIELD_FORM_CURRENCY ),
            self::FIELD_FORM_MINIMUM_DONATION_AMOUNT        => ControlUtils::inputGroup( self::FIELD_FORM_MINIMUM_DONATION_AMOUNT ),
            self::FIELD_FORM_ALLOW_CUSTOM_DONATION_AMOUNT   => ControlUtils::checkbox( self::FIELD_FORM_ALLOW_CUSTOM_DONATION_AMOUNT ),
            self::FIELD_FORM_DONATION_FREQUENCIES           => ControlUtils::checklist( self::FIELD_FORM_DONATION_FREQUENCIES ),
            self::FIELD_FORM_DONATION_FREQUENCY_ONETIME     => ControlUtils::checkbox( self::FIELD_FORM_DONATION_FREQUENCY_ONETIME ),
            self::FIELD_FORM_DONATION_FREQUENCY_DAILY       => ControlUtils::checkbox( self::FIELD_FORM_DONATION_FREQUENCY_DAILY ),
            self::FIELD_FORM_DONATION_FREQUENCY_WEEKLY      => ControlUtils::checkbox( self::FIELD_FORM_DONATION_FREQUENCY_WEEKLY ),
            self::FIELD_FORM_DONATION_FREQUENCY_MONTHLY     => ControlUtils::checkbox( self::FIELD_FORM_DONATION_FREQUENCY_MONTHLY ),
            self::FIELD_FORM_DONATION_FREQUENCY_ANNUAL      => ControlUtils::checkbox( self::FIELD_FORM_DONATION_FREQUENCY_ANNUAL ),
            self::FIELD_FORM_SHOW_DONATION_GOAL             => ControlUtils::checkbox( self::FIELD_FORM_SHOW_DONATION_GOAL, 0 ),
            self::FIELD_FORM_DONATION_GOAL                  => ControlUtils::input( self::FIELD_FORM_DONATION_GOAL )
        ];

        return array_merge( $fields,
            parent::getFields(),
            $addOns->getInvoiceFields()
        );
    }

    /**
     * @return Control
     */
    public function transactionDescription(): Control {
        return $this->transactionDescription;
    }

    /**
     * @return Control
     */
    public function currency(): Control {
        return $this->currency;
    }

    /**
     * @return Control
     */
    public function donationAmounts(): Control {
        return $this->donationAmounts;
    }

    /**
     * @return Control
     */
    public function donationFrequencies(): Control {
        return $this->donationFrequencies;
    }

    /**
     * @return Control
     */
    public function donationFrequencyOnetime(): Control {
        return $this->donationFrequencyOnetime;
    }

    /**
     * @return Control
     */
    public function donationFrequencyDaily(): Control {
        return $this->donationFrequencyDaily;
    }

    /**
     * @return Control
     */
    public function donationFrequencyWeekly(): Control {
        return $this->donationFrequencyWeekly;
    }

    /**
     * @return Control
     */
    public function donationFrequencyMonthly(): Control {
        return $this->donationFrequencyMonthly;
    }

    /**
     * @return Control
     */
    public function donationFrequencyAnnual(): Control {
        return $this->donationFrequencyAnnual;
    }

    /**
     * @return Control
     */
    public function addAmountButton(): Control {
        return $this->addAmountButton;
    }

    /**
     * @return Control
     */
    public function allowCustomDonationAmount(): Control {
        return $this->allowCustomDonationAmount;
    }

    /**
     * @return Control
     */
    public function minimumDonationAmount(): Control {
        return $this->minimumDonationAmount;
    }

    /**
     * @return Control
     */
    public function minimumDonationAmountHidden(): Control {
        return $this->minimumDonationAmountHidden;
    }

    /**
     * @return Control
     */
    public function showDonationGoal(): Control {
        return $this->showDonationGoal;
    }

    /**
     * @return Control
     */
    public function donationGoal(): Control {
        return $this->donationGoal;
    }

    /**
     * @return Control
     */
    public function donationGoalHidden(): Control {
        return $this->donationGoalHidden;
    }
}
