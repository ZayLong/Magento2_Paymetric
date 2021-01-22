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
use Magento\Framework\App\ResourceConnection;

class Payment extends \Magento\Payment\Model\Method\AbstractMethod
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
	protected $_resourceConnection;
    protected $_countryFactory;
	protected $_paymentMethod;
    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_supportedCurrencyCodes = array('USD');

    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];

    public function __construct(
		\Magento\Framework\App\ResourceConnection $resourceConnection,
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
		$this->_resourceConnection = $resourceConnection;
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
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
	public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
//couldn't figure out how magento is calling this method. I assumie it has somethign to do with quote or payment model but I cant find where. Maybe this will make it so that we have some CONTROL over how this is called
    	
		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/cc_debug_paymetric.log');
		$logger = new \Zend\Log\Logger();
		$logger->addWriter($writer); //Use $logger->info() to log items to cc_debug.log
		$logger->info('');

		$gl_number = null;
		//$logger->info('Payment Object');
		//$logger->info($this->print_detail($payment));
		//$logger->info($this->print_detail($amount));

		/** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
		$quoteId = $order->getQuoteId();
        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();

		$user = $this->getConfigData('user');
		$password = $this->getConfigData('password');
		$endpoint_url = $this->getConfigData('url');

		try{
			$logger->info('Constructing XiPaySoapClient');
			$logger->info('TRANSACTION FOR: ' . $billing->getFirstname() . " " . $billing->getLastname());
			$XiPay = new \Tng\Paymetric\Model\XiPaySoapClient($endpoint_url,"paymetric\\".$user,$password);
		}catch(Exception $e){
			$logger->info($e->getMessage());
			$logger->info(print_r('XiPay construction error. '.$e->getMessage()));
			$this->debugData(['request' => $t, 'exception' => $e->getMessage()]);
            throw new \Magento\Framework\Validator\Exception(__('XiPay construction error. '.$e->getMessage()));
		}


		//Setup trace if needed
		$XiPay->Trace = true;
		//$XiPay->TraceFile = "trace.txt"; //trace to a file. If TraceFile not set, trace to console!

		/**
		 * Build transaction structure
		 * See Web Service integration guide or WS WSDL for all properties to set.
		 */
		
		$transaction = new \Tng\Paymetric\Model\Transaction();
		$transaction->Amount = $amount;
		$transaction->CardExpirationDate = sprintf('%02d',$payment->getCcExpMonth()).'/'.$payment->getCcExpYear();
		$transaction->CardNumber = $payment->getCcNumber();
		$transaction->CardPresent = 0;
		$transaction->CurrencyKey = "USD";
		$transaction->MerchantID = "TNG_WORLDWIDE";
		$transaction->Message = "00 Approved"; //honestly don't know if this should be hardcoded like this or not....
		$transaction->CardHolderName = $billing->getFirstname() . " " . $billing->getLastname();
		$transaction->CardHolderName1 = $billing->getFirstname() . " " . $billing->getLastname();
		$transaction->SettlementAmount = $amount;
		$transaction->CardDataSource = "E";
		$transaction->CardCVV2 = $payment->getCcCid();

		//set GL NUMBER BASED ON CARD TYPE
		if($payment->getCcType() == "DI" || $payment->getCcType() == "DISC"){
			$gl_number = '113519';
		} elseif($payment->getCcType() == "AE" || $payment->getCcType() == "AMEX"){
			$gl_number = '113609';
		} else {
			$gl_number = '113519';
		}

		$logger->info("CC Type: " . $payment->getCcType());
		try{
			//$logger->info('Authorizing transaction');
			//$logger->info('Transaction Object');
			//$logger->info($this->print_detail($transaction));
			$logger->info("TRY AUTH BEFORE");
			$authResponse = $XiPay->Authorize($transaction);
			$logger->info("TRY AUTH AFTER");
			//$logger->info('Authorize Response');
			//$logger->info($this->print_detail($authResponse));
		} catch(Exception $e) {
			$logger->info("");
			$logger->info($e->getMessage());
			//$logger->info(print_r('Authorization error. '.$e->getMessage()));
			$this->debugData(['request' => $t, 'exception' => $e->getMessage()]);
            throw new \Magento\Framework\Validator\Exception(__('Authorization error. '.$e->getMessage()));
		}

		/**
		 * @param $authResponse is a SimpleXML structure one can access to all
		 * memebers and update his/her own database for settlement at a later time.
		 * @return If server throws an exception (soap error), $authResponse will be the error message string.
		 */
$logger->info(print_r($authResponse));
$logger->info(print_r($authResponse->Transaction));
		if($authResponse->Transaction != null)$logger->info('Status Code: ' . $authResponse->Transaction->StatusCode);

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
				$additional['GL_ACCOUNT'] = $gl_number;
				$additional['MERCHIDCL'] = $authorized->MerchantID;
				$additional['savePayment'] = $payment->getAdditionalInformation('savePayment');
				$poNumber = $payment->getAdditionalInformation('extOrderId');

				$payment->setCcNumberEnc($authResponse->Transaction->CardNumber);
				$payment->setAdditionalInformation($additional);

				if($additional['savePayment']!=true) {
					$payment->setCcStatusDescription("false");
				} else {
					$payment->setCcStatusDescription("true");
				}

				$logger->info('Save Payment: ' . $payment->getCcStatusDescription());

				$order->setExtOrderId($poNumber);
			} else {
				$logger->info('Status Code (NOT 100): ' . $authResponse->Transaction->StatusCode);
				//throw new \Magento\Framework\Validator\Exception(__('Error storing token/additional data: ' . $authResponse->Message));
				$logger->info('EXCEPTION MESSAGE: ' . $authResponse->Message);
				throw new \Magento\Framework\Exception\LocalizedException(__($authResponse->Message));
				//throw new \Magento\Framework\Validator\Exception(__('Could not create Order: ' . $authResponse->Message));
			}
			
			//Delete all sensitive data that gets sent to paymetric
			try{
				//clear sensitive data
				$connection = $this->_resourceConnection->getConnection();
				$tableName = $this->_resourceConnection->getTableName('quote_payment'); //gives table name with prefix
				$sql = "UPDATE " . $tableName . " SET additional_information = null WHERE quote_id = " . $quoteId;
				$connection->query($sql);
			} catch(\Throwable $th) {

			}
		} catch(\Exception $e){
			$this->debugData(['request' => $transaction, 'exception' => $authResponse->Message]);
			$logger->info('Exception Error: ' . $e->getMessage());
			$logger->info('Authorization error. ' . $authResponse->Message);
			throw new \Magento\Framework\Validator\Exception(__('Authorization error. ' . $authResponse->Message));

			$logger->info('BeforeRedirect');
			$this->_checkoutSession->setRedirectUrl("/checkout/#payment");
			return 'Authorization Error: ' . $authResponse->Message;
			//$logger->info('AfterRedirect');
		}

		$logger->info('At the end!');

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
