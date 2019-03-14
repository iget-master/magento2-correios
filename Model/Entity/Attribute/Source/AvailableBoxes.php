<?php

namespace Iget\Correios\Model\Entity\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AvailableBoxes extends AbstractSource
{
    private $_scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->_scopeConfig = $scopeConfig;
    }

    public function getAllOptions()
    {
        $availableBoxes = json_decode($this->_scopeConfig->getValue(
            "carriers/correios/packages/available_boxes",
            ScopeInterface::SCOPE_STORE
        ), true);

        $options = [];

        if ($availableBoxes) {
            foreach ($availableBoxes as $value => $availableBox) {
                $label = $availableBox['label'] . ' (' . $availableBox['width'] . 'cm x ' . $availableBox['height'] . 'cm x ' . $availableBox['depth'] . 'cm)';

                $options[] = compact('value', 'label');
            }
        }

        return $options;
    }
}
