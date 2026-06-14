import { initSplideHeroPresentBlock } from './splide_hero_present.js';

const ROOT_SELECTOR = '.hermes-front-sections .post-content .hermes-pitch';

function updateTabs(root, activeIndex) {
    root.querySelectorAll('[data-hermes-pitch-tab]').forEach((button) => {
        const tabIndex = parseInt(button.dataset.hermesPitchTab || '0', 10);
        const isActive = tabIndex === activeIndex;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
}

function activateTrack(root, index) {
    const tracks = [...root.querySelectorAll('[data-hermes-pitch-track]')];

    tracks.forEach((track, trackIndex) => {
        const isActive = trackIndex === index;
        track.classList.toggle('is-active', isActive);
        track.hidden = !isActive;

        if (!isActive) {
            return;
        }

        const splideRoot = track.querySelector('.splide-carousel--hero-present');
        if (!splideRoot) {
            return;
        }

        if (splideRoot.dataset.splideHeroPresentMounted !== '1') {
            initSplideHeroPresentBlock(splideRoot);
            return;
        }

        if (splideRoot.splide) {
            splideRoot.splide.refresh();
        }
    });

    updateTabs(root, index);
}

function bindPitchTabs(root) {
    root.querySelectorAll('[data-hermes-pitch-tab]').forEach((button) => {
        if (button.dataset.hermesPitchTabBound === '1') {
            return;
        }

        button.dataset.hermesPitchTabBound = '1';
        button.addEventListener('click', () => {
            const index = parseInt(button.dataset.hermesPitchTab || '0', 10);
            activateTrack(root, index);
        });
    });
}

export function initPostContentPitchPresent(root = document) {
    root.querySelectorAll(ROOT_SELECTOR).forEach((pitch) => {
        if (pitch.dataset.hermesPitchMounted === '1') {
            return;
        }

        pitch.dataset.hermesPitchMounted = '1';
        bindPitchTabs(pitch);
        activateTrack(pitch, 0);
    });
}
