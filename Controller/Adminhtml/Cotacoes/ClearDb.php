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

namespace Iget\Correios\Controller\Adminhtml\Cotacoes;

use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Iget\Correios\Helper\Data as Helper;

class ClearDb extends Action
{
    /**
     * @var Helper
     */
    private $helper;

    const ADMIN_RESOURCE = 'Iget_Correios::correios_menuoption1';

    /**
     * ClearDb constructor.
     * @param Context $context
     * @param Helper $helper
     */
    public function __construct(Context $context, Helper $helper)
    {
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
        $this->helper->truncateCotacoes();
        $this->messageManager->addSuccessMessage(__('The database of postcode tracks was successfully cleared.'));

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
