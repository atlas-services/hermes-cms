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

export function playGsapShapeBuild(gsap, root) {
    const blocks = root.querySelectorAll('.gsap-shape-build__block');
    const paths = root.querySelectorAll('.gsap-shape-build__path');

    gsap.killTweensOf([...blocks, ...paths]);

    const tl = gsap.timeline();

    tl.set(blocks, {
        opacity: 0,
        scale: 0.4,
        y: (i) => (i % 2 === 0 ? -80 : 80),
        x: (i) => (i % 3 === 0 ? -40 : (i % 3 === 1 ? 40 : 0)),
    }, 0);

    tl.to(blocks, {
        opacity: 1,
        scale: 1,
        x: 0,
        y: 0,
        duration: 0.7,
        ease: 'back.out(1.4)',
        stagger: {
            each: 0.045,
            from: 'random',
        },
    }, 0);

    preparePaths(paths);

    tl.to(paths, {
        strokeDashoffset: 0,
        duration: 1.1,
        ease: 'power2.inOut',
        stagger: 0.35,
    }, 0);

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
