// assets/controllers/ckeditor5_controller.js
import { Controller } from '@hotwired/stimulus';
import EnhancedEditor from '../ckeditor5.js';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.editor = EnhancedEditor.create(this.element)
            .then(editor => (this.editor = editor))
            .catch(error => console.error(error));
    }

    disconnect() {
        this.editor.destroy().catch(error => console.error(error));
    }
}
