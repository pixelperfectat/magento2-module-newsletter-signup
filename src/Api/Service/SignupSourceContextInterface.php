<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Service;

/**
 * Decides which source a sign-up in the current request is credited to
 *
 * Order of precedence: the source an entry point set for this request, then the source remembered in the
 * shopper's session, then "other". Unknown codes are ignored everywhere.
 *
 * @api
 */
interface SignupSourceContextInterface
{
    /**
     * Credit sign-ups in this request to the source, for entry points such as the checkout
     *
     * @param string $code
     * @return void
     */
    public function useSource(string $code): void;

    /**
     * Remember the source for the rest of the shopper's visit, for example from a link's source parameter
     *
     * @param string $code
     * @return void
     */
    public function rememberSource(string $code): void;

    /**
     * Name of the storefront request parameter that carries a source code
     *
     * @return string
     */
    public function getParameterName(): string;

    /**
     * Code of the source a sign-up made now is credited to
     *
     * @return string
     */
    public function getSource(): string;
}
