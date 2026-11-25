<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Observer;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Adds this module to the Hyvä theme's Tailwind configuration (event `hyva_config_generate_before`), so the
 * classes of its Hyvä templates are part of the theme build. The event only fires where Hyvä is installed, so
 * the module needs no Hyvä package. A failure is logged and never aborts the config generation.
 */
class RegisterModuleForHyvaConfig implements ObserverInterface
{
    /**
     * @param ComponentRegistrar $componentRegistrar
     * @param DirectoryList $directoryList
     * @param LoggerInterface $logger
     * @param string $moduleName
     */
    public function __construct(
        private readonly ComponentRegistrar $componentRegistrar,
        private readonly DirectoryList $directoryList,
        private readonly LoggerInterface $logger,
        private readonly string $moduleName = 'PixelPerfect_NewsletterSignup'
    ) {
    }

    /**
     * Appends the module path, relative to the Magento root, to the extensions of the Hyvä config
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $config = $observer->getData('config');
            if (!$config instanceof DataObject) {
                return;
            }
            $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $this->moduleName);
            if ($path === null) {
                return;
            }
            $root = rtrim($this->directoryList->getRoot(), '/') . '/';
            $relative = str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
            $extensions = $config->getData('extensions') ?? [];
            $extensions[] = ['src' => $relative];
            $config->setData('extensions', $extensions);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Newsletter sign-up module could not be added to the Hyvä config: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
