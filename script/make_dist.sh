#!/bin/bash

# Ou OUT="$(mktemp)"²
mkdir -p ~/tmp/distrib
cd ~/tmp/distrib
rm yeswiki.tar.gz
rm -rf yeswiki


# Recuperation git

wget https://github.com/mrflos/yeswiki/tarball/stable -O yeswiki.tar.gz
tar -xvzf yeswiki.tar.gz
rm yeswiki.tar.gz

rename 's/.*/yeswiki/'  mrflos*

for i in `find . -type d`; do  chmod 755 $i; done
for i in `find . -type f`; do  chmod 644 $i; done


tar -cvzf yeswiki-0.1.tar.gz yeswiki
zip -r yeswiki-0.1.zip yeswiki
rm -rf yeswiki



# Copie vers serveur
sitecopy --update yeswiki.net-downloads
