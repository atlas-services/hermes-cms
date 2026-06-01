import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['locale', 'menu', 'section', 'pagesJson'];

    static values = {
        defaultLocale: String,
        showSection: Boolean,
    };

    connect() {
        this.pages = this.loadPages();
        this.refresh();
    }

    loadPages() {
        if (!this.hasPagesJsonTarget) {
            return [];
        }

        try {
            const parsed = JSON.parse(this.pagesJsonTarget.textContent.trim());

            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.error('content-transfer-targets: invalid pages JSON', error);

            return [];
        }
    }

    refresh() {
        const locale = (this.localeTarget.value || this.defaultLocaleValue || '').toLowerCase();
        const pages = this.pages.filter((p) => String(p.locale || '').toLowerCase() === locale);

        const currentMenu = this.menuTarget.value;
        this.menuTarget.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = this.menuTarget.dataset.placeholder || '—';
        this.menuTarget.appendChild(placeholder);

        for (const page of pages) {
            const opt = document.createElement('option');
            opt.value = String(page.id);
            opt.textContent = page.displayLabel || String(page.label || '').replace(/^\[[A-Z]+\]\s*/, '');
            if (String(page.id) === currentMenu) {
                opt.selected = true;
            }
            this.menuTarget.appendChild(opt);
        }

        if (this.showSectionValue && this.hasSectionTarget) {
            this.refreshSections();
        }
    }

    refreshSections() {
        const menuId = parseInt(this.menuTarget.value, 10);
        const currentSection = this.sectionTarget.value;
        this.sectionTarget.innerHTML = '';

        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = this.sectionTarget.dataset.newSectionLabel || '';
        this.sectionTarget.appendChild(empty);

        if (!menuId) {
            return;
        }

        const page = this.pages.find((p) => Number(p.id) === menuId);
        if (!page || !page.sections) {
            return;
        }

        for (const section of page.sections) {
            const opt = document.createElement('option');
            opt.value = String(section.id);
            opt.textContent = section.label;
            if (String(section.id) === currentSection) {
                opt.selected = true;
            }
            this.sectionTarget.appendChild(opt);
        }
    }
}
