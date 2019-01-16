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

class PostingMethods implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value'=>40010, 'label'=>__('Sedex Sem Contrato (40010)')),
            array('value'=>4162, 'label'=>__('Sedex Com Contrato (4162)')),
            array('value'=>41106, 'label'=>__('PAC Sem Contrato (41106)')),
            array('value'=>4669, 'label'=>__('PAC Com Contrato (4669)')),
            array('value'=>40215, 'label'=>__('Sedex 10 (40215)')),
            array('value'=>40290, 'label'=>__('Sedex HOJE (40290)')),
            array('value'=>40045, 'label'=>__('Sedex a Cobrar (40045)')),
        );
    }
}
