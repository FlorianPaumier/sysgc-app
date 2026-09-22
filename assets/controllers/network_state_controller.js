import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['banner'];

  connect() {
    this.onOffline = () => this.showOffline();
    this.onOnline = () => this.hideOffline();
    window.addEventListener('offline', this.onOffline);
    window.addEventListener('online', this.onOnline);
    if (window.navigator.onLine === false) this.showOffline();
  }

  disconnect() {
    window.removeEventListener('offline', this.onOffline);
    window.removeEventListener('online', this.onOnline);
  }

  showOffline() {
    if (this.hasBannerTarget) this.bannerTarget.hidden = false;
  }

  hideOffline() {
    if (this.hasBannerTarget) this.bannerTarget.hidden = true;
  }
}
