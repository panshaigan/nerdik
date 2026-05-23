/**
 * Align TinyMCE (Mary x-editor) with --ui-field-* tokens: iframe body + content_css.
 */
function readFieldChrome() {
    const root = document.documentElement;
    const style = getComputedStyle(root);

    return {
        bg: style.getPropertyValue('--ui-field-bg').trim() || style.getPropertyValue('--color-base-200').trim(),
        color: style.getPropertyValue('--color-base-content').trim(),
        border: style.getPropertyValue('--ui-field-border-color').trim() || style.getPropertyValue('--color-base-300').trim(),
    };
}

function contentStyleFromChrome(existing = '') {
    const { bg, color } = readFieldChrome();
    const base = `body{background-color:${bg} !important;color:${color} !important;margin:0.5rem;}`;
    const extra = 'img{max-width:100%;height:auto;}';

    return existing ? `${existing} ${base} ${extra}` : `${base} ${extra}`;
}

export function syncTinyMceEditorBody(editor) {
    if (!editor?.getDoc) {
        return;
    }

    const doc = editor.getDoc();
    if (!doc?.body) {
        return;
    }

    const { bg, color } = readFieldChrome();
    doc.body.style.backgroundColor = bg;
    doc.body.style.color = color;
}

function patchTinyMceInit() {
    if (typeof tinymce === 'undefined' || tinymce.__nerdikFieldChromePatched) {
        return;
    }

    tinymce.__nerdikFieldChromePatched = true;

    const originalInit = tinymce.init.bind(tinymce);

    tinymce.init = (options) => {
        const opts = typeof options === 'object' && options !== null ? { ...options } : options;

        if (typeof opts === 'object' && opts !== null) {
            opts.content_css = false;
            opts.content_style = contentStyleFromChrome(
                typeof opts.content_style === 'string' ? opts.content_style : '',
            );

            const userSetup = opts.setup;
            opts.setup = (editor) => {
                editor.on('init', () => syncTinyMceEditorBody(editor));
                if (typeof userSetup === 'function') {
                    userSetup(editor);
                }
            };
        }

        return originalInit(opts);
    };
}

export function bootTinyMceFieldChrome() {
    patchTinyMceInit();

    if (typeof tinymce !== 'undefined' && Array.isArray(tinymce.editors)) {
        tinymce.editors.forEach(syncTinyMceEditorBody);
    }
}

patchTinyMceInit();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootTinyMceFieldChrome);
} else {
    bootTinyMceFieldChrome();
}

document.addEventListener('nerdik:theme-applied', bootTinyMceFieldChrome);
document.addEventListener('livewire:navigated', bootTinyMceFieldChrome);

window.bootNerdikTinyMceFieldChrome = bootTinyMceFieldChrome;
