import { whenGsapReady } from '../gsap.js';

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

function readText(element) {
    const dataset = element.dataset;

    return (
        dataset.gsapTextRevealTextValue
        || element.querySelector('.gsap-text-reveal__label')?.textContent?.trim()
        || element.querySelector('.gsap-text-reveal__body')?.getAttribute('aria-label')
        || ''
    );
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
    for (const char of text) {
        const span = document.createElement('span');
        span.className = 'gsap-text-reveal__char';
        span.setAttribute('aria-hidden', 'true');
        if (char === ' ') {
            span.classList.add('is-space');
            span.textContent = '\u00a0';
        } else {
            span.textContent = char;
        }
        body.appendChild(span);
        chars.push(span);
    }

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
        body.appendChild(lineEl);
        return lineEl;
    });
}

function playRevealEffect(gsap, root, mode, targets) {
    const config = readModeConfig(root, mode);

    if (mode === 'chars') {
        const fromVars = {
            x: config.x,
            opacity: config.opacity,
            duration: config.duration,
            ease: config.ease,
            stagger: config.stagger,
        };

        return gsap.from(targets, fromVars);
    }

    if (mode === 'words') {
        const fromVars = {
            y: config.y,
            opacity: config.opacity,
            duration: config.duration,
            ease: config.ease,
            stagger: config.stagger,
        };

        if (config.x) {
            fromVars.x = config.x;
        }

        if (config.rotationMin === config.rotationMax) {
            fromVars.rotation = config.rotationMin;
        } else {
            fromVars.rotation = () => gsap.utils.random(config.rotationMin, config.rotationMax);
        }

        return gsap.from(targets, fromVars);
    }

    return gsap.from(targets, {
        rotationX: config.rotationX,
        transformOrigin: config.transformOrigin,
        opacity: config.opacity,
        duration: config.duration,
        ease: config.ease,
        stagger: config.stagger,
    });
}

/**
 * Effets d’apparition texte type GSAP Demo Hub « Animate Text » / SplitText demo.
 * @see https://demos.gsap.com/demo/animate-text/
 * @see https://codepen.io/GreenSock/pen/xxmaNYj
 */
export function playGsapTextReveal(gsap, root, mode) {
    const resolvedMode = DEFAULTS[mode] ? mode : 'chars';
    const body = root.querySelector('.gsap-text-reveal__body');

    if (!body) {
        return null;
    }

    const text = readText(root);
    if (!text.trim() && resolvedMode !== 'lines') {
        return null;
    }

    gsap.killTweensOf(body.querySelectorAll('.gsap-text-reveal__char, .gsap-text-reveal__word, .gsap-text-reveal__line'));

    body.className = `gsap-text-reveal__body gsap-text-reveal__body--${resolvedMode}`;

    let targets = [];
    if (resolvedMode === 'chars') {
        targets = mountChars(body, text);
    } else if (resolvedMode === 'words') {
        targets = mountWords(body, text);
    } else {
        const lines = readLines(root);
        if (!lines.length) {
            return null;
        }
        targets = mountLines(body, lines);
    }

    if (!targets.length) {
        return null;
    }

    root.dataset.gsapTextRevealActiveMode = resolvedMode;
    return playRevealEffect(gsap, root, resolvedMode, targets);
}

function bindRevealControls(gsap, root) {
    const buttons = root.querySelectorAll('[data-gsap-text-reveal-mode]');
    const defaultMode = root.dataset.gsapTextRevealModeValue || 'chars';

    const activate = (mode) => {
        playGsapTextReveal(gsap, root, mode);
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

export function initPostContentGsapTextReveal(root = document) {
    whenGsapReady((gsap) => {
        root.querySelectorAll('.post-content .gsap-text-reveal').forEach((element) => {
            if (element.dataset.gsapTextRevealMounted === '1') {
                return;
            }

            element.dataset.gsapTextRevealMounted = '1';
            bindRevealControls(gsap, element);
        });
    });
}
