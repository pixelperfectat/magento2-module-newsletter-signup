<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Model\Data;

use PixelPerfect\NewsletterSignup\Api\Data\SignupSourceInterface;

class SignupSource implements SignupSourceInterface
{
    /**
     * @param string $code
     * @param string $label
     */
    public function __construct(
        private readonly string $code,
        private readonly string $label
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }
}
