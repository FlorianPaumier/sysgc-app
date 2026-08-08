import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'toggle'];

    connect() {
        this._closeOnEscape = this._closeOnEscape.bind(this);
    }

    toggle() {
        const open = !this.menuTarget.classList.contains('nav--open');
        if (open) {
            this.menuTarget.classList.add('nav--open');
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', this._closeOnEscape);
        } else {
            this.close();
        }
        this._syncAria(open);
    }

    close() {
        this.menuTarget.classList.remove('nav--open');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', this._closeOnEscape);
        this._syncAria(false);
    }

    _syncAria(open) {
        this.toggleTargets.forEach((el) => {
            el.setAttribute('aria-expanded', String(open));
        });
    }

    _closeOnEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
