import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    this.onPhotoUploaded = (event) => this.forward('addPhoto', { path: event.detail.dataUrl });
    this.onSignatureAdded = (event) => this.forward('addSignature', event.detail);
    this.element.addEventListener('photo:uploaded', this.onPhotoUploaded);
    this.element.addEventListener('signature:added', this.onSignatureAdded);
  }

  disconnect() {
    this.element.removeEventListener('photo:uploaded', this.onPhotoUploaded);
    this.element.removeEventListener('signature:added', this.onSignatureAdded);
  }

  forward(action, parameters) {
    const root = this.element.closest('[data-controller~="live"]');
    const live = root && this.application.getControllerForElementAndIdentifier(root, 'live');
    if (!live?.component) return;
    if (this.element.ownerDocument.defaultView.navigator.onLine) {
      live.component.action(action, parameters, 0);
      return;
    }

    const property = action === 'addPhoto' ? 'photos' : 'signatures';
    const value = action === 'addPhoto' ? parameters.path : parameters;
    live.component.set(property, [...(live.component.getData(property) || []), value]);
  }
}
