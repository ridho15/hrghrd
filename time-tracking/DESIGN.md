# Design direction: Time Tracking module

This module lives inside the HR Group HRIS (Laravel app), so it takes that
product's existing identity instead of inventing one. Source of truth for the
identity: the HRIS `resources/css/app.css` and `layouts/app.blade.php`
(emerald brand scale, slate neutrals, Plus Jakarta Sans, light theme).

**Design Read:** internal HRIS time-tracking module (admin/manager dashboard and
employee self-view) for HR staff and employees, in the HR Group HRIS visual
language, dial **ENERGY 1 / RHYTHM 2 / MOTION 1**.

- ENERGY 1: a work tool people open daily; it should be calm and fast to read.
- RHYTHM 2: screens mix a focal number, a metric grid, a bar and data lists, so
  sections differ by content, inside a consistent frame.
- MOTION 1: hover, press and focus states only. No entrance animations, no loops.

## Decisions and reasons (R-31)

| Decision | Reason |
|---|---|
| Light theme only | The HRIS ships light-only; the module must feel like the same product. |
| Background `#f8fafc`, surfaces white, 1px `#e2e8f0` borders | Same neutrals as the HRIS; borders, not shadows, separate surfaces. |
| Brand emerald `#047857` (text/buttons), `#059669` (bars) | HRIS brand and `theme-color`; passes AA on white (5.5:1 text, 3.8:1 graphics). |
| Danger rose `#be123c`, warning amber `#b45309` | Only for real states (offline, rejected, low score / paused, pending). |
| No other colors | Palette = brand + 2 status colors + slate (R-29). Charts encode value by length, not hue. |
| Plus Jakarta Sans 400-700, self-hosted | HRIS typeface; self-hosted so it works on an intranet and under a strict CSP. |
| Tabular figures for every number | Times and counts line up when scanning lists. |
| Radius 6 / 8 / 12 px | 6 for status labels, 8 for controls, 12 for surfaces: shape tells you what a thing is (R-11). |
| Shadow only on the fixed bottom navigation | It is the one surface that sits above scrolling content (R-12). |
| No emoji anywhere, icons only in the navigation | Nav icons help recognition at small sizes and follow the HRIS 2px-stroke style; everywhere else the label does the work (R-04). |
| Sentence case, no em dash, Indonesian copy | Readable, and the HRIS is Indonesian-first (R-02, R-06). |

## Identity motif

**The time rule.** Every duration that matters is drawn as a thin emerald rule
whose length is the share of time: working time vs target on the tracker, active
share per 10-minute interval, productive share per employee. It reads like the
ruler on a paper timesheet and is the one visual gesture repeated across screens.

**The accent** is the focal rule under "Waktu kerja": the thickest, the only one
at 6px, at the one moment the screen exists for.

## One focal point per screen

| Screen | The decision | Focal point |
|---|---|---|
| Tracker | Is this person working as expected today? | Working time vs target |
| Ringkasan KPI | Who needs attention this week? | List sorted by lowest score first |
| Rincian aktivitas | What exactly happened in each interval? | The interval table |
| Approval | Approve or reject this period? | Pending items first, with actions |
| Pengaturan | Add people, hand out tokens | Kelola karyawan at the top |

## Layout states

- **< 640px:** single column, bottom navigation (44px+ targets), content padded
  above the bar so nothing hides under it.
- **640-959px:** same single column, centered at 640px.
- **960px and up:** navigation moves into the header, content widens to 1040px,
  the tracker splits into a summary column and the interval list.

## States (R-27)

Every data view has a loading line ("Memuat ..."), an empty state that says why
it is empty and what to do next, and an error state with a "Coba lagi" button.
