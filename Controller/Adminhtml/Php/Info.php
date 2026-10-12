<?php

namespace MDOQ\Infrastructure\Controller\Adminhtml\Php;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;

class Info extends Action
{
    public const ADMIN_RESOURCE = 'MDOQ_Infrastructure::infrastructure';

    public function __construct(Context $context, private RawFactory $resultRawFactory)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        ob_start();
        phpinfo();
        $output = ob_get_clean();

        return $this->resultRawFactory->create()
            ->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
            ->setHeader('Cache-Control', 'no-store, private', true)
            ->setContents($output);
    }
}