<?php

namespace MDOQ\Infrastructure\Controller\Adminhtml\Php;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutFactory;
use MDOQ\Infrastructure\Block\Adminhtml\PhpDiagnostics;

class Status extends Action
{
    public const ADMIN_RESOURCE = 'MDOQ_Infrastructure::infrastructure';

    public function __construct(
        Context $context,
        private ResultFactory $rawResultFactory,
        private LayoutFactory $layoutFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $layout = $this->layoutFactory->create();
        /** @var PhpDiagnostics $block */
        $block = $layout->createBlock(PhpDiagnostics::class);
        $block->setTemplate('MDOQ_Infrastructure::status.phtml');

        /** @var \Magento\Framework\Controller\Result\Raw $result */
        $result = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);

        return $result
            ->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true)
            ->setHeader('X-Content-Type-Options', 'nosniff', true)
            ->setContents($block->toHtml());
    }
}