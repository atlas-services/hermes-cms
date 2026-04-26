import './stimulus_bootstrap.js';
import 'bootstrap';
import { Tooltip } from 'bootstrap';

// css
import './styles/app.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@fortawesome/fontawesome-free/css/all.css';

document.addEventListener('DOMContentLoaded', () => {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].forEach(el => {
        new Tooltip(el);
    });
});
