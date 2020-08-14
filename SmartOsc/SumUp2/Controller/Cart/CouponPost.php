<?php

namespace SmartOsc\SumUp2\Controller\Cart;

use Exception;
use Magento\Checkout\Helper\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\SalesRule\Model\CouponFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CouponPost extends \Magento\Checkout\Controller\Cart implements HttpPostActionInterface
{
    /**
     * Sales quote repository
     *
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * Coupon factory
     *
     * @var CouponFactory
     */
    protected $couponFactory;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Session $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param Validator $formKeyValidator
     * @param \Magento\Checkout\Model\Cart $cart
     * @param CouponFactory $couponFactory
     * @param CartRepositoryInterface $quoteRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Session $checkoutSession,
        StoreManagerInterface $storeManager,
        Validator $formKeyValidator,
        \Magento\Checkout\Model\Cart $cart,
        CouponFactory $couponFactory,
        CartRepositoryInterface $quoteRepository
    )
    {
        parent::__construct(
            $context,
            $scopeConfig,
            $checkoutSession,
            $storeManager,
            $formKeyValidator,
            $cart
        );
        $this->couponFactory = $couponFactory;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Initialize coupon
     *
     * @return Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $couponCode = $this->getRequest()->getParam('remove') == 1
            ? ''
            : trim($this->getRequest()->getParam('coupon_code'));
        $cartQuote = $this->cart->getQuote();
        $oldCouponCode = $cartQuote->getCouponCode();
        $arrayOldCouponCode = explode(",", $oldCouponCode);
        $codeLength = strlen($couponCode);
        if (!$codeLength && !strlen($oldCouponCode)) {
            return $this->_goBack();
        }
        $coupon = $this->couponFactory->create()->load($couponCode, 'code');
        $removeCouponValue = trim($this->getRequest()->getParam('removed_coupon'));
        try {
            $isCodeLengthValid = $codeLength && $codeLength <= Cart::COUPON_CODE_MAX_LENGTH;
            $itemsCount = $cartQuote->getItemsCount();
            if ($itemsCount) {
                $cartQuote->getShippingAddress()->setCollectShippingRates(true);
                if ($oldCouponCode) {
                    if ($removeCouponValue != '') {
                        $removedCouponArray = implode(',', array_diff($arrayOldCouponCode, [$removeCouponValue]));
                        $cartQuote->setCouponCode($removedCouponArray);
                    } else {
                        if ($isCodeLengthValid && $coupon->getId()) {
                            if (!in_array($couponCode, $arrayOldCouponCode)) {
                                if ($coupon->getUsageLimit() == null || $coupon->getTimesUsed() < $coupon->getUsageLimit()) {
                                    $newListCoupon = $oldCouponCode . ',' . $couponCode;
                                    $cartQuote->setCouponCode($newListCoupon);
                                }
                            }
                        }
                    }
                } else {
                    if ($isCodeLengthValid && $coupon->getId()) {
                        $cartQuote->setCouponCode($couponCode);
                    }
                }
                $cartQuote->collectTotals();
                $this->quoteRepository->save($cartQuote);
            }

            if ($codeLength) {
                $escaper = $this->_objectManager->get(Escaper::class);
                if (!$itemsCount) {
                    if ($isCodeLengthValid && $coupon->getId()) {
                        $this->_checkoutSession->getQuote()->setCouponCode($couponCode)->save();
                        $this->messageManager->addSuccessMessage(
                            __(
                                'You used coupon code "%1".',
                                $escaper->escapeHtml($couponCode)
                            )
                        );
                    } else {
                        $this->messageManager->addErrorMessage(
                            __(
                                'The coupon code "%1" is not valid .',
                                $escaper->escapeHtml($couponCode)
                            )
                        );
                    }
                } else {
                    $arrayCode = explode(",", $cartQuote->getCouponCode());
                    if ($isCodeLengthValid && $coupon->getId() && in_array($couponCode, $arrayCode)) {
                        if ($coupon->getUsageLimit() != null && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
                            $this->messageManager->addErrorMessage(
                                __(
                                    'The coupon code "%1" exceeded the limits of use.',
                                    $escaper->escapeHtml($couponCode)
                                )
                            );
                        } else {
                            $this->messageManager->addSuccessMessage(
                                __(
                                    'You used coupon code "%1".',
                                    $escaper->escapeHtml($couponCode)
                                )
                            );
                        }
                    } else {
                        $this->messageManager->addErrorMessage(
                            __(
                                'The coupon code "%1" is not valid.',
                                $escaper->escapeHtml($couponCode)
                            )
                        );
                    }

                }
            } else {
                $this->messageManager->addSuccessMessage(__('You canceled the coupon code.'));
            }
        } catch
        (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('We cannot apply the coupon code.'));
            $this->_objectManager->get(LoggerInterface::class)->critical($e);
        }

        return $this->_goBack();
    }
}
