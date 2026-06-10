import { whenGsapReady } from '../gsap.js';

const ROOT_SELECTOR = '.hermes-front-sections .post-content .gsap-carousel';

const DEFAULTS = {
    mode: 'slide',
    duration: 0.55,
    ease: 'power2.out',
    autoplay: false,
    interval: 5000,
    swipeThreshold: 48,
};

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

function readString(dataset, key, fallback) {
    const raw = dataset[key];
    return raw === undefined || raw === '' ? fallback : raw;
}

function readConfig(root) {
    const dataset = root.dataset;

    return {
        mode: readString(dataset, 'gsapCarouselModeValue', DEFAULTS.mode) === 'fade' ? 'fade' : 'slide',
        duration: readNumber(dataset, 'gsapCarouselDurationValue', DEFAULTS.duration),
        ease: readString(dataset, 'gsapCarouselEaseValue', DEFAULTS.ease),
        autoplay: readBool(dataset, 'gsapCarouselAutoplayValue', DEFAULTS.autoplay),
        interval: readNumber(dataset, 'gsapCarouselIntervalValue', DEFAULTS.interval),
        swipeThreshold: readNumber(dataset, 'gsapCarouselSwipeThresholdValue', DEFAULTS.swipeThreshold),
    };
}

function buildDots(root, count, goTo) {
    const dotsRoot = root.querySelector('.gsap-carousel__dots');
    if (!dotsRoot || dotsRoot.dataset.gsapCarouselDotsBuilt === '1') {
        return;
    }

    dotsRoot.dataset.gsapCarouselDotsBuilt = '1';
    dotsRoot.textContent = '';

    for (let i = 0; i < count; i += 1) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'gsap-carousel__dot';
        button.setAttribute('aria-label', `Slide ${i + 1}`);
        button.addEventListener('click', () => goTo(i));
        dotsRoot.appendChild(button);
    }
}

function updateDots(root, index) {
    root.querySelectorAll('.gsap-carousel__dot').forEach((dot, i) => {
        dot.classList.toggle('is-active', i === index);
        dot.setAttribute('aria-current', i === index ? 'true' : 'false');
    });
}

function setActiveSlide(slides, index) {
    slides.forEach((slide, i) => {
        const isActive = i === index;
        slide.classList.toggle('is-active', isActive);
        slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
    });
}

function createCarousel(gsap, root) {
    const config = readConfig(root);
    const viewport = root.querySelector('.gsap-carousel__viewport');
    const track = root.querySelector('.gsap-carousel__track');
    const slides = [...root.querySelectorAll('.gsap-carousel__slide')];

    if (!viewport || !track || slides.length < 2) {
        return null;
    }

    root.classList.add(`gsap-carousel--${config.mode}`);

    let current = slides.findIndex((slide) => slide.classList.contains('is-active'));
    if (current < 0) {
        current = 0;
    }

    let autoplayTimer = null;
    let isAnimating = false;
    let dragStartX = 0;
    let dragDeltaX = 0;
    let isDragging = false;

    const slideWidth = () => viewport.clientWidth;

    const stopAutoplay = () => {
        if (autoplayTimer) {
            autoplayTimer.kill();
            autoplayTimer = null;
        }
    };

    const startAutoplay = () => {
        stopAutoplay();
        if (!config.autoplay) {
            return;
        }

        autoplayTimer = gsap.delayedCall(config.interval / 1000, () => {
            goTo(current + 1, { fromAutoplay: true });
            startAutoplay();
        });
    };

    const animateSlide = (next, { immediate = false } = {}) => {
        const x = -next * slideWidth();
        if (immediate) {
            gsap.set(track, { x });
            return;
        }

        isAnimating = true;
        gsap.to(track, {
            x,
            duration: config.duration,
            ease: config.ease,
            onComplete: () => {
                isAnimating = false;
            },
        });
    };

    const animateFade = (next) => {
        const outgoing = slides[current];
        const incoming = slides[next];
        isAnimating = true;

        gsap.timeline({
            defaults: { duration: config.duration, ease: config.ease },
            onComplete: () => {
                isAnimating = false;
            },
        })
            .set(incoming, { opacity: 0, zIndex: 2 })
            .set(outgoing, { zIndex: 1 })
            .to(outgoing, { opacity: 0 }, 0)
            .to(incoming, { opacity: 1 }, 0);
    };

    const goTo = (index, { fromAutoplay = false, immediate = false } = {}) => {
        if (isAnimating && !immediate) {
            return;
        }

        const next = (index + slides.length) % slides.length;
        if (next === current && !immediate) {
            return;
        }

        if (!fromAutoplay) {
            stopAutoplay();
        }

        if (config.mode === 'fade') {
            if (!immediate) {
                animateFade(next);
            } else {
                slides.forEach((slide, i) => {
                    gsap.set(slide, {
                        opacity: i === next ? 1 : 0,
                        zIndex: i === next ? 2 : 1,
                    });
                });
            }
        } else if (!immediate) {
            animateSlide(next);
        } else {
            animateSlide(next, { immediate: true });
        }

        current = next;
        setActiveSlide(slides, current);
        updateDots(root, current);

        if (!fromAutoplay && config.autoplay) {
            startAutoplay();
        }
    };

    const finishDrag = () => {
        viewport.classList.remove('is-dragging');
        isDragging = false;

        if (config.mode !== 'slide') {
            if (Math.abs(dragDeltaX) >= config.swipeThreshold) {
                goTo(current + (dragDeltaX < 0 ? 1 : -1));
            }
            dragDeltaX = 0;
            return;
        }

        if (Math.abs(dragDeltaX) >= config.swipeThreshold) {
            goTo(current + (dragDeltaX < 0 ? 1 : -1));
        } else {
            animateSlide(current);
        }

        dragDeltaX = 0;
    };

    const onPointerDown = (event) => {
        if (isAnimating) {
            return;
        }

        isDragging = true;
        dragStartX = event.clientX;
        dragDeltaX = 0;
        viewport.classList.add('is-dragging');
        viewport.setPointerCapture(event.pointerId);
        stopAutoplay();
    };

    const onPointerMove = (event) => {
        if (!isDragging || config.mode !== 'slide') {
            return;
        }

        dragDeltaX = event.clientX - dragStartX;
        gsap.set(track, { x: -current * slideWidth() + dragDeltaX });
    };

    const onPointerUp = (event) => {
        if (!isDragging) {
            return;
        }

        if (viewport.hasPointerCapture(event.pointerId)) {
            viewport.releasePointerCapture(event.pointerId);
        }

        finishDrag();
    };

    if (config.mode === 'fade') {
        slides.forEach((slide, i) => {
            gsap.set(slide, {
                opacity: i === current ? 1 : 0,
                zIndex: i === current ? 2 : 1,
            });
        });
    } else {
        gsap.set(track, { x: -current * slideWidth() });
    }

    setActiveSlide(slides, current);
    buildDots(root, slides.length, goTo);
    updateDots(root, current);

    const prev = root.querySelector('.gsap-carousel__prev');
    const next = root.querySelector('.gsap-carousel__next');

    if (prev && prev.dataset.gsapCarouselBound !== '1') {
        prev.dataset.gsapCarouselBound = '1';
        prev.addEventListener('click', () => goTo(current - 1));
    }

    if (next && next.dataset.gsapCarouselBound !== '1') {
        next.dataset.gsapCarouselBound = '1';
        next.addEventListener('click', () => goTo(current + 1));
    }

    if (viewport.dataset.gsapCarouselSwipeBound !== '1') {
        viewport.dataset.gsapCarouselSwipeBound = '1';
        viewport.addEventListener('pointerdown', onPointerDown);
        viewport.addEventListener('pointermove', onPointerMove);
        viewport.addEventListener('pointerup', onPointerUp);
        viewport.addEventListener('pointercancel', onPointerUp);
    }

    window.addEventListener('resize', () => {
        if (config.mode === 'slide') {
            goTo(current, { immediate: true });
        }
    });

    if (config.autoplay) {
        startAutoplay();
    }

    root.addEventListener('mouseenter', stopAutoplay);
    root.addEventListener('mouseleave', () => {
        if (config.autoplay) {
            startAutoplay();
        }
    });

    return { goTo };
}

function initGsapCarouselBlock(gsap, root) {
    if (root.dataset.gsapCarouselMounted === '1') {
        return;
    }

    root.dataset.gsapCarouselMounted = '1';
    createCarousel(gsap, root);
}

export function initPostContentGsapCarousel(root = document) {
    whenGsapReady((gsap) => {
        root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
            initGsapCarouselBlock(gsap, block);
        });
    });
}
