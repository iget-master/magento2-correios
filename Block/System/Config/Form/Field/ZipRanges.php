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
class ZipRanges extends AbstractFieldArray
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
            'description',
            [
                'label' => __('Internal description'),
                'class' => 'validate-no-empty validate-alphanum-with-spaces',
            ]
        );

        $this->addColumn(
            'start',
            [
                'label' => __('Start'),
                'class' => 'validate-no-empty validate-number'
            ]
        );

        $this->addColumn(
            'end',
            [
                'label' => __('End'),
                'class' => 'validate-no-empty validate-number'
            ]
        );

        $this->addColumn(
            'minimum_price',
            [
                'label' => __('Minimum Order'),
                'class' => 'validate-no-empty validate-number'
            ]
        );

        $this->addColumn(
            'cost',
            [
                'label' => __('Cost'),
                'class' => 'validate-no-empty validate-number'
            ]
        );

        $this->_addAfter = false;
        parent::_construct();
    }
}
