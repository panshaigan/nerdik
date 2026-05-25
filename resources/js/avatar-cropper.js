import Croppie from 'croppie';
import 'croppie/croppie.css';

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

let avatarCropperAbort;
let cropperInstance = null;
let currentForm = null;
let currentFileInput = null;
let originalImageDataUrl = null;
let hasPendingCrop = false;
let pendingSourceFileName = null;

function findLivewireUploadComponent(form) {
    const root = form?.closest('[wire\\:id]');
    const id = root?.getAttribute('wire:id');
    if (!id || typeof window.Livewire?.find !== 'function') {
        return null;
    }

    return window.Livewire.find(id);
}

function getCropModal() {
    return document.getElementById('ui-profile-avatar-crop-modal');
}

function getCropViewport() {
    return document.querySelector('[data-profile-avatar-croppie]');
}

function getDropzone(form) {
    return form?.querySelector('[data-profile-avatar-dropzone]');
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

    viewport.classList.remove('croppie-container', 'ui-profile-avatar-cropper');
    viewport.replaceChildren();
}

function getCropDimensions(modalBox) {
    const padding = 32;
    const rawWidth = (modalBox?.clientWidth ?? 448) - padding;
    const width = Math.max(280, Math.min(480, rawWidth));
    const viewportSize = Math.round(width * 0.65);

    return {
        boundary: { width, height: width },
        viewport: { width: viewportSize, height: viewportSize, type: 'circle' },
    };
}

function setCropPreview(form, blob) {
    const preview = form?.querySelector('[data-profile-avatar-preview]');
    if (!preview) {
        return;
    }

    const previousUrl = preview.dataset.blobUrl;
    if (previousUrl) {
        URL.revokeObjectURL(previousUrl);
        delete preview.dataset.blobUrl;
    }

    const url = URL.createObjectURL(blob);
    preview.dataset.blobUrl = url;
    preview.src = url;
}

function resetCropPreview(form) {
    const preview = form?.querySelector('[data-profile-avatar-preview]');
    if (!preview) {
        return;
    }

    const previousUrl = preview.dataset.blobUrl;
    if (previousUrl) {
        URL.revokeObjectURL(previousUrl);
        delete preview.dataset.blobUrl;
    }

    const defaultSrc = preview.dataset.defaultSrc;
    if (defaultSrc) {
        preview.src = defaultSrc;
    }
}

function clearFileInput() {
    if (currentFileInput) {
        currentFileInput.value = '';
    }
}

function updateFileUi(form, { buttonText, showRemove, showRecropHint }) {
    const dropzone = getDropzone(form);
    if (!dropzone) {
        return;
    }

    const buttonTextEl = dropzone.querySelector('[data-profile-avatar-file-button-text]');
    const removeBtn = dropzone.querySelector('[data-profile-avatar-remove]');
    const recropHint = dropzone.querySelector('[data-profile-avatar-recrop-hint]');

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

function clearPendingCropState(form, { clearInput = true } = {}) {
    originalImageDataUrl = null;
    hasPendingCrop = false;
    pendingSourceFileName = null;

    if (form) {
        const labels = getDropzoneLabels(getDropzone(form));
        updateFileUi(form, {
            buttonText: labels.choose,
            showRemove: false,
            showRecropHint: false,
        });
        resetCropPreview(form);
    }

    if (clearInput) {
        clearFileInput();
    }
}

function isAcceptedImageFile(file) {
    return file instanceof File && ACCEPTED_IMAGE_TYPES.includes(file.type);
}

function openCropModal() {
    const modal = getCropModal();
    if (!modal) {
        return;
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
        clearPendingCropState(currentForm);
        currentForm = null;
        currentFileInput = null;
    }
}

function initCroppieWithUrl(url) {
    const liveViewport = getCropViewport();
    const liveModal = getCropModal();
    if (!liveViewport || !liveModal) {
        return;
    }

    openCropModal();

    const startCroppie = () => {
        const modalBox = liveModal.querySelector('.modal-box');
        const { boundary, viewport: viewportOptions } = getCropDimensions(modalBox);

        destroyCroppieInstance(cropperInstance);
        cropperInstance = null;
        resetCropViewportElement(liveViewport);

        try {
            cropperInstance = new Croppie(liveViewport, {
                viewport: viewportOptions,
                boundary,
                enableOrientation: true,
                customClass: 'ui-profile-avatar-cropper',
            });
            cropperInstance.bind({ url });
        } catch (error) {
            console.error('Failed to initialize avatar cropper', error);
            closeModal();
        }
    };

    requestAnimationFrame(() => {
        requestAnimationFrame(startCroppie);
    });
}

function openCropperForFile(file, form, fileInput) {
    if (!file || !form || !isAcceptedImageFile(file)) {
        return;
    }

    if (!getCropModal() || !getCropViewport()) {
        return;
    }

    if (hasPendingCrop) {
        const wire = findLivewireUploadComponent(form);
        if (wire && typeof wire.call === 'function') {
            wire.call('clearCroppedAvatar');
        }
    }

    currentForm = form;
    currentFileInput = fileInput;
    pendingSourceFileName = file.name;
    hasPendingCrop = false;

    const labels = getDropzoneLabels(getDropzone(form));
    updateFileUi(form, {
        buttonText: labels.crop,
        showRemove: false,
        showRecropHint: false,
    });

    const reader = new FileReader();
    reader.onload = (e) => {
        const url = e.target?.result;
        if (typeof url !== 'string') {
            return;
        }

        originalImageDataUrl = url;
        initCroppieWithUrl(url);
    };
    reader.readAsDataURL(file);
}

function applyCroppedResult(blob) {
    if (!(blob instanceof Blob) || !currentForm) {
        closeModal();

        return;
    }

    const form = currentForm;
    const labels = getDropzoneLabels(getDropzone(form));

    setCropPreview(form, blob);
    hasPendingCrop = true;

    updateFileUi(form, {
        buttonText: labels.crop,
        showRemove: true,
        showRecropHint: true,
    });

    const file = new File([blob], 'avatar.webp', { type: 'image/webp' });
    const livewireWire = findLivewireUploadComponent(form);
    const livewireUpload = livewireWire?.$upload;

    if (typeof livewireUpload !== 'function') {
        closeModal();

        return;
    }

    livewireUpload(
        'croppedAvatar',
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

    dropzone.classList.toggle('ui-profile-avatar-dropzone--active', active);
}

export function bootProfileAvatarCropper() {
    avatarCropperAbort?.abort();
    avatarCropperAbort = new AbortController();
    const { signal } = avatarCropperAbort;

    document.addEventListener(
        'change',
        (event) => {
            const fileInput = event.target?.closest('[data-profile-avatar-file]');
            if (!fileInput) {
                return;
            }

            const form = fileInput.closest('#ui-profile-avatar-form');
            const file = fileInput.files?.[0];
            if (!file || !form) {
                return;
            }

            openCropperForFile(file, form, fileInput);
        },
        { signal },
    );

    document.addEventListener(
        'click',
        (event) => {
            const trigger = event.target.closest('[data-profile-avatar-file-trigger]');
            if (trigger) {
                const form = trigger.closest('#ui-profile-avatar-form');
                if (form && hasPendingCrop && originalImageDataUrl) {
                    event.preventDefault();
                    currentForm = form;
                    initCroppieWithUrl(originalImageDataUrl);
                }

                return;
            }

            if (event.target.closest('[data-profile-avatar-crop-cancel]')) {
                closeModal();

                return;
            }

            if (event.target.closest('[data-profile-avatar-remove]')) {
                const removeBtn = event.target.closest('[data-profile-avatar-remove]');
                const form = removeBtn?.closest('#ui-profile-avatar-form');
                if (!form) {
                    return;
                }

                clearPendingCropState(form);
                const wire = findLivewireUploadComponent(form);
                if (wire && typeof wire.call === 'function') {
                    wire.call('clearCroppedAvatar');
                }

                return;
            }

            if (!event.target.closest('[data-profile-avatar-crop-apply]')) {
                return;
            }

            if (!cropperInstance || !currentForm) {
                return;
            }

            cropperInstance
                .result({
                    type: 'blob',
                    size: { width: 512, height: 512 },
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
            const dropzone = event.target.closest('[data-profile-avatar-dropzone]');
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
            const dropzone = event.target.closest('[data-profile-avatar-dropzone]');
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
            const dropzone = event.target.closest('[data-profile-avatar-dropzone]');
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
            const dropzone = event.target.closest('[data-profile-avatar-dropzone]');
            if (!dropzone) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            setDropzoneActive(dropzone, false);

            const form = dropzone.closest('#ui-profile-avatar-form');
            const fileInput = form?.querySelector('[data-profile-avatar-file]');
            const file = event.dataTransfer?.files?.[0];

            if (!form || !fileInput || !file) {
                return;
            }

            openCropperForFile(file, form, fileInput);
        },
        { signal },
    );

    if (typeof window.Livewire?.hook === 'function') {
        window.Livewire.hook('morph.updated', ({ el }) => {
            const section = el.matches?.('#ui-profile-avatar-section')
                ? el
                : el.querySelector?.('#ui-profile-avatar-section');
            if (!section) {
                return;
            }

            const form = section.querySelector('#ui-profile-avatar-form');
            const wire = form ? findLivewireUploadComponent(form) : null;
            const hasUpload =
                wire && typeof wire.get === 'function' ? wire.get('croppedAvatar') != null : false;

            if (!hasUpload && form) {
                clearPendingCropState(form, { clearInput: false });
            }
        });
    }
}

document.addEventListener('livewire:navigating', () => {
    avatarCropperAbort?.abort();
    destroyCroppieInstance(cropperInstance);
    cropperInstance = null;
    currentForm = null;
    currentFileInput = null;
    originalImageDataUrl = null;
    hasPendingCrop = false;
    pendingSourceFileName = null;
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootProfileAvatarCropper, { once: true });
} else {
    bootProfileAvatarCropper();
}

document.addEventListener('livewire:navigated', bootProfileAvatarCropper);
