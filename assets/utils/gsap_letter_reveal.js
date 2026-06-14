import { whenGsapReady } from '../gsap.js';

const ROOT_SELECTOR = '.hermes-front-sections .post-content .gsap-letter-reveal';
const DEFAULT_EFFECT = 'vertical-alt';

function readNumber(dataset, key, fallback) {
    const raw = dataset[key];
    if (raw === undefined || raw === '') {
        return fallback;
    }

    const value = parseFloat(raw);
    return Number.isFinite(value) ? value : fallback;
}

function readRevealConfigOverrides(element) {
    if (!element) {
        return {};
    }

    const dataset = element.dataset ?? {};
    const overrides = {};

    const effect = (dataset.gsapLetterRevealEffectValue || dataset.gsapLetterRevealEffect || '').trim();
    if (effect) {
        overrides.effect = effect;
    }

    if (dataset.gsapLetterRevealOffsetValue !== undefined && dataset.gsapLetterRevealOffsetValue !== '') {
        overrides.offset = readNumber(dataset, 'gsapLetterRevealOffsetValue', 150);
    }

    if (dataset.gsapLetterRevealDurationValue !== undefined && dataset.gsapLetterRevealDurationValue !== '') {
        overrides.duration = readNumber(dataset, 'gsapLetterRevealDurationValue', 0.72);
    }

    if (dataset.gsapLetterRevealStaggerValue !== undefined && dataset.gsapLetterRevealStaggerValue !== '') {
        overrides.stagger = readNumber(dataset, 'gsapLetterRevealStaggerValue', 0.11);
    }

    const ease = (dataset.gsapLetterRevealEaseValue || dataset.gsapLetterRevealEase || '').trim();
    if (ease) {
        overrides.ease = ease;
    }

    return overrides;
}

function readRevealConfig(element) {
    const overrides = readRevealConfigOverrides(element);

    return {
        effect: (overrides.effect || DEFAULT_EFFECT).trim(),
        offset: overrides.offset ?? 150,
        duration: overrides.duration ?? 0.72,
        stagger: overrides.stagger ?? 0.11,
        ease: (overrides.ease || 'power4.out').trim(),
    };
}

function readLineText(line) {
    return (line.dataset.gsapLetterRevealTextValue
        || line.dataset.gsapLetterRevealText
        || line.getAttribute('aria-label')
        || line.textContent
        || '')
        .trim()
        .replace(/\s+/g, ' ');
}

function appendLetterChar(line, char, globalIndex) {
    const span = document.createElement('span');
    span.className = 'gsap-letter-reveal__char';
    span.setAttribute('aria-hidden', 'true');
    if (char === ' ') {
        span.classList.add('is-space');
        span.textContent = '\u00a0';
    } else {
        span.textContent = char;
    }
    span.dataset.gsapLetterRevealIndex = String(globalIndex.current);
    span.style.opacity = '0';
    line.appendChild(span);
    globalIndex.current += 1;
}

function readLineAccent(line) {
    return {
        letter: (line.dataset.gsapLetterRevealAccentLetterValue || '').trim(),
        color: (line.dataset.gsapLetterRevealAccentColorValue || '').trim(),
    };
}

function mountWordWrappedLetterLine(line, text, globalIndex, { heroPresent = false } = {}) {
    const accent = readLineAccent(line);
    let accentApplied = false;
    const words = text.split(' ').filter(Boolean);

    line.classList.add('gsap-letter-reveal__line--words');
    if (heroPresent) {
        line.classList.add('hermes-hero-present__letter-line');
    }

    words.forEach((word, wordIndex) => {
        const wordWrap = document.createElement('span');
        wordWrap.className = 'gsap-letter-reveal__word';
        if (heroPresent) {
            wordWrap.classList.add('hermes-hero-present__word');
        }
        wordWrap.setAttribute('aria-hidden', 'true');

        [...word].forEach((char) => {
            const span = document.createElement('span');
            span.className = 'gsap-letter-reveal__char';
            span.setAttribute('aria-hidden', 'true');
            span.textContent = char;
            span.dataset.gsapLetterRevealIndex = String(globalIndex.current);
            span.style.opacity = '0';

            if (!accentApplied && accent.letter && char === accent.letter) {
                span.classList.add('is-accent');
                if (accent.color) {
                    span.style.color = accent.color;
                }
                accentApplied = true;
            }

            wordWrap.appendChild(span);
            globalIndex.current += 1;
        });

        line.appendChild(wordWrap);

        if (wordIndex < words.length - 1) {
            appendLetterChar(line, ' ', globalIndex);
        }
    });
}

/**
 * CKEditor regroupe souvent les lettres en mots entiers : on (re)découpe au runtime.
 * Chaque mot est isolé pour éviter une coupure au milieu lors des retours à la ligne.
 */
export function mountGsapLetterRevealLines(root) {
    const globalIndex = { current: 0 };
    const heroPresent = root.closest('.splide-carousel--hero-present, .hermes-hero-present') !== null;

    root.querySelectorAll('.gsap-letter-reveal__line').forEach((line) => {
        const text = readLineText(line);
        if (!text) {
            return;
        }

        line.textContent = '';
        line.setAttribute('aria-label', text);

        mountWordWrappedLetterLine(line, text, globalIndex, { heroPresent });
    });
}

function prepareGsapLetterRevealBlocks(root = document) {
    root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
        if (block.dataset.gsapLetterRevealPrepared === '1') {
            return;
        }

        block.dataset.gsapLetterRevealPrepared = '1';
        mountGsapLetterRevealLines(block);
    });
}

function resolveEffectName(effect) {
    const known = new Set([
        'vertical-alt',
        'vertical-up',
        'vertical-down',
        'horizontal-alt',
        'horizontal-left',
        'fade',
        'scale',
        'blur',
        'rotate',
    ]);

    return known.has(effect) ? effect : DEFAULT_EFFECT;
}

function letterInitialState(effect, index, config) {
    const hidden = { opacity: 0 };
    const neutral = {
        x: 0,
        y: 0,
        scale: 1,
        rotation: 0,
        filter: 'none',
    };

    switch (effect) {
        case 'vertical-up':
            return { ...hidden, ...neutral, y: config.offset };
        case 'vertical-down':
            return { ...hidden, ...neutral, y: -config.offset };
        case 'horizontal-alt':
            return { ...hidden, ...neutral, x: index % 2 === 0 ? -config.offset : config.offset };
        case 'horizontal-left':
            return { ...hidden, ...neutral, x: -config.offset };
        case 'fade':
            return { ...hidden, ...neutral };
        case 'scale':
            return { ...hidden, ...neutral, scale: 0 };
        case 'blur':
            return { ...hidden, ...neutral, filter: 'blur(12px)' };
        case 'rotate':
            return { ...hidden, ...neutral, scale: 0.35, rotation: index % 2 === 0 ? -18 : 18 };
        case 'vertical-alt':
        default:
            return {
                ...hidden,
                ...neutral,
                y: index % 2 === 0 ? -config.offset : config.offset,
            };
    }
}

function letterFinalState(effect) {
    const final = {
        opacity: 1,
        x: 0,
        y: 0,
        scale: 1,
        rotation: 0,
    };

    if (effect === 'blur') {
        final.filter = 'blur(0px)';
    }

    return final;
}

function animateLetters(gsap, letters, config, timeline) {
    const effect = resolveEffectName(config.effect);

    letters.forEach((letter) => {
        const index = parseInt(letter.dataset.gsapLetterRevealIndex || '0', 10);
        gsap.set(letter, letterInitialState(effect, index, config));
        timeline.to(letter, {
            ...letterFinalState(effect),
            duration: config.duration,
            ease: config.ease,
        }, index * config.stagger);
    });
}

export function resetGsapLetterRevealChars(gsap, chars) {
    if (!chars?.length) {
        return;
    }

    gsap.killTweensOf(chars);
    gsap.set(chars, {
        opacity: 0,
        x: 0,
        y: 0,
        scale: 1,
        rotation: 0,
        filter: 'none',
    });
}

export function playGsapLetterReveal(gsap, root) {
    const letters = root.querySelectorAll('.gsap-letter-reveal__char');
    if (!letters.length) {
        return;
    }

    const blockConfig = readRevealConfig(root);
    const lines = [...root.querySelectorAll('.gsap-letter-reveal__line')];
    const tl = gsap.timeline();

    gsap.killTweensOf(letters);

    if (lines.length) {
        lines.forEach((line) => {
            const lineConfig = {
                ...blockConfig,
                ...readRevealConfigOverrides(line),
            };
            animateLetters(gsap, line.querySelectorAll('.gsap-letter-reveal__char'), lineConfig, tl);
        });
        return;
    }

    animateLetters(gsap, letters, blockConfig, tl);
}

function bindReplay(gsap, root) {
    const replay = root.querySelector('.gsap-letter-reveal__replay');
    if (!replay || replay.dataset.gsapLetterRevealReplayBound === '1') {
        return;
    }

    replay.dataset.gsapLetterRevealReplayBound = '1';
    replay.addEventListener('click', () => playGsapLetterReveal(gsap, root));
}

function initGsapLetterRevealBlock(gsap, root) {
    if (root.closest('.hermes-hero-present')) {
        if (root.dataset.gsapLetterRevealPrepared !== '1') {
            root.dataset.gsapLetterRevealPrepared = '1';
            mountGsapLetterRevealLines(root);
        }

        return;
    }

    if (root.dataset.gsapLetterRevealPrepared !== '1') {
        root.dataset.gsapLetterRevealPrepared = '1';
        mountGsapLetterRevealLines(root);
    }

    if (root.dataset.gsapLetterRevealMounted === '1') {
        return;
    }

    root.dataset.gsapLetterRevealMounted = '1';
    playGsapLetterReveal(gsap, root);
    bindReplay(gsap, root);
}

export function initPostContentGsapLetterReveal(root = document) {
    whenGsapReady((gsap) => {
        root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
            initGsapLetterRevealBlock(gsap, block);
        });
    });
}
