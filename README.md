# Nerdik

A system for organizing and participating in nerd events (RPG sessions, board game meetings, lectures, conventions). Built with Laravel, Livewire, and Breeze.

## Development

- **Stack:** Laravel 12, Sail (Docker), MariaDB, Redis, Mailpit, Adminer
- **Start:** `make up` then open http://localhost (or your Sail URL)
- **Artisan:** run via Sail, e.g. `./vendor/bin/sail artisan migrate` or use Makefile: `make migrate`, `make seed`, `make test`, `make queue`

## Seeding sample data

To load foundations (places, tags) plus sample users, organizations, events, instances, slots, activities, and a proposal for testing:

```bash
make migrate   # if needed
make seed     # or: ./vendor/bin/sail artisan db:seed
```

To reset the database and seed from scratch:

```bash
make migrate-fresh
make seed
```

**Sample users** (password for all: `password`):

| Email               | Nickname | Role in sample data |
|---------------------|----------|----------------------|
| alice@nerdik.test   | alice    | Owns Nerdik Club, Monthly RPG Night, Convention 2026; hosts D&D activity |
| bob@nerdik.test     | bob      | Owns Wrocław Gamers, Board Game Evening; hosts Forbidden Lands activity |
| charlie@nerdik.test | charlie   | Hosts Talisman board game activity |
| diana@nerdik.test   | diana    | Hosts Call of Cthulhu activity; has a **pending proposal** for Convention 2026 |

**Sample entities:**

- **Organizations:** Nerdik Club (owner: alice), Wrocław Gamers (owner: bob).
- **Events:** Monthly RPG Night (alice, Nerdik Club), Convention 2026 (alice, no org), Board Game Evening (bob, Wrocław Gamers).
- **Event instances:** One instance per event (next Friday 18:00–23:00 for Monthly RPG; a weekend in 2 months for Convention; next Wednesday 17:00–22:00 for Board Game Evening). Each has 3 slots (Table #01–#03); #02 requires approval.
- **Activities:** D&D 5e one-shot (host alice) and Forbidden Lands (host bob) on Monthly RPG slots; Talisman (host charlie) on Board Game Evening; Call of Cthulhu (host diana) proposed to Convention 2026 (pending).
- **Proposal:** Diana’s Call of Cthulhu activity is proposed to Convention 2026 (pending). Log in as **alice** → Event instances → Convention 2026 → accept or reject in “Pending proposals”.

All seeded entities have `created_by` set so ownership and audit are consistent. Use **alice** or **bob** to manage events and accept proposals; **diana** to see proposal status; all four to test browse, wishlist, join/leave, and waitlist.

**Optional: Google login** – Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` (e.g. `${APP_URL}/auth/google/callback`) in `.env` to show “Log in with Google” on the login and register pages. Create OAuth 2.0 credentials in [Google Cloud Console](https://console.cloud.google.com/apis/credentials).

## Data & conventions

- **Timezones:** All datetimes are stored in **UTC** in the database. Users can set a timezone in Profile; dates and times are then shown in that timezone. Form inputs (e.g. event instance start/end) are interpreted in the user’s timezone and saved as UTC.
- **Ownership & audit:** Main entities (events, event instances, organizations, slots, places, tags, activities, activity proposals) have `created_by` and `updated_by` (user IDs). The `HasMetaColumns` trait sets these from the authenticated user. These entities also support soft deletes (`deleted_at`, `deleted_by`).

## GitHub

This repo is ready to push to GitHub. After creating a new repository on GitHub:

```bash
git remote add origin https://github.com/YOUR_USERNAME/nerdik.git
git branch -M main   # optional: use main instead of master
git push -u origin main
```

Do not commit `.env` (it’s in `.gitignore`). Commit `.env.example` if you have one so others can copy it for local setup.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
