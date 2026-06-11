/**
 * Port Hermes 2.2.7 — assets/front/js/hermes/js-front.js
 * @see https://github.com/atlas-services/hermes/blob/release/2.2.7/assets/front/js/hermes/js-front.js
 */

import { Carousel } from 'bootstrap';
import { initPostContentGsapTextReveal } from '../../../utils/gsap_text_reveal.js';
import { initPostContentGsapLetterReveal } from '../../../utils/gsap_letter_reveal.js';
import { initPostContentGsapShapeBuild } from '../../../utils/gsap_shape_build.js';
import { initPostContentGsapCarousel } from '../../../utils/gsap_carousel.js';
import { initPostContentSplideCarousel } from '../../../utils/splide_carousel.js';
import { initPostContentHeroPresentStatic, initPostContentSplideHeroPresent } from '../../../utils/splide_hero_present.js';
import { initPostContentGsapCarte } from '../../../utils/gsap_carte.js';

function colorLink() {
    const libreLinks = document.getElementsByClassName('link');
    const contentLinkColor = document.getElementById('content_link_color');
    const contentLinkHoverColor = document.getElementById('content_link_hover_color');

    if (!libreLinks.length || !contentLinkColor || !contentLinkHoverColor) {
        return;
    }

    const linkColor = contentLinkColor.value;
    const linkHoverColor = contentLinkHoverColor.value;

    for (let i = 0; i < libreLinks.length; i++) {
        libreLinks[i].style.color = linkColor;

        libreLinks[i].onmouseover = function () {
            libreLinks[i].style.color = linkHoverColor;
        };

        libreLinks[i].onmouseout = function () {
            libreLinks[i].style.color = linkColor;
        };
    }
}

/**
 * Hermes 2.2.7 — modale #imageModal / #modalImage / #carousel-fade img[data-img-src].
 * Initialisation par bloc .post-content (contenu libre) ou fallback document entier.
 */
function initModalImageInScope(scope) {
    const modalImage = scope.querySelector('#modalImage');
    if (!modalImage) {
        return;
    }

    const modalPrev = scope.querySelector('#modalPrev');
    const modalNext = scope.querySelector('#modalNext');
    const carousel = scope.querySelector('#carousel-fade');
    if (!carousel) {
        return;
    }

    const images = Array.from(carousel.querySelectorAll('img[data-img-src]'));
    if (!images.length) {
        return;
    }

    let currentIndex = 0;

    // Ouvre la modale avec l'image cliquée
    images.forEach((img, index) => {
        img.addEventListener('click', function () {
            currentIndex = index;
            modalImage.setAttribute('src', this.getAttribute('data-img-src'));
        });
    });

    // Navigation Previous
    if (modalPrev) {
        modalPrev.addEventListener('click', function (event) {
            event.preventDefault();
            currentIndex = currentIndex > 0 ? currentIndex - 1 : images.length - 1;
            modalImage.setAttribute('src', images[currentIndex].getAttribute('data-img-src'));
        });
    }

    // Navigation Next
    if (modalNext) {
        modalNext.addEventListener('click', function (event) {
            event.preventDefault();
            currentIndex = currentIndex < images.length - 1 ? currentIndex + 1 : 0;
            modalImage.setAttribute('src', images[currentIndex].getAttribute('data-img-src'));
        });
    }
}

/**
 * Carrousels Bootstrap 5 dans le contenu libre (ex. carousel-multi-item migré Hermes 2.2.7).
 * Répare data-bs-ride mal placé dans class="" et initialise les contrôles prev/next.
 */
function repairCarouselMarkup(carouselEl) {
    [...carouselEl.classList].forEach((cls) => {
        if (cls === 'data-bs-ride' || cls.startsWith('data-bs-ride=')) {
            carouselEl.classList.remove(cls);
        }
    });

    const items = carouselEl.querySelectorAll('.carousel-inner > .carousel-item');
    const hasActive = Array.from(items).some((item) => item.classList.contains('active'));
    items.forEach((item, index) => {
        if (!hasActive) {
            item.classList.toggle('active', index === 0);
        }
    });
}

function bindCarouselControls(carouselEl) {
    const target = carouselEl.id ? `#${carouselEl.id}` : null;

    carouselEl.querySelectorAll('[data-bs-slide]').forEach((control) => {
        if (!control.getAttribute('data-bs-target')) {
            const href = control.getAttribute('href');
            if (href && href.startsWith('#')) {
                control.setAttribute('data-bs-target', href);
            } else if (target) {
                control.setAttribute('data-bs-target', target);
            }
        }
        control.addEventListener('click', (event) => {
            event.preventDefault();
        });
    });
}

function initPostContentCarousels() {
    document.querySelectorAll('.hermes-front-sections .carousel.slide, .post-content .carousel.slide, .carousel-multi-item.carousel.slide').forEach((carouselEl) => {
        repairCarouselMarkup(carouselEl);
        bindCarouselControls(carouselEl);

        const existing = Carousel.getInstance(carouselEl);
        if (existing) {
            existing.dispose();
        }

        const intervalAttr = parseInt(carouselEl.getAttribute('data-bs-interval') || '', 10);
        const rideAttr = (carouselEl.getAttribute('data-bs-ride') || '').toLowerCase();

        Carousel.getOrCreateInstance(carouselEl, {
            interval: Number.isFinite(intervalAttr) && intervalAttr > 0 ? intervalAttr : false,
            ride: rideAttr === 'carousel' ? 'carousel' : false,
            wrap: true,
        });
    });
}

function initModalImage() {
    const scopes = [];
    document.querySelectorAll('.post-content').forEach((el) => {
        if (el.querySelector('#modalImage')) {
            scopes.push(el);
        }
    });

    if (scopes.length === 0 && document.getElementById('modalImage')) {
        scopes.push(document);
    }

    scopes.forEach((scope) => initModalImageInScope(scope));
}

export function initHermesJsFront() {
    initPostContentCarousels();
    initModalImage();
    initPostContentGsapTextReveal();
    initPostContentGsapLetterReveal();
    initPostContentGsapShapeBuild();
    initPostContentGsapCarousel();
    initPostContentSplideHeroPresent();
    initPostContentHeroPresentStatic();
    initPostContentSplideCarousel();
    initPostContentGsapCarte();

    window.addEventListener('load', () => {
        colorLink();
        initPostContentCarousels();
        initPostContentGsapTextReveal();
        initPostContentGsapLetterReveal();
        initPostContentGsapShapeBuild();
        initPostContentGsapCarousel();
        initPostContentSplideCarousel();
        initPostContentGsapCarte();
    });
}
