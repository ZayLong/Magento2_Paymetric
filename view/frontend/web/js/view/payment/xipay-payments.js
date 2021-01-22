/**
 * Tng_Paymetric Magento JS component
 *
 * @category    Tng
 * @package     Tng_Paymetric
 * @author      Daniel McClure
 * @copyright   Tng (http://tngworldwide.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'tng_paymetric',
                component: 'Tng_Paymetric/js/view/payment/method-renderer/xipay-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);