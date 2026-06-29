// assets/controllers/ckeditor5_controller.js
import { Controller } from '@hotwired/stimulus';
import EnhancedEditor from '../ckeditor5.js';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this._onInsert = (ev) => this.insertHtml(ev.detail?.html);
        this._onScrollToEditor = () => this.scrollToEditor(false);
        document.addEventListener('hermes:insertHtml', this._onInsert);
        document.addEventListener('hermes:scrollToEditor', this._onScrollToEditor);
        EnhancedEditor.create(this.element)
            .then((editor) => {
                this.editor = editor;
            })
            .catch((error) => console.error(error));
    }

    disconnect() {
        document.removeEventListener('hermes:insertHtml', this._onInsert);
        document.removeEventListener('hermes:scrollToEditor', this._onScrollToEditor);
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
        this.scrollToEditor(true);
    }

    scrollToEditor(retry = false) {
        const field = this.element.closest('.js-post-field-content') || this.element;
        const editorElement = field.querySelector('.ck-editor__editable') || field.querySelector('.ck-editor') || field;

        requestAnimationFrame(() => {
            const top = editorElement.getBoundingClientRect().top + window.scrollY - 90;
            window.scrollTo({
                top: Math.max(0, top),
                behavior: 'smooth',
            });

            if (this.editor?.editing?.view) {
                this.editor.editing.view.focus();
            }

            if (retry) {
                window.setTimeout(() => this.scrollToEditor(false), 250);
            }
        });
    }
}
