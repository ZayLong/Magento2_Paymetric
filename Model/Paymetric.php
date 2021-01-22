<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Tng\Paymetric\Model;



/**
 * Pay In Store payment method model
 */
class Paymetric extends \Magento\Payment\Model\Method\Cc
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'paymetric';


//    const CGI_URL = 'https://secure.authorize.net/gateway/transact.dll';

    const REQUEST_METHOD_CC = 'CC';

    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';

    const REQUEST_TYPE_AUTH_ONLY = 'AUTH_ONLY';

    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';

    const REQUEST_TYPE_CREDIT = 'CREDIT';

    const REQUEST_TYPE_VOID = 'VOID';

    const REQUEST_TYPE_PRIOR_AUTH_CAPTURE = 'PRIOR_AUTH_CAPTURE';

    const RESPONSE_DELIM_CHAR = '(~)';

    const RESPONSE_CODE_APPROVED = 1;

    const RESPONSE_CODE_DECLINED = 2;

    const RESPONSE_CODE_ERROR = 3;

    const RESPONSE_CODE_HELD = 4;

    const RESPONSE_REASON_CODE_APPROVED = 1;

    const RESPONSE_REASON_CODE_PENDING_REVIEW_AUTHORIZED = 252;

    const RESPONSE_REASON_CODE_PENDING_REVIEW = 253;

    const RESPONSE_REASON_CODE_PENDING_REVIEW_DECLINED = 254;

  

}
