<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use PixelPerfect\NewsletterSignup\Api\Data\SignupSourceInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceListInterface;
use PixelPerfect\NewsletterSignup\Model\Data\SignupSource;

class SignupSourceList implements SignupSourceListInterface
{
    public const XML_PATH_EXTRA_SOURCES = 'newsletter/signup_source/extra_sources';

    private const VALID_CODE = '/^[a-z0-9_]{1,64}$/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param SerializerInterface $serializer
     * @param array<string, string> $defaultSources Default sources, code => label
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SerializerInterface $serializer,
        private readonly array $defaultSources = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        return array_values($this->load());
    }

    /**
     * @inheritDoc
     */
    public function isKnown(string $code): bool
    {
        return isset($this->load()[$code]);
    }

    /**
     * Defaults plus valid configured rows; a configured code never replaces a default
     *
     * @return array<string, SignupSourceInterface>
     */
    private function load(): array
    {
        $sources = [];
        foreach ($this->defaultSources as $code => $label) {
            $sources[(string)$code] = new SignupSource((string)$code, (string)$label);
        }
        foreach ($this->readConfiguredRows() as $row) {
            $code = trim((string)($row['code'] ?? ''));
            if (!preg_match(self::VALID_CODE, $code) || isset($sources[$code])) {
                continue;
            }
            $label = trim((string)($row['label'] ?? ''));
            $sources[$code] = new SignupSource($code, $label !== '' ? $label : $code);
        }

        return $sources;
    }

    /**
     * Rows of the admin "extra sources" field
     *
     * @return array<array<string, mixed>>
     */
    private function readConfiguredRows(): array
    {
        try {
            $rows = $this->serializer->unserialize((string)$this->scopeConfig->getValue(self::XML_PATH_EXTRA_SOURCES));
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($rows) ? array_filter($rows, 'is_array') : [];
    }
}
