# Development

## Customize with code
[filename](custom-folder.md ':include')

## Make your wiki semantic

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?RendreYeswikiSemantique "Tutorial - Make your wiki semantic")_

## Create a bazar widget

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?BazarWidget "Tutorial - Create widget bazaar")_

## Create a local work environment

_[Original documentation on the yeswiki.net site (fr)](https://yeswiki.net/?PageConfiglocal "Tutorial - Create a local dev environment")_


## Create a YesWiki extension

Code created for new features can be proposed to the community via two way.

  1. by making a Pull-Request (PR) in the core project YesWiki : https://github.com/YesWiki/yeswiki/pulls
     - this is reserved to new features validated b ythe community for their utility for the core.
  2. or creating a new dedicated extension which can be easily added or remove. Add an extension is more flexible and can be made by a main developper of YesWiki, with the possibility to the community to ask a posteriori to rename or remove the extension.

### Create a YesWiki extension

 1. having rights to create a folder into the organization https://github.com/YesWiki, create a `repository` whose name must be like `yeswiki-extension-extensionname`, where `extensionname` is replaced by the name of the extension, without special characters.
 2. pull files on the new repository
 3. add a file `LICENSE` (shoudl be AGPL 3.0 at minimum knowing that `YesWiki` follow this license) and a file `README.md`
 4. modify the repository configuraiton file : https://github.com/YesWiki/yeswiki-build-repo/blob/master/repo.config.json
    - add the new extension for each revision of `YesWiki` which supports this extension yb inspiring from other extensions
    - update the concerned documentation link to `README.md` from extension or to a documantation page on the internet
 5. go to the server `repository-api` of `YesWiki` with your `SSH` key (key only given to authorized developpers)
 6. update the local file `repo.config.json`, normally with command `git pull` (warning the command is here only for indication)
 7. start the update of the repository
 8. go back to the folder `https://github.com/YesWiki/yeswiki-extension-extensioname`
 9. click on `Settings`
 10. in left sidebar, click on `Webhooks`, then `Add webhook` (the link looks like `https://github.com/YesWiki/yeswiki-extension-extensionname/settings/hooks/new`)
 11. fill the form like
     - **Payload URL** : url of `repository-api`
     - **Content type** : application/json
     - **Secret** : secret GitHub password from config file on server `repository-api`
     - choose **just the `push` event**
 12. warn developpers community of the creation of this extension on Framateam channel : https://framateam.org/yeswiki/channels/developpement
     - the community `YesWiki` can ask to rename the extension, for that :
       - create a new extension with the new name
       - copy files
       - delete the current extension

### Delete an extension

 1. Delete the concerned repository on `GitHub` (**this action can not be undone**)
 2. go on https://github.com/YesWiki/yeswiki-build-repo/blob/master/repo.config.json and remove references to the extension
 3. go with your `SSH` key on `repository-api` to update the file `repo.config.json` with `git pull`
 4. go to the subfolder of `repository.yeswiki.net` to delete extension's `.zip` files and remove them to be available on the internet
 5.  for each revision of `YesWiki`, go to the concerned subfolder to remove references to the extension in file `package.json`
 6. warn the community on the channel Framateam : https://framateam.org/yeswiki/channels/developpement