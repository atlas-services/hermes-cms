import { whenGsapReady } from '../gsap.js';

const ROOT_SELECTOR = '.post-content .gsap-letter-reveal, .gsap-letter-reveal';

function readLineText(line) {
    return (line.dataset.gsapLetterRevealTextValue
        || line.dataset.gsapLetterRevealText
        || line.getAttribute('aria-label')
        || line.textContent
        || '')
        .trim()
        .replace(/\s+/g, '');
}

/**
 * CKEditor regroupe souvent les lettres en mots entiers : on (re)découpe au runtime.
 */
export function mountGsapLetterRevealLines(root) {
    let globalIndex = 0;

    root.querySelectorAll('.gsap-letter-reveal__line').forEach((line) => {
        const text = readLineText(line);
        if (!text) {
            return;
        }

        line.textContent = '';
        line.setAttribute('aria-label', text);

        [...text].forEach((char) => {
            const span = document.createElement('span');
            span.className = 'gsap-letter-reveal__char';
            span.setAttribute('aria-hidden', 'true');
            span.textContent = char;
            span.dataset.gsapLetterRevealIndex = String(globalIndex);
            span.style.opacity = '0';
            globalIndex += 1;
            line.appendChild(span);
        });
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
    prepareGsapLetterRevealBlocks(root);

    if (root.dataset.gsapLetterRevealMounted === '1') {
        playGsapLetterReveal(gsap, root);
        return;
    }

    root.dataset.gsapLetterRevealMounted = '1';
    playGsapLetterReveal(gsap, root);
    bindReplay(gsap, root);
}

export function initPostContentGsapLetterReveal(root = document) {
    prepareGsapLetterRevealBlocks(root);

    whenGsapReady((gsap) => {
        root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
            initGsapLetterRevealBlock(gsap, block);
        });
    });
}
