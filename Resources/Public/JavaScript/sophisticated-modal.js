import Modal from '@typo3/backend/modal.js';
import RegularEvent from '@typo3/core/event/regular-event.js';

class SophisticatedModal {
  constructor() {
    new RegularEvent('click', (event, target) => {
      event.preventDefault();

      const url = target.dataset.sophisticatedModalUrl;

      if (!url) {
        return;
      }

      Modal.advanced({
        type: Modal.types.iframe,
        title: target.dataset.sophisticatedModalTitle ?? '',
        content: url,
        size: Modal.sizes.large,
      });
    }).delegateTo(document, '[data-sophisticated-modal]');
  }
}

export default new SophisticatedModal();
