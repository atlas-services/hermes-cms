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
        this.items = [];
        this.selectedType = '__all__';
        this.selectedIri = '';
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
            this.items = (data.items || []).map((item) => this.normalizeItem(item));
            if (this.items.length === 0) {
                this.setStatus('Catalogue vide ou format non reconnu.');

                return;
            }
            this.setStatus('');
            this.renderCatalog();
        } catch (e) {
            this.setStatus('Erreur réseau.');
            console.error(e);
        }
    }

    normalizeItem(item) {
        const rawType = typeof item.type === 'string' ? item.type.trim() : '';
        const type = rawType || '__none__';

        return {
            ...item,
            iri: item.iri || '',
            label: item.label || item.iri || '—',
            type,
            typeLabel: rawType || 'Sans type',
        };
    }

    renderCatalog() {
        if (!this.hasListTarget) {
            return;
        }

        const types = this.catalogTypes();
        if (this.selectedType !== '__all__' && !types.some((type) => type.value === this.selectedType)) {
            this.selectedType = '__all__';
        }

        const filteredItems = this.filteredItems();
        if (!filteredItems.some((item) => item.iri === this.selectedIri)) {
            this.selectedIri = filteredItems[0]?.iri || '';
        }

        this.listTarget.innerHTML = '';
        this.listTarget.appendChild(this.renderControls(types, filteredItems));
        this.listTarget.appendChild(this.renderGroupedList(filteredItems));
    }

    catalogTypes() {
        const map = new Map();
        for (const item of this.items) {
            if (!map.has(item.type)) {
                map.set(item.type, item.typeLabel);
            }
        }

        return Array.from(map.entries())
            .map(([value, label]) => ({ value, label }))
            .sort((a, b) => a.label.localeCompare(b.label, 'fr', { sensitivity: 'base' }));
    }

    filteredItems() {
        const items = this.selectedType === '__all__'
            ? this.items
            : this.items.filter((item) => item.type === this.selectedType);

        return [...items].sort((a, b) => {
            const byType = a.typeLabel.localeCompare(b.typeLabel, 'fr', { sensitivity: 'base' });

            return byType !== 0 ? byType : a.label.localeCompare(b.label, 'fr', { sensitivity: 'base' });
        });
    }

    renderControls(types, filteredItems) {
        const wrapper = document.createElement('div');
        wrapper.className = 'border rounded p-2 mb-3 bg-light';

        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end';

        const typeCol = document.createElement('div');
        typeCol.className = 'col-12 col-lg-4';
        const typeLabel = document.createElement('label');
        typeLabel.className = 'form-label small mb-1';
        typeLabel.textContent = 'Filtrer par type';
        const typeSelect = document.createElement('select');
        typeSelect.className = 'form-select form-select-sm';
        typeSelect.appendChild(this.optionElement('__all__', 'Tous les types'));
        for (const type of types) {
            typeSelect.appendChild(this.optionElement(type.value, type.label));
        }
        typeSelect.value = this.selectedType;
        typeSelect.addEventListener('change', () => {
            this.selectedType = typeSelect.value;
            this.selectedIri = '';
            this.renderCatalog();
        });
        typeCol.appendChild(typeLabel);
        typeCol.appendChild(typeSelect);

        const templateCol = document.createElement('div');
        templateCol.className = 'col-12 col-lg-5';
        const templateLabel = document.createElement('label');
        templateLabel.className = 'form-label small mb-1';
        templateLabel.textContent = 'Sélectionner un modèle';
        const templateSelect = document.createElement('select');
        templateSelect.className = 'form-select form-select-sm';
        for (const item of filteredItems) {
            templateSelect.appendChild(this.optionElement(item.iri, `${item.label} (${item.typeLabel})`));
        }
        templateSelect.value = this.selectedIri;
        templateSelect.disabled = filteredItems.length === 0;
        templateSelect.addEventListener('change', () => {
            this.selectedIri = templateSelect.value;
        });
        templateCol.appendChild(templateLabel);
        templateCol.appendChild(templateSelect);

        const actionsCol = document.createElement('div');
        actionsCol.className = 'col-12 col-lg-3 d-flex gap-2';
        const previewBtn = document.createElement('button');
        previewBtn.type = 'button';
        previewBtn.className = 'btn btn-sm btn-outline-secondary flex-fill';
        previewBtn.textContent = this.previewLabelValue || 'Prévisualiser';
        previewBtn.disabled = !this.selectedIri;
        previewBtn.addEventListener('click', () => {
            const item = this.selectedItem();
            if (item) {
                this.openPreview(item.iri, item.label);
            }
        });

        const insertBtn = document.createElement('button');
        insertBtn.type = 'button';
        insertBtn.className = 'btn btn-sm btn-outline-primary flex-fill';
        insertBtn.textContent = this.insertLabelValue || 'Insérer';
        insertBtn.disabled = !this.selectedIri;
        insertBtn.addEventListener('click', () => {
            if (this.selectedIri) {
                this.insertItem(this.selectedIri);
            }
        });
        actionsCol.appendChild(previewBtn);
        actionsCol.appendChild(insertBtn);

        row.appendChild(typeCol);
        row.appendChild(templateCol);
        row.appendChild(actionsCol);
        wrapper.appendChild(row);

        return wrapper;
    }

    renderGroupedList(items) {
        const wrapper = document.createElement('div');
        const groups = new Map();
        for (const item of items) {
            if (!groups.has(item.type)) {
                groups.set(item.type, { label: item.typeLabel, items: [] });
            }
            groups.get(item.type).items.push(item);
        }

        for (const group of Array.from(groups.values()).sort((a, b) => a.label.localeCompare(b.label, 'fr', { sensitivity: 'base' }))) {
            const heading = document.createElement('h6');
            heading.className = 'text-uppercase small text-muted mt-3 mb-1';
            heading.textContent = group.label;
            wrapper.appendChild(heading);

            const ul = document.createElement('ul');
            ul.className = 'list-group list-group-flush small';
            for (const item of group.items) {
                ul.appendChild(this.renderItemRow(item));
            }
            wrapper.appendChild(ul);
        }

        return wrapper;
    }

    renderItemRow(item) {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap';
        const span = document.createElement('span');
        span.className = 'me-auto';
        span.textContent = item.label;
        const wrap = document.createElement('div');
        wrap.className = 'd-flex flex-shrink-0 gap-1';
        const previewBtn = document.createElement('button');
        previewBtn.type = 'button';
        previewBtn.className = 'btn btn-sm btn-outline-secondary';
        previewBtn.textContent = this.previewLabelValue || 'Prévisualiser';
        previewBtn.addEventListener('click', () => this.openPreview(item.iri, item.label));
        const insertBtn = document.createElement('button');
        insertBtn.type = 'button';
        insertBtn.className = 'btn btn-sm btn-outline-primary';
        insertBtn.textContent = this.insertLabelValue || 'Insérer';
        insertBtn.addEventListener('click', () => this.insertItem(item.iri));
        wrap.appendChild(previewBtn);
        wrap.appendChild(insertBtn);
        li.appendChild(span);
        li.appendChild(wrap);

        return li;
    }

    optionElement(value, label) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;

        return option;
    }

    selectedItem() {
        return this.items.find((item) => item.iri === this.selectedIri) || null;
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
        const html = this._pendingHtml;
        this.scrollToEditor();
        this.setStatus('Fragment inséré dans l’éditeur.');
        this._pendingHtml = null;
        this.modalInstance()?.hide();
        window.setTimeout(() => {
            document.dispatchEvent(new CustomEvent('hermes:insertHtml', { detail: { html } }));
        }, 150);
    }

    async insertItem(iri) {
        try {
            this.scrollToEditor();
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

    scrollToEditor() {
        document.dispatchEvent(new CustomEvent('hermes:scrollToEditor'));
    }

    _wrapPreviewDocument(fragmentHtml) {
        const importmapEl = document.querySelector('script[type="importmap"]');
        const importmap = importmapEl ? importmapEl.textContent : '';
        const stylesheetLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
            .filter((link) => /button-flair|card-flair|bootstrap/.test(link.href))
            .map((link) => `<link rel="stylesheet" href="${link.href}">`)
            .join('');
        const polyfill = document.querySelector('script[src*="es-module-shims"]');
        const polyfillTag = polyfill ? `<script async src="${polyfill.src}"></script>` : '';

        return `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">${stylesheetLinks}<script type="importmap">${importmap}</script>${polyfillTag}<script type="module">import 'app';</script></head><body><div class="post-content hermes-front-sections">${fragmentHtml}</div></body></html>`;
    }

    _escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;

        return d.innerHTML;
    }
}
