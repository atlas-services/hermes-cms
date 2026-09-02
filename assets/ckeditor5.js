// assets/ckeditor5.js
import {
    Alignment,
    Autoformat,
    BlockQuote,
    Bold,
    ClassicEditor,
    Essentials,
    Font,
    FontBackgroundColor,
    FontSize,
    FontFamily,
    GeneralHtmlSupport,
    HtmlEmbed,
    Indent,
    IndentBlock,
    Italic,
    ImageBlock,
    ImageCaption,
    ImageInline,
    ImageInsert,
    ImageInsertViaUrl,
    ImageResize,
    ImageTextAlternative,
    ImageToolbar,
    ImageUpload,
    Image,
    Link,
    List,
    MediaEmbed,
    Paragraph,
    Plugin,
    SimpleUploadAdapter,
    SourceEditing,
    Strikethrough,
    Underline,
    ButtonView
} from 'ckeditor5';
// Si vous devez importer des traductions, ici les traductions en français
import coreTranslations from 'ckeditor5/translations/fr.js';
import { CKEDITOR_FONT_FAMILY_OPTIONS } from './config/hermes-font-families.js';
import 'ckeditor5/dist/ckeditor5.min.css';

export default class EnhancedEditor extends ClassicEditor {}

class EditorBackgroundToggle extends Plugin {
    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('editorBackgroundToggle', (locale) => {
            const button = new ButtonView(locale);

            button.set({
                label: 'Fond éditeur',
                tooltip: 'Basculer le fond de la zone d’édition',
                withText: true,
            });

            button.on('execute', () => {
                const editable = editor.ui.getEditableElement();

                if (!editable) {
                    return;
                }

                const isActive = editable.classList.toggle('ck-editor__editable--contrast-bg');
                button.isOn = isActive;
            });

            return button;
        });
    }
}

EnhancedEditor.builtinPlugins = [
    Alignment,
    Autoformat,
    BlockQuote,
    Bold,
    Essentials,
    Font,
    FontBackgroundColor,
    FontSize,
    FontFamily,
    GeneralHtmlSupport,
    HtmlEmbed,
    Indent,
    IndentBlock,
    Italic,
    ImageBlock,
    ImageCaption,
    ImageInline,
    ImageInsert,
    ImageInsertViaUrl,
    ImageResize,
    ImageTextAlternative,
    ImageToolbar,
    ImageUpload,
    Image,
    Link,
    List,
    MediaEmbed,
    Paragraph,
    EditorBackgroundToggle,
    SimpleUploadAdapter,
    SourceEditing,
    Strikethrough,
    Underline
    ];

EnhancedEditor.defaultConfig = {
    licenseKey: 'GPL',
    toolbar: [
        'sourceEditing',
        'editorBackgroundToggle',
        "list",
        "paragraph",
        'fontSize',
        'fontFamily',
        'fontColor',
        'fontBackgroundColor',
        'bold',
        'italic',
        'underline',
        'strikethrough',
        '|',
        "blockQuote",
        '|', 'alignment:left', 'alignment:center', 'alignment:justify', 'alignment:right',
        "|",
        'bulletedList',
        'numberedList',
        "|",
        'outdent',
        'indent',
        "|",
        'link',
        'mediaEmbed',
        'insertImage',
        '|',
        'undo',
        'redo',
    ],
    
    // Vous pouvez supprimer la ligne suivante si vous n'avez pas besoin de charger des traductions
    translations: [coreTranslations],
        heading: {
			options: [
				{
					model: 'paragraph',
					title: 'Paragraph',
					class: 'ck-heading_paragraph',
				},
				{
					model: 'heading1',
					view: 'h1',
					title: 'Heading 1',
					class: 'ck-heading_heading1',
				},
				{
					model: 'heading2',
					view: 'h2',
					title: 'Heading 2',
					class: 'ck-heading_heading2',
				},
				{
					model: 'heading3',
					view: 'h3',
					title: 'Heading 3',
					class: 'ck-heading_heading3',
				},
				{
					model: 'heading4',
					view: 'h4',
					title: 'Heading 4',
					class: 'ck-heading_heading4',
				},
			],
		},
    fontFamily: {
        options: CKEDITOR_FONT_FAMILY_OPTIONS,
        supportAllValues: true,
    },
    htmlSupport: {
      allow: [
          {
              name: /.*/,
              attributes: true,
              classes: true,
              styles: true
          },
          {
              name: 'div',
              classes: ['hermes-flair-cards', 'hermes-card-flair', 'card'],
              attributes: [
                  'data-hermes-flair-accent',
                  'data-hermes-flair-button-bg',
                  'data-hermes-flair-button-hover',
                  'data-hermes-flair-card-bg',
                  'data-hermes-flair-card-border',
                  'data-hermes-flair-card-hover',
                  'data-hermes-card-flair',
              ],
          },
      ]
    },
    simpleUpload :{
      uploadUrl: "/api/file/upload"

    },
    mediaEmbed: {
      previewsInData:true
    },
    link: {
      decorators: {
      isExternal: {
        mode: 'automatic',
        callback: url => url.startsWith( 'http' ),
        attributes: {
          target: '_blank',
          rel: 'noopener noreferrer'
        }
      }
		}
  }
};
