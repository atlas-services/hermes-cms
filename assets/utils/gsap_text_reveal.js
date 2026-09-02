import gsap from 'gsap';

const DEFAULTS = {
    chars: {
        x: 150,
        opacity: 0,
        duration: 0.7,
        ease: 'power4.out',
        stagger: 0.04,
    },
    words: {
        y: -100,
        opacity: 0,
        rotationMin: -80,
        rotationMax: 80,
        duration: 0.7,
        ease: 'back.out(1.7)',
        stagger: 0.15,
    },
    lines: {
        rotationX: -100,
        transformOrigin: '50% 50% -160px',
        opacity: 0,
        duration: 0.8,
        ease: 'power3.out',
        stagger: 0.25,
    },
};

const BLOCK_SELECTOR = '.hermes-front-sections .post-content .gsap-text-reveal, .post-content .gsap-text-reveal, .hermes-flair-cards .gsap-text-reveal, .gsap-text-reveal';

function resolveGsap() {
    return (typeof window !== 'undefined' && window.gsap) ? window.gsap : gsap;
}

function toDatasetKey(mode, prop) {
    const modePart = mode.charAt(0).toUpperCase() + mode.slice(1);
    const propPart = prop.charAt(0).toUpperCase() + prop.slice(1);

    return `gsapTextReveal${modePart}${propPart}Value`;
}

function readNumber(dataset, key, fallback) {
    const raw = dataset[key];
    if (raw === undefined || raw === '') {
        return fallback;
    }

    const value = parseFloat(raw);
    return Number.isFinite(value) ? value : fallback;
}

function readString(dataset, key, fallback) {
    const raw = dataset[key];
    return raw === undefined || raw === '' ? fallback : raw;
}

function readModeConfig(root, mode) {
    const defaults = DEFAULTS[mode] || DEFAULTS.chars;
    const dataset = root.dataset;
    const config = { ...defaults };

    if (mode === 'chars') {
        config.x = readNumber(dataset, toDatasetKey(mode, 'x'), defaults.x);
        config.opacity = readNumber(dataset, toDatasetKey(mode, 'opacity'), defaults.opacity);
        config.duration = readNumber(dataset, toDatasetKey(mode, 'duration'), defaults.duration);
        config.stagger = readNumber(dataset, toDatasetKey(mode, 'stagger'), defaults.stagger);
        config.ease = readString(dataset, toDatasetKey(mode, 'ease'), defaults.ease);
    }

    if (mode === 'words') {
        config.y = readNumber(dataset, toDatasetKey(mode, 'y'), defaults.y);
        config.x = readNumber(dataset, toDatasetKey(mode, 'x'), 0);
        config.opacity = readNumber(dataset, toDatasetKey(mode, 'opacity'), defaults.opacity);
        config.duration = readNumber(dataset, toDatasetKey(mode, 'duration'), defaults.duration);
        config.stagger = readNumber(dataset, toDatasetKey(mode, 'stagger'), defaults.stagger);
        config.ease = readString(dataset, toDatasetKey(mode, 'ease'), defaults.ease);
        config.rotationMin = readNumber(dataset, toDatasetKey(mode, 'rotationMin'), defaults.rotationMin);
        config.rotationMax = readNumber(dataset, toDatasetKey(mode, 'rotationMax'), defaults.rotationMax);

        const fixedRotation = dataset[toDatasetKey(mode, 'rotation')];
        if (fixedRotation !== undefined && fixedRotation !== '' && fixedRotation !== 'random') {
            const rotation = parseFloat(fixedRotation);
            if (Number.isFinite(rotation)) {
                config.rotationMin = rotation;
                config.rotationMax = rotation;
            }
        }
    }

    if (mode === 'lines') {
        config.rotationX = readNumber(dataset, toDatasetKey(mode, 'rotationX'), defaults.rotationX);
        config.transformOrigin = readString(dataset, toDatasetKey(mode, 'transformOrigin'), defaults.transformOrigin);
        config.opacity = readNumber(dataset, toDatasetKey(mode, 'opacity'), defaults.opacity);
        config.duration = readNumber(dataset, toDatasetKey(mode, 'duration'), defaults.duration);
        config.stagger = readNumber(dataset, toDatasetKey(mode, 'stagger'), defaults.stagger);
        config.ease = readString(dataset, toDatasetKey(mode, 'ease'), defaults.ease);
    }

    return config;
}

function normalizeText(value) {
    return (value || '').replace(/\s+/g, ' ').trim();
}

function readText(element) {
    const dataset = element.dataset;
    const fromDataset = normalizeText(dataset.gsapTextRevealTextValue);
    if (fromDataset) {
        return fromDataset;
    }

    const fromLabel = normalizeText(element.querySelector('.gsap-text-reveal__label')?.textContent);
    if (fromLabel) {
        return fromLabel;
    }

    const body = element.querySelector('.gsap-text-reveal__body');
    if (!body) {
        return '';
    }

    return normalizeText(body.getAttribute('aria-label') || body.textContent);
}

function isMountedBody(body) {
    return body.classList.contains('gsap-text-reveal__body--chars')
        || body.classList.contains('gsap-text-reveal__body--words')
        || body.classList.contains('gsap-text-reveal__body--lines');
}

function hasSplitMarkup(body) {
    return Boolean(body.querySelector('.gsap-text-reveal__char, .gsap-text-reveal__word, .gsap-text-reveal__line'));
}

function markTextRevealReady(root) {
    root.dataset.gsapTextRevealReady = '1';
    delete root.dataset.gsapTextRevealPending;
}

function markTextRevealPending(root) {
    root.dataset.gsapTextRevealPending = '1';
}

function resetTextRevealBody(body) {
    const g = resolveGsap();
    g.killTweensOf(body.querySelectorAll('.gsap-text-reveal__char, .gsap-text-reveal__word, .gsap-text-reveal__line'));
    body.classList.remove('gsap-text-reveal__body--chars', 'gsap-text-reveal__body--words', 'gsap-text-reveal__body--lines');
    body.style.removeProperty('visibility');
    body.style.removeProperty('opacity');
}

function showPlainTextFallback(root) {
    const body = root.querySelector('.gsap-text-reveal__body');
    if (!body) {
        markTextRevealReady(root);
        return;
    }

    const text = readText(root);
    resetTextRevealBody(body);

    if (text) {
        body.textContent = text;
    }

    markTextRevealReady(root);
}

function prepareTextRevealRoot(root) {
    const body = root.querySelector('.gsap-text-reveal__body');

    if (!body || isMountedBody(body) || hasSplitMarkup(body)) {
        return;
    }

    const text = readText(root);
    if (!text) {
        return;
    }

    root.dataset.gsapTextRevealTextValue = text;
    markTextRevealPending(root);

    if (!body.getAttribute('aria-label')) {
        body.setAttribute('aria-label', text);
    }

    body.replaceChildren(document.createTextNode('\u00a0'));
}

function readLines(element) {
    const dataset = element.dataset;
    const raw = dataset.gsapTextRevealLinesValue
        || element.querySelector('.gsap-text-reveal__label')?.dataset.lines
        || '';

    if (raw.includes('|')) {
        return raw.split('|').map((line) => line.trim()).filter(Boolean);
    }

    if (raw.includes('\n')) {
        return raw.split('\n').map((line) => line.trim()).filter(Boolean);
    }

    return readText(element).split('|').map((line) => line.trim()).filter(Boolean);
}

function mountChars(body, text) {
    body.innerHTML = '';
    body.setAttribute('aria-label', text);

    const chars = [];
    const words = text.split(/\s+/).filter(Boolean);

    words.forEach((word, wordIndex) => {
        const wordWrap = document.createElement('span');
        wordWrap.className = 'gsap-text-reveal__word-wrap';
        wordWrap.setAttribute('aria-hidden', 'true');

        for (const char of word) {
            const span = document.createElement('span');
            span.className = 'gsap-text-reveal__char';
            span.setAttribute('aria-hidden', 'true');
            span.textContent = char;
            span.style.opacity = '0';
            wordWrap.appendChild(span);
            chars.push(span);
        }

        body.appendChild(wordWrap);

        if (wordIndex < words.length - 1) {
            const space = document.createElement('span');
            space.className = 'gsap-text-reveal__char is-space';
            space.setAttribute('aria-hidden', 'true');
            space.textContent = '\u00a0';
            space.style.opacity = '0';
            body.appendChild(space);
            chars.push(space);
        }
    });

    return chars;
}

function mountWords(body, text) {
    body.innerHTML = '';
    body.setAttribute('aria-label', text);

    return text.split(/\s+/).filter(Boolean).map((word) => {
        const span = document.createElement('span');
        span.className = 'gsap-text-reveal__word';
        span.setAttribute('aria-hidden', 'true');
        span.textContent = word;
        span.style.opacity = '0';
        body.appendChild(span);
        return span;
    });
}

function mountLines(body, lines) {
    body.innerHTML = '';
    body.setAttribute('aria-label', lines.join(' '));

    return lines.map((line) => {
        const lineEl = document.createElement('div');
        lineEl.className = 'gsap-text-reveal__line';
        lineEl.setAttribute('aria-hidden', 'true');
        lineEl.textContent = line;
        lineEl.style.opacity = '0';
        body.appendChild(lineEl);
        return lineEl;
    });
}

function playRevealEffect(g, root, mode, targets) {
    const config = readModeConfig(root, mode);

    if (mode === 'chars') {
        return g.fromTo(targets, {
            x: config.x,
            opacity: config.opacity,
        }, {
            x: 0,
            opacity: 1,
            duration: config.duration,
            ease: config.ease,
            stagger: config.stagger,
        });
    }

    if (mode === 'words') {
        const fromVars = {
            y: config.y,
            opacity: config.opacity,
        };
        const toVars = {
            y: 0,
            opacity: 1,
            duration: config.duration,
            ease: config.ease,
            stagger: config.stagger,
        };

        if (config.x) {
            fromVars.x = config.x;
            toVars.x = 0;
        }

        if (config.rotationMin === config.rotationMax) {
            fromVars.rotation = config.rotationMin;
            toVars.rotation = 0;
        } else {
            fromVars.rotation = () => g.utils.random(config.rotationMin, config.rotationMax);
            toVars.rotation = 0;
        }

        return g.fromTo(targets, fromVars, toVars);
    }

    return g.fromTo(targets, {
        rotationX: config.rotationX,
        transformOrigin: config.transformOrigin,
        opacity: config.opacity,
    }, {
        rotationX: 0,
        opacity: 1,
        duration: config.duration,
        ease: config.ease,
        stagger: config.stagger,
    });
}

export function playGsapTextReveal(g, root, mode) {
    const resolvedMode = DEFAULTS[mode] ? mode : 'chars';
    const body = root.querySelector('.gsap-text-reveal__body');

    if (!body) {
        return null;
    }

    const text = readText(root);
    if (!text && resolvedMode !== 'lines') {
        showPlainTextFallback(root);
        return null;
    }

    resetTextRevealBody(body);
    body.classList.add('gsap-text-reveal__body', `gsap-text-reveal__body--${resolvedMode}`);

    let targets = [];
    if (resolvedMode === 'chars') {
        targets = mountChars(body, text);
    } else if (resolvedMode === 'words') {
        targets = mountWords(body, text);
    } else {
        const lines = readLines(root);
        if (!lines.length) {
            showPlainTextFallback(root);
            return null;
        }
        targets = mountLines(body, lines);
    }

    if (!targets.length) {
        showPlainTextFallback(root);
        return null;
    }

    root.dataset.gsapTextRevealActiveMode = resolvedMode;
    markTextRevealReady(root);

    return playRevealEffect(g, root, resolvedMode, targets);
}

function bindRevealControls(g, root) {
    const buttons = root.querySelectorAll('[data-gsap-text-reveal-mode]');
    const defaultMode = root.dataset.gsapTextRevealModeValue || 'chars';

    const activate = (mode) => {
        const tween = playGsapTextReveal(g, root, mode);
        if (!tween) {
            showPlainTextFallback(root);
        }
        buttons.forEach((button) => {
            const isActive = button.dataset.gsapTextRevealMode === mode;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    buttons.forEach((button) => {
        if (button.dataset.gsapTextRevealBound === '1') {
            return;
        }
        button.dataset.gsapTextRevealBound = '1';
        button.addEventListener('click', () => activate(button.dataset.gsapTextRevealMode));
    });

    activate(defaultMode);
}

function mountTextRevealBlock(g, element) {
    if (element.dataset.gsapTextRevealReady === '1') {
        return;
    }

    prepareTextRevealRoot(element);

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        showPlainTextFallback(element);
        return;
    }

    try {
        bindRevealControls(g, element);
        element.dataset.gsapTextRevealMounted = '1';
    } catch (error) {
        console.error('[hermes] gsap-text-reveal init failed', error);
        delete element.dataset.gsapTextRevealMounted;
        showPlainTextFallback(element);
    }
}

function resolveTextRevealBlocks(root = document) {
    return root.querySelectorAll(BLOCK_SELECTOR);
}

function repairUnreadyTextReveals(root = document) {
    root.querySelectorAll('.gsap-text-reveal:not([data-gsap-text-reveal-ready="1"])').forEach((element) => {
        delete element.dataset.gsapTextRevealMounted;
        showPlainTextFallback(element);
    });
}

export function initPostContentGsapTextReveal(root = document) {
    const g = resolveGsap();
    const blocks = resolveTextRevealBlocks(root);

    blocks.forEach((element) => {
        prepareTextRevealRoot(element);
    });

    blocks.forEach((element) => {
        mountTextRevealBlock(g, element);
    });
}

function bootTextReveal() {
    initPostContentGsapTextReveal();
}

if (typeof window !== 'undefined') {
    window.hermesInitTextReveal = initPostContentGsapTextReveal;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootTextReveal);
    } else {
        bootTextReveal();
    }

    window.addEventListener('load', () => {
        initPostContentGsapTextReveal();
        repairUnreadyTextReveals();
    });
}
