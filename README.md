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
