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

namespace Tng\Paymetric\Model\XiPay;

class XiPayNTLMSoapClient extends \Zend\Soap\Client
{
	public function __doRequest($request, $location, $action, $version, $one_way = NULL ) {
		//$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/test.log');
		//$logger = new \Zend\Log\Logger();
		//$logger->addWriter($writer);
		//$logger->info('XiPayNTLMSoapClient:');

		$headers = array(
		'Method: POST',
		'Connection: Keep-Alive',
		'User-Agent: PHP-SOAP-CURL',
		'Content-Type: text/xml; charset=utf-8',
		'SOAPAction: "'.$action.'"',
		);

		//$logger->info("XiPayNTLMSoapClient Request: ");
		//$logger->info(print_r($request,true));
		//$logger->info("XiPayNTLMSoapClient Headers: ");
		//$logger->info(print_r($headers,true));

		$this->__last_request_headers = $headers;
		$ch = curl_init(str_replace('http:', 'https:', $location));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true );
		curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
		curl_setopt($ch, CURLOPT_USERPWD, $this->getConfigData('user').':'.$this->getConfigData('password'));
		$response = curl_exec($ch);

		//$logger->info("XiPayNTLMSoapClient Response: ");
		//$logger->info(print_r($response,true));

		return $response;
	}

	function __getLastRequestHeaders() {
		return implode("\n", $this->__last_request_headers)."\n";
	}
}