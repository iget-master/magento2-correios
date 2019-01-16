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

class FunctionMode implements ArrayInterface
{
    const MODE_OFFLINE = 1;
    const MODE_HYBRID = 2;
    const MODE_ONLINE = 3;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => static::MODE_OFFLINE, 'label' => __('Only Offline')),
            array('value' => static::MODE_HYBRID, 'label' => __('Hybrid')),
            array('value' => static::MODE_ONLINE, 'label' => __('Only Online')),
        );
    }
}
