<?php
	namespace Tng\Paymetric\Observer;


	use Magento\Framework\DataObject;
    use Magento\Framework\Encryption\EncryptorInterface;
    use Magento\Framework\Event\Observer;
    use Magento\Framework\Exception\LocalizedException;
    use Magento\Payment\Observer\AbstractDataAssignObserver;
    use Magento\Quote\Api\Data\PaymentInterface;
    use Magento\Payment\Model\InfoInterface;

    class SaveCardObserver2 extends AbstractDataAssignObserver
    {
        /**
         * @param Observer $observer
         * @throws LocalizedException
         */
        public function execute(Observer $observer)
        {

			$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/cc_debug.log');
			$logger = new \Zend\Log\Logger();
			$logger->addWriter($writer);
			$logger->info('Save Card Observer2');

        }
    }