#!/usr/bin/env python
import json, sys, codecs

jj = json.load(codecs.open(sys.argv[1], 'r', 'utf-8'))

out = sys.argv[2]

outf = codecs.open(out, 'w', 'utf-8')

for k in jj:
    outf.write('#: nothing\n')
    outf.write('msgid "%s"\n' % k.replace('"', '\\"'))
    outf.write('msgstr "%s"\n' % jj[k].replace('"', '\\"'))
    outf.write('\n')

