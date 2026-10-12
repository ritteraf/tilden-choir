# Choir Plugin (Tilden)

Cantata fields, season logic, the home-page cantata stack, and singers-only page protections.
Pairs with the **Cantata Theme**, but the fields and the stack work under any block theme.

## What it adds

- A **Cantata** box on every post: performance date, rehearsals start, venue, composers, publisher, run time, **songs** (each with any solos, duets, trios, quartets or small ensembles and who sings them, picked from People), other performers, YouTube URL, cover art, program PDF, singers page, offering, tint, status override.
- Two blocks: **Cantata stack** (the home page) and **Cantata details** (the top of a cantata's own page).
- **Settings → Cantata**: default venue and address, directions link, join-page link, emblem image, and the **Go live** switch (also in the admin bar).
- `/backstage/` (and `/singers/`) always leads to the Backstage page set on this year's cantata post.
- Members-only pages (tick the box on the page) are kept out of search engines and out of the page cache, and their password is remembered for 180 days.

## How the season works

The newest post in the Cantata category (by performance date) is "this year's". Its state is decided on each page load:

| State | When |
|---|---|
| Recruiting | Performance is in the future and today is before "Rehearsals start". |
| Upcoming | Performance is in the future (or today). |
| Live | The Go live switch is on. Switches itself off after 12 hours. |
| Just performed | Within 30 days after the performance. |
| Past | Everything else, and every older post. |

Nothing is flagged by hand. Publish next year's post with its date and this year's slides down the stack on its own.
The **Status override** field exists for the day a date is wrong and you need the home page fixed now.

## Rehearsals

**Cantata → Rehearsals** holds one record per rehearsal: date, start and end, type (rehearsal / required / performance), who attends (singers, narrators, actors, tech crew; nothing ticked = everyone), song numbers (printed as titles from the cantata's song list), and an **Afterward** list of separate items with their own time and attendees (solo tryouts, staging team meeting). Anything else goes in the editor and shows under the entry. A **Who is expected** box on each rehearsal lists the people from the roster whose type matches (and the same for each Afterward item), with an email-everyone link and copyable email and phone lists.

**Cantata → Add in bulk** creates a run of weekly draft rehearsals from a first date; title them, tick the songs, mark the required ones, publish.

Two blocks read them: **Rehearsal schedule** (the season as a timeline, grouped by month, past dates faded, the next one highlighted) and **Next rehearsal** (a card for the singers hub).

## People

Each person can have a photo (the featured image). When a person is assigned a part on a song, their name and photo appear in that cantata's entry on the home page and on its own page under "Solos and ensembles"; and every cantata entry has an expandable "Everyone who took part" list of the people whose Seasons include it, grouped by type and voice part. Tick *Keep off the site* on a person to leave them out of that list. Nothing else from the roster is ever shown.

**Cantata → People** is the roster: name, type(s), voice part and solo interest (singers), phone, email, volunteer role, and which seasons they took part in. It is private — editors and up only, no public page, not in the REST API. The list filters by season, type, part, and role, shows counts by voice part for this season, and downloads as a CSV. Volunteer roles are edited under Cantata → Settings.

## The yearly routine

1. Posts → Add New. Title = the cantata. Category = **Cantata**. Excerpt = one sentence on the story.
2. Fill the Cantata box: date and time, composers, cover art, YouTube URL (schedule the stream in YouTube Studio first and paste that link, it becomes the recording afterwards), offering recipient.
3. Create the Backstage page from the "Backstage hub" pattern, set a password, tick "members-only page", and pick it in the Cantata box.
4. After the concert: set the Featured image (a frame from the recording works), drop a Gallery block in the post body.

## SiteGround

SiteGround's Dynamic Cache ignores no-cache headers. After the Backstage pages exist, in **Site Tools → Speed → Caching → Dynamic Cache → Exclude URLs** add each singers page URL. If the SiteGround Optimizer plugin is installed, the plugin hands it those URLs automatically.

## Requirements

WordPress 6.5+, PHP 8.1+. The GD extension (standard on SiteGround) is used to read a tint from each cover.
