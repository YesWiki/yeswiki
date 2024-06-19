# Description of the usage of folder private/backups

This folder is **reserved to backups**.

It **MUST NOT** be accessible from the internet.

- On Apache server, check that the file `.htaccess` is taken in count.
- On Nginx server or other, configure the server to **deny all** access on this folder
