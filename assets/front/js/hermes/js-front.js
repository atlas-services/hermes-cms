/**
 * Port Hermes 2.2.7 — assets/front/js/hermes/js-front.js
 * @see https://github.com/atlas-services/hermes/blob/release/2.2.7/assets/front/js/hermes/js-front.js
 */

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
    window.addEventListener('load', () => {
        colorLink();
    });

    initModalImage();
}
