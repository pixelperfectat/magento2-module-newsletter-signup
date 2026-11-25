<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Data;

/**
 * A place a newsletter sign-up can come from, such as the checkout or the website footer
 *
 * @api
 */
interface SignupSourceInterface
{
    /**
     * Code stored with the subscriber and accepted in the source parameter
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Label shown in the admin
     *
     * @return string
     */
    public function getLabel(): string;
}
