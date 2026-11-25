<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Service;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutOptInInterface;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutSignupInterface;
use Psr\Log\LoggerInterface;

/**
 * Checkout configuration of the sign-up line: whether it is on, whether it invites the cart's email address at
 * page load, the stored choice and the benefit text
 */
class CheckoutSignupConfigProvider implements ConfigProviderInterface
{
    public const CONFIG_KEY = 'pixelperfectNewsletterSignup';
    public const XML_PATH_ENABLED = 'newsletter/signup_checkout/enabled';
    public const XML_PATH_BENEFIT_TEXT = 'newsletter/signup_checkout/benefit_text';

    /**
     * @param CheckoutSession $checkoutSession
     * @param CheckoutSignupInterface $checkoutSignup
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CheckoutSignupInterface $checkoutSignup,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<string, array<string, bool|string>>
     */
    public function getConfig(): array
    {
        if (!$this->scopeConfig->isSetFlag(InvitationDecision::XML_PATH_NEWSLETTER_ACTIVE, ScopeInterface::SCOPE_STORE)
            || !$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE)
        ) {
            return [self::CONFIG_KEY => ['enabled' => false]];
        }

        $invited = false;
        $optIn = false;
        try {
            $quote = $this->checkoutSession->getQuote();
            $quoteId = (int)$quote->getId();
            if ($quoteId > 0) {
                $invited = $this->checkoutSignup->isInvited($quoteId);
                $optIn = $invited && (bool)$quote->getData(CheckoutOptInInterface::QUOTE_FIELD);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Newsletter sign-up: the checkout line could not be prepared: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return [
            self::CONFIG_KEY => [
                'enabled' => true,
                'invited' => $invited,
                'optIn' => $optIn,
                'benefitText' => (string)$this->scopeConfig->getValue(
                    self::XML_PATH_BENEFIT_TEXT,
                    ScopeInterface::SCOPE_STORE
                ),
            ],
        ];
    }
}
