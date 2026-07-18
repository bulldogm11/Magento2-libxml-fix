<?php
/**
 * Bulldog_LibxmlAttrFix
 *
 * Repairs HTML attributes (e.g. data-mage-init) that get corrupted when the
 * server's libxml2 (2.15.1+) re-serializes Page Builder / widget-rendered
 * content, flipping a valid single-quoted attribute containing double-quoted
 * JSON into a double-quoted attribute with unescaped inner quotes — which
 * breaks JSON.parse() in the browser.
 *
 * See: https://swissuplabs.com/blog/magento-2-widget-failures-triggered-by-libxml-2-15-1-immediate-patch-available/
 */
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Bulldog_LibxmlAttrFix',
    __DIR__
);
