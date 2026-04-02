import { initTagSelector } from './tags-selector';

window.initTagSelector = initTagSelector;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tag-selector]').forEach((el) => initTagSelector(el));
});

