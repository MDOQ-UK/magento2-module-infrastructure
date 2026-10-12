<?php

namespace MDOQ\Infrastructure\Controller\Adminhtml\Varnish;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use MDOQ\Infrastructure\Api\VarnishServiceInterface;

class Upload extends Action
{
    public const ADMIN_RESOURCE = 'MDOQ_Infrastructure::infrastructure';

    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private VarnishServiceInterface $mdoqVarnishService
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $file = $this->getRequest()->getFiles('vcl_file');

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $result->setHttpResponseCode(400)->setData(['error' => __('Select a VCL file to upload.')]);
        }

        $name = basename((string)($file['name'] ?? ''));
        $temporaryPath = (string)($file['tmp_name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'vcl' || !is_uploaded_file($temporaryPath)) {
            return $result->setHttpResponseCode(400)->setData(['error' => __('Upload a valid .vcl file.')]);
        }

        $vclContent = file_get_contents($temporaryPath);
        if ($vclContent === false) {
            return $result->setHttpResponseCode(400)->setData(['error' => __('Unable to read the uploaded VCL file.')]);
        }

        try {
            return $result->setData($this->mdoqVarnishService->updateVcl($vclContent));
        } catch (\Throwable) {
            return $result->setHttpResponseCode(502)->setData(['error' => __('Unable to reach the Varnish manager.')]);
        }
    }
}