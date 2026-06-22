import { startStimulusApp } from '@symfony/stimulus-bundle';
import ContentTransferTargetsController from './controllers/content_transfer_targets_controller.js';
import PageChevronController from './controllers/page_chevron_controller.js';
import TopbarDismissController from './controllers/topbar_dismiss_controller.js';

const app = startStimulusApp();

if (!app.router.modulesByIdentifier.has('page-chevron')) {
    app.register('page-chevron', PageChevronController);
}

if (!app.router.modulesByIdentifier.has('content-transfer-targets')) {
    app.register('content-transfer-targets', ContentTransferTargetsController);
}

if (!app.router.modulesByIdentifier.has('topbar-dismiss')) {
    app.register('topbar-dismiss', TopbarDismissController);
}
