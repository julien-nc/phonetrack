#!/bin/bash

cd ..
rm -rf /tmp/phonetrackjs
mv js /tmp/phonetrackjs
translationtool.phar create-pot-files
mv /tmp/phonetrackjs js
cd -
