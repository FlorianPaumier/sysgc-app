import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['canvas', 'name', 'status'];

  connect() {
    this.context = this.canvasTarget.getContext('2d');
    this.canvasTarget.addEventListener('pointerdown', (event) => this.start(event));
    this.canvasTarget.addEventListener('pointermove', (event) => this.draw(event));
    this.canvasTarget.addEventListener('pointerup', () => { this.drawing = false; });
  }

  start(event) {
    this.drawing = true;
    this.context.beginPath();
    this.context.moveTo(event.offsetX, event.offsetY);
  }

  draw(event) {
    if (!this.drawing) return;
    this.context.lineTo(event.offsetX, event.offsetY);
    this.context.stroke();
  }

  clear() {
    this.context.clearRect(0, 0, this.canvasTarget.width, this.canvasTarget.height);
    this.announce('Zone de signature effacée.');
  }

  submit() {
    if (!this.nameTarget.value.trim()) {
      this.nameTarget.focus();
      this.announce('Indiquez le nom complet du travailleur avant de valider la signature.');
      return;
    }
    this.element.dispatchEvent(new CustomEvent('signature:added', {
      bubbles: true,
      detail: { workerName: this.nameTarget.value.trim(), dataUrl: this.canvasTarget.toDataURL('image/png') },
    }));
    this.announce(`Signature de ${this.nameTarget.value.trim()} ajoutée.`);
  }

  announce(message) {
    if (this.hasStatusTarget) this.statusTarget.textContent = message;
  }
}
