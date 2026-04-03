# View layer: Livewire + Mary + DaisyUI

## Stack

- **Livewire**: reactive islands (auth, profile, navigation pieces).
- **Tailwind**: utility foundation.
- **DaisyUI**: semantic theme tokens (`bg-base-*`, `text-base-content`, `btn-primary`, `link-primary`, `alert-*`, `table`, etc.).
- **Mary**: Blade components that wrap Daisy primitives (`<x-input>`, `<x-button>`, `<x-checkbox>`, `<x-password>`, `<x-modal>`, …).

Mary is aligned with Daisy; prefer Mary for **forms** so labels, errors, and inputs stay consistent.

## Rules

1. **Forms (non-Livewire Blade)**: use Mary `<x-input>`, `<x-textarea>`, `<x-checkbox>`, `<x-button>` where possible.
2. **Selects with `old()` / `@selected`**: use `<x-form-select>` (see `components/form-select.blade.php`). upstream `<x-select>` does not set selected options for classic form posts.
3. **Fallback**: raw Daisy classes on the real control (`select`, `input`, `checkbox`) when a third-party root or JS requires it; still use semantic tokens (`border-base-300`, `bg-base-100`).
4. **Surfaces**: `border-base-300`, `bg-base-100`, `text-base-content`; avoid fixed palettes (`gray-*`, `indigo-*`, `bg-white`) in app UI.
5. **Actions**: `btn-primary`, `btn-outline`, `btn-ghost`, `link link-primary`; destructive: `text-error` or `btn-error`.
6. **Flash / status**: prefer `alert alert-success` or `text-success`, not `text-green-600`.
7. **Modals**: Livewire flows → `<x-modal>`; static Blade + JS → Daisy `<dialog class="modal">`. Do not mix without reason.
8. **JS selectors**: add semantic hooks to important interactive elements using:
   - `id="ui-<domain>-<element>"`
   - `class="ui-<domain>-<role>"` (can be combined with utility classes)
   - `data-ui="<domain>-<element>"`
   Keep `data-*` hooks stable; avoid binding JS to visual utility classes.
   For each major form, always mark:
   - root section (`...-section`)
   - form tag (`...-form`)
   - primary submit (`...-submit`)
   - key interactive fields (`...-search`, `...-email`, `...-password`, etc.)

## Documented non-semantic tweaks

Some integrations need one-off utilities; keep them local and comment if non-obvious:

- Quill / rich text: `min-h-[11rem]` on the editor mount div.
- Leaflet: `z-[1000]` / `min-h-[280px]` on map containers.
- Mass slot modal: `z-[9999]` so it stacks above Leaflet.
- **ui-avatars.com** query params use fixed hex colors for generated avatars (third-party API, not theme tokens).
- Event map hero overlay uses `bg-black/55` + `backdrop-blur-[1px]` for image/map readability.
