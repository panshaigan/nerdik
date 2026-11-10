# Product Overview

## What nerdik Is

nerdik is an event and activity coordination platform for tabletop and nerd communities. It combines event-level planning with activity-level participation so organizers can publish programmes and players can discover, join, and host sessions.

## Primary User Roles

- **Guest**: browses public listings, catalogs, and event/activity detail pages.
- **Registered user**: tracks interests, joins activities (open, waitlist, or lottery), manages play preferences and availability, and can belong to play groups.
- **Activity host**: creates self-hosted or event-proposed activities, manages rosters, invites, and table setup.
- **Event organizer**: creates organizations, events, slots, and enrolment windows; reviews host proposals or schedules activities directly onto slots.
- **Admin**: has full modification rights across entities.

## Main Domain Entities

- **Organization**: optional ownership context for events.
- **Event**: the container for a public/private programme.
- **Slot**: a schedulable time/place block in an event; can require approval and constrain participation settings.
- **Activity**: the playable item hosted by a user; can be self-hosted or scheduled into an event.
- **ActivityProposal**: a pending decision item used when proposing activities into events.
- **ActivityUser / ActivityWaitlistEntry**: participant roster and queue records.
- **Place**: venues and rooms used by events and slots.
- **Tag / ActivityType**: discovery and compatibility metadata.
- **Group**: a shared-availability play crew (not an Organization). Public groups can be found and joined; the calendar stays members-only.

## Product Areas

## Discovery And Browsing

- Unified public browse route: `/search` (map and list). Legacy `/events` and `/activities` redirect there.
- Filters include tags, dates, place, organization, free seats, and a one-click apply of the user’s play area and interest tags.
- Catalogs for places, organizations, and event or activity series.
- Global search in the navigation bar.
- Map and geocoding endpoints are rate-limited for stability.

## Play Preferences And Groups

- Profile play preferences: a play area on the map plus interest tags. They appear on the contact preview and feed search / Find groups.
- Groups share a calendar of who is free which day. Availability (weekday defaults and date exceptions) lives on the user profile and aggregates in every group.
- Groups can be private (invite) or public (open join for signed-in users). Discover matches people by group tags and area.
- Hosts can start an activity from a calendar day; members marked available that day can be enrolled automatically.

## Event Management

- Organizers create and edit events, venues, rooms, and slots (including mass create).
- Slots can require approval and lock participation rules on assigned activities.
- Event owners decide on activity proposals, or schedule an activity and slot together from the event page without a proposal.
- Phased enrolment windows with per-person and per-activity limits.

## Activity Management

- Hosts create activities with type, duration, participant constraints, and hosting mode.
- Activities can be self-hosted or attached to event slots (via proposal, auto-accept, or organizer schedule).
- Optional familiarity questions, suggested things to bring, tags, covers, and spectators.

## Participation

- Users join/leave activities directly when capacity and rules permit.
- Waitlists support full activities or approval-required activities; lottery mode runs timed draws.
- Overlap checks block signing up for two activities at the same time.
- Hosts can approve waitlist entries and manage participant status (absent, remove, ban).

## Personal Dashboard

- Stats for hosting, joining, following, and groups.
- An action queue for pending requests and proposals.
- Upcoming listings grouped by month, plus a this-week strip.

## Seeded Demo Story

The seed data is designed to demonstrate a realistic workflow:

- `alice` and `bob` manage organizations and events.
- `charlie` and `diana` host activities.
- One proposal starts in pending state so acceptance/rejection can be tested.
- Sample data covers browse, join/leave, waitlist, and proposal decision paths.

## Related Docs

- Mechanisms and business rules: [`domain-mechanics.md`](domain-mechanics.md)
- Local setup and commands: [`development-workflow.md`](development-workflow.md)
- Public catalog of user-facing capabilities: `/features`
