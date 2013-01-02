#!/bin/bash
# Version : nouvelle fonctionnalité du HEAD, nouvelle branche
# Release : correction de livraison, tag
# Prendre modele sur hashcash V1
# L'arborescence locale contient les branches (V0, V1 etc) qui font office de head. Au moment de la livraison, pose de tag.

# Utilisation de tag uniquement

# liste_tag.pl -l wikiplug_db

#  Aceditor_V0_R1 branch
#  Aceditor_V1_R0 branch
# 
#  Attach_V0_R1 branch
#  
#  Hashcash_V0_R1 revision/tag
#  Hashcash_V1_R0 branche 
#  Hashcash_V1_R1 revision/tag
#  Hashcash_V1_R2 revision/tag
#  
#  Navigation_V0_R1 branch
#  Navigation_V0_R2 revision/tag
#  
#  Tableau_V0_R1 branch
#  
#  Template_V0_R0_branch
#
#  Toolsmng_V0_R1 branch
#  Toolsmng_V1_R0 branch
#  Toolsmng_V1_R1 revision/tag
#  
#  Wikiplug_V0_R1 branch
#  Wikiplug_V0_R2 branch (aurait du être revision/tag)
#  
#  


rm -rf tmp/distrib
mkdir -p tmp/distrib
cd tmp/distrib
# Recuperation depuis CVS Wikiplug
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r Aceditor_V1_R0 wikiplug/tools/aceditor
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r Attach_V0_R1 wikiplug/tools/attach
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools/attach/players
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r Hashcash_V1_R0 wikiplug/tools/hashcash
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r Wikiplug_V0_R2 wikiplug/tools/hooks
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r Wikiplug_V0_R2 wikiplug/tools/libs
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r Toolsmng_V1_R0 wikiplug/tools/toolsmng
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r Navigation_V0_R1 wikiplug/tools/navigation
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r Tableau_V0_R1 wikiplug/tools/tableau
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r Templates_V0_R0 wikiplug/tools/templates



# Reorganisation code
mv wikiplug/tools/hooks/prepend.php wikiplug/tools/prepend.php
mv wikiplug/tools/hooks/tools.php wikiplug/tools.php
rm -rf wikiplug/tools/hooks
mkdir wikiplug/files

# Gestion des droits
chmod 755 wikiplug/tools -R
chmod 755 wikiplug/files 
chmod 755 wikiplug/tools.php

# Livraison depuis Wikini tag  wikini 0.4.4
# svn export svn://svn.gna.org/wikini/wikini/tags/wikini-0.4.4 wikini
cp -a ../../reference/wikini-0.4.4/ wikini

#cp -a ../../reference/wikini-0.4.4/*	 .

ex wikini/wakka.php << EOF
	% s/^\$wiki->Run(\$page, \$method);/include('tools\/prepend.php');\$wiki->Run(\$page, \$method);/g
wq
EOF

cp -a wikiplug/. wikini/.
tar -cvzf wikini-0.4.4.tar.gz wikini
zip -r9 wikini-0.4.4.zip wikini
rm -rf wikini

# Livraison depuis Wikini tag  wikini 0.5
#svn export svn://svn.gna.org/wikini/wikini/tags/wikini-0.5 wikini
cp -a ../../reference/wikini-0.5/ wikini


ex wikini/wakka.php << EOF
	% s/^\$wiki->Run(\$page, \$method);/include('tools\/prepend.php');\$wiki->Run(\$page, \$method);/g
wq
EOF

cp -a wikiplug/. wikini/.
tar -cvzf wikini-0.5.tar.gz wikini
zip -r9 wikini-0.5.zip wikini
rm -rf wikini

# Livraison extensions  base
mkdir -p extension_base
cp -a wikiplug/. extension_base/.
tar -cvzf extension_base_wikini.tar.gz extension_base
zip -r9 extension_base_wikini.zip extension_base


# Tout head :

cd ..
rm -rf all
mkdir -p all
cd all
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools
cd ..
tar -cvzf all.tar.gz all
zip -r9 all.zip all
mv all.tar.gz distrib
mv all.zip distrib


# plugins

mkdir distrib/plugins
cd ..
# sans doute plus utilisé : sh make_plug.sh

# Copie vers serveur
# sitecopy --update outils-reseaux.org-download-wikini-base


