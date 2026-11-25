<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Service;

use Magento\Framework\Session\Generic as Session;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceContextInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceListInterface;

class SignupSourceContext implements SignupSourceContextInterface
{
    private const SESSION_KEY = 'pixelperfect_newsletter_signup_source';

    /** @var string|null Source an entry point set for this request */
    private ?string $explicitSource = null;

    /**
     * @param Session $session
     * @param SignupSourceListInterface $sourceList
     * @param string $fallbackSource Code used when no known source applies
     * @param string $parameterName Storefront request parameter that carries a source code
     */
    public function __construct(
        private readonly Session $session,
        private readonly SignupSourceListInterface $sourceList,
        private readonly string $fallbackSource = 'other',
        private readonly string $parameterName = 'nl_source'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function useSource(string $code): void
    {
        $this->explicitSource = $code;
    }

    /**
     * @inheritDoc
     */
    public function rememberSource(string $code): void
    {
        if ($this->sourceList->isKnown($code)) {
            $this->session->setData(self::SESSION_KEY, $code);
        }
    }

    /**
     * @inheritDoc
     */
    public function getParameterName(): string
    {
        return $this->parameterName;
    }

    /**
     * @inheritDoc
     */
    public function getSource(): string
    {
        foreach ([$this->explicitSource, $this->session->getData(self::SESSION_KEY)] as $candidate) {
            if (is_string($candidate) && $this->sourceList->isKnown($candidate)) {
                return $candidate;
            }
        }

        return $this->fallbackSource;
    }
}
