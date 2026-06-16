/*
 * This file is part of the TYPO3 CMS extension "monitoring".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

(function () {
  'use strict';

  var SELECTOR = '.t3js-monitoring-copy-button';
  var FEEDBACK_TIMEOUT_MS = 1500;

  function attach(button) {
    if (button.dataset.monitoringClipboardWired === '1') {
      return;
    }
    button.dataset.monitoringClipboardWired = '1';

    var defaultLabel = button.dataset.monitoringClipboardLabelDefault || button.textContent;
    var successLabel = button.dataset.monitoringClipboardLabelSuccess || defaultLabel;
    var errorLabel = button.dataset.monitoringClipboardLabelError || defaultLabel;

    button.addEventListener('click', function () {
      var value = button.dataset.monitoringClipboardValue || '';

      if (value === '' || !navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
        showFeedback(button, errorLabel, defaultLabel);
        return;
      }

      navigator.clipboard.writeText(value).then(
        function () { showFeedback(button, successLabel, defaultLabel); },
        function () { showFeedback(button, errorLabel, defaultLabel); }
      );
    });
  }

  function showFeedback(button, transientLabel, restoreLabel) {
    button.textContent = transientLabel;
    button.disabled = true;

    window.setTimeout(function () {
      button.textContent = restoreLabel;
      button.disabled = false;
    }, FEEDBACK_TIMEOUT_MS);
  }

  function init() {
    document.querySelectorAll(SELECTOR).forEach(attach);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
