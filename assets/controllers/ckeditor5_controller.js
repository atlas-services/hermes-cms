// assets/controllers/ckeditor5_controller.js
import { Controller } from '@hotwired/stimulus';
import EnhancedEditor from '../ckeditor5.js';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this._onInsert = (ev) => this.insertHtml(ev.detail?.html);
        document.addEventListener('hermes:insertHtml', this._onInsert);
        EnhancedEditor.create(this.element)
            .then((editor) => {
                this.editor = editor;
            })
            .catch((error) => console.error(error));
    }

    disconnect() {
        document.removeEventListener('hermes:insertHtml', this._onInsert);
        if (this.editor && typeof this.editor.destroy === 'function') {
            this.editor.destroy().catch((error) => console.error(error));
        }
    }

    insertHtml(html) {
        if (!html || !this.editor) {
            return;
        }
        const ed = this.editor;
        const current = ed.getData() || '';
        ed.setData(current + (current.trim() !== '' ? '\n' : '') + html);
    }
}
