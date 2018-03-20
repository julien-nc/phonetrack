# this is just a memo
git checkout master
git pull origin master
git checkout l10n_master
git reset --hard HEAD~200
git pull origin master:l10n_master
git pull origin l10n_master
git rebase -i master
git checkout master
git merge l10n_master
git push origin master
git push origin master:l10n_master -f
