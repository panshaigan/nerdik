import Croppie from 'croppie';
import 'croppie/croppie.css';

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

let imageCropperAbort;
let cropperInstance = null;
let currentForm = null;
let currentDropzone = null;
let currentFileInput = null;
let originalImageDataUrl = null;
let hasPendingCrop = false;

function findLivewireUploadComponent(form) {
    const root = form?.closest('[wire\\:id]');
    const id = root?.getAttribute('wire:id');
    if (!id || typeof window.Livewire?.find !== 'function') {
        return null;
    }

    return window.Livewire.find(id);
}

function getCropModal() {
    return document.getElementById('ui-image-crop-modal');
}

function getCropViewport() {
    return document.querySelector('[data-image-crop-croppie]');
}

function resolveForm(dropzone) {
    const selector = dropzone?.dataset?.imageCropForm;
    if (selector) {
        if (selector.startsWith('#') || selector.startsWith('[')) {
            return document.querySelector(selector);
        }

        return dropzone.closest(selector) ?? document.querySelector(`[${selector}]`);
    }

    return dropzone?.closest('form') ?? null;
}

function getDropzoneConfig(dropzone) {
    if (!dropzone) {
        return null;
    }

    return {
        aspect: dropzone.dataset.imageCropAspect ?? 'square',
        wireProperty: dropzone.dataset.imageCropWireProperty ?? 'croppedImage',
        clearMethod: dropzone.dataset.imageCropClearMethod ?? 'clearCroppedImage',
        output: parseOutputSize(dropzone.dataset.imageCropOutput ?? '512,512'),
        fileName: dropzone.dataset.imageCropFileName ?? 'image.webp',
        previewSavedEvent: dropzone.dataset.imageCropSavedEvent ?? null,
    };
}

function parseOutputSize(raw) {
    const parts = String(raw).split(',').map((n) => parseInt(n.trim(), 10));

    return {
        width: Number.isFinite(parts[0]) ? parts[0] : 512,
        height: Number.isFinite(parts[1]) ? parts[1] : 512,
    };
}

function getDropzoneLabels(dropzone) {
    return {
        choose: dropzone?.dataset.labelChoose ?? 'Choose file',
        crop: dropzone?.dataset.labelCrop ?? 'Crop file',
    };
}

function destroyCroppieInstance(croppie) {
    if (croppie && typeof croppie.destroy === 'function') {
        try {
            croppie.destroy();
        } catch {
            // Element may already be detached after a Livewire morph.
        }
    }
}

function resetCropViewportElement(viewport) {
    if (!viewport) {
        return;
    }

    viewport.classList.remove('croppie-container', 'ui-image-crop-croppie-instance');
    viewport.replaceChildren();
}

function getCropDimensions(modalBox, aspect) {
    const padding = 32;
    const rawWidth = (modalBox?.clientWidth ?? 448) - padding;
    const width = Math.max(280, Math.min(560, rawWidth));

    if (aspect === 'video') {
        const boundaryWidth = width;
        const boundaryHeight = Math.round(width * (9 / 16));
        const viewportWidth = Math.round(boundaryWidth * 0.92);
        const viewportHeight = Math.round(viewportWidth * (9 / 16));

        return {
            boundary: { width: boundaryWidth, height: boundaryHeight },
            viewport: { width: viewportWidth, height: viewportHeight, type: 'square' },
        };
    }

    const viewportSize = Math.round(width * 0.65);

    return {
        boundary: { width, height: width },
        viewport: { width: viewportSize, height: viewportSize, type: 'circle' },
    };
}

function getPreview(form) {
    return form?.querySelector('[data-image-crop-preview]');
}

function hidePreviewPlaceholder(form) {
    const placeholder = form?.querySelector('[data-image-crop-preview-placeholder]');
    if (placeholder) {
        placeholder.classList.add('hidden');
    }
}

function setCropPreview(form, blob) {
    const preview = getPreview(form);
    if (!preview) {
        return;
    }

    hidePreviewPlaceholder(form);

    const previousUrl = preview.dataset.blobUrl;
    if (previousUrl) {
        URL.revokeObjectURL(previousUrl);
        delete preview.dataset.blobUrl;
    }

    const url = URL.createObjectURL(blob);
    preview.dataset.blobUrl = url;
    preview.src = url;
    preview.classList.remove('hidden');
}

function revokeCropPreviewBlob(preview) {
    const previousUrl = preview?.dataset.blobUrl;
    if (!previousUrl) {
        return;
    }

    URL.revokeObjectURL(previousUrl);
    delete preview.dataset.blobUrl;
}

function syncPreviewUrl(form, url) {
    const preview = getPreview(form);
    if (!preview || !url) {
        return;
    }

    hidePreviewPlaceholder(form);

    revokeCropPreviewBlob(preview);
    preview.dataset.defaultSrc = url;
    preview.src = url;
    preview.classList.remove('hidden');
}

function syncNavigationAvatars(url) {
    if (!url) {
        return;
    }

    document.querySelectorAll('[data-nav-user-avatar]').forEach((img) => {
        img.src = url;
    });
}

function resetCropPreview(form) {
    const preview = getPreview(form);
    if (!preview) {
        return;
    }

    revokeCropPreviewBlob(preview);

    const defaultSrc = preview.dataset.defaultSrc;
    if (defaultSrc) {
        preview.src = defaultSrc;
        preview.classList.remove('hidden');
    }
}

function clearFileInput() {
    if (currentFileInput) {
        currentFileInput.value = '';
    }
}

function updateFileUi(dropzone, { buttonText, showRemove, showRecropHint }) {
    if (!dropzone) {
        return;
    }

    const buttonTextEl = dropzone.querySelector('[data-image-crop-file-button-text]');
    const removeBtn = dropzone.querySelector('[data-image-crop-remove]');
    const recropHint = dropzone.querySelector('[data-image-crop-recrop-hint]');

    if (buttonTextEl && buttonText !== undefined) {
        buttonTextEl.textContent = buttonText;
    }

    if (removeBtn) {
        removeBtn.classList.toggle('hidden', !showRemove);
    }

    if (recropHint) {
        recropHint.classList.toggle('hidden', !showRecropHint);
    }
}

function resetPendingCropUi(form, dropzone, { resetPreview = true } = {}) {
    originalImageDataUrl = null;
    hasPendingCrop = false;

    if (!form || !dropzone) {
        return;
    }

    const labels = getDropzoneLabels(dropzone);
    updateFileUi(dropzone, {
        buttonText: labels.choose,
        showRemove: false,
        showRecropHint: false,
    });

    if (resetPreview) {
        resetCropPreview(form);
    }
}

function clearPendingCropState(form, dropzone, { clearInput = true, resetPreview = true } = {}) {
    resetPendingCropUi(form, dropzone, { resetPreview });

    if (clearInput) {
        clearFileInput();
    }
}

function resolveSavedEventUrl(event) {
    const detail = event?.detail;

    if (typeof detail === 'string') {
        return detail;
    }

    if (detail && typeof detail === 'object') {
        if (typeof detail.url === 'string') {
            return detail.url;
        }

        if (typeof detail.avatarUrl === 'string') {
            return detail.avatarUrl;
        }

        if (Array.isArray(detail) && typeof detail[0] === 'string') {
            return detail[0];
        }
    }

    return null;
}

function isAcceptedImageFile(file) {
    return file instanceof File && ACCEPTED_IMAGE_TYPES.includes(file.type);
}

function openCropModal(dropzone) {
    const modal = getCropModal();
    if (!modal) {
        return;
    }

    const title = dropzone?.dataset?.imageCropModalTitle;
    const titleEl = modal.querySelector('[data-image-crop-modal-title]');
    if (titleEl && title) {
        titleEl.textContent = title;
    }

    if (typeof modal.showModal === 'function') {
        modal.showModal();
    } else {
        modal.setAttribute('open', '');
    }
}

function closeModal({ resetSession = false } = {}) {
    const modal = getCropModal();
    if (modal) {
        if (typeof modal.close === 'function') {
            modal.close();
        } else {
            modal.removeAttribute('open');
        }
    }

    destroyCroppieInstance(cropperInstance);
    cropperInstance = null;
    resetCropViewportElement(getCropViewport());

    if (resetSession) {
        clearPendingCropState(currentForm, currentDropzone);
        currentForm = null;
        currentDropzone = null;
        currentFileInput = null;
    }
}

function initCroppieWithUrl(url) {
    const liveViewport = getCropViewport();
    const liveModal = getCropModal();
    if (!liveViewport || !liveModal || !currentDropzone) {
        return;
    }

    const config = getDropzoneConfig(currentDropzone);
    openCropModal(currentDropzone);

    const startCroppie = () => {
        const modalBox = liveModal.querySelector('.modal-box');
        const { boundary, viewport: viewportOptions } = getCropDimensions(modalBox, config.aspect);

        destroyCroppieInstance(cropperInstance);
        cropperInstance = null;
        resetCropViewportElement(liveViewport);

        try {
            cropperInstance = new Croppie(liveViewport, {
                viewport: viewportOptions,
                boundary,
                enableOrientation: true,
                customClass: 'ui-image-crop-croppie-instance',
            });
            cropperInstance.bind({ url });
        } catch (error) {
            console.error('Failed to initialize image cropper', error);
            closeModal();
        }
    };

    requestAnimationFrame(() => {
        requestAnimationFrame(startCroppie);
    });
}

function openCropperForFile(file, dropzone, fileInput) {
    if (!file || !dropzone || !isAcceptedImageFile(file)) {
        return;
    }

    const form = resolveForm(dropzone);
    if (!form || !getCropModal() || !getCropViewport()) {
        return;
    }

    const config = getDropzoneConfig(dropzone);

    if (hasPendingCrop) {
        const wire = findLivewireUploadComponent(form);
        if (wire && typeof wire.call === 'function') {
            wire.call(config.clearMethod);
        }
    }

    currentForm = form;
    currentDropzone = dropzone;
    currentFileInput = fileInput;
    hasPendingCrop = false;

    const labels = getDropzoneLabels(dropzone);
    updateFileUi(dropzone, {
        buttonText: labels.crop,
        showRemove: false,
        showRecropHint: false,
    });

    const reader = new FileReader();
    reader.onload = (e) => {
        const dataUrl = e.target?.result;
        if (typeof dataUrl !== 'string') {
            return;
        }

        originalImageDataUrl = dataUrl;
        initCroppieWithUrl(dataUrl);
    };
    reader.readAsDataURL(file);
}

function applyCroppedResult(blob) {
    if (!(blob instanceof Blob) || !currentForm || !currentDropzone) {
        closeModal();

        return;
    }

    const form = currentForm;
    const dropzone = currentDropzone;
    const config = getDropzoneConfig(dropzone);
    const labels = getDropzoneLabels(dropzone);

    setCropPreview(form, blob);
    hasPendingCrop = true;

    updateFileUi(dropzone, {
        buttonText: labels.crop,
        showRemove: true,
        showRecropHint: true,
    });

    const file = new File([blob], config.fileName, { type: 'image/webp' });
    const livewireWire = findLivewireUploadComponent(form);
    const livewireUpload = livewireWire?.$upload;

    if (typeof livewireUpload !== 'function') {
        closeModal();

        return;
    }

    livewireUpload(
        config.wireProperty,
        file,
        () => closeModal(),
        () => closeModal(),
        () => {},
        () => {},
    );
}

function handleDropzoneDragOver(event) {
    event.preventDefault();
    event.stopPropagation();
}

function setDropzoneActive(dropzone, active) {
    if (!dropzone) {
        return;
    }

    dropzone.classList.toggle('ui-image-crop-dropzone--active', active);
}

function findDropzoneFromEvent(event, selector) {
    return event.target?.closest(selector) ?? null;
}

export function bootImageCropper() {
    imageCropperAbort?.abort();
    imageCropperAbort = new AbortController();
    const { signal } = imageCropperAbort;

    document.addEventListener(
        'change',
        (event) => {
            const fileInput = event.target?.closest('[data-image-crop-file]');
            if (!fileInput) {
                return;
            }

            const dropzone = fileInput.closest('[data-image-crop-dropzone]');
            const file = fileInput.files?.[0];
            if (!dropzone || !file) {
                return;
            }

            openCropperForFile(file, dropzone, fileInput);
        },
        { signal },
    );

    document.addEventListener(
        'click',
        (event) => {
            const trigger = event.target.closest('[data-image-crop-file-trigger]');
            if (trigger) {
                const dropzone = trigger.closest('[data-image-crop-dropzone]');
                const form = dropzone ? resolveForm(dropzone) : null;
                if (dropzone && form && hasPendingCrop && originalImageDataUrl) {
                    event.preventDefault();
                    currentForm = form;
                    currentDropzone = dropzone;
                    initCroppieWithUrl(originalImageDataUrl);
                }

                return;
            }

            if (event.target.closest('[data-image-crop-cancel]')) {
                closeModal();

                return;
            }

            const removeBtn = event.target.closest('[data-image-crop-remove]');
            if (removeBtn) {
                const dropzone = removeBtn.closest('[data-image-crop-dropzone]');
                const form = dropzone ? resolveForm(dropzone) : null;
                if (!dropzone || !form) {
                    return;
                }

                const config = getDropzoneConfig(dropzone);
                clearPendingCropState(form, dropzone);
                const wire = findLivewireUploadComponent(form);
                if (wire && typeof wire.call === 'function') {
                    wire.call(config.clearMethod);
                }

                return;
            }

            if (!event.target.closest('[data-image-crop-apply]')) {
                return;
            }

            if (!cropperInstance || !currentForm || !currentDropzone) {
                return;
            }

            const { width, height } = getDropzoneConfig(currentDropzone).output;

            cropperInstance
                .result({
                    type: 'blob',
                    size: { width, height },
                    format: 'webp',
                    quality: 0.92,
                    circle: false,
                })
                .then(applyCroppedResult)
                .catch(() => closeModal());
        },
        { signal },
    );

    document.addEventListener(
        'dragenter',
        (event) => {
            const dropzone = findDropzoneFromEvent(event, '[data-image-crop-dropzone]');
            if (!dropzone) {
                return;
            }

            handleDropzoneDragOver(event);
            setDropzoneActive(dropzone, true);
        },
        { signal },
    );

    document.addEventListener(
        'dragover',
        (event) => {
            const dropzone = findDropzoneFromEvent(event, '[data-image-crop-dropzone]');
            if (!dropzone) {
                return;
            }

            handleDropzoneDragOver(event);
            setDropzoneActive(dropzone, true);
        },
        { signal },
    );

    document.addEventListener(
        'dragleave',
        (event) => {
            const dropzone = findDropzoneFromEvent(event, '[data-image-crop-dropzone]');
            if (!dropzone) {
                return;
            }

            const related = event.relatedTarget;
            if (related && dropzone.contains(related)) {
                return;
            }

            setDropzoneActive(dropzone, false);
        },
        { signal },
    );

    document.addEventListener(
        'drop',
        (event) => {
            const dropzone = findDropzoneFromEvent(event, '[data-image-crop-dropzone]');
            if (!dropzone) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            setDropzoneActive(dropzone, false);

            const fileInput = dropzone.querySelector('[data-image-crop-file]');
            const file = event.dataTransfer?.files?.[0];

            if (!fileInput || !file) {
                return;
            }

            openCropperForFile(file, dropzone, fileInput);
        },
        { signal },
    );

    document.addEventListener(
        'profile-avatar-updated',
        (event) => {
            const avatarUrl = resolveSavedEventUrl(event);
            const form = document.querySelector('#ui-profile-avatar-form');

            if (avatarUrl) {
                syncNavigationAvatars(avatarUrl);

                if (form) {
                    syncPreviewUrl(form, avatarUrl);
                }
            }

            const dropzone = form?.querySelector('[data-image-crop-dropzone]');
            if (form && dropzone) {
                resetPendingCropUi(form, dropzone, { resetPreview: !avatarUrl });
            }
        },
        { signal },
    );

    if (typeof window.Livewire?.hook === 'function') {
        window.Livewire.hook('morph.updated', ({ el }) => {
            el.querySelectorAll?.('[data-image-crop-dropzone]')?.forEach((dropzone) => {
                const form = resolveForm(dropzone);
                if (!form) {
                    return;
                }

                const config = getDropzoneConfig(dropzone);
                const wire = findLivewireUploadComponent(form);
                const hasUpload =
                    wire && typeof wire.get === 'function'
                        ? wire.get(config.wireProperty) != null
                        : false;

                if (!hasUpload) {
                    resetPendingCropUi(form, dropzone, { resetPreview: false });
                }
            });

            if (el.matches?.('[data-image-crop-dropzone]')) {
                const form = resolveForm(el);
                const config = getDropzoneConfig(el);
                const wire = form ? findLivewireUploadComponent(form) : null;
                const hasUpload =
                    wire && typeof wire.get === 'function'
                        ? wire.get(config.wireProperty) != null
                        : false;

                if (!hasUpload && form) {
                    resetPendingCropUi(form, el, { resetPreview: false });
                }
            }
        });
    }
}

document.addEventListener('livewire:navigating', () => {
    imageCropperAbort?.abort();
    destroyCroppieInstance(cropperInstance);
    cropperInstance = null;
    currentForm = null;
    currentDropzone = null;
    currentFileInput = null;
    originalImageDataUrl = null;
    hasPendingCrop = false;
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootImageCropper, { once: true });
} else {
    bootImageCropper();
}

document.addEventListener('livewire:navigated', bootImageCropper);
