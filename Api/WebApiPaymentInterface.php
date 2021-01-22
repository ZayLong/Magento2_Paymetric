<?php

namespace Tng\Paymetric\Api;

/**
 * Interface for managing card approval for paymetric
 * @api
 */
interface WebApiPaymentInterface
{
	 /**
     * Returns authorization determination to user
     *
     * 
     * @param mixed $cartId
     * @param mixed $paymentMethod
     * @param float $amount
     * @return string
     */
	public function webAuthorize(
		$cartId,
		$paymentMethod,
		$amount = 0
	);

     
     /**
     * Returns tokenized card 
     *
     * @return $cardInfo
     * @throws \Magento\Framework\Validator\Exception
     */
     public function getCreditCardInfo();
}
