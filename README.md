# Tilden Community Choir — site code

Source for [tildencommunitychoir.org](https://tildencommunitychoir.org): a WordPress site (hosted on SiteGround) that is two things at once:

- **The public site** — the cantata "trophy case" home page, about / join / contact pages, the schedule.
- **Backstage** — a password-protected area for the cast and crew: a personal dashboard, a full-screen practice app with synced sheet music and director's notes, and a report that tells the director how each song is coming, week by week.

| Folder | What it is |
| --- | --- |
| `tilden-cantata/` | **Choir Plugin (Tilden)** — everything with data or behaviour: cantatas, people, rehearsals, practice tracks, the practice app, sheet music, notes, feedback, the director report, sign-in, contact form, home-screen app |
| `tilden-choir/` | **Cantata Theme (Tilden)** — block theme: templates, patterns, logo, fonts |
| `tools/` | `clean_score.py` — cleans up a scanned score PDF before it is uploaded |
| `tcc-cue-export/` | one-off helper that read the old Cue playlists (no longer needed) |
| `dist/` | the built zips WordPress installs: `choir-plugin.zip`, `cantata-theme.zip` |

**Never in this repo:** audio (practice tracks live only in the WordPress media library) and score PDFs (the publisher's copyright). `.gitignore` blocks both, so the repo can stay public.

---

## What it does

### Public site
- **Home page** — every cantata as a stack of "covers" (the trophy case), the current one on top with its date, a countdown and the next rehearsal. Built from the cantata posts; the cached front page is purged whenever a cantata or the settings change.
- **Cantata pages** — details, song list, credits, a YouTube recording when there is one.
- **Schedule** — every rehearsal for the season; the next one opened up with what's being worked on (★ focus songs, required groups, a note from the director).
- **Join / About / Contact** — the contact form saves each message and emails the director.

### Backstage (`/backstage/`, one shared password)
- **A friendly password page** — instead of WordPress's "This content is password-protected", visitors see what Backstage is, that the password comes from anyone taking part (or the director, linked), a "Come in" box, a gentle note when a password didn't work, and a link to joining.
- **Sign-in without accounts.** A device is linked to a person once ("I've sung with the choir before" → find your name; "I'm new this year" → a one-minute sign-up that replaces the paper sheet; "Just browsing"). **"Who's practicing?"** — a quick switch for shared phones and iPads: everyone who has used the device, one tap each, with "Someone else", "New this year" and "Remove a name from this device". Where-you-left-off is kept per person. The director gets an email for **new** sign-ups only.
- **Just browsing** — the same widget dashboard for someone a choir member shared the password with: a welcome, the join pitch from the Join page with an "I'd like to join" button, the next rehearsal, the countdown, the full schedule, and the practice tracks explained part by part. The sign-in doors (find my name / new this year / just browsing) use the same look, and a "Sign in" button sits where a member's name would.
- **Personal dashboard** — an app-style grid of widgets: the next rehearsal as a calendar page (who's expected, *Required*, the songs with ★ focus, afterward items like team meetings, the director's note), *Continue practicing* at the exact spot you stopped, a red performance countdown, a *Full schedule* tile, and *Your songs* (each song's mark as a dot, totals, and a play button per song). Two columns on phones, three on computers.
- **Schedule tab** (`/backstage/schedule/`, the dashboard block with *view: schedule*) — every rehearsal as a card, month by month: the next one for you outlined in red, ones that aren't for you dimmed, a "Just mine" filter, the performance as the closing card, and earlier rehearsals folded away.
- **Director widget** — for anyone signed in to WordPress who can edit: this practice week at a glance on the Backstage home (singers practiced, listening time, open tricky spots, songs nobody practiced, and a bar per song); tap it for the full report, which uses the same card style.
- **Teams** (*Cantata → Teams*) — each team's name and description, its members, and its tasks for the season (task, details, due date). Members see their team's tasks on the Team tab and tick them off for themselves; teammates see who has finished, and the admin page shows "2 of 4" with names. A task due within the week shows on the member's Home tab until they tick it.
- **Documents** (*Cantata → Documents*) — the current cantata's documents in one place, each aimed at everyone, a group or a team. The Docs tab shows a person only what's for them.
- **Tab bar** — Home · Practice · Schedule · Team · Docs on every Backstage page except the practice app: along the bottom on phones, across the top on computers. Team and Docs are tabs of the hub (`#team`, `#docs`).
- **Home-screen app** — Backstage installs as **"Tilden Community Choir"** with its own icon (manifest + Apple tags). The dashboard detects the phone and browser (iPhone Safari/Chrome/Firefox/Edge, iPad, Android Chrome/Samsung/Firefox, Facebook/Instagram in-app browsers) and shows the right "Add to Home Screen" steps. Opened from the icon, the site header and footer disappear (the tab bar is the way around), and links that leave Backstage open in the browser instead.

### The practice app (`/practice/`)
Full-screen, built for phones and older singers with "fat fingers" — big controls everywhere.
- **Tracks by part** — Soprano, Alto, Tenor, Bass (Mezzo and Baritone singers get both of their parts), plus past years' cantatas.
- **Controls** — play/pause, previous/next song, back 5 s / forward 5 s, a scrub bar with note and page-turn marks, speed (1×, 0.85×, 0.75×), A–B loop, and lock-screen / headphone controls.
- **Voices ⇄ Accompaniment** — for practicing your own part with the voices taken out: one toggle swaps between your part track (people icon, "Voices") and the full accompaniment we perform with (music icon, "Accomp."), seamlessly at the same spot — the other version waits ready in a second player and cuts in once it's lined up. Your part doesn't change. Greyed out for a song with no accompaniment track yet. (Accompaniment on its own is still a choice in the part switcher.)
- **Sheet music, synced** — the score scrolls with the music. Two pages side by side on a wide screen, turning like a book (only once the right-hand page is done); one page at a time on a phone, fitted to the width. Pinch / double-tap zoom and pan, flick to look ahead ("Back to now" returns). The top bar shows the page and a countdown to the next turn.
- **Director's notes** at a moment in the song — with a measure number, an optional gist icon (turtle = slower, rabbit = faster, louder, softer, breathe, fermata, cut-off, entrance, watch me, listen, higher, lower, short, smooth, count, smile, words, important) and a "shows for" length (8 s by default).
  - Desktop/tablet: notes **flow** beside the music — the next few wait in line with a countdown, each one lights up when the music reaches it and fades away when its time is up.
  - Phone: the current note drops down from under the header, full width, one line (tap to read it all).
  - Tap any note to play from 4 s before it. The pencil edits a note (yours, or any director note when signed in to WordPress): words, time (±1 s or "move it to where the music is now"), measure, icon, length, who sees it, delete.
- **Personal notes** — singers can leave themselves notes at a moment in a song; only they see them.
- **Feedback** — the Feedback button (its circle shows your mark) opens three big choices: *Feeling good*, *Getting there*, *Tricky spots* — plus every note on the song. When a song finishes, the same three choices slide up with a 10-second countdown before the next song plays anyway. It only asks when the singer has heard most of the song (pauses don't reset that), at most once a day per song, never while looping or recording page turns. Wording is deliberately calm: "Your marks help the director decide what to work on at rehearsal."
- **Sectioned accompaniment** — the CD tracks (split at the circled numbers in the score) are listed as "CD 5 · starts 0:12 in"; the app knows where each starts in its song so pages and notes stay in step.

### Director tools
- **Director report** (`/director/`, editors only) — "How the cantata is coming", one practice week at a time (rehearsal to rehearsal), or the whole season:
  - singers who practiced, minutes listened, songs marked tricky, songs nobody practiced;
  - **getting ready for the next rehearsal** — the planned (or ★ focus) songs with a bar per section;
  - **Tricky spots — who to check in with** — names, song, part, since when, with a "handled" box (comes back if they mark it again);
  - **every song × every section** — how many of each section practiced it ("5/8") and how they marked it, as of the end of that week;
  - **where singers go back** — a heat strip per song from loops, 5-second rewinds and backward drags, with page numbers from the page turns and the most repeated stretch called out;
  - **section balance** — who has signed up for each voice part.
- **Page turns** — on the practice page, *Set page turns* plays the song from the top and the director taps **Turn** at each page change (one page at a time while recording). Turns are shared by every part.
- **Notes** — added and edited right in the practice app (or in the cantata's *Timestamp notes* table).

---

## Setting up a new season

1. **Cantata** — add a post in the *cantata* category: title, year, performance date and time, cover, song list (one per line).
2. **People and rehearsals** — rehearsals under *Cantata → Rehearsals* (or *Add in bulk*), with songs, ★ focus songs, required groups and a note. Singers add themselves through Backstage sign-in.
3. **Practice tracks** — upload the audio to the media library, then on the cantata's *Practice tracks* box add them per part. Tracks are matched to songs by title or track number; set one part by hand and files called "Track 04" in the other parts follow. Parts: Soprano, Alto, Tenor, Bass, *Accompaniment (performance)* — the full-song accompaniment the Voices/Accomp. toggle uses — *Accompaniment, sectioned* (the CD sections) and *All voices*.
4. **Score** — clean the scan (below), then upload it in the cantata's *Score (sheet music)* box: page offset (so PDF page = printed page), and each song's first and last printed page ("Fill in last pages" helps). The PDF is stored outside the public uploads folder (random name, deny-all `.htaccess`) and streamed only to Backstage with a token.
5. **Page turns** — record them per song on `/practice/` (see above).
6. **Pages** — `/backstage/` (the hub: hero + dashboard, password-protected), `/practice/`, `/backstage/schedule/`, `/director/` use the *Backstage page* template; any page with that template or a password is members-only. `/schedule/` is the public copy of the schedule (default template, same *Rehearsal schedule* block), linked from the Join page. Add Backstage URLs to SiteGround's Dynamic Cache exclusions.

### Cleaning a scanned score

```
python3 tools/clean_score.py scan.pdf workdir/ clean.pdf
```

Straightens each page from its staff lines (pages without staves are left alone), makes it pure black and white, clears scanner shadows at the edges and specks, and rebuilds a small 300 dpi CCITT G4 PDF. Needs `poppler-utils` (`pdfimages`), `opencv-python`, `numpy`, `img2pdf`.

---

## How it's built

### Plugin layout (`tilden-cantata/`)

| File | |
| --- | --- |
| `includes/helpers.php`, `meta.php`, `state.php`, `cache.php` | fields, settings, "current cantata" logic, front-page cache purge |
| `includes/render.php`, `blocks.php` | the public blocks (cantata stack, details, listen, page head…) |
| `includes/people.php`, `rehearsals.php`, `contact.php` | people, rehearsals/schedule, contact form |
| `includes/members.php`, `signin.php` | Backstage protection (one password, no-cache), device sign-in |
| `includes/dashboard.php` | the Backstage dashboard block and `GET /me` |
| `includes/tracks.php` | practice tracks, notes, feedback marks, the practice tables, the player blocks |
| `includes/score.php` | score storage and streaming, page turns |
| `includes/app.php` | home-screen app: manifest, icons, standalone mode |
| `includes/teams.php` | teams, team tasks (`POST /taskdone`), the Teams and Documents admin pages |
| `includes/director.php` | the director report |
| `assets/app.js` / `app.css` | the practice app |
| `assets/dashboard.js`, `signin.js`, `director.js`, `score-admin.js`, `tracks-admin.js` | the other front ends |
| `assets/vendor/pdfjs/` | pdf.js 3.11.174 (legacy build, Apache-2.0) |

### Data
- **Post types** — `tcc_person`, `tcc_rehearsal`, `tcc_message`; cantatas are ordinary posts in the `cantata` category.
- **On the cantata** — `_tcc_tracks` (per part), `_tcc_cues` (director notes: time, words, parts, measure, icon, length), `_tcc_score`, `_tcc_score_songs`, `_tcc_turns`, `_tcc_help_done`.
- **Tables** — `{prefix}tcc_practice` (per person, song and part: plays, seconds, current mark), `{prefix}tcc_pnotes` (personal notes), `{prefix}tcc_events` (dated log of listening, mark changes, loops, rewinds and backward drags — what the weekly report and heat maps are built from).

### REST (`/wp-json/tcc/v1/`)
Backstage calls carry a practice token (`X-TCC-Token`) and the device's person cookie (`tcc_who`); director calls use the normal WordPress login.

| Endpoint | |
| --- | --- |
| `me`, `state`, `who`, `roster`, `people`, `hints`, `signup` | who's on this device, their marks and notes, sign-in |
| `play`, `flag`, `note`, `heat` | listening, feedback marks, personal notes, loops/rewinds |
| `score` | streams the score PDF (token required) |
| `cue`, `turns`, `helpdone` | director: notes, page turns, ticking off tricky-spot requests |

### Privacy
- No accounts for singers; a person is linked to a device by a cookie.
- Personal notes are visible only to the person who wrote them.
- The director sees names for *Tricky spots* and counts by section for everything else; there are no per-person practice leaderboards.

---

## Building and deploying

```
./build.sh
```

Lints the PHP, checks for undefined `tcc_*` calls, parses the JS, then writes the two zips into `dist/`. Needs `php`, `node` and `zip`.

Install: wp-admin → Plugins → Add New → Upload Plugin → `dist/choir-plugin.zip` → "Replace current with uploaded". Same for the theme under Appearance → Themes → Add New → Upload Theme. Bump the version in `tilden-cantata/tilden-cantata.php` (both the header and `TCC_VERSION`) so browsers pick up new scripts.

Current versions: plugin **2.21.3**, theme **1.8.0**.
