<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Service;

/**
 * Records where a newsletter subscriber signed up; the first recorded source is kept
 *
 * @api
 */
interface SignupSourceRecorderInterface
{
    /**
     * Record the current sign-up source for the subscriber, unless one is recorded already
     *
     * @param int $subscriberId
     * @return void
     */
    public function record(int $subscriberId): void;
}
