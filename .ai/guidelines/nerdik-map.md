# nerdik codebase map

Open these files first. Do not grep the whole app when one of these owns the feature.

## Browse and search

- UI: `app/Livewire/Browse/BrowseEvents.php`, `app/Livewire/Browse/BrowseActivities.php`
- Query: `app/Support/Browse/BrowseListingQuery.php`, `app/Support/Browse/BrowseSearchState.php`
- Public URL: route `search.index` (`/search`). `/events`, `/activities`, and legacy `/browse/*` redirect there.

## Event page

- Shell: `app/Livewire/Events/ShowEvent.php`
- Plan tab (slots, schedule): `app/Livewire/Events/EventShowPlanTab.php`
- Proposals tab: `app/Livewire/Events/EventShowProposalsTab.php`
- Map tab: `app/Livewire/Events/EventShowMapTab.php`
- Read cache: `app/Services/EventShowReadCache.php`
- Event create/edit: `app/Livewire/Events/ManageEventForm.php`, `app/Services/SlotFormService.php`

## Activity page and roster

- Show page: `app/Livewire/Activities/ShowActivity.php`
- Join, waitlist, host roster: `app/Services/ActivityParticipationService.php`, `app/Services/ActivityParticipantRosterService.php`
- Activity create/edit: `app/Livewire/Activities/ManageActivityForm.php`, `app/Services/ActivityFormService.php`
- Hosting modes: `app/Services/ActivityHostingModeService.php`

## Proposals

- Submit: `app/Services/ActivityProposalFlowService.php`, UI `app/Livewire/ActivityProposals/CreateProposalForm.php`
- Accept/reject: `app/Services/ActivityProposalDecisionService.php`
- Slot fit checks: `app/Services/SlotParticipationConstraintService.php`

## User requests (invites, join requests)

- Registry: `app/Services/UserRequests/UserRequestHandlerRegistry.php`
- Handlers: `app/Services/UserRequests/Handlers/`
- UI: `app/Livewire/UserRequests/`

## Lottery

- `app/Services/ActivityLotteryService.php` (enum `app/Enums/LotteryDrawTrigger.php`)

## Catalog, places, organizations

- Places: `app/Livewire/Places/ManagePlaceForm.php`
- Organizations: `app/Livewire/Organizations/ManageOrganizationForm.php`, `app/Livewire/Catalog/CatalogOrganizations.php`
- Series catalog: `app/Livewire/Catalog/CatalogSeries.php`, `app/Support/Catalog/CatalogQuery.php`

## Admin and ops

- Filament CRUD: `app/Filament/Admin/`
- Admin ops nav links (Sentry, Umami, etc.): `app/Support/AdminOpsNavLinks.php`

## Views and copy

- Livewire views: `resources/views/livewire/` (match component namespace)
- UI strings: `lang/en/ui.php` and `lang/pl/ui.php` — update both locales
