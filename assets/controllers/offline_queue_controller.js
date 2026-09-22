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
      const payload = Object.fromEntries(['weather', 'incidentReport', 'checklistItems', 'hazardPhotos', 'workerSignatures']
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
    if (!live || queue.length === 0) return;

    const event = queue[0];
    event.status = 'syncing';
    localStorage.setItem(this.keyValue, JSON.stringify(queue));
    this.updateBanner(queue);
    for (const [name, value] of Object.entries(event.payload || event)) live.set(name, value);
    try {
      await live.action('sync', { clientEventId: event.clientEventId, baseVersion: event.baseVersion });
      event.status = 'synced';
      queue.shift();
      localStorage.setItem(this.keyValue, JSON.stringify(queue));
      this.updateBanner(queue);
      if (queue.length > 0) await this.sync();
    } catch (_) {
      // Keep the report in local storage and retry after the next online event.
    }
  }

  updateBanner(queue) {
    if (!this.hasBannerTarget) return;
    this.bannerTarget.hidden = queue.length === 0;
    if (queue.length > 0) this.bannerTarget.textContent = `Hors ligne — ${queue.length} modification${queue.length > 1 ? 's' : ''} en attente.`;
  }

  liveComponent() {
    const root = this.element.querySelector('[data-controller~="live"]');
    const live = root && this.application.getControllerForElementAndIdentifier(root, 'live');
    return live?.component || null;
  }
}
