<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceContextInterface;

/**
 * Remembers the source parameter of an uncached storefront request, such as a form post, for the rest of the visit
 */
class RememberSignupSource implements ObserverInterface
{
    /**
     * @param RequestInterface $request
     * @param SignupSourceContextInterface $context
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly SignupSourceContextInterface $context
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $code = $this->request->getParam($this->context->getParameterName());
        if (is_string($code) && $code !== '') {
            $this->context->rememberSource($code);
        }
    }
}
