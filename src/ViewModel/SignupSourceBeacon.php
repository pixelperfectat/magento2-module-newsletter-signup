<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceContextInterface;

/**
 * Data for the storefront script that hands a link's source parameter to the shop, also from cached pages
 */
class SignupSourceBeacon implements ArgumentInterface
{
    /**
     * @param SignupSourceContextInterface $context
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly SignupSourceContextInterface $context,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Name of the source parameter
     *
     * @return string
     */
    public function getParameterName(): string
    {
        return $this->context->getParameterName();
    }

    /**
     * URL that remembers the source in the session
     *
     * @return string
     */
    public function getRememberUrl(): string
    {
        return $this->url->getUrl('newslettersignup/source/remember');
    }
}
