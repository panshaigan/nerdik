# nerdik conventions

## Layering

- Livewire components and thin controllers orchestrate; domain logic lives in `app/Services/`.
- `app/Actions/` — single-purpose side effects (media upload, locale switch, GDPR anonymize).
- `app/Support/` — queries, presenters, URL builders, pure helpers. No HTTP redirects here.
- Do not reimplement join/waitlist/proposal/slot-fit rules in Blade or Livewire when a service already owns them.

## Authorization

- No Policy classes. Use `User::canModifyEntity($model)` and Eloquent `visibleTo($user)` scopes.
- Admin users bypass ownership via `is_admin`.

## Domain state

- Backed enums in `app/Enums/` (`ParticipationMode`, `ActivityProposalStatus`, `LotteryDrawTrigger`, etc.).
- Activity hosting-mode transitions: `app/Services/ActivityHostingModeService.php` only — not in UI layers.

## Participation and proposals

- Extend methods on `ActivityParticipationService`, `ActivityParticipantRosterService`, `ActivityProposalFlowService`, or `ActivityProposalDecisionService`.
- Product rules (when join is blocked, waitlist promotion, auto-accept): see `docs/domain-mechanics.md`.

## Commands and tooling

- Sail via Makefile: `make artisan …`, `make test --filter=…`, `make pint`.
- Cursor rules in `.cursor/rules/` cover tooltips, env files, and test assertion style — follow those; do not restate them here.
- Boost agents (Cursor + Codex only): edit `boost.json` / `config/boost.php`. Skills live in `.agents/skills`; `.cursor/skills` is a symlink. Guidelines refresh into `AGENTS.md` via `make artisan boost:update --no-interaction`.

## When to read code vs docs

- **Which file to edit:** this map (`nerdik-map.md`).
- **What the business rule means:** `docs/domain-mechanics.md`.
- **How to run the app:** `docs/development-workflow.md`.
