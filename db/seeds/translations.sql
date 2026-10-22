-- Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
--
-- UI translations (Translation model, table `translations`), served by GET /translations/{locale}.
-- The migration creates the table; this seed adds the en-us labels of the system issue statuses,
-- the ones Prometheus has in translations/en-us.json under views.app.issue.lists.status.
-- Idempotent: rows that already exist (same locale and path) are left as they are, so labels
-- changed later are kept.
--   psql -d <database> -f db/seeds/translations.sql
-- IDs are deterministic (uuid5 of locale:path) so every environment has the same rows.
-- After seeding a running server, delete filesystem/translations/en-us.json so it is rebuilt.

INSERT INTO translations (id, locale, path, value, "dateCreated", "dateModified", deleted, "createdUser", "createdUserName", "modifiedUser", "modifiedUserName")
VALUES
    ('da4d642c-cf37-5633-8b0d-9d626c4ba718', 'en-us', 'views.app.issue.lists.status.new', 'New', NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('00447f98-6f63-5928-bacf-7219f1c6438f', 'en-us', 'views.app.issue.lists.status.in_progress', 'In Progress', NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('1b6c21cb-ffce-57f9-adb8-66a824633f48', 'en-us', 'views.app.issue.lists.status.in_review', 'In Review', NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('fd4defb6-467e-5a6d-86ec-1e8bf1d20935', 'en-us', 'views.app.issue.lists.status.done', 'Done', NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('3409dee5-a4b4-57b8-aa0d-4e749608a83e', 'en-us', 'views.app.issue.lists.status.feedback', 'Feedback', NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('84ef3c30-5172-5f13-902e-3289f198d07c', 'en-us', 'views.app.issue.lists.status.pending', 'Pending', NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('7f52d2a9-ab60-5cb9-aec3-9b46c6c7e48e', 'en-us', 'views.app.issue.lists.status.deferred', 'Deferred', NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('19c62b1d-0229-5a6d-9a38-32976f36fa32', 'en-us', 'views.app.issue.lists.status.wont_fix', 'Won''t Fix', NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan')
ON CONFLICT (locale, path) DO NOTHING;
