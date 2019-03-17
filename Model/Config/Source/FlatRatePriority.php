<?php

/**
 * Correios
 *
 * Correios Shipping Method for Magento 2.
 *
 * @package Iget\Correios
 * @author Igor Ludgero Miura <igor@imaginemage.com>
 * @copyright Copyright (c) 2017 Imagination Media (http://imaginemage.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

namespace Iget\Correios\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class FlatRatePriority implements ArrayInterface
{
    const PRIORITY_MOST_SPECIFIC = 1;
    const PRIORITY_CHEAPEST = 2;
    const PRIORITY_EXPENSIVEST = 3;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => static::PRIORITY_MOST_SPECIFIC, 'label' => __('Most specific')),
            array('value' => static::PRIORITY_CHEAPEST, 'label' => __('Cheapest')),
            array('value' => static::PRIORITY_EXPENSIVEST, 'label' => __('Expensivest')),
        );
    }
}
