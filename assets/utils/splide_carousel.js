import Splide from '@splidejs/splide';
import '@splidejs/splide/css';

const ROOT_SELECTOR = '.hermes-front-sections .splide-carousel:not(.splide-carousel--hero-present)';

const EFFECTS = ['slide', 'fade', 'focus', 'peek'];

const DEFAULTS = {
    effect: 'slide',
    type: 'loop',
    autoplay: false,
    interval: 5000,
    pauseOnHover: true,
    arrows: true,
    pagination: true,
    perPage: 3,
    perPageMobile: 1,
    breakpoint: 992,
    gap: '1rem',
    gapMobile: '0',
    padding: '',
    paddingMobile: '',
    speed: 700,
    easing: 'cubic-bezier(0.33, 1, 0.68, 1)',
    drag: true,
    rewind: true,
    scaleInactive: 0.88,
    updateOnMove: true,
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
    const effect = readString(dataset, 'splideCarouselEffectValue', DEFAULTS.effect);
    const type = readString(dataset, 'splideCarouselTypeValue', DEFAULTS.type);

    return {
        effect: EFFECTS.includes(effect) ? effect : DEFAULTS.effect,
        type: ['slide', 'loop', 'fade'].includes(type) ? type : DEFAULTS.type,
        autoplay: readBool(dataset, 'splideCarouselAutoplayValue', DEFAULTS.autoplay),
        interval: readNumber(dataset, 'splideCarouselIntervalValue', DEFAULTS.interval),
        pauseOnHover: readBool(dataset, 'splideCarouselPauseOnHoverValue', DEFAULTS.pauseOnHover),
        arrows: readBool(dataset, 'splideCarouselArrowsValue', DEFAULTS.arrows),
        pagination: readBool(dataset, 'splideCarouselPaginationValue', DEFAULTS.pagination),
        perPage: readNumber(dataset, 'splideCarouselPerPageValue', DEFAULTS.perPage),
        perPageMobile: readNumber(dataset, 'splideCarouselPerPageMobileValue', DEFAULTS.perPageMobile),
        breakpoint: readNumber(dataset, 'splideCarouselBreakpointValue', DEFAULTS.breakpoint),
        gap: readString(dataset, 'splideCarouselGapValue', DEFAULTS.gap),
        gapMobile: readString(dataset, 'splideCarouselGapMobileValue', DEFAULTS.gapMobile),
        padding: readString(dataset, 'splideCarouselPaddingValue', DEFAULTS.padding),
        paddingMobile: readString(dataset, 'splideCarouselPaddingMobileValue', DEFAULTS.paddingMobile),
        speed: readNumber(dataset, 'splideCarouselSpeedValue', DEFAULTS.speed),
        easing: readString(dataset, 'splideCarouselEasingValue', DEFAULTS.easing),
        drag: readBool(dataset, 'splideCarouselDragValue', DEFAULTS.drag),
        rewind: readBool(dataset, 'splideCarouselRewindValue', DEFAULTS.rewind),
        scaleInactive: readNumber(dataset, 'splideCarouselScaleInactiveValue', DEFAULTS.scaleInactive),
        updateOnMove: readBool(dataset, 'splideCarouselUpdateOnMoveValue', DEFAULTS.updateOnMove),
        fixedWidth: readString(dataset, 'splideCarouselFixedWidthValue', ''),
        fixedHeight: readString(dataset, 'splideCarouselFixedHeightValue', ''),
        height: readString(dataset, 'splideCarouselHeightValue', ''),
        trimSpace: readBool(dataset, 'splideCarouselTrimSpaceValue', false),
    };
}

function resolveEffectType(config, slideCount = 0) {
    if (config.effect === 'fade') {
        return 'fade';
    }

    if (config.effect === 'focus') {
        return 'loop';
    }

    if (config.type === 'slide') {
        return 'slide';
    }

    if (config.effect === 'peek' && slideCount <= 3) {
        return 'slide';
    }

    return 'loop';
}

function resolveDesktopPadding(config) {
    if (config.padding) {
        return config.padding;
    }

    if (config.effect === 'peek') {
        return '1.5rem';
    }

    return 0;
}

function isNaturalLayout(root) {
    return root.classList.contains('splide-carousel--natural');
}

function isCardsLayout(root) {
    return root.classList.contains('splide-carousel--cards');
}

function buildMobileBreakpointOptions(config, natural = false, cardsLayout = false) {
    const mobile = {
        type: config.effect === 'fade' ? 'fade' : 'slide',
        perPage: config.effect === 'fade' ? 1 : config.perPageMobile,
        perMove: 1,
        gap: cardsLayout ? config.gap : 0,
        focus: cardsLayout ? 'center' : 0,
        padding: 0,
        arrows: config.arrows,
        pagination: config.pagination,
        speed: config.speed,
        trimSpace: true,
        rewind: true,
    };

    if (natural || cardsLayout) {
        mobile.autoHeight = true;
    }

    if (!cardsLayout && config.paddingMobile) {
        mobile.padding = config.paddingMobile;
    }

    if (config.gapMobile && config.gapMobile !== '0') {
        mobile.gap = config.gapMobile;
    }

    return mobile;
}

function buildBreakpoints(config, natural = false, cardsLayout = false) {
    return {
        [config.breakpoint]: buildMobileBreakpointOptions(config, natural, cardsLayout),
    };
}

function resetInlineLayoutStyles(root) {
    root.querySelectorAll('.splide__slide').forEach((slide) => {
        slide.style.removeProperty('width');
        slide.style.removeProperty('margin-right');
    });

    const track = root.querySelector('.splide__track');
    if (track) {
        track.style.removeProperty('padding-left');
        track.style.removeProperty('padding-right');
    }
}

function shouldUseSlideType(config, slideCount, desktopPerPage) {
    if (config.effect === 'fade' || config.effect === 'focus') {
        return false;
    }

    if (config.type === 'slide') {
        return true;
    }

    // loop + slides <= perPage décale la piste (ex. 3 cartes / perPage 3)
    return slideCount <= desktopPerPage;
}

function buildSplideOptions(config, slideCount = 0, natural = false, cardsLayout = false) {
    const effectType = resolveEffectType(config, slideCount);
    const desktopPerPage = config.effect === 'fade' ? 1 : Math.max(1, config.perPage);
    const useSlideType = cardsLayout || shouldUseSlideType(config, slideCount, desktopPerPage);
    const cardsDesktopMulti = cardsLayout && desktopPerPage > 1;

    const options = {
        type: useSlideType ? 'slide' : effectType,
        autoplay: config.autoplay,
        interval: config.interval,
        pauseOnHover: config.pauseOnHover,
        pauseOnFocus: true,
        arrows: config.arrows,
        pagination: config.pagination,
        perPage: desktopPerPage,
        perMove: cardsDesktopMulti ? desktopPerPage : 1,
        gap: config.gap,
        padding: cardsLayout ? 0 : resolveDesktopPadding(config),
        focus: cardsLayout && desktopPerPage === 1 ? 'center' : 0,
        speed: config.speed,
        easing: config.easing,
        drag: config.drag,
        rewind: config.effect === 'fade' || useSlideType,
        trimSpace: cardsLayout,
        autoHeight: natural || config.effect === 'fade' || cardsLayout,
        updateOnMove: config.updateOnMove,
        start: 0,
        keyboard: 'global',
        accessibility: true,
        breakpoints: buildBreakpoints(config, natural, cardsLayout),
    };

    if (config.fixedWidth) {
        options.fixedWidth = config.fixedWidth;
    }

    if (config.fixedHeight) {
        options.fixedHeight = config.fixedHeight;
    }

    if (config.height) {
        options.height = config.height;
    }

    return options;
}

function applyEffectPresentation(root, config) {
    root.classList.remove(
        'splide-carousel--effect-slide',
        'splide-carousel--effect-fade',
        'splide-carousel--effect-focus',
        'splide-carousel--effect-peek',
    );
    root.classList.add(
        `splide-carousel--effect-${config.effect}`,
        'w-100',
        'position-relative',
        'overflow-hidden',
    );

    if (!root.classList.contains('splide-carousel--cards')) {
        root.classList.add('splide-carousel--fullwidth');
    }

    root.style.setProperty('--splide-carousel-speed', `${config.speed}ms`);
    root.style.setProperty('--splide-carousel-breakpoint', `${config.breakpoint}px`);
}

function initSplideCarouselBlock(root) {
    if (root.classList.contains('splide-carousel--hero-present')) {
        return;
    }

    if (root.dataset.splideCarouselMounted === '1') {
        return;
    }

    const track = root.querySelector('.splide__track');
    const slides = root.querySelectorAll('.splide__slide');

    if (!track || slides.length === 0) {
        return;
    }

    const config = readConfig(root);
    if (slides.length === 1) {
        config.autoplay = false;
        config.effect = 'slide';
        config.type = 'slide';
    }
    root.dataset.splideCarouselMounted = '1';
    resetInlineLayoutStyles(root);
    applyEffectPresentation(root, config);

    const natural = isNaturalLayout(root);
    const cardsLayout = isCardsLayout(root);
    const splide = new Splide(root, buildSplideOptions(config, slides.length, natural, cardsLayout));

    if (natural) {
        const refreshHeight = () => {
            if (isMobileLayout(config)) {
                splide.refresh();
            }
        };

        splide.on('active', refreshHeight);
        root.querySelectorAll('.splide__slide img').forEach((img) => {
            if (img.complete) {
                return;
            }

            img.addEventListener('load', refreshHeight, { once: true });
        });
    }

    splide.mount();
}

export function initPostContentSplideCarousel(root = document) {
    root.querySelectorAll(ROOT_SELECTOR).forEach((block) => {
        initSplideCarouselBlock(block);
    });
}
