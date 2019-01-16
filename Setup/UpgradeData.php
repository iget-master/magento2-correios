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

        if (version_compare($context->getVersion(), '1.2.16') < 0) {
            $this->migrate_1_2_16($setup, $context);
        }

        $setup->endSetup();
    }

    private function migrate_1_2_16(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $eavSetup->addAttributeGroup(
            Product::ENTITY,
            'Default',
            'Iget Correios',
            10
        );

        $productTypes = join(',', [
            Type::TYPE_SIMPLE,
            Type::TYPE_VIRTUAL,
        ]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'correios_width',
            [
                'type'                    => 'text',
                'label'                   => 'Width (cm)',
                'input'                   => 'text',
                'sort_order'              => 50,
                'global'                  => Attribute::SCOPE_WEBSITE,
                'user_defined'            => true,
                'required'                => false,
                'used_in_product_listing' => true,
                'apply_to'                => $productTypes,
                'group'                   => 'Iget Correios',
                'unique'                  => false,
                'visible_on_front'        => true,
                'searchable'              => false,
                'filterable'              => true,
                'comparable'              => true,
                'visible'                 => true,
                'backend'                 => '',
                'frontend'                => '',
                'class'                   => '',
                'source'                  => '',
                'default'                 => '',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'correios_height',
            [
                'type'                    => 'text',
                'label'                   => 'Height (cm)',
                'input'                   => 'text',
                'sort_order'              => 51,
                'global'                  => Attribute::SCOPE_WEBSITE,
                'user_defined'            => true,
                'required'                => false,
                'used_in_product_listing' => true,
                'apply_to'                => $productTypes,
                'group'                   => 'Iget Correios',
                'unique'                  => false,
                'visible_on_front'        => true,
                'searchable'              => false,
                'filterable'              => true,
                'comparable'              => true,
                'visible'                 => true,
                'backend'                 => '',
                'frontend'                => '',
                'class'                   => '',
                'source'                  => '',
                'default'                 => '',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'correios_depth',
            [
                'type'                    => 'text',
                'label'                   => 'Depth (cm)',
                'input'                   => 'text',
                'sort_order'              => 52,
                'global'                  => Attribute::SCOPE_WEBSITE,
                'user_defined'            => true,
                'required'                => false,
                'used_in_product_listing' => true,
                'apply_to'                => $productTypes,
                'group'                   => 'Iget Correios',
                'unique'                  => false,
                'visible_on_front'        => true,
                'searchable'              => false,
                'filterable'              => true,
                'comparable'              => true,
                'visible'                 => true,
                'backend'                 => '',
                'frontend'                => '',
                'class'                   => '',
                'source'                  => '',
                'default'                 => '',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'correios_boxes',
            [
                'type'                    => 'text',
                'source'                  => AvailableBoxes::class,
                'label'                   => 'Fit on boxes',
                'input'                   => 'multiselect',
                'sort_order'              => 53,
                'global'                  => Attribute::SCOPE_WEBSITE,
                'user_defined'            => true,
                'required'                => false,
                'used_in_product_listing' => true,
                'apply_to'                => $productTypes,
                'group'                   => 'Iget Correios',
                'unique'                  => false,
                'visible_on_front'        => true,
                'searchable'              => false,
                'filterable'              => true,
                'comparable'              => true,
                'visible'                 => true,
                'backend'                 => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                'frontend'                => '',
                'class'                   => '',
                'default'                 => '',
            ]
        );
    }
}
