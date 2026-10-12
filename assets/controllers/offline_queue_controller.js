import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['banner'];
  static values = { key: String };

  connect() {
    this.onOnline = () => this.sync();
    window.addEventListener('online', this.onOnline);
  }

  disconnect() {
    window.removeEventListener('online', this.onOnline);
  }

  saved(event) {
    if (this.element.ownerDocument.defaultView.navigator.onLine) return;

    this.queue(event.detail || {});
  }

  queueSafetyReport(event) {
    if (this.element.ownerDocument.defaultView.navigator.onLine) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    this.queue(this.report());
  }

  toggleChecklistItem(event) {
    if (this.element.ownerDocument.defaultView.navigator.onLine) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const live = this.liveComponent();
    const index = Number(event.currentTarget.dataset.liveIndexParam);
    const items = [...(live?.getData('checklistItems') || [])];
    if (!items[index]) return;
    items[index] = { ...items[index], checked: !items[index].checked };
    live.set('checklistItems', items, false);
    event.currentTarget.setAttribute('aria-pressed', String(items[index].checked));
    event.currentTarget.classList.toggle('is-checked', items[index].checked);
  }

  queue(report) {
    const queue = JSON.parse(localStorage.getItem(this.keyValue) || '[]');
    const payload = report && report.payload ? report.payload : report;
    const fingerprint = JSON.stringify(payload);
    if (queue.some((entry) => entry.fingerprint === fingerprint)) {
      this.updateBanner(queue);
      return;
    }
    queue.push({
      clientEventId: globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`,
      baseVersion: report?.baseVersion || 1,
      payload,
      fingerprint,
      status: 'queued',
    });
    localStorage.setItem(this.keyValue, JSON.stringify(queue));
    this.updateBanner(queue);
  }

  report() {
    const live = this.liveComponent();
    if (live) {
      const payload = Object.fromEntries(['weather', 'incidentReport', 'checklistItems', 'photos', 'signatures']
        .map((name) => [name, live.getData(name)]));
      return { payload, baseVersion: live.getData('version') || 1 };
    }

    return {
      weather: this.element.querySelector('[data-model="weather"]')?.value || '',
      incidentReport: this.element.querySelector('[data-model="incidentReport"]')?.value || '',
    };
  }

  async sync() {
    const live = this.liveComponent();
    const queue = JSON.parse(localStorage.getItem(this.keyValue) || '[]');
    this.updateBanner(queue);
    if (!live || queue.length === 0 || this.syncing || queue[0].status === 'conflict' || !this.element.ownerDocument.defaultView.navigator.onLine) return;

    this.syncing = true;
    const event = queue[0];
    event.status = 'syncing';
    localStorage.setItem(this.keyValue, JSON.stringify(queue));
    try {
      for (const [name, value] of Object.entries(event.payload || event)) live.set(name, value, false);
      const result = await live.action('sync', { clientEventId: event.clientEventId, baseVersion: event.baseVersion });
      if (!result.response.ok || !result.response.headers.get('Content-Type')?.includes('application/vnd.live-component+html')) throw new Error('Safety synchronization failed');
      if ((live.getData('syncConflicts') || []).length > 0) {
        event.status = 'conflict';
        return;
      }
      event.status = 'synced';
      queue.shift();
    } catch (_) {
      event.status = 'queued';
    } finally {
      this.syncing = false;
      localStorage.setItem(this.keyValue, JSON.stringify(queue));
      this.updateBanner(queue);
    }
    if (queue.length > 0 && event.status !== 'queued') await this.sync();
  }

  retry() {
    const queue = JSON.parse(localStorage.getItem(this.keyValue) || '[]');
    if (queue[0]?.status !== 'conflict') return;
    queue[0].baseVersion = this.liveComponent()?.getData('version');
    queue[0].status = 'queued';
    localStorage.setItem(this.keyValue, JSON.stringify(queue));
    return this.sync();
  }

  updateBanner(queue) {
    if (!this.hasBannerTarget) return;
    this.bannerTarget.hidden = queue.length === 0;
    if (queue[0]?.status === 'conflict') {
      this.bannerTarget.textContent = 'Conflit : vos modifications restent enregistrées sur cet appareil.';
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.textContent = 'Appliquer mes modifications à la version actuelle';
      retry.dataset.action = 'offline-queue#retry';
      this.bannerTarget.append(retry);
      return;
    }
    if (queue.length > 0) this.bannerTarget.textContent = `Hors ligne — ${queue.length} modification${queue.length > 1 ? 's' : ''} en attente.`;
  }

  liveComponent() {
    const root = this.element.querySelector('[data-controller~="live"]');
    const live = root && this.application.getControllerForElementAndIdentifier(root, 'live');
    return live?.component || null;
  }
}
