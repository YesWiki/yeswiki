#!/bin/bash
# 
# Un g�n�rateur d'archives compress�es (.zip et .tar.gz) contenant les tools encore en d�veloppement, mais souvent utilis�s
#
#

# M�nage initial
rm -rf tmp/tools-dev
mkdir -p tmp/tools-dev
cd tmp/tools-dev

# R�cup�ration depuis CVS Wikiplug
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools/bazar
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools/contact
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools/login
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools/tags
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools/templates
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools/pointimagewiki
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools/syndication
cvs -z3 -qd:pserver:anonymous@cvs.berlios.de:/cvsroot/wikiplug export -r HEAD wikiplug/tools/chatmot


# Gestion des droits
chmod 755 wikiplug/tools -R

# Cr�ation des archives par tools
cd wikiplug/tools/
tar -cvzf ../../bazar.tar.gz bazar
zip -r9 ../../bazar.zip bazar
tar -cvzf ../../contact.tar.gz contact
zip -r9 ../../contact.zip contact
tar -cvzf ../../login.tar.gz login
zip -r9 ../../login.zip login
tar -cvzf ../../tags.tar.gz tags
zip -r9 ../../tags.zip tags
tar -cvzf ../../templates.tar.gz templates
zip -r9 ../../templates.zip templates
tar -cvzf ../../pointimagewiki.tar.gz pointimagewiki
zip -r9 ../../pointimagewiki.zip pointimagewiki
tar -cvzf ../../syndication.tar.gz syndication
zip -r9 ../../syndication.zip syndication
tar -cvzf ../../chatmot.tar.gz chatmot
zip -r9 ../../chatmot.zip chatmot
# Cr�ation des archives int�grales
cd ..
tar -cvzf ../tools-dev.tar.gz tools
zip -r9 ../tools-dev.zip tools 

# On efface le contenu du CVS
cd ..
rm -rf wikiplug

# Copie vers le serveur d'Outils-R�seaux
# premiere fois : sitecopy --init outils-reseaux.org-download-wikini-dev
sitecopy --update outils-reseaux.org-download-wikini-dev