import Croppie from 'croppie';
import 'croppie/croppie.css';

let avatarCropperAbort;
let cropperInstance = null;
let currentForm = null;
let currentFileInput = null;

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

function clearFileInput() {
    if (currentFileInput) {
        currentFileInput.value = '';
    }
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

function closeModal() {
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
    clearFileInput();
    currentForm = null;
    currentFileInput = null;
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

            if (!getCropModal() || !getCropViewport()) {
                return;
            }

            currentForm = form;
            currentFileInput = fileInput;

            const reader = new FileReader();
            reader.onload = (e) => {
                const url = e.target?.result;
                if (typeof url !== 'string') {
                    return;
                }

                initCroppieWithUrl(url);
            };
            reader.readAsDataURL(file);
        },
        { signal },
    );

    document.addEventListener(
        'click',
        (event) => {
            if (event.target.closest('[data-profile-avatar-crop-cancel]')) {
                closeModal();

                return;
            }

            if (!event.target.closest('[data-profile-avatar-crop-apply]')) {
                return;
            }

            if (!cropperInstance || !currentForm) {
                return;
            }

            const livewireWire = findLivewireUploadComponent(currentForm);
            const livewireUpload = livewireWire?.$upload;

            if (typeof livewireUpload !== 'function') {
                closeModal();

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
                .then((blob) => {
                    if (!(blob instanceof Blob)) {
                        closeModal();

                        return;
                    }

                    setCropPreview(currentForm, blob);

                    const file = new File([blob], 'avatar.webp', { type: 'image/webp' });
                    livewireUpload(
                        'croppedAvatar',
                        file,
                        () => closeModal(),
                        () => closeModal(),
                        () => {},
                        () => {},
                    );
                })
                .catch(() => closeModal());
        },
        { signal },
    );
}

document.addEventListener('livewire:navigating', () => {
    avatarCropperAbort?.abort();
    destroyCroppieInstance(cropperInstance);
    cropperInstance = null;
    currentForm = null;
    currentFileInput = null;
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootProfileAvatarCropper, { once: true });
} else {
    bootProfileAvatarCropper();
}

document.addEventListener('livewire:navigated', bootProfileAvatarCropper);
