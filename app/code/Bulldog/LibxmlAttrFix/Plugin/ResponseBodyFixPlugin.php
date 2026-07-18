<?php
declare(strict_types=1);

namespace Bulldog\LibxmlAttrFix\Plugin;

use Magento\Framework\App\Response\Http;
use Psr\Log\LoggerInterface;

/**
 * Repairs data-* JSON-init attributes corrupted by the libxml2 2.15.1
 * attribute-normalization regression.
 *
 * Root cause: Magento core always renders JSON-config attributes like
 * data-mage-init with SINGLE quotes as the HTML attribute delimiter, e.g.:
 *
 *     data-mage-init='{"validation": {"errorClass": "mage-error"}}'
 *
 * That is valid HTML: single quotes wrap the attribute, double quotes are
 * free to use inside for the JSON. When content passes through a
 * DOMDocument-based re-serialization step server-side (Page Builder content
 * rendering, in our case) on a server running libxml2 >= 2.15.1, the
 * serializer re-emits the attribute using DOUBLE quotes on the outside
 * WITHOUT escaping the inner double quotes as &quot;. The result:
 *
 *     data-mage-init="{"validation": {"errorClass": "mage-error"}}"
 *
 * A browser's HTML parser reads that as the attribute closing at the first
 * inner ", leaving the visible value as a bare "{" — which then fails
 * JSON.parse() in mage/apply/main.js with:
 *   "Uncaught SyntaxError: JSON.parse: end of data while reading object
 *   contents"
 *
 * This plugin scans the final response body for that specific corruption
 * signature and rewrites the attribute back to the valid single-quoted
 * form, without touching anything else on the page.
 *
 * This is a stop-gap safety net, not a replacement for the real fix
 * (pinning/downgrading libxml2, or switching PHP builds on the host to one
 * that doesn't bundle the affected libxml2 version). Remove this plugin
 * once the underlying library issue is resolved server-side.
 */
class ResponseBodyFixPlugin
{
    /**
     * Matches: `some-attr="{ ... }"` where the value starts with { and ends
     * with the first `}"` that is followed by whitespace, `/`, or `>` — i.e.
     * the boundary of the next attribute or the tag close. Lazy quantifier
     * keeps this from over-matching across multiple corrupted attributes.
     */
    private const CORRUPTION_PATTERN =
        '/(data-mage-init|data-bind|data-widget-options)="(\{.*?\})"(?=[\s\/>])/s';

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Http $subject
     * @return void
     */
    public function beforeSendResponse(Http $subject): void
    {
        $body = $subject->getBody();

        if (!is_string($body) || $body === '' || strpos($body, 'data-mage-init="{') === false) {
            // Fast bail-out: nothing that looks like the corruption signature.
            return;
        }

        $fixedCount = 0;

        $fixedBody = preg_replace_callback(
            self::CORRUPTION_PATTERN,
            function (array $matches) use (&$fixedCount): string {
                [$fullMatch, $attrName, $jsonCandidate] = $matches;

                // Sanity-check that what we captured is actually valid JSON
                // before rewriting anything — if it's not, leave the markup
                // alone rather than risk mangling something unrelated.
                json_decode($jsonCandidate);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $fullMatch;
                }

                $fixedCount++;

                return $attrName . "='" . $jsonCandidate . "'";
            },
            $body
        );

        if ($fixedCount > 0 && $fixedBody !== null) {
            $this->logger->warning(
                sprintf(
                    'Bulldog_LibxmlAttrFix: repaired %d corrupted data-* attribute(s) '
                    . 'on %s (libxml2 attribute-normalization regression workaround).',
                    $fixedCount,
                    $subject->getHeader('X-Original-Url') ?: ($_SERVER['REQUEST_URI'] ?? 'unknown')
                )
            );
            $subject->setBody($fixedBody);
        }
    }
}
