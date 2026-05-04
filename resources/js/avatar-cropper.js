import Croppie from 'croppie';
import 'croppie/croppie.css';

let avatarCropperAbort;

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

    const form = document.getElementById('ui-profile-avatar-form');
    const fileInput = document.querySelector('[data-profile-avatar-file]');
    const modal = document.getElementById('ui-profile-avatar-crop-modal');
    const viewport = document.querySelector('[data-profile-avatar-croppie]');
    const applyBtn = document.querySelector('[data-profile-avatar-crop-apply]');
    const cancelBtn = document.querySelector('[data-profile-avatar-crop-cancel]');

    if (!form || !fileInput || !modal || !viewport || !applyBtn || !cancelBtn) {
        return;
    }

    let croppie = null;

    function closeModal() {
        if (typeof modal.close === 'function') {
            modal.close();
        }
        destroyCroppieInstance(croppie);
        croppie = null;
        fileInput.value = '';
    }

    fileInput.addEventListener(
        'change',
        () => {
            const file = fileInput.files?.[0];
            if (!file) {
                return;
            }

            destroyCroppieInstance(croppie);
            croppie = null;

            const reader = new FileReader();
            reader.onload = (e) => {
                const url = e.target?.result;
                if (typeof url !== 'string') {
                    return;
                }

                croppie = new Croppie(viewport, {
                    viewport: { width: 220, height: 220, type: 'circle' },
                    boundary: { width: 320, height: 320 },
                    enableOrientation: true,
                });
                croppie.bind({ url });

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
            if (!croppie) {
                return;
            }

            const component = findLivewireUploadComponent(form);
            if (!component || typeof component.upload !== 'function') {
                closeModal();

                return;
            }

            croppie
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
                    component.upload(
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
