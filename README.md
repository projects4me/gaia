# Gaia

[![Maintainability](https://api.codeclimate.com/v1/badges/dbe2ca5d0427744da7a5/maintainability)](https://codeclimate.com/github/projects4me/gaia/maintainability)
[![bitHound Overall Score](https://www.bithound.io/github/projects4me/gaia/badges/score.svg)](https://www.bithound.io/github/projects4me/gaia)
[![Dependency Status](https://beta.gemnasium.com/badges/github.com/projects4me/gaia.svg)](https://beta.gemnasium.com/projects/github.com/projects4me/gaia)
[![Build Status](https://travis-ci.org/projects4me/gaia.svg?branch=master)](https://travis-ci.org/projects4me/gaia)
[![Test Coverage](https://api.codeclimate.com/v1/badges/dbe2ca5d0427744da7a5/test_coverage)](https://codeclimate.com/github/projects4me/gaia/test_coverage)

Gaia is the server side component of Projects4Me. It manages the data, exposes the REST API and performs the maintenance
tasks on the application. 

Gaia is created keeping performance in mind. In order to minimize the memory and CPU footprint it is created on top
of PhalconPHP. The decision to build the platform on top of PhalconPHP was not for performance sake alone, in the later
phases on the application customization options will be introduced. This change will require the application to be
built on a very modular approach making most of the application extensible. Since PhalconPHP is extremely modular in its
design, more so than most of the other frameworks, it was the best choice to build the application in.

# Installation

## Semantic search upkeep

- **On start**: the dev image's `docker/dev/start.sh` runs `php cli.php search backfill` after `migration migrate`. It
  stores the semantic text and `searchDesiredHash` of records that have none or an outdated one, publishes nothing
  and prints counts per type. A failed backfill is logged and the container still starts.
- **Ares catches up**: the Ares service pages through `GET /api/v1/searchpending?after=<cursor>&limit=<n>` for the
  records whose embedding is missing or stale (ids only, `next` cursor, `limit` default 200, at most 500). Only an
  active `ares` service client whose token has the scope `searchpending.get` may call it; an Ares client enrolled
  before that scope existed needs it added.
- **By hand**: `php cli.php search reindex [type]` backfills and also re-publishes `search.semantic.changed` for every
  record whose embedding is missing or stale. There is no scheduler.

See `docs/architecture/search/decisions.md` (D-006, D-009).

## Cache

Gaia caches through `Gaia\Libraries\Cache\CacheInterface` (the shared `cache` service). With no cache variables
set it is a `NullCache`: every read is a miss and Gaia behaves as with no cache. Set in the environment
(`config/cache.php`):

| Variable | Default | Meaning |
|---|---|---|
| `CACHE_ADAPTER` | `none` | `none`, `stream`, `apcu`, `redis` or `memory` (per request only) |
| `CACHE_PREFIX` | `gaia-` | Prefix of every key |
| `CACHE_VERSION` | `0` | Part of every key; set it per deploy (e.g. the image tag) so a new image never reads an older one's entries |
| `CACHE_TTL` | `3600` | Default lifetime of an entry, in seconds |
| `CACHE_STREAM_DIR` | `cache/data` | Directory of the `stream` adapter (git-ignored; writable in the dev image) |
| `CACHE_REDIS_HOST`, `CACHE_REDIS_PORT`, `CACHE_REDIS_INDEX` | `127.0.0.1`, `6379`, `0` | Redis server of the `redis` adapter |

The cache fails open: an adapter that can't be used (missing APCu or redis extension, Redis unreachable, stream
directory not writable) logs one warning in `logs/application.log` and leaves caching off; a failing read is a miss
and a failing write is logged and ignored. The image has neither APCu nor redis. `php cli.php cache clear` empties
the configured cache.

# Contributions

# License
Gaia is the server side component of Projects4Me and is available under the same license

Projects4Me is a commercial open source project management software developed by PROJECTS4ME Copyright (C) 2015-2018 
PROJECTS4ME.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU Affero General Public 
License version 3 (GNU AGPL v3) as published be the Free Software Foundation with the addition of the following 
permission added to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK IN WHICH THE COPYRIGHT IS 
OWNED BY PROJECTS4ME., Projects4Me DISCLAIMS THE WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied 
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU AGPL v3 for more details.

You should have received a copy of the GNU AGPL v3 along with this program; if not, see http://www.gnu.org/licenses or 
write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

You can contact PROJECTS4ME, at email address hammad@projects4.me.

The interactive user interfaces in modified source and object code versions of this program must display Appropriate 
Legal Notices, as required under Section 5 of the GNU AGPL v3.

In accordance with Section 7(b) of the GNU AGPL v3, these Appropriate Legal Notices must retain the display of the 
"Powered by Projects4Me" logo. If the display of the logo is not reasonably feasible for technical reasons, the 
Appropriate Legal Notices must display the words "Powered by Projects4Me".