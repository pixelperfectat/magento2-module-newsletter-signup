<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PixelPerfect\NewsletterSignup\Api\Data\SubscriptionStatus;
use PixelPerfect\NewsletterSignup\Api\Service\InvitationDecisionInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SubscriptionStatusCheckInterface;
use Psr\Log\LoggerInterface;

class InvitationDecision implements InvitationDecisionInterface
{
    public const XML_PATH_NEWSLETTER_ACTIVE = 'newsletter/general/active';
    public const XML_PATH_ALLOW_GUEST_SUBSCRIBE = 'newsletter/subscription/allow_guest_subscribe';

    /**
     * @param SubscriptionStatusCheckInterface $statusCheck
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param array<string, string> $places Configuration group path per place code, e.g.
     *     `newsletter/signup_checkout`; the group holds the `enabled` and `hide_unsubscribed` flags
     */
    public function __construct(
        private readonly SubscriptionStatusCheckInterface $statusCheck,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly array $places = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function shouldInvite(string $place, string $email, int $storeId, bool $isGuest): bool
    {
        $group = $this->places[$place] ?? null;
        if ($group === null
            || trim($email) === ''
            || !$this->flag(self::XML_PATH_NEWSLETTER_ACTIVE, $storeId)
            || !$this->flag($group . '/enabled', $storeId)
            || ($isGuest && !$this->flag(self::XML_PATH_ALLOW_GUEST_SUBSCRIBE, $storeId))
        ) {
            return false;
        }

        try {
            $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
            $status = $this->statusCheck->getStatus($email, $websiteId);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Newsletter sign-up: the subscription status could not be read: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return false;
        }

        return match ($status) {
            SubscriptionStatus::NotSubscribed => true,
            SubscriptionStatus::Unsubscribed => !$this->flag($group . '/hide_unsubscribed', $storeId),
            default => false,
        };
    }

    /**
     * Store-view flag
     *
     * @param string $path
     * @param int $storeId
     * @return bool
     */
    private function flag(string $path, int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
