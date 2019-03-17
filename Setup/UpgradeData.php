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

namespace Iget\Correios\Setup;

use Iget\Correios\Model\Entity\Attribute\Source\AvailableBoxes;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * Upgrades data for a module
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1') < 0) {
            $this->migrate_1_0_1($setup, $context);
        }

        if (version_compare($context->getVersion(), '1.0.2') < 0) {
            $this->migrate_1_0_2($setup, $context);
        }

        $setup->endSetup();
    }

    private function migrate_1_0_1(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

    }

    private function migrate_1_0_2(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $configDataTable = $setup->getTable('core_config_data');
        $connection = $setup->getConnection();

        // Rename flat rate by zip path on `core_config_data`
        $where = $connection->quoteInto('path = ?', 'carriers/correios/free_shipping/by_zip_range');
        $connection->update($configDataTable, ['path' => 'carriers/correios/flat_rate/by_zip_range'], $where);

        // Add cost column to flat rate by zip range config.
        // Prior this version, it was free shipping.
        $query = $connection->select()->from($configDataTable)->where(
            'path = ?',
            'carriers/correios/flat_rate/by_zip_range'
        );

        $oldValues = $connection->fetchAll($query);

        foreach ($oldValues as $oldValue) {
            $zipRanges = json_decode($oldValue['value'], true);
            foreach ($zipRanges as $index => $zipRange) {
                $zipRanges[$index]['cost'] = 0;
            }

            $whereConfigId = $connection->quoteInto('config_id = ?', $oldValue['config_id']);
            $connection->update($configDataTable, ['value' => json_encode($zipRanges)], $whereConfigId);
        }

    }
}
