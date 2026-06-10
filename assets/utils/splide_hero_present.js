import Splide from '@splidejs/splide';
import { whenGsapReady } from '../gsap.js';
import { mountGsapLetterRevealLines, playGsapLetterReveal } from './gsap_letter_reveal.js';

const ROOT_SELECTOR = '.hermes-front-sections .post-content .splide-carousel--hero-present';
const STATIC_SELECTOR = '.hermes-front-sections .post-content .hermes-hero-present--static';
const RESPONSIVE_MAX_WIDTH = 991.98;

function readBool(dataset, key, fallback) {
    const raw = dataset[key];
    if (raw === undefined || raw === '') {
        return fallback;
    }

    return raw === 'true' || raw === '1';
}

function readNumber(dataset, key, fallback) {
    const raw = dataset[key];
    if (raw === undefined || raw === '') {
        return fallback;
    }

    const value = parseFloat(raw);
    return Number.isFinite(value) ? value : fallback;
}

function destroySplideInstance(root) {
    if (root.splide) {
        root.splide.destroy(true);
        root.splide = null;
    }

    root.querySelectorAll('.splide__pagination, .splide__arrows').forEach((el) => {
        el.remove();
    });

    delete root.dataset.splideCarouselMounted;
    delete root.dataset.splideHeroPresentMounted;
}

function prepareSlideLetterReveals(slide) {
    slide.querySelectorAll('.gsap-letter-reveal').forEach((block) => {
        delete block.dataset.gsapLetterRevealPrepared;
        mountGsapLetterRevealLines(block);
        block.dataset.gsapLetterRevealPrepared = '1';
    });
}

function resetSlideMotion(slide, gsap) {
    const bodyEls = slide.querySelectorAll('.hermes-hero-present__body, .hermes-hero-present__icon, .hermes-hero-present__actions');
    const chars = slide.querySelectorAll('.gsap-letter-reveal__char');

    if (gsap) {
        gsap.killTweensOf([...bodyEls, ...chars]);
        gsap.set(bodyEls, { opacity: 0, y: 22 });
        gsap.set(chars, { opacity: 0 });
        return;
    }

    bodyEls.forEach((el) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(1.35rem)';
    });

    chars.forEach((char) => {
        char.style.opacity = '0';
    });
}

function resolveSlideElement(component) {
    if (component instanceof Element) {
        return component;
    }

    return component?.slide ?? null;
}

function isHeroResponsiveViewport() {
    return window.matchMedia(`(max-width: ${RESPONSIVE_MAX_WIDTH}px)`).matches;
}

function bindResponsiveArrowVisibility(root, splide) {
    const activate = () => {
        if (isHeroResponsiveViewport()) {
            root.classList.add('is-arrows-active');
        }
    };

    const deactivate = () => {
        requestAnimationFrame(() => {
            if (root.querySelector('.splide__arrow:hover, .splide__arrow:focus-visible')) {
                return;
            }

            root.classList.remove('is-arrows-active');
        });
    };

    const bindArrows = () => {
        root.querySelectorAll('.splide__arrow').forEach((arrow) => {
            if (arrow.dataset.heroArrowBound === '1') {
                return;
            }

            arrow.dataset.heroArrowBound = '1';
            arrow.addEventListener('pointerenter', activate);
            arrow.addEventListener('pointerleave', deactivate);
            arrow.addEventListener('focus', activate);
            arrow.addEventListener('blur', deactivate);
        });
    };

    bindArrows();
    splide.on('arrows:mounted', bindArrows);
    splide.on('arrows:updated', bindArrows);
}

function bindResponsiveSlidePause(root, splide, autoplayEnabled) {
    if (!autoplayEnabled) {
        return;
    }

    const autoplayComponent = () => splide.Components?.Autoplay;
    const interactionTarget = root.closest('.hermes-hero-present') || root.querySelector('.splide__track') || root;
    let touchPauseActive = false;

    const pauseIfResponsive = () => {
        if (!isHeroResponsiveViewport()) {
            return;
        }

        autoplayComponent()?.pause(true);
    };

    const playIfResponsive = () => {
        if (!isHeroResponsiveViewport() || touchPauseActive) {
            return;
        }

        autoplayComponent()?.play();
    };

    interactionTarget.addEventListener('pointerenter', pauseIfResponsive);
    interactionTarget.addEventListener('pointerleave', playIfResponsive);
    interactionTarget.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'touch' || !isHeroResponsiveViewport()) {
            return;
        }

        touchPauseActive = true;
        pauseIfResponsive();
    });

    const releaseTouchPause = (event) => {
        if (event.pointerType !== 'touch' || !touchPauseActive) {
            return;
        }

        touchPauseActive = false;
        playIfResponsive();
    };

    interactionTarget.addEventListener('pointerup', releaseTouchPause);
    interactionTarget.addEventListener('pointercancel', releaseTouchPause);
}

function playSlideMotion(gsap, slide) {
    if (!slide) {
        return;
    }

    const letterRoot = slide.querySelector('.gsap-letter-reveal');
    if (letterRoot) {
        if (letterRoot.dataset.gsapLetterRevealPrepared !== '1') {
            letterRoot.dataset.gsapLetterRevealPrepared = '1';
            mountGsapLetterRevealLines(letterRoot);
        }

        playGsapLetterReveal(gsap, letterRoot);
    }

    const bodyEls = slide.querySelectorAll('.hermes-hero-present__body, .hermes-hero-present__icon, .hermes-hero-present__actions');
    if (!bodyEls.length) {
        return;
    }

    gsap.killTweensOf(bodyEls);
    gsap.fromTo(bodyEls, {
        opacity: 0,
        y: 22,
    }, {
        opacity: 1,
        y: 0,
        duration: 0.82,
        stagger: 0.1,
        ease: 'power3.out',
        delay: letterRoot ? 0.28 : 0,
    });
}

function initSplideHeroPresentBlock(root) {
    if (root.dataset.splideHeroPresentMounted === '1' && root.splide) {
        return;
    }

    if (root.dataset.splideHeroPresentPending === '1') {
        return;
    }

    const slides = [...root.querySelectorAll('.splide__slide')];
    if (slides.length < 2) {
        return;
    }

    root.dataset.splideHeroPresentPending = '1';
    destroySplideInstance(root);

    const dataset = root.dataset;
    const interval = readNumber(dataset, 'splideCarouselIntervalValue', 2000);
    const autoplay = readBool(dataset, 'splideCarouselAutoplayValue', true);
    const speed = readNumber(dataset, 'splideCarouselSpeedValue', 900);
    const pauseOnHover = readBool(dataset, 'splideCarouselPauseOnHoverValue', true);

    whenGsapReady((gsap) => {
        slides.forEach((slide) => {
            prepareSlideLetterReveals(slide);
            resetSlideMotion(slide, gsap);
        });

        const splide = new Splide(root, {
            type: 'fade',
            rewind: true,
            autoplay,
            interval,
            speed,
            pauseOnHover: pauseOnHover && !isHeroResponsiveViewport(),
            pauseOnFocus: true,
            arrows: true,
            pagination: true,
            keyboard: 'global',
            cover: false,
            trimSpace: false,
        });

        splide.on('move', (newIndex) => {
            slides.forEach((slide, index) => {
                if (index !== newIndex) {
                    resetSlideMotion(slide, gsap);
                }
            });
        });

        splide.on('active', (component) => {
            playSlideMotion(gsap, resolveSlideElement(component));
        });

        splide.mount();
        root.splide = splide;
        bindResponsiveArrowVisibility(root, splide);
        bindResponsiveSlidePause(root, splide, autoplay);
        root.dataset.splideHeroPresentMounted = '1';
        delete root.dataset.splideHeroPresentPending;
        root.closest('.hermes-hero-present')?.classList.add('is-ready');

        requestAnimationFrame(() => {
            playSlideMotion(gsap, slides[splide.index] ?? slides[0]);
        });
    });
}

export function initPostContentSplideHeroPresent(root = document) {
    root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
        initSplideHeroPresentBlock(block);
    });
}

function initHeroPresentStaticBlock(hero) {
    if (hero.dataset.heroPresentStaticMounted === '1') {
        return;
    }

    const slide = hero.querySelector('.hermes-hero-present__slide, .hermes-hero-present__screen');
    if (!slide) {
        return;
    }

    hero.dataset.heroPresentStaticMounted = '1';

    whenGsapReady((gsap) => {
        prepareSlideLetterReveals(slide);
        resetSlideMotion(slide, gsap);
        hero.classList.add('is-ready');
        playSlideMotion(gsap, slide);
    });
}

export function initPostContentHeroPresentStatic(root = document) {
    root.querySelectorAll(STATIC_SELECTOR).forEach((hero) => {
        initHeroPresentStaticBlock(hero);
    });
}
