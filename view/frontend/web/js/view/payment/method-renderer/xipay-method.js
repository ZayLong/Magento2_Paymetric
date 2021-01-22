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
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Ui/js/model/messages',
        'uiLayout',
        'Magento_Checkout/js/action/redirect-on-success',
		'Magento_Checkout/js/action/set-payment-information',
		'Magento_Checkout/js/model/quote'
    ],
    function (
        Component, 
        $, 
        validator,
        additionalValidators,
        Messages,
        layout,
        redirectOnSuccessAction, 
        setPaymentInformationAction,
		quote,
        saveCardAction
	) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Tng_Paymetric/payment/xipay-form',
                    additionalData: {},
                    savePayment: null,
					extOrderId: null
            },

            initObservable: function () {
            	this._super()
                    .observe([
                    'creditCardType',
                    'creditCardExpYear',
                    'creditCardExpMonth',
                    //'creditCardNumberFormat',
                    'creditCardNumber',
                    'creditCardVerificationNumber',
                    'creditCardSsStartMonth',
                    'creditCardSsStartYear',
                    'selectedCardType',
                    'savePayment',
                    'extOrderId'
                    ]);
            	return this;
            },

            getCode: function() {
                return 'tng_paymetric';
            },

            getData: function() {
                var extId = this.extOrderId();
				var date = new Date();
				/*if(extId==null || extId =="") 
				{
					if(quote.billingAddress().customAttributes)	{
						extId = quote.billingAddress().customAttributes.sap_address_token.value +
						' - ' + date.toISOString().split('T')[0];
					} else {
						extId = date.toISOString().split('T')[0];
					}
				}*/

                return {
                    'method': this.item.method,
                    'po_number': null,
                    'additional_data': {
                        'cc_cid': this.creditCardVerificationNumber(),
                        'cc_ss_start_month': this.creditCardSsStartMonth(),
                        'cc_ss_start_year': this.creditCardSsStartYear(),
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        //'cc_number_format': this.creditCardNumberFormat(),
                        'cc_number': this.creditCardNumber(),
                        'savePayment': this.savePayment(),
                        'extOrderId': extId
                    }
                };
            },

            isActive: function() {
                return true;
            },

            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            /**
             * Masking CC Number Events
             */

            /*
            focusNumber: function(data, event){
                console.log('FOCUS EVENT');
                
                var $shownField = $('#' + this.getCode() + '_cc_number_format');
                var $hiddenField = $('#' + this.getCode() + '_cc_number');
                var hiddenValue = $hiddenField.val();

                $shownField.val(hiddenValue);
            },

            blurNumber: function(data, event){
                console.log('BLUR EVENT');

                var $shownField = $('#' + this.getCode() + '_cc_number_format');
                var $hiddenField = $('#' + this.getCode() + '_cc_number');
                var shownValue = $shownField.val();
                var last4 = shownValue.substr(-4);

                if(false){
                    //var theMask = 'xxxx-xxxxxx-x';
                    var theMask = 'xxxxxxxxxxx';
                } else {
                    //var theMask = 'xxxx-xxxx-xxxx-';
                    var theMask = 'xxxxxxxxxxxx';
                }
                
                console.log ('last4', last4);

                $hiddenField.val(shownValue); //Send the data to hidden field

                if(shownValue == ''){
                    $shownField.val('');
                } else {
                    $shownField.val(theMask + last4);
                }    
            }
            */
        });
    }
);
