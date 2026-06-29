@props(['enabled' => true])

@php
    $darkSources = \App\Support\Media\StaticPictureSources::fromAppShellBackground('dark');
@endphp

@if ($enabled)
    <div
        class="pointer-events-none fixed inset-0 z-0 overflow-hidden bg-[#00050a] light:bg-[#f5f0e8]"
        data-ui="app-shell-background"
        aria-hidden="true"
        x-data="{
            theme: document.documentElement.getAttribute('data-theme') || 'dark',
            lightMounted: false,
            syncTheme() {
                this.theme = document.documentElement.getAttribute('data-theme') || 'dark';

                if (this.theme === 'light') {
                    this.mountLightBackground();
                }
            },
            async mountLightBackground() {
                if (this.lightMounted) {
                    return;
                }

                const host = this.$refs.lightBackgroundHost;

                if (! host) {
                    return;
                }

                const response = await fetch(@js(asset('images/app/backgrounds/manifest.json')), {
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    return;
                }

                const manifest = await response.json();
                const themeManifest = manifest.light;

                if (! themeManifest?.variants) {
                    return;
                }

                host.replaceChildren(this.buildPicture(themeManifest));
                this.lightMounted = true;
            },
            buildPicture(themeManifest) {
                const picture = document.createElement('picture');
                picture.className = 'block h-full w-full overflow-hidden';

                const sizes = @js(config('media.app_shell_background.sizes', '100vw'));
                const mobileQuery = @js('(max-width: '.(config('media.app_shell_background.desktop_min_width', 1025) - 1).'px)');
                const desktopQuery = @js('(min-width: '.config('media.app_shell_background.desktop_min_width', 1025).'px)');
                const mobileWidths = @js(config('media.app_shell_background.mobile_widths', [384, 512, 640, 768, 1024]));
                const desktopWidths = @js(config('media.app_shell_background.desktop_widths', [1536, 1716]));
                const assetRoot = @js(rtrim(asset(''), '/'));

                const srcsetFor = (format, widths) => {
                    const variants = (themeManifest.variants[format] ?? [])
                        .filter((entry) => widths.includes(entry.width))
                        .sort((a, b) => a.width - b.width)
                        .map((entry) => `${assetRoot}/${entry.path} ${entry.width}w`);

                    return variants.join(', ');
                };

                const appendSource = (type, media, srcset) => {
                    if (srcset === '') {
                        return;
                    }

                    const source = document.createElement('source');
                    source.type = type;
                    source.media = media;
                    source.srcset = srcset;
                    source.sizes = sizes;
                    picture.appendChild(source);
                };

                appendSource('image/webp', mobileQuery, srcsetFor('webp', mobileWidths));
                appendSource('image/webp', desktopQuery, srcsetFor('webp', desktopWidths));

                const webpVariants = (themeManifest.variants.webp ?? []).sort((a, b) => a.width - b.width);
                const img = document.createElement('img');
                img.className = 'h-full w-full object-cover';
                img.sizes = sizes;
                img.alt = '';
                img.loading = 'eager';
                img.fetchPriority = 'high';
                img.decoding = 'async';

                if (themeManifest.width) {
                    img.width = themeManifest.width;
                }

                if (themeManifest.height) {
                    img.height = themeManifest.height;
                }

                const largestWebp = webpVariants[webpVariants.length - 1];

                if (largestWebp) {
                    img.src = `${assetRoot}/${largestWebp.path}`;
                }

                picture.appendChild(img);

                return picture;
            },
        }"
        x-init="syncTheme()"
        @nerdik:theme-applied.window="syncTheme()"
    >
        <template x-if="theme === 'dark'">
            <x-app-shell-background-picture
                :sources="$darkSources"
                class="h-full w-full object-cover"
            />
        </template>
        <div
            x-show="theme === 'light'"
            x-ref="lightBackgroundHost"
            class="h-full w-full"
        ></div>
    </div>
@endif
