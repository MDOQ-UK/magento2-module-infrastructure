<?php

namespace MDOQ\Infrastructure\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\FormKey;
use MDOQ\Infrastructure\Model\CacheRecommendations;

class Cache extends Template
{
    public function __construct(
        Template\Context $context,
        private CacheRecommendations $cacheRecommendations,
        private FormKey $cacheFormKeyService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getReport(): array
    {
        return $this->cacheRecommendations->getReport();
    }

    public function getFormKeyValue(): string
    {
        return $this->cacheFormKeyService->getFormKey();
    }

    public function hasChanges(array $report): bool
    {
        foreach ($report['scopes'] as $scope) {
            if ($scope['applicable'] && $scope['changes']) {
                return true;
            }
        }

        return false;
    }
}
