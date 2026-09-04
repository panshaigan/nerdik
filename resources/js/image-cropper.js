import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const ZOOM_RANGE_MULTIPLIER = 3;

let imageCropperAbort;
let cropperInstance = null;
let currentForm = null;
let currentDropzone = null;
let currentFileInput = null;
let originalImageDataUrl = null;
let originalSourceFile = null;
let hasPendingCrop = false;
let zoomMin = 0;
let zoomMax = 1;
let syncingZoomSlider = false;

const MODAL_LAYOUT_MAX_FRAMES = 30;

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
    return document.querySelector('.ui-image-crop-stage');
}

function getCropViewport() {
    return document.querySelector('[data-image-crop-stage]');
}

function getCropImage() {
    return document.querySelector('[data-image-crop-image]');
}

function getZoomInput() {
    return document.querySelector('[data-image-crop-zoom]');
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
        sourceWireProperty: dropzone.dataset.imageCropSourceWireProperty ?? 'sourceImage',
        sourceClearMethod: dropzone.dataset.imageCropSourceClearMethod ?? 'clearSourceImage',
        sourceFileName: dropzone.dataset.imageCropSourceFileName ?? 'source.webp',
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

function destroyCropperInstance() {
    if (cropperInstance && typeof cropperInstance.destroy === 'function') {
        try {
            cropperInstance.destroy();
        } catch {
            // Element may already be detached after a Livewire morph.
        }
    }

    cropperInstance = null;
    zoomMin = 0;
    zoomMax = 1;
    syncingZoomSlider = false;

    const img = getCropImage();
    if (img) {
        img.removeAttribute('src');
        img.src = '';
    }

    const zoomInput = getZoomInput();
    if (zoomInput) {
        zoomInput.value = '0';
    }
}

function ensureCropImage(viewport) {
    let img = viewport.querySelector('[data-image-crop-image]');
    if (img) {
        return img;
    }

    img = document.createElement('img');
    img.setAttribute('data-image-crop-image', '');
    img.alt = '';
    img.className = 'block max-w-full';
    viewport.appendChild(img);

    return img;
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

function getCurrentZoomRatio() {
    if (!cropperInstance) {
        return zoomMin;
    }

    const imageData = cropperInstance.getImageData();
    if (!imageData?.naturalWidth) {
        return zoomMin;
    }

    return imageData.width / imageData.naturalWidth;
}

function updateZoomRangeFromCropper() {
    if (!cropperInstance) {
        return;
    }

    const imageData = cropperInstance.getImageData();
    const containerData = cropperInstance.getContainerData();
    if (!imageData?.naturalWidth || !imageData?.naturalHeight || !containerData) {
        return;
    }

    const fitRatio = Math.min(
        containerData.width / imageData.naturalWidth,
        containerData.height / imageData.naturalHeight,
    );

    zoomMin = Number.isFinite(fitRatio) && fitRatio > 0 ? fitRatio : getCurrentZoomRatio();
    zoomMax = Math.max(zoomMin * ZOOM_RANGE_MULTIPLIER, zoomMin + 0.01);
}

function syncZoomSliderFromCropper() {
    const zoomInput = getZoomInput();
    if (!zoomInput || !cropperInstance) {
        return;
    }

    updateZoomRangeFromCropper();

    const current = getCurrentZoomRatio();
    const span = zoomMax - zoomMin;
    const normalized = span > 0 ? (current - zoomMin) / span : 0;
    const clamped = Math.min(1, Math.max(0, normalized));

    syncingZoomSlider = true;
    zoomInput.value = String(clamped);
    syncingZoomSlider = false;
}

function applyZoomFromSlider(value) {
    if (!cropperInstance || syncingZoomSlider) {
        return;
    }

    updateZoomRangeFromCropper();

    const t = Number(value);
    if (!Number.isFinite(t)) {
        return;
    }

    const ratio = zoomMin + Math.min(1, Math.max(0, t)) * (zoomMax - zoomMin);
    cropperInstance.zoomTo(ratio);
}

function fileToWebpBlob(file, quality = 0.92) {
    return new Promise((resolve, reject) => {
        if (!(file instanceof Blob)) {
            reject(new Error('Invalid source file'));

            return;
        }

        if (file.type === 'image/webp') {
            resolve(file);

            return;
        }

        const objectUrl = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(objectUrl);

            const canvas = document.createElement('canvas');
            canvas.width = image.naturalWidth || image.width;
            canvas.height = image.naturalHeight || image.height;

            const context = canvas.getContext('2d');
            if (!context) {
                reject(new Error('Canvas unavailable'));

                return;
            }

            context.drawImage(image, 0, 0);

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
        };

        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Failed to load source image'));
        };

        image.src = objectUrl;
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
    originalSourceFile = null;
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

    destroyCropperInstance();

    if (resetSession) {
        clearPendingCropState(currentForm, currentDropzone);
        currentForm = null;
        currentDropzone = null;
        currentFileInput = null;
    }
}

function canvasToWebpBlob(canvas, quality = 0.92) {
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

async function initCropperWithUrl(url) {
    const stage = getCropStage();
    const viewport = getCropViewport();
    const liveModal = getCropModal();
    if (!stage || !viewport || !liveModal || !currentDropzone) {
        return;
    }

    const config = getDropzoneConfig(currentDropzone);
    openCropModal(currentDropzone);

    await waitForModalLayout(liveModal);
    if (!currentDropzone || getCropModal() !== liveModal) {
        return;
    }

    stage.dataset.aspect = config.aspect;

    destroyCropperInstance();

    const img = ensureCropImage(viewport);
    img.src = url;

    try {
        cropperInstance = new Cropper(img, {
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
    if (!form || !getCropModal() || !getCropViewport()) {
        return;
    }

    const config = getDropzoneConfig(dropzone);

    if (hasPendingCrop) {
        const wire = findLivewireUploadComponent(form);
        if (wire && typeof wire.call === 'function') {
            wire.call(config.clearMethod);
            if (config.sourceClearMethod) {
                wire.call(config.sourceClearMethod);
            }
        }
    }

    currentForm = form;
    currentDropzone = dropzone;
    currentFileInput = fileInput;
    originalSourceFile = file;
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

async function applyCroppedResult(blob) {
    if (!(blob instanceof Blob) || !currentForm || !currentDropzone) {
        closeModal();

        return;
    }

    const form = currentForm;
    const dropzone = currentDropzone;
    const config = getDropzoneConfig(dropzone);
    const labels = getDropzoneLabels(dropzone);
    const sourceFile = originalSourceFile;

    setCropPreview(form, blob);
    hasPendingCrop = true;

    updateFileUi(dropzone, {
        buttonText: labels.crop,
        showRemove: true,
        showRecropHint: true,
    });

    const croppedFile = new File([blob], config.fileName, { type: 'image/webp' });
    const livewireWire = findLivewireUploadComponent(form);

    if (!livewireWire || typeof livewireWire.$upload !== 'function') {
        closeModal();

        return;
    }

    try {
        await uploadLivewireFile(livewireWire, config.wireProperty, croppedFile);

        if (sourceFile && config.sourceWireProperty) {
            const sourceBlob = await fileToWebpBlob(sourceFile);
            const sourceUpload = new File([sourceBlob], config.sourceFileName, {
                type: 'image/webp',
            });
            await uploadLivewireFile(livewireWire, config.sourceWireProperty, sourceUpload);
        }

        closeModal();
    } catch (error) {
        console.error('Failed to upload cropped image', error);
        closeModal();
    }
}

function exportCroppedBlob() {
    if (!cropperInstance || !currentDropzone) {
        return Promise.reject(new Error('Cropper unavailable'));
    }

    const { width, height } = getDropzoneConfig(currentDropzone).output;
    const canvas = cropperInstance.getCroppedCanvas({
        width,
        height,
        imageSmoothingQuality: 'high',
        fillColor: '#fff',
    });

    if (!canvas) {
        return Promise.reject(new Error('Cropped canvas unavailable'));
    }

    return canvasToWebpBlob(canvas, 0.92);
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
            const zoomInput = event.target?.closest('[data-image-crop-zoom]');
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
                    if (config.sourceClearMethod) {
                        wire.call(config.sourceClearMethod);
                    }
                }

                return;
            }

            if (!event.target.closest('[data-image-crop-apply]')) {
                return;
            }

            if (!cropperInstance || !currentForm || !currentDropzone) {
                return;
            }

            exportCroppedBlob()
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
    destroyCropperInstance();
    currentForm = null;
    currentDropzone = null;
    currentFileInput = null;
    originalImageDataUrl = null;
    originalSourceFile = null;
    hasPendingCrop = false;
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootImageCropper, { once: true });
} else {
    bootImageCropper();
}

document.addEventListener('livewire:navigated', bootImageCropper);
