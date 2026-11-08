# Changes

## 1.1.0 (2026-11-08)

Redesigned pages, same features.

- The course page shows where every grade is as one strip of stages in order, each as wide as its share; each stage still filters the table. The stage that waits on you is marked.
- One plain line with the submit or approve action, in place of the coloured box.
- No percentage column when the course total is already out of 100.

## 1.0.0 (2026-11-08)

First stable release.

- Submitting is locked per course and exporting is locked site-wide, so a double click, two teachers at once, or "Export now" during the nightly run can no longer create duplicate rows or send the same grades twice.
- A grade the gradebook refuses to lock is not recorded as submitted.
- CSV text cells that start with = + - or @ get a leading apostrophe, so they cannot run as spreadsheet formulas.
- A drop folder that is missing, not writable or inside the Moodle code folder stops the export with a clear error before anything is marked as sent. Written files get the site's file permissions.
- The export capability is marked as a write capability.
- Privacy: deleting all data in the system context removes who made each export.
- Course names with "&" no longer show as "&amp;" on the exports page.
- CI covers Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 on PostgreSQL and MariaDB.

## 0.1.0 (2026-11-05)

First alpha.
