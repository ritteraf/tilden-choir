# Cantata Theme (Tilden)

Block theme for the Tilden Community Choir. Requires the **Choir Plugin** for the home-page stack and the cantata fields.

## Install

1. Plugins → Add New → Upload → `choir-plugin.zip` → Activate.
2. Appearance → Themes → Add New → Upload → `cantata-theme.zip` → Activate.
3. Settings → Cantata: default venue and address. Settings → General: timezone = Chicago.
4. Create these pages and add them to the header menu (Appearance → Editor → Navigation): **About the choir**, **Join** (use the "Join page" pattern), **What's a cantata?**, **Contact**.
5. Add the five cantata posts (see the plugin README). The home page shows the stack automatically; no Reading-settings change is needed.

## What you can edit without touching code

- **Appearance → Editor → Patterns → Template parts:** Header, Footer, and "Under this year's cantata" (the definition band).
- **Appearance → Editor → Styles:** colours and fonts. The stack reads the theme's palette (Ink, Paper, Carol red, Pine, Gold) and fonts, so changes there flow through.
- **Templates:** front page (the stack), single cantata (entry + post body), page, index, 404. A "Singers page" template is included for singers pages if you want them laid out differently.

## Backstage pages

Seven pages, one password. Create **Backstage** from the "Backstage hub" pattern with the *Backstage page* template, then child pages: Soprano, Alto, Tenor, Bass and Accompaniment from the "Voice part page" pattern, and **Schedule** from the "Rehearsal schedule page" pattern. Give every one the same password (Visibility → Password protected) and tick *members-only page*. Set the Backstage page on this year's cantata post; the header's **Cast &amp; Crew** button follows it. The hub has a section each for singers, narrators, actors and tech crew. Documents (scripts, staging directions, run sheets, logo files) are entered on the cantata post under **Documents**, each marked for the group it's for, and the hub shows them as cards; a group with no documents shows nothing.

The hub's listening video comes from the *Listening video (YouTube)* field on this year's cantata post. Rehearsals are entered under **Cantata → Rehearsals** (or generated with **Add a season**); the schedule page and the hub's "next rehearsal" card read them.

## Fonts

Zilla Slab and Libre Franklin are bundled (SIL Open Font License; see assets/fonts). The site makes no requests to Google Fonts.

## The wash on the collapsed bands

If photos feel too washed out, lower `--tcc-wash-strength` in the plugin's `assets/stack.css` (1 = as designed; 0.85 lets more photo through). It's one number.
