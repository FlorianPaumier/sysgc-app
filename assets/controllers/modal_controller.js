import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['panel'];

  connect() {
    this.onKeydown = (event) => this.trapFocus(event);
    this.element.addEventListener('keydown', this.onKeydown);
    if (this.element.hasAttribute('open') && typeof this.element.showModal === 'function') {
      this.element.removeAttribute('open');
      this.element.showModal();
      this.focusFirstElement();
    }
  }

  disconnect() {
    this.element.removeEventListener('keydown', this.onKeydown);
    if (this.previouslyFocused) this.restoreFocus();
  }

  open(event) {
    if (event.detail?.id && event.detail.id !== this.element.id) return;

    this.previouslyFocused = event.detail?.trigger ?? document.activeElement;
    if (typeof this.element.showModal === 'function') {
      if (!this.element.open) this.element.showModal();
    } else {
      this.element.setAttribute('open', '');
    }
    this.focusFirstElement();
  }

  close(event) {
    event?.preventDefault();
    if (typeof this.element.close === 'function') {
      this.element.close();
    } else {
      this.element.removeAttribute('open');
    }
    this.restoreFocus();
  }

  backdropClose(event) {
    if (event.target === this.element) this.close(event);
  }

  focusFirstElement() {
    window.setTimeout(() => {
      if (!this.element.open) return;
      const target = this.focusableElements()[0] ?? this.element;
      if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
      target.focus();
    }, 0);
  }

  trapFocus(event) {
    if (event.key !== 'Tab') return;

    const elements = this.focusableElements();
    if (elements.length === 0) {
      event.preventDefault();
      this.element.focus();
      return;
    }

    const first = elements[0];
    const last = elements[elements.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  focusableElements() {
    return [...this.element.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];
  }

  restoreFocus() {
    const target = this.previouslyFocused;
    this.previouslyFocused = null;
    if (target && typeof target.focus === 'function' && target.isConnected !== false) target.focus();
  }
}
