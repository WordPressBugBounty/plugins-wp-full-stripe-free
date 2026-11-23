<?php

namespace ThemeIsle\WPFullPay\CustomerPortal;

class CustomerPortalAccount
{
    protected $stripeCustomerId;
    protected $name;
    protected $createdAt;
    protected $createdAtLabel;
    protected $numberOfSubscriptions;

    /**
     * @return mixed
     */
    public function getStripeCustomerId()
    {
        return $this->stripeCustomerId;
    }

    /**
     * @param mixed $stripeCustomerId
     */
    public function setStripeCustomerId($stripeCustomerId)
    {
        $this->stripeCustomerId = $stripeCustomerId;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return mixed
     */
    public function getNumberOfSubscriptions()
    {
        return $this->numberOfSubscriptions;
    }

    /**
     * @param mixed $numberOfSubscriptions
     */
    public function setNumberOfSubscriptions($numberOfSubscriptions)
    {
        $this->numberOfSubscriptions = $numberOfSubscriptions;
    }

    /**
     * @return mixed
     */
    public function getCreatedAtLabel()
    {
        return $this->createdAtLabel;
    }

    /**
     * @param mixed $createdAtLabel
     */
    public function setCreatedAtLabel($createdAtLabel)
    {
        $this->createdAtLabel = $createdAtLabel;
    }
}
