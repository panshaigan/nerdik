# Test suite speed baseline

Captured with:

```bash
./vendor/bin/sail artisan test --compact --parallel --log-junit=storage/logs/junit-baseline.xml
```

- **Wall clock:** 6:24 (12 processes)
- **Tests:** 1805
- **Assertions:** 6485
- **Note:** Per-file times below are summed across workers (can exceed wall clock). Use relative order for the speed backlog.

## Top 30 slowest files (total time)

| Rank | Seconds | File |
| ---: | ---: | --- |
| 1 | 132.91 | `tests/Unit/Support/Media/MediaPictureSourcesTest.php` |
| 2 | 108.02 | `tests/Feature/Livewire/ManageActivityFormImageTest.php` |
| 3 | 90.01 | `tests/Feature/EventSeriesTest.php` |
| 4 | 82.19 | `tests/Feature/ActivitySeriesTest.php` |
| 5 | 78.22 | `tests/Feature/Livewire/ManageActivityFormUiTest.php` |
| 6 | 77.24 | `tests/Feature/Livewire/ManageEventFormImageTest.php` |
| 7 | 76.87 | `tests/Feature/Livewire/ManageEventFormGalleryTest.php` |
| 8 | 73.72 | `tests/Feature/Media/UserGalleryTest.php` |
| 9 | 69.82 | `tests/Unit/Support/Ui/BrowseListingCardPresenterTest.php` |
| 10 | 64.20 | `tests/Feature/ScheduledPeriodicNotificationsCommandTest.php` |
| 11 | 63.87 | `tests/Feature/Browse/BrowseTagSuggestionsTest.php` |
| 12 | 62.42 | `tests/Feature/ProfileTest.php` |
| 13 | 61.62 | `tests/Feature/DeletionAndCancelGatesTest.php` |
| 14 | 53.26 | `tests/Feature/Avatar/AvatarSourceTest.php` |
| 15 | 52.48 | `tests/Feature/Notifications/AppNotificationsTest.php` |
| 16 | 52.11 | `tests/Feature/ActivityLateAnnounceTest.php` |
| 17 | 50.02 | `tests/Feature/Browse/BrowseFullTextSearchTest.php` |
| 18 | 45.49 | `tests/Unit/Support/Ui/ActivityListingImageResolverTest.php` |
| 19 | 45.40 | `tests/Feature/ListingCardActivityPreviewTest.php` |
| 20 | 45.15 | `tests/Unit/Services/Ui/ActivityBadgeGroupBuilderTest.php` |
| 21 | 44.61 | `tests/Feature/Livewire/ShowActivityActionsTest.php` |
| 22 | 43.24 | `tests/Feature/BrowseMapFeaturesTest.php` |
| 23 | 41.11 | `tests/Feature/Filament/FilamentTableFiltersTest.php` |
| 24 | 40.92 | `tests/Feature/Livewire/ShowEventPlanTabProposalVisibilityTest.php` |
| 25 | 40.20 | `tests/Feature/Security/ContentVisibilityTest.php` |
| 26 | 39.79 | `tests/Unit/Support/Calendar/CalendarLinksTest.php` |
| 27 | 38.17 | `tests/Feature/ParticipantRosterPdfTest.php` |
| 28 | 37.38 | `tests/Feature/Notifications/CancellationNotificationsTest.php` |
| 29 | 36.89 | `tests/Feature/Notifications/InterestedPlacesThresholdNotifierTest.php` |
| 30 | 35.35 | `tests/Feature/ActivityFamiliarityTest.php` |

## Likely `@group slow` candidates (after cleanup)

Media/image pipelines, gallery, PDF roster, and remaining giants after Steps 3–4 (digest command slim + ProfileTest split).
