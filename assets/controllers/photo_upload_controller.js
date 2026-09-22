import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['input'];

  async upload() {
    const [file] = this.inputTarget.files;
    if (!file) return;
    if (file.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      this.element.dispatchEvent(new CustomEvent('photo:error', { bubbles: true }));
      return;
    }

    const dataUrl = await this.compress(file);
    this.element.dispatchEvent(new CustomEvent('photo:uploaded', { bubbles: true, detail: { dataUrl } }));
  }

  compress(file) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => {
        const width = Math.min(image.width, 1200);
        const height = Math.round(image.height * (width / image.width));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d').drawImage(image, 0, 0, width, height);
        resolve(canvas.toDataURL('image/jpeg', 0.8));
      };
      image.onerror = reject;
      image.src = URL.createObjectURL(file);
    });
  }
}
