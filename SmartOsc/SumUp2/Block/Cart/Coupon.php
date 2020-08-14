<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace SmartOsc\SumUp2\Block\Cart;
use Magento\Checkout\Block\Cart\AbstractCart;

/**
 * Block with apply-coupon form.
 *
 * @api
 * @since 100.0.2
 */
class Coupon extends AbstractCart
{
    /**
     * @return array
     */
    public function getCouponArray()
    {
        return array_filter(explode(",", $this->getQuote()->getCouponCode()));
    }

}
