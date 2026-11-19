#!/bin/bash
# Run the given command (apache2-foreground) with the scheduler (supercronic on docker/cron/crontab) beside it.
# GAIA_SCHEDULER=off runs the command alone: for the test containers, or a replica that should not run the jobs.
# Jobs run as www-data, like Apache, so the files they write stay writable for the web server.
# Stop signals go to both; when either exits the other is stopped and the container exits, so a dead scheduler
# restarts the container instead of leaving it serving without its daily tasks.

CRONTAB=/var/www/html/docker/cron/crontab

if [ "${GAIA_SCHEDULER:-on}" = "off" ]; then
    echo "Scheduler off (GAIA_SCHEDULER=off)"
    exec "$@"
fi

echo "Starting the scheduler on ${CRONTAB} (UTC)..."
TZ=UTC HOME=/var/www setpriv --reuid=www-data --regid=www-data --init-groups supercronic "${CRONTAB}" &
scheduler=$!

"$@" &
app=$!

# The php:apache image stops with SIGWINCH (Apache's graceful stop); pass each signal on to Apache as it came.
# A requested stop is not a scheduler failure, and Apache is left to finish the stop it was sent.
stopping=0
for signal in TERM INT WINCH; do
    trap "stopping=1; kill -${signal} ${app} 2> /dev/null; kill -TERM ${scheduler} 2> /dev/null" "${signal}"
done

# wait returns early when a trapped signal arrives; keep waiting until one of the two has really exited.
while kill -0 "${app}" 2> /dev/null && kill -0 "${scheduler}" 2> /dev/null; do
    wait -n
done

scheduler_died=0
if [ "${stopping}" = 0 ] && ! kill -0 "${scheduler}" 2> /dev/null; then
    echo "The scheduler exited; stopping the container" >&2
    scheduler_died=1
fi
if [ "${stopping}" = 0 ]; then
    kill -TERM "${app}" 2> /dev/null
fi
kill -TERM "${scheduler}" 2> /dev/null
wait "${app}"
status=$?
wait "${scheduler}"
# Apache stopped by TERM exits 0; fail instead, so an on-failure restart policy still restarts the container.
if [ "${scheduler_died}" = 1 ]; then
    exit 1
fi
exit "${status}"
