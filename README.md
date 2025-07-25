<img alt="Drupal Logo" src="https://www.drupal.org/files/Wordmark_blue_RGB.png" height="60px">

This version of Drupal 11 is broken.

Although Drupal 11.3.x is supposed to work with PHP 8.3+ (and even lower), it's not working with PHP8.3.23 We can see that if we `ddev start` and `ddev launch /core/install.php` we'll get an error.

Use `git bisect` to find out when PHP 8.3.23 stopped working.

1. Check out the repo, `git clone https://github.com/rfay/git-bisect-example`
2. `ddev config --php-version=8.3` (Gets current default DDEV PHP, 8.3.23 as of 7/2024)
3. `ddev composer install`
4. `ddev launch` will fail with `Your PHP installation is too old`

You know that Drupal 11 installed fine with PHP 8.3.23 in version 11.2.0, but it doesn't with the current version here.

Use `git bisect` to find out what went wrong.

```bash
git bisect start
git bisect bad # We know it's bad right here
git checkout 11.2.0
ddev launch  # Should work
git bisect good  # so we mark it good
```

Continue with `git bisect good` or `git bisect bad` using `ddev launch` to check whether the install will work, until it finds the bad commit.

You can go a step farther and automate the check. For example,

```
curl -s https://git-bisect-example.ddev.site/core/install.php | grep "<title>Choose language" >/dev/null
``````

will return bash "true" or 0 when it's working, so can be used instead of manually hitting `ddev launch` and verifying it.

So with this script in `~/tmp/check-installable.sh` and the script set to executable, you can find the answer much more quickly. (The script can't be in the repository because we're checking over various checkouts of various revisions of the repository.)

```bash
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
```

```bash
git bisect reset
git bisect start 11.x 11.2.0 # git bisect bad good
git bisect run ~/tmp/check-installable.sh
```

**Caveats**:

* At each point in the bisect, git has checked out fresh code. You may need to take action to make that code completely usable. So the script does a `ddev composer install` for example, so the `vendor` dir and related files are up-to-date.
* In some situations you might have to load a fresh database at each point, or take whatever action is required to set the site to the initial state you're interested in. We don't have to do that in this example.
* On macOS and Windows, Mutagen is on by default, and we are making massive changes with each git checkout that `git bisect` does. It can take mutagen a moment to sync all those changes. As a result, the script adds a `ddev mutagen sync` to ensure that the sync is complete before we execute our test.
