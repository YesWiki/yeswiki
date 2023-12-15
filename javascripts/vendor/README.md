Javascript Libraries Management
-----------------------------

Do not add manually a library here, instead use `yarn` do add the lib to the project
Then adjust the file `javascripts/vendor/copy_libs_from_node_modules.sh` to copy the necessary files (usually in the `dist` folder) from node_modules to this folder
Then run `yarn` to launch the copy

This way, libraries are managed by yarn, but we don't need to install nodejs on the production server, cause this folder is included in the git repository