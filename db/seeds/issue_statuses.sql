-- Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
--
-- Colour and sort order for the system issue statuses (Issuestatus `color` / `sortOrder`).
-- The migration adds the columns; this seed fills them to match what Prometheus showed before
-- they existed: the colours from styles/issue/issue-status.scss and the task board column order
-- (wont_fix is not a board column, so it goes last).
-- Idempotent: only fills values that are still empty, so changes made in Settings are kept.
--   psql -d <database> -f db/seeds/issue_statuses.sql

UPDATE issue_statuses AS s
SET color = COALESCE(s.color, v.color),
    "sortOrder" = COALESCE(s."sortOrder", v.sort_order)
FROM (
    VALUES
        ('new', '#80b5d3', 1),
        ('in_progress', '#b3244f', 2),
        ('in_review', '#80b5d3', 3),
        ('done', '#5ac594', 4),
        ('feedback', '#f7bf65', 5),
        ('pending', '#f7bf65', 6),
        ('deferred', '#f7bf65', 7),
        ('wont_fix', '#e98a7e', 8)
) AS v(name, color, sort_order)
WHERE s.name = v.name
  AND s.system = 1
  AND (s.color IS NULL OR s."sortOrder" IS NULL);
