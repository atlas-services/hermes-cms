import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/**
 * Panneau admin : catalogue modèles « libre » via API Hermes + insertion dans CKEditor 5.
 */
export default class extends Controller {
    static targets = ['list', 'status', 'modal', 'modalTitle', 'previewFrame', 'confirmInsertBtn'];

    static values = {
        catalogUrl: String,
        htmlUrl: String,
        previewLabel: String,
        insertLabel: String,
        modalHeading: String,
        insertEditorLabel: String,
        closeLabel: String,
        loadingLabel: String,
    };

    connect() {
        this._pendingHtml = null;
        this._bootstrapModal = null;
        this.loadCatalog();
    }

    disconnect() {
        this._bootstrapModal?.dispose();
        this._bootstrapModal = null;
    }

    async loadCatalog() {
        if (!this.hasListTarget) {
            return;
        }
        this.setStatus('…');
        try {
            const res = await fetch(this.catalogUrlValue, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!data.enabled) {
                this.setStatus(data.hint || '');
                this.listTarget.innerHTML = '';

                return;
            }
            const items = data.items || [];
            if (items.length === 0) {
                this.setStatus('Catalogue vide ou format non reconnu.');

                return;
            }
            this.setStatus('');
            this.listTarget.innerHTML = '';
            const ul = document.createElement('ul');
            ul.className = 'list-group list-group-flush small';
            for (const it of items) {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap';
                const span = document.createElement('span');
                span.className = 'me-auto';
                span.textContent = it.label || it.iri;
                const wrap = document.createElement('div');
                wrap.className = 'd-flex flex-shrink-0 gap-1';
                const previewBtn = document.createElement('button');
                previewBtn.type = 'button';
                previewBtn.className = 'btn btn-sm btn-outline-secondary';
                previewBtn.textContent = this.previewLabelValue || 'Prévisualiser';
                previewBtn.addEventListener('click', () => this.openPreview(it.iri, it.label || it.iri));
                const insertBtn = document.createElement('button');
                insertBtn.type = 'button';
                insertBtn.className = 'btn btn-sm btn-outline-primary';
                insertBtn.textContent = this.insertLabelValue || 'Insérer';
                insertBtn.addEventListener('click', () => this.insertItem(it.iri));
                wrap.appendChild(previewBtn);
                wrap.appendChild(insertBtn);
                li.appendChild(span);
                li.appendChild(wrap);
                ul.appendChild(li);
            }
            this.listTarget.appendChild(ul);
        } catch (e) {
            this.setStatus('Erreur réseau.');
            console.error(e);
        }
    }

    setStatus(msg) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = msg || '';
        }
    }

    modalInstance() {
        if (!this.hasModalTarget) {
            return null;
        }
        if (!this._bootstrapModal) {
            this._bootstrapModal = new Modal(this.modalTarget);
        }

        return this._bootstrapModal;
    }

    /** @returns {Promise<string|null>} */
    async fetchHtml(iri) {
        const url = `${this.htmlUrlValue}?iri=${encodeURIComponent(iri)}`;
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) {
            return null;
        }
        const data = await res.json();
        const html = data.html;

        return typeof html === 'string' && html !== '' ? html : null;
    }

    async openPreview(iri, label) {
        const modal = this.modalInstance();
        if (!modal) {
            return;
        }
        this._pendingHtml = null;
        if (this.hasModalTitleTarget) {
            this.modalTitleTarget.textContent = `${this.modalHeadingValue || ''} — ${label}`;
        }
        if (this.hasConfirmInsertBtnTarget) {
            this.confirmInsertBtnTarget.disabled = true;
        }
        if (this.hasPreviewFrameTarget) {
            const loading = this.loadingLabelValue || '…';
            this.previewFrameTarget.srcdoc = `<!DOCTYPE html><html><head><meta charset="utf-8"><title></title></head><body><p class="p-3 text-muted">${this._escapeHtml(loading)}</p></body></html>`;
        }
        modal.show();

        try {
            const html = await this.fetchHtml(iri);
            if (!html) {
                this.setStatus('Impossible de charger ce modèle.');
                if (this.hasPreviewFrameTarget) {
                    this.previewFrameTarget.srcdoc = `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><p class="p-3 text-danger">Erreur de chargement.</p></body></html>`;
                }

                return;
            }
            this._pendingHtml = html;
            if (this.hasPreviewFrameTarget) {
                this.previewFrameTarget.srcdoc = this._wrapPreviewDocument(html);
            }
            if (this.hasConfirmInsertBtnTarget) {
                this.confirmInsertBtnTarget.disabled = false;
            }
        } catch (e) {
            console.error(e);
            this.setStatus('Erreur réseau.');
            if (this.hasPreviewFrameTarget) {
                this.previewFrameTarget.srcdoc = `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><p class="p-3 text-danger">Erreur réseau.</p></body></html>`;
            }
        }
    }

    confirmInsert() {
        if (!this._pendingHtml) {
            return;
        }
        document.dispatchEvent(new CustomEvent('hermes:insertHtml', { detail: { html: this._pendingHtml } }));
        this.setStatus('Fragment inséré dans l’éditeur.');
        this._pendingHtml = null;
        this.modalInstance()?.hide();
    }

    async insertItem(iri) {
        try {
            const html = await this.fetchHtml(iri);
            if (!html) {
                this.setStatus('Impossible de charger ce modèle.');

                return;
            }
            document.dispatchEvent(new CustomEvent('hermes:insertHtml', { detail: { html } }));
            this.setStatus('Fragment inséré dans l’éditeur.');
        } catch (e) {
            this.setStatus('Erreur réseau.');
            console.error(e);
        }
    }

    _wrapPreviewDocument(fragmentHtml) {
        const base = '<base target="_blank" rel="noopener noreferrer">';

        return `<!DOCTYPE html><html><head><meta charset="utf-8">${base}<meta name="viewport" content="width=device-width, initial-scale=1"></head><body>${fragmentHtml}</body></html>`;
    }

    _escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;

        return d.innerHTML;
    }
}
