import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  select(event) {
    this.pendingFrameId = event.currentTarget.dataset.turboFrame;
  }

  focusFrame(event) {
    if (!this.pendingFrameId || event.target.id !== this.pendingFrameId) return;

    const focusTarget = event.target.querySelector('[data-tab-focus]') || event.target;
    if (!focusTarget.hasAttribute('tabindex')) focusTarget.setAttribute('tabindex', '-1');
    focusTarget.focus({ preventScroll: false });
    this.pendingFrameId = null;
  }
}
