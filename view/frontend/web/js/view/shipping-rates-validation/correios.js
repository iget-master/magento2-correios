/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'uiComponent',
    'Magento_Checkout/js/model/shipping-rates-validator',
    'Magento_Checkout/js/model/shipping-rates-validation-rules',
    '../../model/shipping-rates-validator/correios',
    '../../model/shipping-rates-validation-rules/correios'
], function (
    Component,
    defaultShippingRatesValidator,
    defaultShippingRatesValidationRules,
    correiosShippingRatesValidator,
    correiosShippingRatesValidationRules
) {
    'use strict';

    defaultShippingRatesValidator.registerValidator('correios', correiosShippingRatesValidator);
    defaultShippingRatesValidationRules.registerRules('correios', correiosShippingRatesValidationRules);

    return Component;
});
