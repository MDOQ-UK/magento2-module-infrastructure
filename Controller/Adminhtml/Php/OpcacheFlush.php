<?php

namespace MDOQ\Infrastructure\Controller\Adminhtml\Php;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;

class OpcacheFlush extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MDOQ_Infrastructure::infrastructure';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $script = (string)$this->getRequest()->getParam('script', '');
        $success = false;
        $message = '';

        if (!function_exists('opcache_reset')) {
            $message = (string)__('The OPcache extension is not loaded.');
        } elseif ($script !== '') {
            $success = @opcache_invalidate($script, true);
            $message = $success ? (string)__('Script cleared from OPcache.') : (string)__('Unable to clear the script.');
        } else {
            $success = @opcache_reset();
            $message = $success ? (string)__('OPcache flushed.') : (string)__('Unable to flush OPcache.');
        }

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $result->setData(['success' => $success, 'message' => $message]);
    }
}
