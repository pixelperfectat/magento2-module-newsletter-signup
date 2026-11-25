<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PixelPerfect\NewsletterSignup\Api\Data\SubscriptionStatus;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutOptInInterface;
use PixelPerfect\NewsletterSignup\Api\Service\InvitationDecisionInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceContextInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SubscriptionStatusCheckInterface;
use Psr\Log\LoggerInterface;

/**
 * Data for the newsletter sign-up block on the order confirmation page, from the last order in the session
 */
class ConfirmationPageInvitation implements ArgumentInterface
{
    public const XML_PATH_BENEFIT_TEXT = 'newsletter/signup_confirmation_page/benefit_text';
    public const XML_PATH_TRACKING_PARAMS = 'newsletter/signup_confirmation_page/tracking_params';
    public const XML_PATH_ENABLED = 'newsletter/signup_confirmation_page/enabled';
    public const XML_PATH_PENDING_NOTE_TEXT = 'newsletter/signup_confirmation_page/pending_note_text';
    public const XML_PATH_SUBSCRIBED_NOTE_TEXT = 'newsletter/signup_confirmation_page/subscribed_note_text';
    public const XML_PATH_NEWSLETTER_ACTIVE = 'newsletter/general/active';

    /**
     * @param CheckoutSession $checkoutSession
     * @param InvitationDecisionInterface $invitationDecision
     * @param SignupSourceContextInterface $sourceContext
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $url
     * @param EncoderInterface $urlEncoder
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param CartRepositoryInterface $cartRepository
     * @param SubscriptionStatusCheckInterface $statusCheck
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly InvitationDecisionInterface $invitationDecision,
        private readonly SignupSourceContextInterface $sourceContext,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $url,
        private readonly EncoderInterface $urlEncoder,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly SubscriptionStatusCheckInterface $statusCheck
    ) {
    }

    /**
     * Whether the block invites the order's email address
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        $order = $this->getOrder();
        if ($order === null) {
            return false;
        }

        return $this->invitationDecision->shouldInvite(
            InvitationDecisionInterface::PLACE_CONFIRMATION_PAGE,
            $this->getEmail(),
            (int)$order->getStoreId(),
            (bool)$order->getCustomerIsGuest()
        );
    }

    /**
     * Note shown instead of the invitation when the shopper ticked the checkout sign-up box for the last order
     *
     * "Check your inbox" while the subscription waits for the confirmation click, "you are subscribed" once it
     * is confirmed. Empty when the box was not ticked, the sign-up did not happen, or the block is switched off;
     * the block then behaves as the invitation.
     *
     * @return string
     */
    public function getNoteText(): string
    {
        $order = $this->getOrder();
        if ($order === null || !$this->isSwitchedOn((int)$order->getStoreId()) || !$this->tickedTheBox($order)) {
            return '';
        }

        $path = match ($this->getStatus($order)) {
            SubscriptionStatus::Pending => self::XML_PATH_PENDING_NOTE_TEXT,
            SubscriptionStatus::Confirmed => self::XML_PATH_SUBSCRIBED_NOTE_TEXT,
            default => null,
        };

        return $path === null
            ? ''
            : (string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, (int)$order->getStoreId());
    }

    /**
     * Email address of the last order
     *
     * @return string
     */
    public function getEmail(): string
    {
        return (string)$this->getOrder()?->getCustomerEmail();
    }

    /**
     * Benefit text of the store view
     *
     * @return string
     */
    public function getBenefitText(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_BENEFIT_TEXT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * URL of Magento's own newsletter sign-up
     *
     * @return string
     */
    public function getFormAction(): string
    {
        return $this->url->getUrl('newsletter/subscriber/new', ['_secure' => true]);
    }

    /**
     * Name of the field that carries the redirect target after the sign-up
     *
     * @return string
     */
    public function getRedirectFieldName(): string
    {
        return ActionInterface::PARAM_NAME_URL_ENCODED;
    }

    /**
     * Encoded page the shopper lands on after signing up: the home page with the tracking parameters
     *
     * @return string
     */
    public function getRedirectValue(): string
    {
        $query = ltrim(
            trim((string)$this->scopeConfig->getValue(self::XML_PATH_TRACKING_PARAMS, ScopeInterface::SCOPE_STORE)),
            '?'
        );
        $params = $query === '' ? [] : ['_query' => $query];

        return $this->urlEncoder->encode($this->url->getUrl('', $params));
    }

    /**
     * Name of the source field
     *
     * @return string
     */
    public function getSourceParameterName(): string
    {
        return $this->sourceContext->getParameterName();
    }

    /**
     * Source code of this block
     *
     * @return string
     */
    public function getSourceCode(): string
    {
        return InvitationDecisionInterface::PLACE_CONFIRMATION_PAGE;
    }

    /**
     * Whether the newsletter and this block are switched on in the order's store view
     *
     * @param int $storeId
     * @return bool
     */
    private function isSwitchedOn(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_NEWSLETTER_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId)
            && $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether the shopper ticked the checkout sign-up box for the order
     *
     * @param Order $order
     * @return bool
     */
    private function tickedTheBox(Order $order): bool
    {
        try {
            $quote = $this->cartRepository->get((int)$order->getQuoteId());
        } catch (\Throwable) {
            return false;
        }

        return $quote instanceof Quote && (bool)$quote->getData(CheckoutOptInInterface::QUOTE_FIELD);
    }

    /**
     * Subscription status of the order's email address on the order's website, null when it cannot be read
     *
     * @param Order $order
     * @return SubscriptionStatus|null
     */
    private function getStatus(Order $order): ?SubscriptionStatus
    {
        try {
            $websiteId = (int)$this->storeManager->getStore((int)$order->getStoreId())->getWebsiteId();
            return $this->statusCheck->getStatus($this->getEmail(), $websiteId);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Newsletter sign-up: the subscription status could not be read: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return null;
        }
    }

    /**
     * Last order placed in this session
     *
     * @return Order|null
     */
    private function getOrder(): ?Order
    {
        $order = $this->checkoutSession->getLastRealOrder();

        return $order->getId() ? $order : null;
    }
}
