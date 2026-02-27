import {
    ClassicEditor,
    Essentials,
    Paragraph,
    Heading,
    Bold,
    Italic,
    Font,
    List,
    ListProperties,
    Image,
    ImageToolbar,
    ImageUpload,
    ImageInsert,
    Base64UploadAdapter,
    SourceEditing,
    GeneralHtmlSupport,
    FullPage
} from '/modules/commerce_store/assets/ckeditor5/ckeditor5.js';

function initCkEditor(element) {
    return ClassicEditor.create(element, {
        licenseKey: 'GPL',
        plugins: [
            Essentials,
            Paragraph,
            Heading,
            Bold,
            Italic,
            Font,
            List,
            ListProperties,
            Image,
            ImageToolbar,
            ImageUpload,
            ImageInsert,
            Base64UploadAdapter,
            SourceEditing,
            GeneralHtmlSupport,
            FullPage
        ],
        toolbar: [
            'heading', '|',
            'bold', 'italic', '|',
            'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|',
            'bulletedList', 'numberedList', '|',
            'insertImage', '|',
            'sourceEditing', '|',
            'undo', 'redo'
        ],
        image: {
            toolbar: ['imageTextAlternative', 'imageStyle:full', 'imageStyle:side']
        },
        codeBlock: {
            languages: [
                { language: 'plaintext', label: 'Plain text' },
                { language: 'c', label: 'C' },
                { language: 'cs', label: 'C#' },
                { language: 'cpp', label: 'C++' },
                { language: 'css', label: 'CSS' },
                { language: 'diff', label: 'Diff' },
                { language: 'html', label: 'HTML' },
                { language: 'java', label: 'Java' },
                { language: 'javascript', label: 'JavaScript' },
                { language: 'php', label: 'PHP' },
                { language: 'python', label: 'Python' },
                { language: 'ruby', label: 'Ruby' },
                { language: 'typescript', label: 'TypeScript' },
                { language: 'xml', label: 'XML' }
            ]
        },
        htmlSupport: {
            allow: [
                {
                    name: /.*/,
                    attributes: true,
                    classes: true,
                    styles: true
                }
            ],
            fullPage: {
                allowRenderStylesFromHead: true,
                sanitizeCss(css) {
                    return { css, hasChanged: false };
                }
            }
        }
    });
}

// Initial load
if (document.querySelectorAll('textarea')) {
    const textarea = document.querySelectorAll('textarea');
    Array.from(textarea).forEach((element) => {
        if (element.classList.contains('editor')) {
            initCkEditor(element)
                .then(editor => { window.editor = editor; })
                .catch(error => { console.error(error); });
        }
    });
}

// Reload function
function reloadCk() {
    if (document.querySelectorAll('textarea')) {
        const textarea = document.querySelectorAll('textarea');
        Array.from(textarea).forEach((element) => {
            if (element.classList.contains('editor')) {
                initCkEditor(element)
                    .then(editor => { window.editor = editor; })
                    .catch(error => { console.error(error); });
            }
        });
    }
}

window.reloadCk = reloadCk;
window.initCkEditor = initCkEditor;