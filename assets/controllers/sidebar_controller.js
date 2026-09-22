import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['sidebar', 'toggle'];

  connect() {
    const collapsed = localStorage.getItem(this.#storageKey) === '1';
    this.#apply(collapsed);
  }

  toggle() {
    const isCurrentlyCollapsed = this.sidebarTarget.classList.contains('app-sidebar--collapsed');
    this.#apply(!isCurrentlyCollapsed);
  }

  #apply(collapsed) {
    this.sidebarTarget.classList.toggle('app-sidebar--collapsed', collapsed);
    this.element.classList.toggle('app-shell--sidebar-collapsed', collapsed);
    this.element.dataset.sidebarCollapsed = String(collapsed);
    for (const btn of this.toggleTargets) {
      btn.setAttribute('aria-expanded', String(!collapsed));
      btn.setAttribute(
        'aria-label',
        collapsed ? 'Agrandir la barre latérale' : 'Réduire la barre latérale',
      );
    }
    localStorage.setItem(this.#storageKey, collapsed ? '1' : '0');
  }

  get #storageKey() {
    // Per-user key, set via data-sidebar-storage-key-value in the template.
    return this.data.get('storage-key') ?? 'sysgc:sidebar';
  }
}
