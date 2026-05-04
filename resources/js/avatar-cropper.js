import Croppie from 'croppie';
import 'croppie/croppie.css';

let avatarCropperAbort;
let cropperInstance = null;

function findLivewireUploadComponent(form) {
    const root = form?.closest('[wire\\:id]');
    const id = root?.getAttribute('wire:id');
    if (!id || typeof window.Livewire?.find !== 'function') {
        return null;
    }

    return window.Livewire.find(id);
}

function destroyCroppieInstance(croppie) {
    if (croppie && typeof croppie.destroy === 'function') {
        croppie.destroy();
    }
}

export function bootProfileAvatarCropper() {
    avatarCropperAbort?.abort();
    avatarCropperAbort = new AbortController();
    const { signal } = avatarCropperAbort;

    const modal = document.getElementById('ui-profile-avatar-crop-modal');
    const viewport = document.querySelector('[data-profile-avatar-croppie]');
    const applyBtn = document.querySelector('[data-profile-avatar-crop-apply]');
    const cancelBtn = document.querySelector('[data-profile-avatar-crop-cancel]');

    if (!modal || !viewport || !applyBtn || !cancelBtn) {
        return;
    }

    let currentForm = null;
    let currentFileInput = null;

    function closeModal() {
        if (typeof modal.close === 'function') {
            modal.close();
        }
        destroyCroppieInstance(cropperInstance);
        cropperInstance = null;
        currentForm = null;
        currentFileInput = null;
    }

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

            currentForm = form;
            currentFileInput = fileInput;
            destroyCroppieInstance(cropperInstance);
            cropperInstance = null;

            const reader = new FileReader();
            reader.onload = (e) => {
                const url = e.target?.result;
                if (typeof url !== 'string') {
                    return;
                }

                cropperInstance = new Croppie(viewport, {
                    viewport: { width: 220, height: 220, type: 'circle' },
                    boundary: { width: 320, height: 320 },
                    enableOrientation: true,
                });
                cropperInstance.bind({ url });

                if (typeof modal.showModal === 'function') {
                    modal.showModal();
                }
            };
            reader.readAsDataURL(file);
        },
        { signal },
    );

    cancelBtn.addEventListener('click', () => closeModal(), { signal });

    applyBtn.addEventListener(
        'click',
        () => {
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
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootProfileAvatarCropper, { once: true });
} else {
    bootProfileAvatarCropper();
}

document.addEventListener('livewire:navigated', bootProfileAvatarCropper);
