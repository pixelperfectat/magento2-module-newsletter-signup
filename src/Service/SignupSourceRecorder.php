<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Service;

use Magento\Framework\Stdlib\DateTime\DateTime;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceContextInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceRecorderInterface;
use PixelPerfect\NewsletterSignup\Model\ResourceModel\SignupSource as SignupSourceResource;

class SignupSourceRecorder implements SignupSourceRecorderInterface
{
    /**
     * @param SignupSourceResource $resource
     * @param SignupSourceContextInterface $context
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly SignupSourceResource $resource,
        private readonly SignupSourceContextInterface $context,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @inheritDoc
     */
    public function record(int $subscriberId): void
    {
        if ($this->resource->getSourceCode($subscriberId) !== null) {
            return;
        }

        $this->resource->saveSource($subscriberId, $this->context->getSource(), $this->dateTime->gmtDate());
    }
}
