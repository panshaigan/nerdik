export function captureBrowserTimezone() {
    const timezone = Intl.DateTimeFormat?.().resolvedOptions?.().timeZone ?? '';

    if (timezone === '') {
        return '';
    }

    document.cookie = `browser_timezone=${encodeURIComponent(timezone)}; path=/; max-age=31536000; samesite=lax`;

    return timezone;
}
