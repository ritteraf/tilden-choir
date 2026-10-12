# Tilden Community Choir — site code

Source for the two pieces that make [tildencommunitychoir.org](https://tildencommunitychoir.org) tick:

| Folder | What it is |
| --- | --- |
| `tilden-cantata/` | **Choir Plugin (Tilden)** — cantatas, people, rehearsal schedule, practice player, Backstage sign-in, contact form |
| `tilden-choir/` | **Cantata Theme (Tilden)** — block theme, patterns, logo and fonts |
| `tcc-cue-export/` | one-off helper used to read the old Cue playlists (no longer needed) |
| `dist/` | the built zips WordPress installs: `choir-plugin.zip`, `cantata-theme.zip` |

## Building

```
./build.sh
```

Lints the PHP, checks for undefined `tcc_*` calls, parses the JS, then writes the two zips into `dist/`. Needs `php`, `node` and `zip`.

## Installing on the site

wp-admin → Plugins → Add New → Upload Plugin → pick `dist/choir-plugin.zip` → "Replace current with uploaded".
Same for the theme under Appearance → Themes → Add New → Upload Theme.

## Not in this repo

No audio. Practice tracks are uploaded to the WordPress media library and attached to a cantata under *Cantata → Practice tracks*; they are never committed here (`.gitignore` blocks them).
