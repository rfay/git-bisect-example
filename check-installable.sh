#!/bin/bash

sleep 1
ddev mutagen sync >/dev/null 2>&1 # make sure the git checkout has propagated if mutagen enabled
echo "Result of ddev mutagen sync: $?"
sleep 1
ddev composer install --no-interaction >/dev/null 2>&1
echo "Result of composer install: $?"
curl -sfL https://git-bisect-example.ddev.site/core/install.php | grep "<title>Choose language" >/dev/null
rv=$?
#sleep 1
echo "Result of curl-grep is $rv"
exit $rv
