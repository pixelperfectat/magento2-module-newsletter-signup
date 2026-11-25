<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Controller\Source;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceContextInterface;

/**
 * Remembers a link's source parameter in the session; called from cached pages, which cannot write the session
 */
class Remember implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param SignupSourceContextInterface $context
     * @param RawFactory $rawFactory
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly SignupSourceContextInterface $context,
        private readonly RawFactory $rawFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(): Raw
    {
        $code = $this->request->getParam($this->context->getParameterName());
        if (is_string($code) && $code !== '') {
            $this->context->rememberSource($code);
        }

        return $this->rawFactory->create()
            ->setHttpResponseCode(204)
            ->setHeader('Cache-Control', 'no-store', true)
            ->setContents('');
    }
}
