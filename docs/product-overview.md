# Product Overview

## What Nerdik Is

Nerdik is an event and activity coordination platform for tabletop and nerd communities. It combines event-level planning with activity-level participation so organizers can publish programs and players can discover and join sessions.

## Primary User Roles

- **Guest**: browses public listings and event/activity detail pages.
- **Registered user**: can track interests, join activities, and use waitlists where needed.
- **Organizer/host**: creates organizations, events, slots, and activities; manages proposals and attendance.
- **Admin**: has full modification rights across entities.

## Main Domain Entities

- **Organization**: optional ownership context for events.
- **Event**: the container for a public/private program.
- **Slot**: a schedulable time/place block in an event; can require approval.
- **Activity**: the playable item hosted by a user; can be self-hosted or scheduled into an event.
- **ActivityProposal**: a pending decision item used when proposing activities into events.
- **ActivityUser / ActivityWaitlistEntry**: participant roster and queue records.
- **Place**: venues and rooms used by events and slots.
- **Tag / ActivityType**: discovery and compatibility metadata.

## Product Areas

## Discovery And Browsing

- Unified public browse route: `/search`.
- Legacy `/events` and `/activities` redirect to `/search`.
- Map and geocoding endpoints are rate-limited for stability.

## Event Management

- Organizers create and edit events and slots.
- Slots can be configured as requiring approval.
- Event owners decide on activity proposals.

## Activity Management

- Hosts create activities with type, duration, participant constraints, and hosting mode.
- Activities can be self-hosted or attached to event slots.
- Activities may require approval before participants are admitted.

## Participation

- Users join/leave activities directly when capacity and rules permit.
- Waitlists support full activities or approval-required activities.
- Hosts can approve waitlist entries and manage participant status.

## Seeded Demo Story

The seed data is designed to demonstrate a realistic workflow:

- `alice` and `bob` manage organizations and events.
- `charlie` and `diana` host activities.
- One proposal starts in pending state so acceptance/rejection can be tested.
- Sample data covers browse, join/leave, waitlist, and proposal decision paths.

## Related Docs

- Mechanisms and business rules: [`domain-mechanics.md`](domain-mechanics.md)
- Local setup and commands: [`development-workflow.md`](development-workflow.md)
