<?php
/**
 * Paymetric payment method model
 *
 * @category    Tng
 * @package     Tng_Paymetric
 * @author      Daniel McClure
 * @copyright   Tng (http://tngworldwide.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tng\Paymetric\Model;

use Magento\Framework\DataObject;
use Magento\Payment\Model\MethodInterface;
use	Magento\Quote\Api\Data\PaymentInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Insync\RealTimePricing\Helper\Data;

class WebApiPayment extends \Magento\Payment\Model\Method\AbstractMethod implements \Tng\Paymetric\Api\WebApiPaymentInterface
{


	const CODE = 'tng_paymetric';

    protected $_code = self::CODE;

    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
	protected $_saveCard					= false;

    protected $_paymetricCredentials;

    protected $_countryFactory;
	protected $_paymentMethod;

    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_supportedCurrencyCodes = array('USD');

    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];

    public function __construct(
        \Magento\Directory\Model\CountryFactory $countryFactory,
		\Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []

    ) {
		$this->_countryFactory = $countryFactory;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

	 /**
     * Returns authorization determination to user
     *
     * @api
     * @param mixed $cartId users cart id.
     * @param mixed $paymentMethod
     * @param float $amount cart total charge
     * @return string
     */
	public function webAuthorize(
		$cartId,
		$paymentMethod,
		$amount = 0)
    {

		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/cc_web_debug.log');
		$logger = new \Zend\Log\Logger();
		$logger->addWriter($writer); //Use $logger->info() to log items to cc_debug.log

		$logger->info('===========================================');
		$logger->info($this->print_detail($paymentMethod));
		$logger->info('Amount: ' . $amount);
		$logger->info('CartId: ' . $cartId);

		$payment = (object)$paymentMethod;
		$payment->additional_data = (object)$payment->additional_data;

		//$logger->info('LOG PAYMENT METHOD INFO FOR CART IDD: ' . $cartId);

		//$paymentJSON = json_decode($paymentMethod);
		//$logger->info('payment method data as string: ' . $payment->additional_data->cc_exp_month;
		//$logger->info('payment method data as print_r: ' . print_r($paymentMethod));
		//$logger->info('payment method data as var_dump: ' . var_dump($paymentMethod));
		//$logger->info('Payment Object');
		//$logger->info($this->print_detail($payment));
		//$logger->info($this->print_detail($amount));

		/** @var \Magento\Sales\Model\Order $order */
       // $order = $payment->getOrder();

        /** @var \Magento\Sales\Model\Order\Address $billing */
        //$billing = $order->getBillingAddress();

		$user = $this->getConfigData('user');
		$password = $this->getConfigData('password');
		$endpoint_url = $this->getConfigData('url');

		try{
			$logger->info('Constructing XiPaySoapClient');
			$XiPay = new \Tng\Paymetric\Model\XiPaySoapClient($endpoint_url,"paymetric\\".$user,$password);
			//$logger->info("Password is: " . $password);
			//$logger->info("URL is: " . $endpoint_url);
		}catch(\Exception $e){
			//$logger->info(print_r('XiPay construction error. '.$e->getMessage()));

			//$this->debugData(['request' => $t, 'exception' => $e->getMessage()]);
            throw new \Magento\Framework\Validator\Exception(__('XiPay construction error. '.$e->getMessage()));
		}

		//Setup trace if needed
		$XiPay->Trace = true;
		//$XiPay->TraceFile = "trace.txt"; //trace to a file. If TraceFile not set, trace to console!

		/**
		 * Build transaction structure
		 * See Web Service integration guide or WS WSDL for all properties to set.
		 */
		$amount = 0.01;
		$transaction = new \Tng\Paymetric\Model\Transaction();
		$transaction->Amount = $amount;
		$transaction->CardExpirationDate = sprintf('%02d',$payment->additional_data->cc_exp_month).'/'.$payment->additional_data->cc_exp_year;
		$transaction->CardNumber = $payment->additional_data->cc_number;
		$transaction->CardPresent = 0;
		$transaction->CurrencyKey = "USD";
		$transaction->MerchantID = "TNG_WORLDWIDE";
		$transaction->Message = "00 Approved"; //honestly don't know if this should be hardcoded like this or not....
		$transaction->CardHolderName = "Test Name";
		$transaction->CardHolderName1 = "Test Name";
		$transaction->SettlementAmount = $amount;
		$transaction->CardDataSource = "E";


		// Check if purchase order number was entered
		if($payment->additional_data->extOrderId){
			$transaction->PONumber = $payment->additional_data->extOrderId;
		}

		try{
			//$logger->info('Authorizing transaction');
			//$logger->info('Transaction Object');
			//$logger->info($this->print_detail($transaction));
			$authResponse = $XiPay->Authorize($transaction);
			$logger->info('Authorize Response');
			$logger->info($this->print_detail($authResponse));
		} catch(\Exception $e) {
			//$logger->info(print_r('Authorization error. '.$e->getMessage()));
			//$this->debugData(['request' => $t, 'exception' => $e->getMessage()]);
            throw new \Magento\Framework\Validator\Exception(__('Authorization error. '.$e->getMessage()));
		}

		/**
		 * @param $authResponse is a SimpleXML structure one can access to all
		 * memebers and update his/her own database for settlement at a later time.
		 * @return If server throws an exception (soap error), $authResponse will be the error message string.
		 */

		$logger->info('Status Code: ' . $authResponse->Transaction->StatusCode);

		try{
			if($authResponse->Transaction->StatusCode == 100){
				/**
			     * This is the authorized transaction (fully constructed) that comes back from authorization call.
			     * Client needs to persist this transaction to somewhere in the database for capture and settle
			     * later in the workflow.
			     */
				$logger->info('Transaction authorized');

			    $authorized = $authResponse->Transaction;

			   /* echo "\nTransaction ID: " . $authorized->TransactionID;
			    echo " Status Code: " . $authorized->StatusCode;
				echo " Message: " . $authorized->Message ."\n";*/

				//$logger->info("Additional Data: ");
				//$logger->info(print_r($payment->getAdditionalInformation(),true));

				$additional['method_title'] = 'Credit Card';
				$additional['AUTH_CC_NO'] = $authorized->AuthorizationCode;
				$additional['AUTH_REFNO'] = $authorized->TransactionID;
				$additional['GL_ACCOUNT'] = '113519';
				$additional['MERCHIDCL'] = $authorized->MerchantID;
				$additional['savePayment'] = $payment->additional_data->savePayment;
				$poNumber = $payment->additional_data->extOrderId;

			/*	$payment->setCcNumberEnc($authResponse->Transaction->CardNumber);
				$payment->setAdditionalInformation($additional);

				if($additional['savePayment']!=true) {
					$payment->setCcStatusDescription("false");
				} else {
					$payment->setCcStatusDescription("true");
				}*/

				//$order->setExtOrderId($poNumber);
			} else {
				$logger->info('Status Code (NOT 100): ' . $authResponse->Transaction->StatusCode);
				throw new \Magento\Framework\Validator\Exception(__('Error storing token/additional data: ' . $authResponse->Message));
			}
		} catch(\Exception $e){
			$this->debugData(['request' => $transaction, 'exception' => $authResponse->Message]);
			$logger->info('Exception Error: ' . $e->getMessage());
			$logger->info('Authorization error. ' . $authResponse->Message);
			//throw new \Magento\Framework\Validator\Exception(__('Authorization error. ' . $authResponse->Message));

			$logger->info('BeforeRedirect');
			//$this->_checkoutSession->setRedirectUrl("/checkout/#payment");
			return 'Authorization Error: ' . $authResponse->Message;
			$logger->info('AfterRedirect');
		}


		/*
		if ($authResponse->Transaction->StatusCode == 100)
		{
		    //
		    //This is the authorized transaction (fully constructed) that comes back from authorization call.
		    //Client needs to persist this transaction to somewhere in the database for capture and settle
		    //later in the workflow.
		    //
			$logger->info('Transaction authorized');

		    $authorized = $authResponse->Transaction;

		    echo "\nTransaction ID: " . $authorized->TransactionID;
		    echo " Status Code: " . $authorized->StatusCode;
			echo " Message: " . $authorized->Message ."\n";

			$logger->info("Additional Data: ");
			//$logger->info(print_r($payment->getAdditionalInformation(),true));

			$additional['method_title'] = 'Credit Card';
			$additional['AUTH_CC_NO'] = $authorized->AuthorizationCode;
			$additional['AUTH_REFNO'] = $authorized->TransactionID;
			$additional['GL_ACCOUNT'] = '113519';
			$additional['MERCHIDCL'] = $authorized->MerchantID;
			$additional['savePayment'] = $payment->getAdditionalInformation('savePayment');
			$poNumber = $payment->getAdditionalInformation('extOrderId');

			try{
				$payment->setCcNumberEnc($authResponse->Transaction->CardNumber);
				$payment->setAdditionalInformation($additional);

				if($additional['savePayment']!=true) {
					$payment->setCcStatusDescription("false");
				} else {
					$payment->setCcStatusDescription("true");
				}

				$order->setExtOrderId($poNumber);

			} catch(\Exception $e) {
				throw new \Magento\Framework\Validator\Exception(__('Error storing token/additional data: '.$e->getMessage()));
			}
		}else{
			$this->debugData(['request' => $transaction, 'exception' => $authResponse->Message]);
			$this->_logger->error(__('Authorization error. '.$authResponse->Message));
			throw new \Magento\Framework\Validator\Exception(__('Authorization error. '.$authResponse->Message));
			$this->_checkoutSession->setRedirectUrl("/checkout/#payment");
		}
		*/

		$logger->info('At the end!');

		return $authResponse->Transaction->StatusCode;

    }

	/**
     * Returns tokenized card
     *
     * @return $cardInfo
     * @throws \Magento\Framework\Validator\Exception
     */
     public function getCreditCardInfo(){
     	$cardInfo = null;
     	try{
     		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
     		//$logger->info('BEFORE INSYNC CALL');
     		$cardInfo = $objectManager->create('Insync\RealTimePricing\Helper\Data')->getRealtimeCreditCardInfo();
     		//$logger->info('AFTER INSYNC CALL');
     	} catch(Exception $e){
     		$cardInfo = $e->getMessage();
     	}

     	return $cardInfo;
     }


	/**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
	public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
    	return $this;
    }


	/**
     * Additional information setter
     * Updates data inside the 'additional_information' array
     * or all 'additional_information' if key is data array
     *
     * @param bool $value
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setSaveCard($value)
    {
        $this->$_saveCard = $value;

		return $this;
    }

	/**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {

        parent::assignData($data);

		$additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        /** @var DataObject $info */
        $info = $this->getInfoInstance();
        $info->addData(
            [
                'cc_type' => $additionalData->getCcType(),
                'cc_owner' => $additionalData->getCcOwner(),
                'cc_last_4' => substr($additionalData->getCcNumber(), -4),
                'cc_number' => $additionalData->getCcNumber(),
                'cc_cid' => $additionalData->getCcCid(),
                'cc_exp_month' => $additionalData->getCcExpMonth(),
                'cc_exp_year' => $additionalData->getCcExpYear(),
                'cc_ss_issue' => $additionalData->getCcSsIssue(),
                'cc_ss_start_month' => $additionalData->getCcSsStartMonth(),
                'cc_ss_start_year' => $additionalData->getCcSsStartYear(),
				'savePayment' => $additionalData->getSavePayment()
            ]
        );
    }

    /**
     * Just a convenient way to get detailed info to error logs, hopefully...
     * Remove when necessary
     * @author J. Trpka
     */
    private function print_detail($stuff){
    	ob_start();
    	var_dump($stuff);
    	return ob_get_clean();
    }

}
