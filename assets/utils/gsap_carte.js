import Splide from '@splidejs/splide';
import { whenGsapReady } from '../gsap.js';

const ROOT_SELECTOR = '.hermes-front-sections .post-content .hermes-carte';

function initHero(gsap, root) {
    const hero = root.querySelector('.hermes-carte__hero');
    if (!hero || hero.dataset.gsapCarteHeroDone === '1') {
        return;
    }

    hero.dataset.gsapCarteHeroDone = '1';

    const img = hero.querySelector('.hermes-carte__hero-img');
    const overlay = hero.querySelector('.hermes-carte__hero-overlay');
    const kicker = hero.querySelector('.hermes-carte__hero-kicker');

    const tl = gsap.timeline({ defaults: { ease: 'power2.out' } });

    if (img) {
        gsap.set(img, { scale: 1.06 });
        tl.to(img, { scale: 1, duration: 1.4, ease: 'power1.out' }, 0);
    }

    if (overlay) {
        tl.from(overlay, { opacity: 0, duration: 0.85 }, 0);
    }

    if (kicker) {
        tl.from(kicker, { y: 18, opacity: 0, duration: 0.65 }, 0.15);
    }
}

function revealBlock(gsap, block) {
    if (block.dataset.gsapCarteRevealed === '1') {
        return;
    }

    block.dataset.gsapCarteRevealed = '1';

    const headLines = block.querySelectorAll('.hermes-carte__head-line');
    const headTitle = block.querySelector('.hermes-carte__head-title');
    const items = block.querySelectorAll('.hermes-carte__item');

    const tl = gsap.timeline({ defaults: { ease: 'power2.out' } });

    if (headLines.length) {
        tl.from(headLines, {
            scaleX: 0,
            opacity: 0,
            duration: 0.55,
            stagger: 0.08,
            transformOrigin: 'center center',
        });
    }

    if (headTitle) {
        tl.from(headTitle, { y: 22, opacity: 0, duration: 0.6 }, headLines.length ? '-=0.35' : 0);
    }

    if (items.length) {
        tl.from(items, { y: 26, opacity: 0, duration: 0.5, stagger: 0.07 }, '-=0.2');
        tl.from(block.querySelectorAll('.hermes-carte__item-price'), {
            scale: 0.85,
            opacity: 0,
            duration: 0.4,
            stagger: 0.07,
        }, '-=0.45');
    }
}

function revealDrinksPage(gsap, root) {
    const page = root.querySelector('.hermes-carte__page--drinks');
    if (!page || page.dataset.gsapCarteDrinksRevealed === '1') {
        return;
    }

    page.dataset.gsapCarteDrinksRevealed = '1';

    const banner = page.querySelector('.hermes-carte__drinks-banner');
    const panels = page.querySelectorAll('.hermes-carte__drink-panel');
    const note = page.querySelector('.hermes-carte__drinks-note');

    const tl = gsap.timeline({ defaults: { ease: 'power2.out' } });

    if (banner) {
        const img = banner.querySelector('.hermes-carte__drinks-banner-img');
        const content = banner.querySelector('.hermes-carte__drinks-banner-content');

        if (img) {
            gsap.set(img, { scale: 1.08 });
            tl.to(img, { scale: 1, duration: 1.1, ease: 'power1.out' }, 0);
        }

        if (content) {
            tl.from(content.children, { y: 28, opacity: 0, duration: 0.65, stagger: 0.1 }, 0.12);
        }
    }

    if (panels.length) {
        tl.from(panels, {
            y: 36,
            opacity: 0,
            duration: 0.55,
            stagger: 0.09,
        }, banner ? '-=0.35' : 0);
    }

    if (note) {
        tl.from(note, { opacity: 0, y: 12, duration: 0.45 }, '-=0.15');
    }
}

function updateTabs(root, index) {
    root.querySelectorAll('[data-hermes-carte-tab]').forEach((button) => {
        const tabIndex = parseInt(button.dataset.hermesCarteTab || '0', 10);
        const isActive = tabIndex === index;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
}

function initPagesSplide(gsap, root) {
    const pagesEl = root.querySelector('.hermes-carte__pages-splide');
    if (!pagesEl || pagesEl.dataset.hermesCartePagesMounted === '1') {
        return;
    }

    pagesEl.dataset.hermesCartePagesMounted = '1';

    const splide = new Splide(pagesEl, {
        type: 'slide',
        perPage: 1,
        perMove: 1,
        gap: 0,
        padding: 0,
        arrows: true,
        pagination: false,
        drag: true,
        speed: 800,
        easing: 'cubic-bezier(0.33, 1, 0.68, 1)',
        autoHeight: true,
        rewind: false,
        flickPower: 400,
        flickMaxPages: 1,
    });

    splide.on('mounted', () => {
        updateTabs(root, splide.index);
    });

    splide.on('move', (newIndex) => {
        updateTabs(root, newIndex);
        if (newIndex === 1) {
            revealDrinksPage(gsap, root);
        }
    });

    splide.mount();
    pagesEl.splide = splide;

    root.querySelectorAll('[data-hermes-carte-tab]').forEach((button) => {
        if (button.dataset.hermesCarteTabBound === '1') {
            return;
        }

        button.dataset.hermesCarteTabBound = '1';
        button.addEventListener('click', () => {
            const index = parseInt(button.dataset.hermesCarteTab || '0', 10);
            splide.go(index);
        });
    });
}

function observeBlocks(gsap, root) {
    const blocks = root.querySelectorAll('.hermes-carte__food-wrap .hermes-carte__block');

    if (!blocks.length || typeof IntersectionObserver === 'undefined') {
        blocks.forEach((block) => revealBlock(gsap, block));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            revealBlock(gsap, entry.target);
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -4% 0px',
    });

    blocks.forEach((block) => observer.observe(block));
}

function initCarteBlock(gsap, root) {
    if (root.dataset.gsapCarteMounted === '1') {
        return;
    }

    root.dataset.gsapCarteMounted = '1';
    initHero(gsap, root);
    initPagesSplide(gsap, root);
    observeBlocks(gsap, root);
}

export function initPostContentGsapCarte(root = document) {
    whenGsapReady((gsap) => {
        root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
            initCarteBlock(gsap, block);
        });
    });
}
