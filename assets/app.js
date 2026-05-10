import './stimulus_bootstrap.js';
import 'bootstrap';
import { Tooltip } from 'bootstrap';
import AOS from 'aos';

// css
import './styles/app.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@fortawesome/fontawesome-free/css/all.css';
import 'aos/dist/aos.css';

document.addEventListener('DOMContentLoaded', () => {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].forEach(el => {
        new Tooltip(el);
    });

    if (typeof AOS !== 'undefined' && typeof AOS.init === 'function') {
        AOS.init({
            once: true,
            duration: 800,
            startEvent: 'DOMContentLoaded',
        });
        requestAnimationFrame(() => {
            if (typeof AOS.refresh === 'function') {
                AOS.refresh();
            }
        });
    }
});
