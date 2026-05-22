import { Controller } from '@hotwired/stimulus';

/**
 * Chevron page : clic = scroll selon l’icône visible, puis bascule immédiate.
 */
export default class extends Controller {
    static targets = ['navLink', 'iconDown', 'iconUp', 'accueilDown'];

    static values = {
        scrollThreshold: { type: Number, default: 8 },
    };

    connect() {
        this.onScroll = this.onScroll.bind(this);

        this.pointsDown = true;
        this.renderNavIcon();

        window.addEventListener('scroll', this.onScroll, { passive: true });
    }

    disconnect() {
        window.removeEventListener('scroll', this.onScroll);
    }

    onScroll() {
        if (this.hasAccueilDownTarget && !this.accueilDownTarget.hidden) {
            const atTop = this.getScrollTop() < this.scrollThresholdValue;
            this.accueilDownTarget.hidden = !atTop;
        }
    }

    navigate(event) {
        event.preventDefault();
        event.stopPropagation();

        if (this.hasAccueilDownTarget && event.currentTarget.closest('#chevron_accueil_down_div')) {
            this.scrollToTop();
            this.accueilDownTarget.hidden = true;
            this.pointsDown = true;
            this.renderNavIcon();
            return;
        }

        const scrollDown = this.pointsDown;

        if (scrollDown) {
            this.scrollToBottom();
        } else {
            this.scrollToTop();
        }

        this.pointsDown = !scrollDown;
        this.renderNavIcon();
    }

    renderNavIcon() {
        const showDown = this.pointsDown;

        if (this.hasIconDownTarget) {
            this.iconDownTarget.hidden = !showDown;
            this.iconDownTarget.classList.toggle('d-none', !showDown);
            this.iconDownTarget.setAttribute('aria-hidden', showDown ? 'false' : 'true');
        }

        if (this.hasIconUpTarget) {
            this.iconUpTarget.hidden = showDown;
            this.iconUpTarget.classList.toggle('d-none', showDown);
            this.iconUpTarget.setAttribute('aria-hidden', showDown ? 'true' : 'false');
        }

        if (this.hasNavLinkTarget) {
            const label = showDown
                ? this.navLinkTarget.dataset.labelDown || 'Descendre'
                : this.navLinkTarget.dataset.labelUp || 'Remonter';
            this.navLinkTarget.setAttribute('aria-label', label);
        }
    }

    scrollToTop() {
        this.smoothScrollTo(0);
    }

    scrollToBottom() {
        this.smoothScrollTo(this.getMaxScrollTop());
    }

    smoothScrollTo(top) {
        const root = document.documentElement;

        root.scrollTo({ top, left: 0, behavior: 'smooth' });
        window.scrollTo({ top, left: 0, behavior: 'smooth' });

        let snapped = false;
        const snap = () => {
            if (snapped) {
                return;
            }
            snapped = true;
            root.scrollTop = top;
            document.body.scrollTop = top;
            window.scrollTo({ top, left: 0, behavior: 'auto' });
        };

        window.addEventListener('scrollend', snap, { once: true });
        window.setTimeout(snap, 1000);
    }

    getMaxScrollTop() {
        const doc = document.documentElement;
        const body = document.body;
        const scrollHeight = Math.max(
            body?.scrollHeight ?? 0,
            body?.offsetHeight ?? 0,
            doc.scrollHeight,
            doc.offsetHeight,
        );

        return Math.max(0, scrollHeight - window.innerHeight);
    }

    getScrollTop() {
        const doc = document.documentElement;
        const body = document.body;

        return Math.max(
            window.scrollY || 0,
            doc.scrollTop || 0,
            body.scrollTop || 0,
        );
    }
}
