import Modal from '@typo3/backend/modal.js';
import RegularEvent from '@typo3/core/event/regular-event.js';

class PreviewModal {
  constructor() {
    new RegularEvent('click', (event, target) => {
      event.preventDefault();

      const url = target.dataset.previewModalUrl;

      if (!url) {
        return;
      }

      Modal.advanced({
        type: Modal.types.iframe,
        title: target.dataset.previewModalTitle ?? '',
        content: url,
        size: Modal.sizes.large,
      });
    }).delegateTo(document, '[data-preview-modal]');
  }
}

export default new PreviewModal();
