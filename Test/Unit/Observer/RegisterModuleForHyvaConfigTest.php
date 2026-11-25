<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Test\Unit\Observer;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\NewsletterSignup\Observer\RegisterModuleForHyvaConfig;
use Psr\Log\LoggerInterface;

class RegisterModuleForHyvaConfigTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testTheModulePathIsAddedRelativeToTheMagentoRoot(): void
    {
        $config = new DataObject(['extensions' => [['src' => 'vendor/other/module/src']]]);

        $this->observer('/var/www/html/vendor/vendor/module/src')->execute(new Observer(['config' => $config]));

        $this->assertSame(
            [['src' => 'vendor/other/module/src'], ['src' => 'vendor/vendor/module/src']],
            $config->getData('extensions')
        );
    }

    public function testTheExtensionsListIsCreatedWhenThereIsNone(): void
    {
        $config = new DataObject();

        $this->observer('/var/www/html/packages/module/src')->execute(new Observer(['config' => $config]));

        $this->assertSame([['src' => 'packages/module/src']], $config->getData('extensions'));
    }

    public function testARootWithATrailingSlashGivesTheSamePath(): void
    {
        $config = new DataObject();

        $this->observer('/var/www/html/packages/module/src', '/var/www/html/')
            ->execute(new Observer(['config' => $config]));

        $this->assertSame([['src' => 'packages/module/src']], $config->getData('extensions'));
    }

    public function testAModuleOutsideTheRootKeepsItsFullPath(): void
    {
        $config = new DataObject();

        $this->observer('/opt/modules/module/src')->execute(new Observer(['config' => $config]));

        $this->assertSame([['src' => '/opt/modules/module/src']], $config->getData('extensions'));
    }

    public function testAnUnknownModuleLeavesTheConfigAlone(): void
    {
        $this->logger->expects($this->never())->method('error');
        $config = new DataObject(['extensions' => []]);

        $this->observer(null)->execute(new Observer(['config' => $config]));

        $this->assertSame([], $config->getData('extensions'));
    }

    public function testAnEventWithoutConfigIsIgnored(): void
    {
        $this->logger->expects($this->never())->method('error');
        $event = new Observer(['config' => ['extensions' => []]]);

        $this->observer('/var/www/html/packages/module/src')->execute($event);

        $this->assertSame(['extensions' => []], $event->getData('config'));
    }

    public function testAFailureIsLoggedAndNeverStopsTheConfigGeneration(): void
    {
        $failure = new \RuntimeException('registrar broken');
        $this->logger->expects($this->once())->method('error')->with(
            'Newsletter sign-up module could not be added to the Hyvä config: registrar broken',
            ['exception' => $failure]
        );
        $config = new DataObject(['extensions' => [['src' => 'vendor/other/module/src']]]);

        $this->observer(null, '/var/www/html', $failure)->execute(new Observer(['config' => $config]));

        $this->assertSame([['src' => 'vendor/other/module/src']], $config->getData('extensions'));
    }

    private function observer(
        ?string $path,
        string $root = '/var/www/html',
        ?\Throwable $failure = null
    ): RegisterModuleForHyvaConfig {
        $registrar = $this->createMock(ComponentRegistrar::class);
        $getPath = $registrar->method('getPath')->with(ComponentRegistrar::MODULE, 'PixelPerfect_NewsletterSignup');
        $failure === null ? $getPath->willReturn($path) : $getPath->willThrowException($failure);
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($root);

        return new RegisterModuleForHyvaConfig($registrar, $directoryList, $this->logger);
    }
}
