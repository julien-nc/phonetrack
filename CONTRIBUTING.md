# Translate PhoneTrack in your language

Translation is done in the [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

If your language is not present in the project, send me a private message in Crowdin or an e-mail and i'll add it.

# Report a bug

[Here](https://github.com/julien-nc/phonetrack/issues) is the link to submit a new issue.

Please check if the issue has already been fixed or if it is already currently discussed in an existing issue.

Don't forget to mention :

* your Nextcloud version
* your PhoneTrack version : release version or commit ID (if you're using a git working copy)
* your database type
* your browser name and version
* a more or less precise protocol to reproduce the bug

# Suggest a feature

You can also submit a [new issue](https://github.com/julien-nc/phonetrack/issues) to suggest a change or to make a feature request.

Please make sure the feature you ask for is not too specific to your use case and make sense in the project.

# Submit your own changes

Feel free to fork PhoneTrack to make your own changes.

## Workflow

Here is a brief description of the `fork and merge request` workflow:

* Fork the project to get a copy of which you are the owner
* Don't push commits in your main branch, it is easier to use your main branch to stay up to date with original project
* Create a branch from your up-to-date main one to make a bunch of commits **related to one single topic**. Name the branch explicitly.
* Create a pull request from this branch to the main branch of the original project

Here is a memo of the git commands to run after having forked the original project on GitHub :
``` bash
git clone https://github.com/yourGithubName/phonetrack
cd phonetrack

# on your local main branch, to get changes from the original project's main branch:
git pull https://github.com/julien-nc/phonetrack main

# create a branch to work on a future pull request
git checkout -b new-feature-1
# make changes, then commit
git commit -a -m "beginning to implement my new feature"
# continue developing
git commit -a -m "new feature is now ready"
# push it to your repo
git push origin new-feature-1
# now you can create your pull request ^^ !

# you want to update your main branch
git checkout main
git pull https://github.com/julien-nc/phonetrack main

# optional expert git trick ;-) :
# you've started to work on new-feature-1 and in the meantime,
# the main branch of the original project integrated some new stuff.
# If you want to get the new stuff in your new-feature-1 branch :
git checkout main
git pull https://github.com/julien-nc/phonetrack main
git checkout new-feature-1
# rebasing a branch means trying to put the commits of local branch on top of the requested branch
# in this example: it will temporarily stash your changes, get the new commits from main and put your changes on top!
git rebase main
# if there is no conflict between your changes
# and the original project's main branch
# the rebase will go just fine.
# You can then continue developing on your new-feature-1 branch
# To push again, you need to force-push:
git push origin new-feature-1 -f
```

## Tests

If you want to trigger Continuous Integration tests on GitHub, just push to your branch `test`

``` bash
# from any branch, for example from branch 'new-feature-1'
git push origin new-feature-1:test -f
```

Those tests only concern controller part. If someone could show me the way and just start to implement front-end (JS) tests with Karma, i'll be more than grateful !

## Recomandations

* Try not to make changes to libraries. Any css can be overriden in `css/phonetrack.css`. Leaflet plugins can sometimes be modified in `js/phonetrack.js` when using it.
* Try to use explicit variable names
* Try not to change HTML structure too much
* Try to comment your code if what it does it not obvious
