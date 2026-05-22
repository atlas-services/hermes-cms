import { startStimulusApp } from '@symfony/stimulus-bundle';
import PageChevronController from './controllers/page_chevron_controller.js';

const app = startStimulusApp();

if (!app.router.modulesByIdentifier.has('page-chevron')) {
    app.register('page-chevron', PageChevronController);
}
