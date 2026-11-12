<?php

namespace MDOQ\Infrastructure\Controller\Adminhtml\Cache;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'MDOQ_Infrastructure::infrastructure';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->_view->loadLayout();
        $this->_setActiveMenu('MDOQ_Infrastructure::infrastructure');
        $this->_view->getPage()->getConfig()->getTitle()->prepend(__('Cache'));
        $this->_view->renderLayout();
    }
}
