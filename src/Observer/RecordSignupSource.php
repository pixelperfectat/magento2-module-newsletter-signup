<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceRecorderInterface;
use Psr\Log\LoggerInterface;

/**
 * Records the sign-up source whenever Magento saves a subscriber who has just signed up
 */
class RecordSignupSource implements ObserverInterface
{
    private const SIGNED_UP = [
        Subscriber::STATUS_SUBSCRIBED,
        Subscriber::STATUS_NOT_ACTIVE,
        Subscriber::STATUS_UNCONFIRMED,
    ];

    /**
     * @param SignupSourceRecorderInterface $recorder
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SignupSourceRecorderInterface $recorder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $subscriber = $observer->getEvent()->getData('subscriber');
        if (!$subscriber instanceof Subscriber
            || !$subscriber->getId()
            || !$subscriber->isStatusChanged()
            || !in_array((int)$subscriber->getSubscriberStatus(), self::SIGNED_UP, true)
        ) {
            return;
        }

        try {
            $this->recorder->record((int)$subscriber->getId());
        } catch (\Throwable $e) {
            // A missing source must never stop a sign-up
            $this->logger->error('Newsletter sign-up source not recorded: ' . $e->getMessage());
        }
    }
}
