<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Iget\Correios\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
/**
 * Class Locations Backend system config array field renderer
 */
class AvailableBoxes extends AbstractFieldArray
{
    /**
     * Initialise columns for 'Store Locations'
     * Label is name of field
     * Class is storefront validation action for field
     *
     * @return void
     */
    protected function _construct()
    {
        $this->addColumn(
            'label',
            [
                'label' => __('Box Label'),
                'class' => 'validate-no-empty validate-alphanum-with-spaces',
            ]
        );

        $this->addColumn(
            'height',
            [
                'label' => __('Height'),
                'class' => 'validate-no-empty validate-number'
            ]
        );

        $this->addColumn(
            'width',
            [
                'label' => __('Width'),
                'class' => 'validate-no-empty validate-number'
            ]
        );

        $this->addColumn(
            'depth',
            [
                'label' => __('Length'),
                'class' => 'validate-no-empty validate-number'
            ]
        );

        $this->addColumn(
            'weight',
            [
                'label' => __('Weight'),
                'class' => 'validate-no-empty validate-number'
            ]
        );

        $this->addColumn(
            'capacity',
            [
                'label' => __('Capacity'),
                'class' => 'validate-no-empty validate-number'
            ]
        );

        $this->_addAfter = false;
        parent::_construct();
    }
}
