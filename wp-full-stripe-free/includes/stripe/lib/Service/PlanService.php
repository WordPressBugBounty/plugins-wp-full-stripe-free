<?php

// File generated from our OpenAPI spec

namespace StripeWPFS\Service;

class PlanService extends \StripeWPFS\Service\AbstractService
{
    /**
     * Returns a list of your plans.
     *
     * @param null|array $params
     * @param null|array|\StripeWPFS\Util\RequestOptions $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\Collection<\StripeWPFS\Plan>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/plans', $params, $opts);
    }

    /**
     * You can now model subscriptions more flexibly using the <a href="#prices">Prices
     * API</a>. It replaces the Plans API and is backwards compatible to simplify your
     * migration.
     *
     * @param null|array $params
     * @param null|array|\StripeWPFS\Util\RequestOptions $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\Plan
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/plans', $params, $opts);
    }

    /**
     * Deleting plans means new subscribers can’t be added. Existing subscribers aren’t
     * affected.
     *
     * @param string $id
     * @param null|array $params
     * @param null|array|\StripeWPFS\Util\RequestOptions $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\Plan
     */
    public function delete($id, $params = null, $opts = null)
    {
        return $this->request('delete', $this->buildPath('/v1/plans/%s', $id), $params, $opts);
    }

    /**
     * Retrieves the plan with the given ID.
     *
     * @param string $id
     * @param null|array $params
     * @param null|array|\StripeWPFS\Util\RequestOptions $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\Plan
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/plans/%s', $id), $params, $opts);
    }

    /**
     * Updates the specified plan by setting the values of the parameters passed. Any
     * parameters not provided are left unchanged. By design, you cannot change a
     * plan’s ID, amount, currency, or billing cycle.
     *
     * @param string $id
     * @param null|array $params
     * @param null|array|\StripeWPFS\Util\RequestOptions $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\Plan
     */
    public function update($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/plans/%s', $id), $params, $opts);
    }
}
