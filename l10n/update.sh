#!/bin/bash

rm -rf /tmp/phonetrack
git clone https://github.com/julien-nc/phonetrack /tmp/phonetrack -b l10n_main --single-branch
cp -r /tmp/phonetrack/l10n/descriptions/[a-z][a-z]_[A-Z][A-Z] ./descriptions/
cp -r /tmp/phonetrack/translationfiles/[a-z][a-z]_[A-Z][A-Z] ../translationfiles/
rm -rf /tmp/phonetrack

echo "files copied"
