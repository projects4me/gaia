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

## Scheduled tasks
The Gaia containers run the CLI tasks in [`docker/cron/crontab`](docker/cron/crontab) with
[supercronic](https://github.com/aptible/supercronic), started beside Apache by `docker/cron/scheduler.sh` (the deployed
image through `docker/dev/start.sh`, the local `gaia` service as its command). Times are UTC, the jobs run as
`www-data` with the container's environment, their output goes to the container log (`docker logs gaia-dev`), and a
job never starts while its previous run is still going in that container. Every container that runs the scheduler runs
the jobs: the Helm chart deploys one replica (`replicaCount: 1`), and with more replicas every one of them would run
the 03:00 reindex, since the chart sets the same environment on all pods. Running more replicas needs a separate
single-replica scheduler workload first. If
the scheduler exits, the container stops with a failure rather than serving without its daily tasks: a deployment with
a restart policy (the default in Kubernetes) restarts it, while the local Compose `gaia` service has none and stays
stopped until you start it again.

| Schedule (UTC) | Command | Why |
|---|---|---|
| 03:00 daily | `php cli.php search reindex` | Backfill the semantic text and re-publish records whose embedding is missing or stale |

Set `GAIA_SCHEDULER=off` to skip the scheduler (the test containers do). The image build runs `supercronic -test` on
the crontab, and `tests/tools/docker/CrontabTest.php` checks that every job runs a task and action that exist. After a
change to the crontab, restart the local container (`docker compose restart gaia`): the `./` bind mount supplies the
edited file, so no rebuild is needed.

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