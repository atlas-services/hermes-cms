import './gsap.js';
import { initPostContentGsapButtonFlair, initPostContentGsapCardFlair } from './utils/gsap_button_flair.js';
import { initPostContentGsapTextReveal } from './utils/gsap_text_reveal.js';
import './stimulus_bootstrap.js';
import './controllers/csrf_protection_controller.js';
import { initHermesJsFront } from './front/js/hermes/js-front.js';
import 'bootstrap';
import { Tooltip } from 'bootstrap';
import AOS from 'aos';

// css
import './styles/site-fonts.js';
import './styles/app.css';
import './styles/showcase.css';
import './styles/legal.css';
import './styles/splide.css';
import './styles/carte.css';
import './styles/gsap-letter-reveal.css';
import './styles/gsap-image-reveal.css';
import './styles/gsap-shape-build.css';
import './styles/gsap-text-reveal.css';
import './styles/hero-split.css';
import './styles/hero-present.css';
import './styles/buttons.css';
import './styles/gsap-demo.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/button-flair.css';
import './styles/card-flair.css';
import './styles/atw.css';
import '@fortawesome/fontawesome-free/css/all.css';
import 'aos/dist/aos.css';

document.addEventListener('DOMContentLoaded', () => {
    initPostContentGsapButtonFlair();
    initPostContentGsapCardFlair();
    initPostContentGsapTextReveal();

    if (!document.body.classList.contains('hermes-admin')) {
        initHermesJsFront();
    }

    window.addEventListener('load', () => {
        initPostContentGsapButtonFlair();
        initPostContentGsapCardFlair();
        initPostContentGsapTextReveal();
    });

    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].forEach(el => {
        new Tooltip(el);
    });

    if (typeof AOS !== 'undefined' && typeof AOS.init === 'function') {
        const refreshAos = () => {
            if (typeof AOS.refresh === 'function') {
                AOS.refresh();
            }
        };

        AOS.init({
            once: true,
            duration: 600,
            startEvent: 'DOMContentLoaded',
        });
        requestAnimationFrame(refreshAos);
        window.addEventListener('load', refreshAos);

        document.querySelectorAll('.navbar-collapse').forEach((collapseEl) => {
            collapseEl.addEventListener('shown.bs.collapse', refreshAos);
        });
    }
});
