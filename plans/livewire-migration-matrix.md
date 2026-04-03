# Livewire migration matrix (app UI vs Filament admin)

This document maps **user-facing** screens to a recommended stack: **keep Blade**, **migrate to Livewire**, or **already covered elsewhere** (Filament admin, existing Livewire).

**Legend**

| Decision | Meaning |
|----------|---------|
| **Filament** | Admin CRUD in `/admin` — keep; do not duplicate in Livewire. |
| **Livewire (done)** | Already implemented with Livewire / Volt. |
| **→ Livewire** | Good candidate to migrate: stateful UI, forms, or lots of glue JS. |
| **Blade (ok)** | Fine to leave as Blade: mostly static or trivial interaction. |
| **Blade (later)** | Could migrate eventually; low ROI unless the screen grows. |

---

## Summary table

| Area | Route / screen | Current stack | Recommendation | Priority |
|------|----------------|---------------|------------------|----------|
| Welcome | `/` | Blade | Blade (ok) | — |
| Locale | `locale/{locale}` | Closure | Blade (ok) | — |
| Browse | `browse/events`, `browse/activities`, `browse/organizations` | Controller + Blade | → Livewire | **P1** |
| Dashboard | `dashboard` | Controller + Blade | → Livewire | **P1** |
| Profile | `profile` | Blade shell + Livewire forms | Livewire (done) | — |
| Auth | login, register, forgot/reset, verify, confirm | Volt (Livewire) | Livewire (done) | — |
| Nav | layout | `livewire/layout/navigation` | Livewire (done) | — |
| **Activities** | index, create, edit, show | Controller + Blade (+ Quill JS) | → Livewire | **P0** |
| **Events** | index, create, edit, show | Controller + Blade (+ Quill/Leaflet JS) | → Livewire | **P0** |
| Slots | index, create, edit, mass-create, edit modal | Controller + Blade (+ modal JS) | → Livewire | **P2** |
| Places | index, create, edit | Controller + Blade | Blade (later) / → Livewire if you add maps/validation UX | P3 |
| Organizations | index, create, edit | Controller + Blade | Blade (later) | P3 |
| Tags | index, create, edit | Controller + Blade | Blade (later) | P3 |
| Activity proposals | index, create | Controller + Blade | → Livewire | **P2** |
| Notifications | `notifications` | Controller + Blade | → Livewire | **P2** |
| Wishlist / participation | POST routes from show pages | Controller | Absorbed when parent pages → Livewire | — |
| Geocode API | `geocode/*` | Controller JSON | N/A (API) | — |
| **Filament admin** | Users, Activities, Events, Places, Tags, Organizations | Filament | **Filament** — source of truth for admin CRUD | — |

---

## Filament admin (do not “migrate” to app Livewire)

These resources live under `app/Filament/Admin/Resources/` and should stay Filament:

- Users  
- Activities  
- Events  
- Places  
- Tags  
- Organizations  

**Note:** The **public app** still has its own CRUD routes for logged-in users (`activities`, `events`, etc.). Those are *not* Filament; the matrix above is about modernizing **that** layer with Livewire, not replacing Filament.

---

## Already Livewire (no action)

- **Auth:** Volt routes in `routes/auth.php` (`pages.auth.*`).  
- **Profile:** `livewire/profile/*` embedded in `resources/views/profile.blade.php`.  
- **Welcome nav:** `livewire/welcome/navigation.blade.php`.  
- **App nav:** `livewire/layout/navigation.blade.php`.

---

## Recommended migration order (by impact)

1. **P0 — Activities & Events forms**  
   Heavy forms (rich text, maps, validation, proposal section). Maximum payoff for Mary `<x-editor>`, less custom JS, Livewire-native validation.

2. **P1 — Browse + Dashboard**  
   Filter state, buttons, and dashboard widgets fit Livewire well; reduces ad-hoc request/query juggling in Blade.

3. **P2 — Event/activity “hub” pages + proposals + notifications**  
   `events/show`, `activities/show`, `activity-proposals/*`, `notifications/index`: interactive actions, wishlist, participation — consolidate into Livewire actions and fewer full page POST round-trips where it helps.

4. **P3 — Smaller CRUD (places, organizations, tags)**  
   Migrate when you touch them for features, or if you want one consistent pattern app-wide.

5. **Leave as Blade**  
   `welcome`, simple redirects, email templates, and purely static content.

---

## What “full Livewire” does *not* mean here

- **Replacing Filament** for admin — not recommended; Filament is the right tool for that boundary.  
- **Rewriting every Blade file** — static and rare pages can stay Blade.  
- **Big-bang** — use the priority order above and migrate incrementally.

---

*Generated for the Nerdik codebase (routes in `routes/web.php`, Filament under `app/Filament/Admin`). Update this file when routes or scope change.*
