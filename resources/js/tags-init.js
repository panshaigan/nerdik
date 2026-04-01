import { initTagSelector } from './tags-selector';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tag-selector]').forEach((el) => initTagSelector(el));
});

