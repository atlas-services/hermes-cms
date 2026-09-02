import gsap from 'gsap';

const FLAIR_CARDS_ROOT = '.hermes-flair-cards';
const FLAIR_THEME_ROOT = `${FLAIR_CARDS_ROOT}, .hermes-front-sections[data-hermes-flair-button-bg]`;
const BUTTON_SELECTOR = '.hermes-front-sections .post-content a[data-hermes-button-flair], .post-content a[data-hermes-button-flair], a[data-hermes-button-flair], .hermes-flair-cards a[data-hermes-button-flair], .hermes-flair-cards a.button, .post-content a.button';
const CARD_SELECTOR = '.hermes-front-sections .post-content [data-hermes-card-flair], .post-content [data-hermes-card-flair], .post-content .card, .hermes-front-sections .post-content .card, [data-hermes-card-flair], .hermes-flair-cards .card';

const FLAIR_CARD_SKIP = [
    '.content-showcase-card',
    '.content-showcase .card',
    '.atw-glass',
    '.atw-transparent',
].join(', ');

const CARD_CLASSES_TO_STRIP = ['shadow-sm', 'shadow', 'border-0'];

const CARD_UTILITIES_TO_KEEP = /^(h-100|mb-[0-9]+|mt-[0-9]+|mx-auto|w-100)$/;

const FLAIR_BUTTON_SKIP = [
    '.carousel-control-prev',
    '.carousel-control-next',
    '.btn-floating',
    '.btn-close',
    '.portfolio-link',
    '[data-bs-toggle]',
    '[data-bs-dismiss]',
    '[data-bs-slide]',
].join(', ');

const BTN_CLASSES_TO_STRIP = [
    'btn', 'btn-lg', 'btn-sm', 'btn-primary', 'btn-secondary', 'btn-dark',
    'btn-outline-primary', 'btn-outline-dark', 'btn-outline-light', 'btn-outline-secondary',
    'border', 'fw-semibold', 'rounded-pill', 'rounded-0',
    'hermes-hero-present__btn', 'hermes-hero-present__btn--primary', 'hermes-hero-present__btn--ghost',
];

const BTN_UTILITIES_TO_KEEP = /^(m[xy]?-[a-z0-9-]+|mt-|mb-|ms-|me-|mx-|my-|p[xy]?-[a-z0-9-]+|d-block|w-100|w-lg-auto)$/;

const THEME_DEFAULTS = {
    buttonBg: '#4a1822',
    buttonHover: '#ff1234',
    cardBg: '#ffffff',
    cardBorder: '#4a1822',
    cardHover: 'rgba(74, 24, 34, 0.2)',
};

function resolveGsap() {
    return (typeof window !== 'undefined' && window.gsap) ? window.gsap : gsap;
}

function directChild(element, className) {
    return [...element.children].find((child) => child.classList.contains(className)) ?? null;
}

function readThemeFromContainer(container) {
    if (!(container instanceof HTMLElement)) {
        return null;
    }

    const read = (name) => container.getAttribute(name)?.trim() || '';

    const buttonBg = read('data-hermes-flair-button-bg');
    const buttonHover = read('data-hermes-flair-button-hover');
    const cardBg = read('data-hermes-flair-card-bg');
    const cardBorder = read('data-hermes-flair-card-border');
    const cardHover = read('data-hermes-flair-card-hover');
    const accent = read('data-hermes-flair-accent');

    if (!buttonBg && !buttonHover && !cardBg && !cardBorder && !cardHover && !accent) {
        return null;
    }

    const resolvedButtonBg = buttonBg || cardBorder || THEME_DEFAULTS.buttonBg;
    const resolvedCardBorder = cardBorder || buttonBg || THEME_DEFAULTS.cardBorder;

    return {
        accent: accent || resolvedCardBorder,
        buttonBg: resolvedButtonBg,
        buttonHover: buttonHover || THEME_DEFAULTS.buttonHover,
        cardBg: cardBg || THEME_DEFAULTS.cardBg,
        cardBorder: resolvedCardBorder,
        cardHover: cardHover || THEME_DEFAULTS.cardHover,
    };
}

function isFlairButtonScope(link) {
    return Boolean(link.closest(`${FLAIR_CARDS_ROOT}, .post-content, .hermes-front-sections, .ck-content`));
}

function shouldUpgradeToFlairButton(link) {
    if (!(link instanceof HTMLAnchorElement) || !link.getAttribute('href')) {
        return false;
    }

    if (link.matches(FLAIR_BUTTON_SKIP)) {
        return false;
    }

    if (!isFlairButtonScope(link)) {
        return false;
    }

    if (link.hasAttribute('data-hermes-button-flair') || link.classList.contains('button')) {
        return true;
    }

    if (link.classList.contains('btn') || link.classList.contains('hermes-hero-present__btn')) {
        return true;
    }

    return false;
}

function normalizeFlairButton(link) {
    if (link.dataset.hermesButtonFlairNormalized === '1') {
        return;
    }

    const utilities = [...link.classList].filter((className) => BTN_UTILITIES_TO_KEEP.test(className));

    BTN_CLASSES_TO_STRIP.forEach((className) => link.classList.remove(className));
    link.classList.add('button');
    utilities.forEach((className) => link.classList.add(className));

    link.setAttribute('data-hermes-button-flair', '');
    link.style.removeProperty('background-color');
    link.style.removeProperty('color');
    link.style.removeProperty('border-color');

    link.dataset.hermesButtonFlairNormalized = '1';
}

function normalizeAllFlairButtons(root = document) {
    const scope = root instanceof Element ? root : document;

    scope.querySelectorAll(`${FLAIR_CARDS_ROOT} a[href], .post-content a[href], .hermes-front-sections a[href], .ck-content a[href]`).forEach((link) => {
        if (shouldUpgradeToFlairButton(link)) {
            normalizeFlairButton(link);
        }
    });
}

function isFlairButton(link) {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }

    if (shouldUpgradeToFlairButton(link)) {
        normalizeFlairButton(link);
        return true;
    }

    return false;
}

function isFlairCardScope(card) {
    return Boolean(card.closest(`${FLAIR_CARDS_ROOT}, .post-content, .hermes-front-sections, .ck-content, .section-content`));
}

function shouldUpgradeToFlairCard(card) {
    if (!(card instanceof HTMLElement) || !card.classList.contains('card')) {
        return false;
    }

    if (card.matches(FLAIR_CARD_SKIP)) {
        return false;
    }

    if (!isFlairCardScope(card)) {
        return false;
    }

    if (card.hasAttribute('data-hermes-card-flair') || card.classList.contains('hermes-card-flair')) {
        return true;
    }

    if (card.closest(FLAIR_CARDS_ROOT)) {
        return true;
    }

    if (card.querySelector(':scope > .card-body, :scope > .card-img-top, :scope > .card-img-bottom')) {
        return true;
    }

    return false;
}

function normalizeFlairCard(card) {
    if (card.dataset.hermesCardFlairNormalized === '1') {
        return;
    }

    const utilities = [...card.classList].filter((className) => CARD_UTILITIES_TO_KEEP.test(className));

    CARD_CLASSES_TO_STRIP.forEach((className) => card.classList.remove(className));
    card.classList.add('hermes-card-flair', 'shadow-none');
    utilities.forEach((className) => card.classList.add(className));

    card.setAttribute('data-hermes-card-flair', '');
    card.style.removeProperty('background-color');
    card.style.removeProperty('border-color');

    card.dataset.hermesCardFlairNormalized = '1';
}

function normalizeAllFlairCards(root = document) {
    const scope = root instanceof Element ? root : document;

    scope.querySelectorAll(`${FLAIR_CARDS_ROOT} .card, .post-content .card, .hermes-front-sections .card, .ck-content .card, .section-content .card`).forEach((card) => {
        if (shouldUpgradeToFlairCard(card)) {
            normalizeFlairCard(card);
        }
    });
}

function isFlairCard(card) {
    if (!(card instanceof HTMLElement) || !card.classList.contains('card')) {
        return false;
    }

    if (shouldUpgradeToFlairCard(card)) {
        normalizeFlairCard(card);
        return true;
    }

    return false;
}

function applyThemeVars(container, theme) {
    container.style.setProperty('--hermes-flair-accent', theme.accent);
    container.style.setProperty('--hermes-flair-button-bg', theme.buttonBg);
    container.style.setProperty('--hermes-flair-button-hover', theme.buttonHover);
    container.style.setProperty('--hermes-flair-card-bg', theme.cardBg);
    container.style.setProperty('--hermes-flair-card-border', theme.cardBorder);
    container.style.setProperty('--hermes-flair-card-hover', theme.cardHover);
    // alias utilisés par le CSS existant
    container.style.setProperty('--button-bg', theme.buttonBg);
    container.style.setProperty('--button-flair', theme.buttonHover);
    container.style.setProperty('--card-bg', theme.cardBg);
    container.style.setProperty('--card-border', theme.cardBorder);
    container.style.setProperty('--card-flair', theme.cardHover);
    container.style.setProperty('--accent', theme.accent);
}

function applyButtonTheme(button, theme) {
    button.style.setProperty('--button-bg', theme.buttonBg);
    button.style.setProperty('--button-flair', theme.buttonHover);
    button.style.setProperty('background-color', theme.buttonBg);

    const flair = button.querySelector('.button__flair');
    if (flair) {
        flair.style.setProperty('--button-flair', theme.buttonHover);
    }
}

function applyCardTheme(card, theme) {
    card.style.setProperty('--card-bg', theme.cardBg);
    card.style.setProperty('--card-border', theme.cardBorder);
    card.style.setProperty('--card-flair', theme.cardHover);
    card.style.setProperty('border-color', theme.cardBorder);
    card.style.setProperty('background-color', theme.cardBg);

    const flair = card.querySelector('.card__flair');
    if (flair) {
        flair.style.setProperty('--card-flair', theme.cardHover);
    }
}

function readPaletteForElement(element) {
    const container = element?.closest(FLAIR_THEME_ROOT);
    const theme = readThemeFromContainer(container);
    return theme ?? { ...THEME_DEFAULTS, accent: THEME_DEFAULTS.cardBorder };
}

function bindFlairHover(target, flair, { targetScale, duration = 0.5, boundKey }) {
    if (target.dataset[boundKey] === '1') {
        return;
    }

    const g = resolveGsap();
    const flairElement = flair;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        target.dataset[boundKey] = '1';
        return;
    }

    if (typeof g.quickTo !== 'function') {
        console.error('GSAP quickTo unavailable for flair effect');
        return;
    }

    g.set(flairElement, {
        scale: 0,
        x: 0,
        y: 0,
        transformOrigin: '0 0',
        force3D: true,
    });

    const xTo = g.quickTo(flairElement, 'x', { duration, ease: 'power3' });
    const yTo = g.quickTo(flairElement, 'y', { duration, ease: 'power3' });

    const moveFlair = (event) => {
        const rect = target.getBoundingClientRect();
        xTo(event.clientX - rect.left);
        yTo(event.clientY - rect.top);
    };

    const resolveScale = () => (typeof targetScale === 'function' ? targetScale(target) : targetScale);

    target.addEventListener('mouseenter', (event) => {
        moveFlair(event);
        g.to(flairElement, {
            scale: resolveScale(),
            duration,
            ease: 'power3.out',
            overwrite: 'auto',
        });
    });

    target.addEventListener('mouseleave', (event) => {
        moveFlair(event);
        g.to(flairElement, {
            scale: 0,
            duration,
            ease: 'power3.out',
            overwrite: 'auto',
        });
    });

    target.addEventListener('mousemove', moveFlair);

    target.dataset[boundKey] = '1';
}

function cardFlairScale(card) {
    const rect = card.getBoundingClientRect();
    const base = Math.max(rect.width, rect.height, 1);

    return Math.max(2.2, (Math.hypot(rect.width, rect.height) / base) * 2);
}

function resolveFlairContainers(root = document) {
    const containers = new Set();

    if (root instanceof Element && root.matches(FLAIR_CARDS_ROOT)) {
        containers.add(root);
    }

    if (root instanceof Element || root instanceof Document || root instanceof DocumentFragment) {
        root.querySelectorAll(FLAIR_CARDS_ROOT).forEach((container) => containers.add(container));
    }

    return [...containers];
}

function resolveFlairThemeContainers(root = document) {
    const containers = new Set();

    resolveFlairContainers(root).forEach((container) => containers.add(container));

    if (root instanceof Element && root.matches('.hermes-front-sections[data-hermes-flair-button-bg]')) {
        containers.add(root);
    }

    if (root instanceof Element || root instanceof Document || root instanceof DocumentFragment) {
        root.querySelectorAll('.hermes-front-sections[data-hermes-flair-button-bg]').forEach((container) => {
            containers.add(container);
        });
    }

    return [...containers];
}

function applyFlairThemeToContainer(container, theme) {
    applyThemeVars(container, theme);

    container.querySelectorAll('a[data-hermes-button-flair], a.button').forEach((button) => {
        if (container.matches('.hermes-front-sections') && button.closest(FLAIR_CARDS_ROOT)) {
            return;
        }

        applyButtonTheme(button, theme);
    });

    container.querySelectorAll('.card[data-hermes-card-flair], .card.hermes-card-flair').forEach((card) => {
        if (container.matches('.hermes-front-sections') && card.closest(FLAIR_CARDS_ROOT)) {
            return;
        }

        applyCardTheme(card, theme);
    });

    if (container.matches(FLAIR_CARDS_ROOT)) {
        container.querySelectorAll('.gsap-text-reveal').forEach((block) => {
            block.style.setProperty('color', theme.accent);
        });
    }
}

/** Lit les couleurs du template (data-hermes-flair-* sur le conteneur) et les propage aux boutons/cards. */
export function syncHermesFlairPalettes(root = document) {
    normalizeAllFlairButtons(root);
    normalizeAllFlairCards(root);

    resolveFlairThemeContainers(root).forEach((container) => {
        const theme = readThemeFromContainer(container);
        if (!theme) {
            return;
        }

        applyFlairThemeToContainer(container, theme);
    });
}

function ensureButtonStructure(link) {
    link.classList.add('button');

    let flair = directChild(link, 'button__flair');
    if (!flair) {
        flair = document.createElement('span');
        flair.className = 'button__flair';
        flair.setAttribute('aria-hidden', 'true');
        link.insertBefore(flair, link.firstChild);
    }

    let label = directChild(link, 'button__label');
    if (!label) {
        label = document.createElement('span');
        label.className = 'button__label';

        [...link.childNodes].forEach((node) => {
            if (node !== flair) {
                label.appendChild(node);
            }
        });

        if (!label.textContent.trim()) {
            label.textContent = 'En savoir plus';
        }

        link.appendChild(label);
    }

    return flair;
}

function ensureCardStructure(card) {
    card.classList.add('hermes-card-flair');

    let flair = directChild(card, 'card__flair');
    if (!flair) {
        flair = document.createElement('span');
        flair.className = 'card__flair';
        flair.setAttribute('aria-hidden', 'true');
        card.insertBefore(flair, card.firstChild);
    }

    [...card.children].forEach((child) => {
        if (child !== flair && child.classList.contains('card-body')) {
            child.classList.add('card__content');
        }
    });

    return flair;
}

function bindButtonFlair(button) {
    const flair = ensureButtonStructure(button);
    applyButtonTheme(button, readPaletteForElement(button));
    bindFlairHover(button, flair, {
        targetScale: 1,
        duration: 0.45,
        boundKey: 'gsapButtonFlairBound',
    });
}

function bindCardFlair(card) {
    const flair = ensureCardStructure(card);
    applyCardTheme(card, readPaletteForElement(card));
    bindFlairHover(card, flair, {
        targetScale: cardFlairScale,
        duration: 0.55,
        boundKey: 'gsapCardFlairBound',
    });
}

function collectButtons(root = document) {
    const buttons = new Set();

    root.querySelectorAll(BUTTON_SELECTOR).forEach((button) => {
        if (isFlairButton(button)) {
            buttons.add(button);
        }
    });

    return [...buttons];
}

function collectCards(root = document) {
    const cards = new Set();

    root.querySelectorAll(CARD_SELECTOR).forEach((card) => {
        if (isFlairCard(card)) {
            cards.add(card);
        }
    });

    return [...cards];
}

function setupButtonDelegation() {
    if (document.documentElement.dataset.gsapButtonFlairDelegation === '1') {
        return;
    }

    document.documentElement.dataset.gsapButtonFlairDelegation = '1';

    document.addEventListener('mouseover', (event) => {
        const button = event.target.closest('a[href]');
        if (!button || !isFlairButton(button)) {
            return;
        }

        bindButtonFlair(button);
    }, true);
}

function setupCardDelegation() {
    if (document.documentElement.dataset.gsapCardFlairDelegation === '1') {
        return;
    }

    document.documentElement.dataset.gsapCardFlairDelegation = '1';

    document.addEventListener('mouseover', (event) => {
        const card = event.target.closest('.card');
        if (!card || !isFlairCard(card)) {
            return;
        }

        bindCardFlair(card);
    }, true);
}

export function initPostContentGsapButtonFlair(root = document) {
    syncHermesFlairPalettes(root);
    setupButtonDelegation();
    collectButtons(root).forEach((button) => {
        bindButtonFlair(button);
    });
}

export function initPostContentGsapCardFlair(root = document) {
    syncHermesFlairPalettes(root);
    setupCardDelegation();
    collectCards(root).forEach((card) => {
        bindCardFlair(card);
    });
}

function bootHermesFlair() {
    syncHermesFlairPalettes();
    initPostContentGsapButtonFlair();
    initPostContentGsapCardFlair();
}

if (typeof window !== 'undefined') {
    window.hermesInitButtonFlair = initPostContentGsapButtonFlair;
    window.hermesInitCardFlair = initPostContentGsapCardFlair;
    window.hermesSyncFlairPalettes = syncHermesFlairPalettes;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootHermesFlair);
    } else {
        bootHermesFlair();
    }

    window.addEventListener('load', bootHermesFlair);
}
