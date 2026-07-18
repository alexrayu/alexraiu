/**
 * @file
 * Copies the language class from <code> to its parent <pre>.
 *
 * Imported markdown emits <pre><code class="language-*">, but PrismJS block
 * styling (background, padding, overflow scrolling) targets the <pre>. Mirroring
 * the class onto the <pre> lets the active Prism theme style the block correctly.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.codeHighlightPre = {
    attach: function (context) {
      once('code-highlight-pre', 'pre > code[class*="language-"]', context).forEach(function (code) {
        var lang = (code.className.match(/language-[\w-]+/) || [])[0];
        if (lang) {
          code.parentNode.classList.add(lang);
        }
      });
    }
  };
})(Drupal, once);
