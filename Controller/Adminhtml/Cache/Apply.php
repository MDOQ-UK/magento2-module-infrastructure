<?php

namespace MDOQ\Infrastructure\Controller\Adminhtml\Cache;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\DeploymentConfig\Writer;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use MDOQ\Infrastructure\Model\CacheRecommendations;

class Apply extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MDOQ_Infrastructure::infrastructure';

    public function __construct(
        Context $context,
        private CacheRecommendations $cacheRecommendations,
        private Writer $deploymentConfigWriter,
        private ConfigFilePool $configFilePool,
        private Filesystem $filesystem
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('mdoq_infrastructure/cache/index');

        try {
            $updates = $this->cacheRecommendations->getUpdates();
            if (!$updates) {
                $this->messageManager->addNoticeMessage(
                    __('There are no Redis compression settings that need applying.')
                );
                return $resultRedirect;
            }

            $this->deploymentConfigWriter->saveConfig(
                [ConfigFilePool::APP_ENV => $updates],
                false,
                null,
                [],
                true
            );
            $this->invalidateEnvPhp();
            $this->messageManager->addSuccessMessage(
                __('Redis compression settings were saved to env.php and cleared from OPcache.')
            );
        } catch (FileSystemException $exception) {
            $this->messageManager->addErrorMessage(
                __('Unable to save env.php: %1', $exception->getMessage())
            );
        } catch (\RuntimeException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $resultRedirect;
    }

    private function invalidateEnvPhp(): void
    {
        if (!function_exists('opcache_invalidate')) {
            return;
        }

        $path = $this->configFilePool->getPaths()[ConfigFilePool::APP_ENV];
        $absolutePath = $this->filesystem
            ->getDirectoryRead(DirectoryList::CONFIG)
            ->getAbsolutePath($path);

        if (!opcache_invalidate($absolutePath, true)) {
            throw new \RuntimeException(
                (string)__('env.php was updated, but it could not be cleared from OPcache.')
            );
        }
    }
}
