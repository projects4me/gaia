# Backfill relatedTo/relatedId on old system notifications

agaia-4064 added `relatedTo`/`relatedId` to `system_notifications` and sets them on every new notification.
Notifications stored before that have them as NULL. The `notification backfillRelated` CLI action fills them in
(agaia-4117).

## Run

From the Gaia root, in the Gaia container, after `php cli.php migration migrate` has added the columns:

```sh
php cli.php notification backfillRelated
```

It prints three counts:

```
Notification related-entity backfill completed.
  updated:    120
  skipped:    40 (already set)
  unresolved: 2 (left NULL)
```

- **updated**: notifications that got `relatedTo`/`relatedId` in this run.
- **skipped**: notifications that already had `relatedId` and were not touched.
- **unresolved**: notifications whose related entity couldn't be worked out; they stay NULL.

It is idempotent: it only reads and writes rows where `relatedId` is NULL, so a second run reports `updated: 0`.
It writes only `relatedTo` and `relatedId`: `context`, `description`, `dateModified` and the recipient rows are
left as they are.

## Mapping

The same as the notification modules in `app/api/v1/controllers/components/notifications/modules/` apply to new
notifications, told apart by `context.relatedTo`:

| `context.relatedTo` | `relatedTo` | `relatedId` from |
|---|---|---|
| `issue`, `comment`, `timelog` | `issue` | the description's `{{Issue@<id>}}` tag; else the issue found by `context.projectShortcode` + `context.issueNumber` |
| `milestone` | `milestone` | `context.milestoneId`; else the description's `{{Milestone@<id>}}` tag |
| `project` (membership) | `project` | `context.projectId`; else the description's `{{Project@<id>}}` tag; else the project found by `context.projectShortcode` |

Anything else (no context, no `context.relatedTo`, an unknown kind, or no id found) counts as unresolved.

Code: `core/tasks/NotificationTask.php` (CLI) → `core/services/NotificationRelatedEntityBackfill.php` (reads and
writes the rows) → `NotificationRelatedEntityResolver.php` (the mapping) → `NotificationRelatedRecordFinder.php`
(the short-code lookups).
