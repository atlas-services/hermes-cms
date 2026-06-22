import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        enabled: Boolean,
        storagePrefix: { type: String, default: 'hermes:topbar-dismissed:' },
    };

    connect() {
        if (!this.enabledValue) {
            this.revealPendingSections();
            return;
        }

        if (!this.storageAvailable()) {
            this.revealPendingSections();
            return;
        }

        this.syncSectionVisibility();
        this.onAlertClose = this.onAlertClose.bind(this);
        this.element.addEventListener('close.bs.alert', this.onAlertClose);
    }

    disconnect() {
        if (this.onAlertClose) {
            this.element.removeEventListener('close.bs.alert', this.onAlertClose);
        }
    }

    syncSectionVisibility() {
        this.sectionElements().forEach((section) => {
            if (localStorage.getItem(this.storageKey(section)) === '1') {
                section.classList.add('d-none');
                section.removeAttribute('data-hermes-topbar-pending-display');

                return;
            }

            this.revealSection(section);
        });
    }

    onAlertClose(event) {
        const section = event.target.closest('[data-hermes-topbar-section-id]');
        if (!section) {
            return;
        }

        localStorage.setItem(this.storageKey(section), '1');
        section.classList.add('d-none');
    }

    sectionElements() {
        return this.element.querySelectorAll('[data-hermes-topbar-section-id]');
    }

    revealPendingSections() {
        this.element.querySelectorAll('[data-hermes-topbar-pending-display]').forEach((section) => {
            this.revealSection(section);
        });
    }

    revealSection(section) {
        section.classList.remove('d-none');
        section.removeAttribute('data-hermes-topbar-pending-display');
    }

    storageKey(section) {
        return `${this.storagePrefixValue}${section.dataset.hermesTopbarSectionId}`;
    }

    storageAvailable() {
        try {
            const key = `${this.storagePrefixValue}test`;
            localStorage.setItem(key, '1');
            localStorage.removeItem(key);

            return true;
        } catch {
            return false;
        }
    }
}
