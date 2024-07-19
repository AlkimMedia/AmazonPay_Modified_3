const alkimAmazonPay = {
    payButtonCount: 0,
    init() {
        alkimAmazonPay.registerAmazonPayButtons();
        alkimAmazonPay.bindChangeActions();
    },
    registerAmazonPayButtons() {
        try {
            const buttons = document.querySelectorAll('.amazon-pay-button');
            for (let i = 0; i < buttons.length; i++) {
                const button = buttons[i];
                const id = 'amazon-pay-button-' + alkimAmazonPay.payButtonCount++;
                button.id = id;
                amazon.Pay.renderButton('#' + id, {
                    merchantId: amazonPayParameters.merchantId,
                    createCheckoutSession: {
                        url: amazonPayParameters.createCheckoutSessionUrl
                    },
                    sandbox: amazonPayParameters.isSandbox,
                    estimatedOrderAmount: amazonPayParameters.estimatedOrderAmount,
                    ledgerCurrency: amazonPayParameters.ledgerCurrency,
                    checkoutLanguage: amazonPayParameters.language,
                    productType: amazonPayParameters.productType,
                    placement: amazonPayParameters.placement,
                    buttonColor: amazonPayParameters.checkoutButtonColor
                });
            }
        } catch (e) {
            console.warn(e);
        }

        try {
            if (document.getElementById('amazon-pay-button-product-info')) {
                const btn = amazon.Pay.renderButton('#amazon-pay-button-product-info', {
                    merchantId: amazonPayParameters.merchantId,
                    sandbox: amazonPayParameters.isSandbox,
                    estimatedOrderAmount: amazonPayParameters.estimatedOrderAmountInclProduct,
                    ledgerCurrency: amazonPayParameters.ledgerCurrency,
                    checkoutLanguage: amazonPayParameters.language,
                    productType: amazonPayParameters.productType,
                    placement: amazonPayParameters.placement,
                    buttonColor: amazonPayParameters.checkoutButtonColor
                });

                btn.onClick(function () {
                    alkimAmazonPay.ajaxPost(document.getElementById('cart_quantity'), function () {
                        btn.initCheckout({
                            createCheckoutSession: {
                                url: amazonPayParameters.createCheckoutSessionUrl
                            }
                        });
                    });
                });
            }
        } catch (e) {
            console.warn(e);
        }

    },

    registerAmazonLoginButtons() {

        try {
            const buttons = document.querySelectorAll('.amazon-login-button');
            for (let i = 0; i < buttons.length; i++) {
                const button = buttons[i];
                const id = 'amazon-login-button-' + alkimAmazonPay.payButtonCount++;
                button.id = id;
                amazon.Pay.renderButton('#' + id, {
                    merchantId: amazonPayParameters.merchantId,
                    sandbox: amazonPayParameters.isSandbox,
                    ledgerCurrency: amazonPayParameters.ledgerCurrency,
                    checkoutLanguage: amazonPayParameters.language,
                    productType: amazonPayParameters.productType,
                    placement: amazonPayParameters.placement,
                    buttonColor: amazonPayParameters.loginButtonColor,
                    signInConfig: {
                        payloadJSON: amazonPayParameters.loginPayload,
                        signature: amazonPayParameters.loginSignature,
                        publicKeyId: amazonPayParameters.publicKeyId,
                    }
                });
            }
        } catch (e) {
            console.warn(e);
        }
    },

    doApbCheckout(createCheckoutSessionConfig) {

        try {
            const button = document.getElementById('amazon-pay-button-hidden');
            if (!button) {
                return;
            }
            const buttonConfiguration = {
                merchantId: amazonPayParameters.merchantId,
                sandbox: amazonPayParameters.isSandbox,
                ledgerCurrency: amazonPayParameters.ledgerCurrency,
                checkoutLanguage: amazonPayParameters.language,
                productType: amazonPayParameters.productType,
                placement: 'Checkout',
                buttonColor: amazonPayParameters.checkoutButtonColor,
                estimatedOrderAmount: amazonPayParameters.estimatedOrderAmount,
            };
            const amazonPayButtonObject = amazon.Pay.renderButton('#amazon-pay-button-hidden', buttonConfiguration);
            amazonPayButtonObject.initCheckout({
                createCheckoutSessionConfig: createCheckoutSessionConfig
            });
        } catch (e) {
            console.warn(e);
        }
    },


    bindChangeActions() {
        try {
            amazon.Pay.bindChangeAction('#amz-change-address', {
                amazonCheckoutSessionId: '$checkoutSessionId',
                changeAction: 'changeAddress'
            });
        } catch (e) {
            //console.warn(e);
        }
        try {
            amazon.Pay.bindChangeAction('#amz-change-payment', {
                amazonCheckoutSessionId: '$checkoutSessionId',
                changeAction: 'changePayment'
            });
        } catch (e) {
            //console.warn(e);
        }
    },
    ajaxPost: function (form, callback) {
        const url = form.action, xhr = new XMLHttpRequest();
        const params = [];
        const fields = form.querySelectorAll('input, select, textarea');
        for (let i = 0; i < fields.length; i++) {
            const field = fields[i];
            if (field.name && field.value) {
                params.push(encodeURIComponent(field.name) + '=' + encodeURIComponent(field.value));
            }
        }
        xhr.open("POST", url);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.onload = callback.bind(xhr);
        xhr.send(params.join('&'));
    }
};

document.addEventListener('DOMContentLoaded', function () {
    alkimAmazonPay.init();
});

const commentsInput = document.getElementById('checkout-confirmation-comments-input');
if (commentsInput) {
    commentsInput.addEventListener('keyup', function () {
        document.getElementById('checkout-confirmation-comments').value = commentsInput.value;
    });
}

const amazonPayUseCreditCheckbox = document.querySelector('[name="amazon_pay_use_credit"]');
if (amazonPayUseCreditCheckbox) {
    amazonPayUseCreditCheckbox.addEventListener('change', function () {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", useCreditUrl);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.send('use_credit=' + (amazonPayUseCreditCheckbox.checked ? 1 : 0));
        xhr.onload = function () {
            window.location.reload();
        }
    });
}
