import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static values = { duration: { type: Number, default: 3000 } };

  connect() {
    if (this.durationValue > 0) {
      this.timeout = window.setTimeout(() => this.dismiss(), this.durationValue);
    }
  }

  disconnect() {
    if (this.timeout) window.clearTimeout(this.timeout);
  }

  dismiss() {
    this.element.remove();
  }
}
