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
                type: 'paymentez_module',
                component: 'Paymentez_Module/js/view/payment/method-renderer/card-method'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
