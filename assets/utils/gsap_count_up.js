import { whenGsapReady } from '../gsap.js';

const ROOT_SELECTOR = '.hermes-front-sections .post-content [data-gsap-count-up]';

function readNumber(value, fallback) {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : fallback;
}

function formatValue(value, decimals) {
    return decimals > 0 ? value.toFixed(decimals) : String(Math.round(value));
}

function animateCount(gsap, el) {
    if (el.dataset.gsapCountUpPlayed === '1') {
        return;
    }

    el.dataset.gsapCountUpPlayed = '1';

    const target = readNumber(el.dataset.gsapCountUp, 0);
    const start = readNumber(el.dataset.gsapCountUpStart, 0);
    const duration = readNumber(el.dataset.gsapCountUpDuration, 1.6);
    const decimals = Math.max(0, Math.round(readNumber(el.dataset.gsapCountUpDecimals, 0)));
    const counter = { value: start };

    el.textContent = formatValue(start, decimals);

    gsap.to(counter, {
        value: target,
        duration,
        ease: el.dataset.gsapCountUpEase || 'power1.out',
        onUpdate: () => {
            el.textContent = formatValue(counter.value, decimals);
        },
        onComplete: () => {
            el.textContent = formatValue(target, decimals);
        },
    });
}

function observeCount(gsap, el) {
    if (el.dataset.gsapCountUpBound === '1') {
        return;
    }

    el.dataset.gsapCountUpBound = '1';

    if (!('IntersectionObserver' in window)) {
        animateCount(gsap, el);
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            animateCount(gsap, el);
            observer.disconnect();
        });
    }, { threshold: 0.35 });

    observer.observe(el);
}

export function initPostContentGsapCountUp(root = document) {
    whenGsapReady((gsap) => {
        root.querySelectorAll(ROOT_SELECTOR).forEach((el) => observeCount(gsap, el));
    });
}
