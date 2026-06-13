import { whenGsapReady } from '../gsap.js';

const ROOT_SELECTOR = '.hermes-front-sections .post-content .gsap-letter-reveal';

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

export function playGsapLetterReveal(gsap, root) {
    const letters = root.querySelectorAll('.gsap-letter-reveal__char');
    if (!letters.length) {
        return;
    }

    const offset = 150;
    const tl = gsap.timeline();

    gsap.killTweensOf(letters);

    letters.forEach((letter) => {
        const i = parseInt(letter.dataset.gsapLetterRevealIndex || '0', 10);
        const fromY = i % 2 === 0 ? -offset : offset;

        gsap.set(letter, { opacity: 0, y: fromY });
        tl.to(letter, {
            y: 0,
            opacity: 1,
            duration: 0.72,
            ease: 'power4.out',
        }, i * 0.11);
    });
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
        playGsapLetterReveal(gsap, root);
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
