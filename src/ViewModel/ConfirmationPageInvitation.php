<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use PixelPerfect\NewsletterSignup\Api\Service\InvitationDecisionInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceContextInterface;

/**
 * Data for the newsletter sign-up block on the order confirmation page, from the last order in the session
 */
class ConfirmationPageInvitation implements ArgumentInterface
{
    public const XML_PATH_BENEFIT_TEXT = 'newsletter/signup_confirmation_page/benefit_text';
    public const XML_PATH_TRACKING_PARAMS = 'newsletter/signup_confirmation_page/tracking_params';

    /**
     * @param CheckoutSession $checkoutSession
     * @param InvitationDecisionInterface $invitationDecision
     * @param SignupSourceContextInterface $sourceContext
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $url
     * @param EncoderInterface $urlEncoder
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly InvitationDecisionInterface $invitationDecision,
        private readonly SignupSourceContextInterface $sourceContext,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $url,
        private readonly EncoderInterface $urlEncoder
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
