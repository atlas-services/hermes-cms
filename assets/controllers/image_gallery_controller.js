import { Controller } from '@hotwired/stimulus';

/**
 * Galerie modale « slide » Hermes (modale1) : une instance par section (`id="image-gallery-{sectionId}"`).
 * Au show.bs.modal, collecte uniquement les vignettes `a[data-bs-target="#" + même id][data-image]`.
 */
export default class extends Controller {
    static targets = ['image', 'title', 'postName', 'postContent'];

    connect() {
        this.items = [];
        this.idx = 0;
        this.onShow = this.onShow.bind(this);
        this.element.addEventListener('show.bs.modal', this.onShow);
    }

    disconnect() {
        this.element.removeEventListener('show.bs.modal', this.onShow);
    }

    gather() {
        this.items = [];
        const modalId = this.element.id;
        if (!modalId) {
            return;
        }
        const sel = 'a[data-bs-target="#' + modalId + '"][data-image]';
        document.querySelectorAll(sel).forEach((a) => {
            this.items.push({
                image: a.getAttribute('data-image') || '',
                name: a.getAttribute('data-name') || '',
                content: a.getAttribute('data-content') || '',
            });
        });
    }

    renderAt(i) {
        if (!this.items.length) {
            return;
        }
        this.idx = (i + this.items.length) % this.items.length;
        const it = this.items[this.idx];
        if (this.hasImageTarget) {
            this.imageTarget.src = it.image;
            this.imageTarget.alt = it.name;
        }
        if (this.hasTitleTarget) {
            this.titleTarget.textContent = it.name;
        }
        if (this.hasPostNameTarget) {
            this.postNameTarget.textContent = it.name;
        }
        if (this.hasPostContentTarget) {
            this.postContentTarget.innerHTML = it.content;
        }
    }

    onShow(event) {
        this.gather();
        const trigger = event.relatedTarget;
        if (trigger && trigger.getAttribute('data-image')) {
            const targetImg = trigger.getAttribute('data-image');
            const found = this.items.findIndex((x) => x.image === targetImg);
            this.idx = found >= 0 ? found : 0;
        } else {
            this.idx = 0;
        }
        this.renderAt(this.idx);
    }

    previous() {
        this.gather();
        this.renderAt(this.idx - 1);
    }

    next() {
        this.gather();
        this.renderAt(this.idx + 1);
    }
}
