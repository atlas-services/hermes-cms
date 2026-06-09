import { whenGsapReady } from '../gsap.js';

const ROOT_SELECTOR = '.post-content .gsap-shape-build, .gsap-shape-build';

function preparePaths(paths) {
    paths.forEach((path) => {
        const length = path.getTotalLength();
        path.style.strokeDasharray = String(length);
        path.style.strokeDashoffset = String(length);
        path.style.visibility = 'visible';
    });
}

export function prepareGsapShapeBuildBlocks(root = document) {
    root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
        if (block.dataset.gsapShapeBuildPrepared === '1') {
            return;
        }

        block.dataset.gsapShapeBuildPrepared = '1';

        block.querySelectorAll('.gsap-shape-build__block').forEach((el) => {
            el.style.opacity = '0';
        });

        preparePaths(block.querySelectorAll('.gsap-shape-build__path'));
    });
}

/** @returns {'sync'|'bottom-up'|'sequential'} */
function resolveBuildMode(root) {
    const mode = (root.dataset.gsapShapeBuildMode || '').trim().toLowerCase();

    if (mode === 'sync' || mode === 'bottom-up' || mode === 'sequential') {
        return mode;
    }

    if (root.classList.contains('gsap-shape-build--bottom-up')) {
        return 'bottom-up';
    }

    if (root.classList.contains('gsap-shape-build--sync')) {
        return 'sync';
    }

    return 'sequential';
}

/** Plus grand Y SVG = plus bas dans le dessin → construit en premier. */
function pathBottomY(path) {
    try {
        const box = path.getBBox();

        return box.y + box.height;
    } catch {
        return 0;
    }
}

function pathsBottomToTop(paths) {
    return [...paths].sort((a, b) => pathBottomY(b) - pathBottomY(a));
}

/** Durée du tracé SVG (secondes). Défaut : 4 s (sync ou séquentiel). */
function resolveDurationSeconds(root) {
    const raw = root.dataset.gsapShapeBuildDuration;
    if (raw !== undefined && raw !== '') {
        const parsed = Number.parseFloat(raw);
        if (!Number.isNaN(parsed) && parsed > 0) {
            return parsed;
        }
    }

    const cssVar = getComputedStyle(root).getPropertyValue('--gsap-shape-build-duration').trim();
    if (cssVar !== '') {
        const parsed = Number.parseFloat(cssVar);
        if (!Number.isNaN(parsed) && parsed > 0) {
            return parsed;
        }
    }

    return 4;
}

export function playGsapShapeBuild(gsap, root) {
    const blocks = root.querySelectorAll('.gsap-shape-build__block');
    const paths = root.querySelectorAll('.gsap-shape-build__path');
    const mode = resolveBuildMode(root);
    const sync = mode === 'sync';
    const bottomUp = mode === 'bottom-up';
    const duration = resolveDurationSeconds(root);

    gsap.killTweensOf([...blocks, ...paths]);

    const tl = gsap.timeline();

    if (blocks.length > 0) {
        tl.set(blocks, {
            opacity: 0,
            scale: 0.4,
            y: (i) => (i % 2 === 0 ? -80 : 80),
            x: (i) => (i % 3 === 0 ? -40 : (i % 3 === 1 ? 40 : 0)),
        }, 0);

        const blockTween = {
            opacity: 1,
            scale: 1,
            x: 0,
            y: 0,
            duration: sync ? duration * 0.28 : 0.7,
            ease: sync ? 'power2.out' : 'back.out(1.4)',
        };

        if (!sync) {
            blockTween.stagger = {
                each: 0.045,
                from: 'random',
            };
        }

        tl.to(blocks, blockTween, 0);
    }

    preparePaths(paths);

    if (paths.length > 0) {
        const orderedPaths = bottomUp ? pathsBottomToTop(paths) : [...paths];

        if (bottomUp) {
            const pathDuration = Math.min(duration * 0.38, 1.4);
            const staggerEach = orderedPaths.length > 1
                ? Math.max((duration - pathDuration) / (orderedPaths.length - 1), 0.05)
                : 0;

            tl.to(orderedPaths, {
                strokeDashoffset: 0,
                duration: pathDuration,
                ease: 'power2.inOut',
                stagger: staggerEach,
            }, 0);
        } else {
            const pathTween = {
                strokeDashoffset: 0,
                duration,
                ease: 'power2.inOut',
            };

            if (!sync) {
                pathTween.stagger = Math.min(duration * 0.12, 0.55);
            }

            tl.to(orderedPaths, pathTween, 0);
        }
    }

    return tl;
}

function bindReplay(gsap, root) {
    const replay = root.querySelector('.gsap-shape-build__replay');
    if (!replay || replay.dataset.gsapShapeBuildReplayBound === '1') {
        return;
    }

    replay.dataset.gsapShapeBuildReplayBound = '1';
    replay.addEventListener('click', () => playGsapShapeBuild(gsap, root));
}

function initGsapShapeBuildBlock(gsap, root) {
    prepareGsapShapeBuildBlocks(root);

    if (root.dataset.gsapShapeBuildMounted === '1') {
        playGsapShapeBuild(gsap, root);
        return;
    }

    root.dataset.gsapShapeBuildMounted = '1';
    playGsapShapeBuild(gsap, root);
    bindReplay(gsap, root);
}

export function initPostContentGsapShapeBuild(root = document) {
    prepareGsapShapeBuildBlocks(root);

    whenGsapReady((gsap) => {
        root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
            initGsapShapeBuildBlock(gsap, block);
        });
    });
}
