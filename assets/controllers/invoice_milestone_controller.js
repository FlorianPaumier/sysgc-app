import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['percentage', 'amount', 'preview'];
    static values = { budget: Number };
    connect() { this.update(); }
    update() {
        const percentage = Number(this.percentageTarget.value || 0);
        const amount = this.budgetValue * percentage / 100;
        if (Number.isFinite(amount) && amount >= 0) {
            this.amountTarget.value = amount.toFixed(2);
            if (this.hasPreviewTarget) this.previewTarget.textContent = `${amount.toLocaleString('fr-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} $`;
        }
    }
}
