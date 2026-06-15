import { whenGsapReady } from '../gsap.js';

const ROOT_SELECTOR = '.hermes-front-sections .gsap-image-reveal';
const DEFAULT_EFFECT = 'horizontal-alt';

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

    const effect = (dataset.gsapImageRevealEffectValue || dataset.gsapImageRevealEffect || '').trim();
    if (effect) {
        overrides.effect = effect;
    }

    if (dataset.gsapImageRevealOffsetValue !== undefined && dataset.gsapImageRevealOffsetValue !== '') {
        overrides.offset = readNumber(dataset, 'gsapImageRevealOffsetValue', 120);
    }

    if (dataset.gsapImageRevealDurationValue !== undefined && dataset.gsapImageRevealDurationValue !== '') {
        overrides.duration = readNumber(dataset, 'gsapImageRevealDurationValue', 0.75);
    }

    if (dataset.gsapImageRevealStaggerValue !== undefined && dataset.gsapImageRevealStaggerValue !== '') {
        overrides.stagger = readNumber(dataset, 'gsapImageRevealStaggerValue', 0.08);
    }

    const ease = (dataset.gsapImageRevealEaseValue || dataset.gsapImageRevealEase || '').trim();
    if (ease) {
        overrides.ease = ease;
    }

    return overrides;
}

function readRevealConfig(element) {
    const overrides = readRevealConfigOverrides(element);

    return {
        effect: (overrides.effect || DEFAULT_EFFECT).trim(),
        offset: overrides.offset ?? 120,
        duration: overrides.duration ?? 0.75,
        stagger: overrides.stagger ?? 0.08,
        ease: (overrides.ease || 'power3.out').trim(),
    };
}

function resolveEffectName(effect) {
    const known = new Set([
        'horizontal-alt',
        'horizontal-left',
        'horizontal-right',
        'vertical-alt',
        'vertical-up',
        'vertical-down',
        'fade',
        'scale',
        'blur',
        'rotate',
    ]);

    return known.has(effect) ? effect : DEFAULT_EFFECT;
}

function itemInitialState(effect, index, config) {
    const hidden = { opacity: 0 };
    const neutral = {
        x: 0,
        y: 0,
        scale: 1,
        rotation: 0,
        filter: 'none',
    };

    switch (effect) {
        case 'horizontal-left':
            return { ...hidden, ...neutral, x: -config.offset };
        case 'horizontal-right':
            return { ...hidden, ...neutral, x: config.offset };
        case 'vertical-up':
            return { ...hidden, ...neutral, y: config.offset };
        case 'vertical-down':
            return { ...hidden, ...neutral, y: -config.offset };
        case 'vertical-alt':
            return { ...hidden, ...neutral, y: index % 2 === 0 ? -config.offset : config.offset };
        case 'fade':
            return { ...hidden, ...neutral };
        case 'scale':
            return { ...hidden, ...neutral, scale: 0.82 };
        case 'blur':
            return { ...hidden, ...neutral, filter: 'blur(10px)' };
        case 'rotate':
            return { ...hidden, ...neutral, scale: 0.88, rotation: index % 2 === 0 ? -8 : 8 };
        case 'horizontal-alt':
        default:
            return { ...hidden, ...neutral, x: index % 2 === 0 ? -config.offset : config.offset };
    }
}

function itemFinalState(effect) {
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

export function playGsapImageReveal(gsap, root) {
    const items = [...root.querySelectorAll('.gsap-image-reveal__item')];
    if (!items.length) {
        return;
    }

    const config = readRevealConfig(root);
    const effect = resolveEffectName(config.effect);
    const tl = gsap.timeline();

    gsap.killTweensOf(items);

    items.forEach((item, index) => {
        gsap.set(item, itemInitialState(effect, index, config));
        tl.to(item, {
            ...itemFinalState(effect),
            duration: config.duration,
            ease: config.ease,
        }, index * config.stagger);
    });
}

function bindReplay(gsap, root) {
    const replay = root.querySelector('.gsap-image-reveal__replay');
    if (!replay || replay.dataset.gsapImageRevealReplayBound === '1') {
        return;
    }

    replay.dataset.gsapImageRevealReplayBound = '1';
    replay.addEventListener('click', () => playGsapImageReveal(gsap, root));
}

function bindEffectSelect(gsap, root) {
    const select = root.querySelector('.gsap-image-reveal__effect-select');
    if (!select || select.dataset.gsapImageRevealEffectSelectBound === '1') {
        return;
    }

    select.dataset.gsapImageRevealEffectSelectBound = '1';
    const effect = resolveEffectName(readRevealConfig(root).effect);
    select.value = effect;

    select.addEventListener('change', () => {
        root.dataset.gsapImageRevealEffectValue = select.value;
        playGsapImageReveal(gsap, root);
    });
}

function initGsapImageRevealBlock(gsap, root) {
    if (root.dataset.gsapImageRevealMounted === '1') {
        return;
    }

    root.dataset.gsapImageRevealMounted = '1';
    playGsapImageReveal(gsap, root);
    bindReplay(gsap, root);
    bindEffectSelect(gsap, root);
}

export function initPostContentGsapImageReveal(root = document) {
    whenGsapReady((gsap) => {
        root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
            initGsapImageRevealBlock(gsap, block);
        });
    });
}
