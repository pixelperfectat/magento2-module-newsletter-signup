<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceListInterface;

/**
 * Known sign-up sources as code => label, for the admin subscriber grid filter
 */
class SignupSourceOptions implements OptionSourceInterface
{
    /**
     * @param SignupSourceListInterface $sourceList
     */
    public function __construct(
        private readonly SignupSourceListInterface $sourceList
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<string, string>
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->sourceList->getAll() as $source) {
            $options[$source->getCode()] = $source->getLabel();
        }

        return $options;
    }
}
