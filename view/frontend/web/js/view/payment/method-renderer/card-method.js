/*browser:true*/
/*global define*/
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'https://s3.amazonaws.com/cdn.paymentez.com/js/ccapi/stg/paymentez.magento.js'
    ],
    function (Component, $, quote, customer, validator) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Paymentez_Module/payment/cc-form'
            },

            getCode: function() {
                return 'paymentez_module';
            },

            context: function() {
                return this;
            },

            isActive: function() {
                return window.checkoutConfig.payment.paymentez.is_active;
            },

            tokenize: function () {
            	let customerInfo;
            	let guestMail = quote.guestEmail || "foo@mail.com";
            	let guestUser = {
            		website_id: guestMail,
            		email: guestMail
            	};

                let settings = window.checkoutConfig.payment.paymentez;

                if (Array.isArray(window.customerData) && window.customerData.length == 0) {
                	customerInfo = guestUser;
                } else {
                	customerInfo = window.customerData;
                }

                if(this.validate()) {
                    this.messageContainer.clear();

                    // Initialize Paymentez.js library
                    Paymentez.init(settings.env, settings.app_code, settings.app_key);

                    let sessionId = Paymentez.getSessionId();
                    let checkout = this;
                    let tokenParams = {
                          "session_id": sessionId,
                          "user": {
                            "id": customerInfo.website_id,
                            "email": customerInfo.email,
                            "fiscal_number": ""
                          },
                          "card": {
                            "number": this.creditCardNumber(),
                            "holder_name": $("#holder-name").val(),
                            "expiry_month": parseInt(this.creditCardExpMonth()),
                            "expiry_year": parseInt(this.creditCardExpYear().replace(/ /g, '')),
                            "cvc": this.creditCardVerificationNumber(),
                            "type": this.creditCardType().toLowerCase()
                          }
                    };


                    Paymentez.createToken(tokenParams,  function (response) {
                        $("#card-token").val(response.card.token);
                        checkout.placeOrder();
                    }, function (err) {
                        let errorType = err.error.type;
                        let errorTypeArr = errorType.split(' ');

                        // When credit card is already exists
                        if (errorTypeArr.length === 4
                            && errorTypeArr[3]
                            && typeof errorTypeArr[3] == 'string'
                            && errorTypeArr[3].length > 0) {
                            $("#card-token").val(errorTypeArr[3]);
                            checkout.placeOrder();
                        } else {
                            return checkout.messageContainer.addErrorMessage(err.error.help);
                        }
                    });
                } else {
                    return this.validate();
                }
            },

            getData: function () {
                let number = this.creditCardNumber().replace(/\D/g,'');
                let data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_bin': number.substring(0, 6),
                        'cc_last_4': number.substring((number.length - 4), number.length),
                        'card_token': $("#card-token").val()
                    }
                };

                return data;
            },

            getTotal: function() {
                return parseFloat(window.checkoutConfig.payment.total);
            },

            validate: function() {
                let cc_form = $('#' + this.getCode() + '-form');
                return cc_form.validation() && cc_form.validation('isValid');
            }
        });
    }
);
