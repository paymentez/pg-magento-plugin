/*browser:true*/
/*global define*/
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'Magento_Checkout/js/model/full-screen-loader',
        'https://cdn.paymentez.com/ccapi/plugin/payment_magento_2.0.1.min.js'
    ],
    function (Component, $, quote, customer, validator, fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Paymentez_Module/payment/cc-form'
            },

            getCode: function () {
                return 'paymentez_module';
            },

            context: function () {
                return this;
            },

            isActive: function () {
                return window.checkoutConfig.payment.paymentez.is_active;
            },

            tokenize: function () {
                let customerInfo;
                let guestMail = quote.guestEmail || "foo@mail.com";
                let guestUser = {
                    id: guestMail,
                    email: guestMail
                };

                let settings = window.checkoutConfig.payment.paymentez;

                if (Array.isArray(window.customerData) && window.customerData.length === 0) {
                    customerInfo = guestUser;
                } else {
                    customerInfo = window.customerData;
                }

                if (this.validate()) {
                    this.messageContainer.clear();
                    fullScreenLoader.startLoader();

                    // Initialize Payment.js library
                    Payment.init(settings.env, settings.app_code, settings.app_key);

                    let sessionId = Payment.getSessionId();
                    let checkout = this;
                    let tokenParams = {
                        "session_id": sessionId,
                        "user": {
                            "id": customerInfo.id,
                            "email": customerInfo.email,
                            "fiscal_number": ""
                        },
                        "card": {
                            "number": this.creditCardNumber(),
                            "holder_name": $("#holder-name").val(),
                            "expiry_month": parseInt(this.creditCardExpMonth()),
                            "expiry_year": parseInt(this.creditCardExpYear().replace(/ /g, '')),
                            "cvc": this.creditCardVerificationNumber(),
                        }
                    };


                    Payment.createToken(tokenParams, function (response) {
                        if (response.card.status === 'valid') {
                            $("#card-token").val(response.card.token);
                            fullScreenLoader.stopLoader();
                            checkout.placeOrder();
                        } else {
                            let message = `Lo sentimos, tu pago no pudo ser procesado. (code: ${response.card.transaction_reference})`;
                            fullScreenLoader.stopLoader();
                            return checkout.messageContainer.addErrorMessage({message});
                        }
                    }, function (err) {
                        let errorType = err.error.type;
                        let errorTypeArr = errorType.split(' ');

                        // When credit card is already exists
                        if (errorTypeArr.length === 4
                            && errorTypeArr[3]
                            && typeof errorTypeArr[3] == 'string'
                            && errorTypeArr[3].length > 0) {
                            $("#card-token").val(errorTypeArr[3]);
                            fullScreenLoader.stopLoader();
                            checkout.placeOrder();
                        } else {
                            let message = err.error.help ? err.error.help : err.error.description;
                            fullScreenLoader.stopLoader();
                            return checkout.messageContainer.addErrorMessage({message});
                        }
                    });
                } else {
                    return this.validate();
                }
            },

            getData: function () {
                let number = this.creditCardNumber().replace(/\D/g, '');
                let data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_bin': number.substring(0, 6),
                        'cc_last_4': number.substring((number.length - 4), number.length),
                        'card_token': $("#card-token").val(),
                        'installments': $("#installments").val()
                    }
                };

                return data;
            },

            getTotal: function () {
                return parseFloat(window.checkoutConfig.payment.total);
            },

            validate: function () {
                let cc_form = $('#' + this.getCode() + '-form');
                return cc_form.validation() && cc_form.validation('isValid');
            },

            allowInstallments: function() {
                return Boolean(window.checkoutConfig.payment.ccform.allow_installments);
            },

            getAvailableInstallments: function (amount) {
                function Installment(value, text) {
                    this.value = value;
                    this.text = text;
                };

                let installments = [
                    new Installment(undefined, '¿En cuantas cuotas deseas pagar?'),
                    new Installment(1, `Total - $ ${amount}`)
                ];

                for (var i = 2; i <= 36; i++) {
                    installments.push(new Installment(i, `${i} cuotas`));
                }

                return installments;
            }

        });
    }
);
