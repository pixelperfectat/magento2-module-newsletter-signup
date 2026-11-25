<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Service;

use PixelPerfect\NewsletterSignup\Api\Data\SignupSourceInterface;

/**
 * The known sign-up sources: the defaults from di.xml plus the ones added in the admin configuration
 *
 * @api
 */
interface SignupSourceListInterface
{
    /**
     * All known sources, defaults first
     *
     * @return SignupSourceInterface[]
     */
    public function getAll(): array;

    /**
     * Whether the code belongs to a known source
     *
     * @param string $code
     * @return bool
     */
    public function isKnown(string $code): bool;
}
