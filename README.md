# Bulldog_LibxmlAttrFix

Stop-gap fix for the libxml2 2.15.+ "attribute normalization" regression,
which corrupts `data-mage-init` (and similar JSON-config) attributes when
Page Builder content is re-serialized server-side, e.g.:

    Correct (as rendered by core Magento templates):
    data-mage-init='{"validation": {"errorClass": "mage-error"}}'

    Corrupted (after libxml2 2.15.1 DOM re-serialization):
    data-mage-init="{"validation": {"errorClass": "mage-error"}}"

The corrupted version causes the browser to parse the attribute as just `{`,
which throws:

    Uncaught SyntaxError: JSON.parse: end of data while reading object contents

This module hooks into the very end of the request (`Http::beforeSendResponse`)
and repairs any attribute matching that corruption signature, rewriting it
back to the valid single-quoted form, right before the response is sent to
the browser.

**This is a workaround, not a permanent fix.** The real fix is either:
- pinning/downgrading the server's libxml2 package to a pre-2.15.1 version, or
- switching to a PHP build (e.g. via cPanel's MultiPHP Manager) that doesn't
  bundle the affected libxml2 version.

Remove this module once the underlying library issue is resolved on the
server.

## Install

1. Copy `app/code/Bulldog/LibxmlAttrFix` into your Magento root at the same
   path (`app/code/Bulldog/LibxmlAttrFix`).
2. From the Magento root, run:

   ```bash
   bin/magento module:enable Bulldog_LibxmlAttrFix
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```

   (Skip `setup:di:compile` if you're not running in production mode /
   compiled mode.)

## Test

1. Load a page that previously showed the console error (e.g. the homepage,
   given the newsletter block appears site-wide).
2. Open DevTools → Console — the `JSON.parse` / `SyntaxError` should be gone.
3. View source (`Ctrl+U`) and search for `data-mage-init` — every instance
   should be single-quote delimited, e.g.:

   ```html
   data-mage-init='{"validation": {"errorClass": "mage-error"}}'
   ```

4. Check `var/log/system.log` (or wherever your logger writes `warning`
   level) for entries like:

   ```
   Bulldog_LibxmlAttrFix: repaired 1 corrupted data-* attribute(s) on
   https://sunfoil.com/ (libxml2 attribute-normalization regression
   workaround).
   ```

   If you see zero log entries but the error is also gone, double check
   whether Full Page Cache is serving an old cached copy of the page from
   before you deployed the module — flush FPC and reload.

## Uninstall (once the server-side libxml2 issue is fixed)

```bash
bin/magento module:disable Bulldog_LibxmlAttrFix
rm -rf app/code/Bulldog/LibxmlAttrFix
bin/magento setup:upgrade
bin/magento cache:flush
```
