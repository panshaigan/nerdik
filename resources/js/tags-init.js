import { initTagSelector } from './tags-selector';

window.initTagSelector = initTagSelector;

export function bootTagSelectors() {
    document.querySelectorAll('[data-tag-selector]').forEach((el) => initTagSelector(el));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootTagSelectors);
} else {
    bootTagSelectors();
}

document.addEventListener('livewire:navigated', bootTagSelectors);

