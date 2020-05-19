#!/bin/bash

rm -rf /tmp/phonetrack
git clone https://gitlab.com/eneiluj/phonetrack-oc /tmp/phonetrack -b l10n_master
cp -r /tmp/phonetrack/l10n/descriptions/[a-z][a-z]_[A-Z][A-Z] ./descriptions/
cp -r /tmp/phonetrack/translationfiles/[a-z][a-z]_[A-Z][A-Z] ../translationfiles/
rm -rf /tmp/phonetrack

echo "files copied"
