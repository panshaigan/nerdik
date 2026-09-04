import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MODAL_LAYOUT_MAX_FRAMES = 30;
const WEBP_QUALITY = 0.92;

let imageCropperAbort;
let cropperInstance = null;
let currentForm = null;
let currentDropzone = null;
let currentFileInput = null;
let originalImageDataUrl = null;
let hasPendingCrop = false;
let zoomMin = 0;
let zoomMax = 1;
let syncingZoomSlider = false;

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

function getCropStage() {
    return document.querySelector('[data-image-crop-stage]');
}

function getCropImage() {
    return document.querySelector('[data-image-crop-image]');
}

function getCropZoomInput() {
    return document.querySelector('[data-image-crop-zoom]');
}

function getCropStageRoot() {
    return document.querySelector('.ui-image-crop-stage');
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
    const parts = String(raw)
        .split(',')
        .map((n) => parseInt(n.trim(), 10));

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

function destroyCropperInstance() {
    if (cropperInstance && typeof cropperInstance.destroy === 'function') {
        try {
            cropperInstance.destroy();
        } catch {
            // Element may already be detached after a Livewire morph.
        }
    }

    cropperInstance = null;
}

function resetCropStage() {
    const image = getCropImage();
    if (image) {
        image.removeAttribute('src');
        image.alt = '';
    }

    const stageRoot = getCropStageRoot();
    if (stageRoot) {
        stageRoot.dataset.aspect = 'square';
    }

    const zoom = getCropZoomInput();
    if (zoom) {
        zoom.value = '0';
        zoom.disabled = true;
    }

    zoomMin = 0;
    zoomMax = 1;
}

function waitForModalLayout(modal, attempt = 0) {
    return new Promise((resolve) => {
        const modalBox = modal.querySelector('.modal-box');
        const width = modalBox?.clientWidth ?? 0;

        if (width > 0 || attempt >= MODAL_LAYOUT_MAX_FRAMES) {
            resolve(modalBox);

            return;
        }

        requestAnimationFrame(() => {
            waitForModalLayout(modal, attempt + 1).then(resolve);
        });
    });
}

function canvasToWebpBlob(canvas, quality = WEBP_QUALITY) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('WebP conversion failed'));
                }
            },
            'image/webp',
            quality,
        );
    });
}

function uploadLivewireFile(wire, property, file) {
    return new Promise((resolve, reject) => {
        const upload = wire?.$upload;
        if (typeof upload !== 'function') {
            reject(new Error('Livewire upload unavailable'));

            return;
        }

        upload(
            property,
            file,
            () => resolve(),
            () => reject(new Error(`Failed to upload ${property}`)),
            () => {},
            () => {},
        );
    });
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

    const aspect = dropzone?.dataset?.imageCropAspect ?? 'square';
    const stageRoot = getCropStageRoot();
    if (stageRoot) {
        stageRoot.dataset.aspect = aspect === 'video' ? 'video' : 'square';
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

    destroyCropperInstance();
    resetCropStage();

    if (resetSession) {
        clearPendingCropState(currentForm, currentDropzone);
        currentForm = null;
        currentDropzone = null;
        currentFileInput = null;
    }
}

function syncZoomSliderFromCropper() {
    if (!cropperInstance) {
        return;
    }

    const zoomInput = getCropZoomInput();
    if (!zoomInput) {
        return;
    }

    const imageData = cropperInstance.getImageData();
    if (!imageData?.naturalWidth) {
        return;
    }

    const currentRatio = imageData.width / imageData.naturalWidth;

    if (!(zoomMin > 0)) {
        zoomMin = currentRatio;
    }

    if (!(zoomMax > zoomMin)) {
        zoomMax = Math.max(zoomMin * 3, zoomMin + 0.5);
    }

    if (currentRatio > zoomMax) {
        zoomMax = currentRatio;
    }

    const span = zoomMax - zoomMin || 1;
    const normalized = Math.min(1, Math.max(0, (currentRatio - zoomMin) / span));

    syncingZoomSlider = true;
    zoomInput.min = '0';
    zoomInput.max = '1';
    zoomInput.step = '0.01';
    zoomInput.value = String(normalized);
    zoomInput.disabled = false;
    syncingZoomSlider = false;
}

function applyZoomFromSlider(value) {
    if (!cropperInstance || syncingZoomSlider) {
        return;
    }

    const ratio = zoomMin + Number(value) * (zoomMax - zoomMin || 1);
    cropperInstance.zoomTo(ratio);
}

async function initCropperWithUrl(url) {
    const stage = getCropStage();
    const image = getCropImage();
    const liveModal = getCropModal();
    if (!stage || !image || !liveModal || !currentDropzone) {
        return;
    }

    const config = getDropzoneConfig(currentDropzone);
    openCropModal(currentDropzone);

    await waitForModalLayout(liveModal);
    if (!currentDropzone || getCropModal() !== liveModal) {
        return;
    }

    destroyCropperInstance();

    image.alt = '';
    image.src = url;

    try {
        cropperInstance = new Cropper(image, {
            viewMode: 1,
            dragMode: 'move',
            aspectRatio: config.aspect === 'video' ? 16 / 9 : 1,
            autoCropArea: 1,
            responsive: true,
            background: false,
            guides: false,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            ready() {
                const imageData = this.getImageData();
                const currentRatio = imageData.width / imageData.naturalWidth;
                zoomMin = currentRatio;
                zoomMax = Math.max(currentRatio * 3, currentRatio + 0.5);
                syncZoomSliderFromCropper();
            },
            zoom() {
                syncZoomSliderFromCropper();
            },
        });
    } catch (error) {
        console.error('Failed to initialize image cropper', error);
        closeModal();
    }
}

function openCropperForFile(file, dropzone, fileInput) {
    if (!file || !dropzone || !isAcceptedImageFile(file)) {
        return;
    }

    const form = resolveForm(dropzone);
    if (!form || !getCropModal() || !getCropStage()) {
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
        initCropperWithUrl(dataUrl);
    };
    reader.readAsDataURL(file);
}

async function applyCroppedResult() {
    if (!cropperInstance || !currentForm || !currentDropzone) {
        closeModal();

        return;
    }

    const form = currentForm;
    const dropzone = currentDropzone;
    const config = getDropzoneConfig(dropzone);
    const labels = getDropzoneLabels(dropzone);
    const { width, height } = config.output;

    let canvas;
    try {
        canvas = cropperInstance.getCroppedCanvas({
            width,
            height,
            imageSmoothingQuality: 'high',
            fillColor: '#fff',
        });
    } catch (error) {
        console.error('Failed to export cropped image', error);
        closeModal();

        return;
    }

    if (!canvas) {
        closeModal();

        return;
    }

    try {
        const blob = await canvasToWebpBlob(canvas);
        setCropPreview(form, blob);
        hasPendingCrop = true;

        updateFileUi(dropzone, {
            buttonText: labels.crop,
            showRemove: true,
            showRecropHint: true,
        });

        const file = new File([blob], config.fileName, { type: 'image/webp' });
        const livewireWire = findLivewireUploadComponent(form);

        if (!livewireWire || typeof livewireWire.$upload !== 'function') {
            closeModal();

            return;
        }

        await uploadLivewireFile(livewireWire, config.wireProperty, file);
        closeModal();
    } catch (error) {
        console.error('Failed to upload cropped image', error);
        closeModal();
    }
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
        'input',
        (event) => {
            const zoomInput = event.target?.closest?.('[data-image-crop-zoom]');
            if (!zoomInput) {
                return;
            }

            applyZoomFromSlider(zoomInput.value);
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
                    initCropperWithUrl(originalImageDataUrl);
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

            applyCroppedResult();
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
    destroyCropperInstance();
    resetCropStage();
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
