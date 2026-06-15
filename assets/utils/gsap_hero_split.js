const ROOT_SELECTOR = '.hermes-front-sections .post-content .gsap-hero-split--vh';

function normalizeHeroSplitHeight(value) {
    const raw = String(value ?? '').trim();
    if (!raw) {
        return null;
    }

    if (/^\d+(\.\d+)?vh$/i.test(raw)) {
        return raw.toLowerCase();
    }

    const match = raw.match(/^(\d+(?:\.\d+)?)/);
    if (!match) {
        return null;
    }

    return `${match[1]}vh`;
}

function applyHeroSplitHeight(block) {
    const fromData = block.dataset.gsapHeroSplitH;
    const normalizedFromData = normalizeHeroSplitHeight(fromData);
    if (normalizedFromData) {
        block.style.setProperty('--gsap-hero-split-h', normalizedFromData);
        return;
    }

    const fromStyle = block.style.getPropertyValue('--gsap-hero-split-h');
    const normalizedFromStyle = normalizeHeroSplitHeight(fromStyle);
    if (normalizedFromStyle) {
        block.style.setProperty('--gsap-hero-split-h', normalizedFromStyle);
    }
}

export function initPostContentGsapHeroSplit(root = document) {
    root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
        if (block.dataset.gsapHeroSplitHeightBound === '1') {
            return;
        }

        block.dataset.gsapHeroSplitHeightBound = '1';
        applyHeroSplitHeight(block);
    });
}
