<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace SmartOsc\SumUp2\Block\Adminhtml\Order\Create\Coupons;

class Form extends \Magento\Sales\Block\Adminhtml\Order\Create\Coupons\Form
{
    /**
    * Get coupon code
    *
    * @return string
    */
    public function getCouponCode()
    {
        return $this->getParentBlock()->getQuote()->getCouponCode();
    }
}
