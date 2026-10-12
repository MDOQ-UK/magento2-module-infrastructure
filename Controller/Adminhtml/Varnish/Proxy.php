<?php

namespace MDOQ\Infrastructure\Controller\Adminhtml\Varnish;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use MDOQ\Infrastructure\Model\VarnishManagerClient;

class Proxy extends Action
{
    public const ADMIN_RESOURCE = 'MDOQ_Infrastructure::infrastructure';

    private const ENDPOINTS = ['stats', 'config', 'env', 'vcl'];

    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private VarnishManagerClient $managerClient
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $endpoint = (string)$this->getRequest()->getParam('endpoint', '');

        if (!in_array($endpoint, self::ENDPOINTS, true)) {
            return $result->setHttpResponseCode(400)->setData(['error' => __('Unsupported endpoint.')]);
        }

        try {
            $response = $this->managerClient->get($endpoint);
            $data = json_decode($response['body'], true);
            if (!is_array($data)) {
                return $result->setHttpResponseCode(502)->setData(['error' => __('Varnish manager returned an invalid response.')]);
            }

            return $result->setHttpResponseCode($response['status'])->setData($data);
        } catch (\Throwable) {
            return $result->setHttpResponseCode(502)->setData(['error' => __('Unable to reach the Varnish manager.')]);
        }
    }
}