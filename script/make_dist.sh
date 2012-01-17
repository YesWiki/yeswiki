#!/bin/bash

cur_stable=v0r9-anacoluthe

# Ou OUT="$(mktemp)"²
mkdir -p ~/tmp/distrib
cd ~/tmp/distrib
rm yeswiki.tar.gz

# Recuperation git

wget https://github.com/mrflos/yeswiki/tarball/stable -O yeswiki.tar.gz
tar -xvzf yeswiki.tar.gz

rename 's/.*/yeswiki/'  mrflos*

rm yeswiki.tar.gz
tar -cvzf yeswiki.tar.gz yeswiki

exit

# Reorganisation code
mv wikiplug/tools/hooks/prepend.php wikiplug/tools/prepend.php
mv wikiplug/tools/hooks/tools.php wikiplug/tools.php
rm -rf wikiplug/tools/hooks
mkdir wikiplug/files

# Gestion des droits
chmod 755 wikiplug/tools -R
chmod 755 wikiplug/files 
chmod 755 wikiplug/tools.php


# Livraison depuis Wikini tag  wikini 0.5
svn export svn://svn.gna.org/wikini/wikini/tags/wikini-0.5 yeswiki
#cp -a ../../reference/wikini-0.5/ yeswiki


cp -a wikiplug/. yeswiki/.
tar -cvzf yeswiki-0.1.tar.gz yeswiki
zip -r9 yeswiki-0.1.zip yeswiki
rm -rf yeswiki



# Copie vers serveur
#sitecopy --update yeswiki.net-downloads
