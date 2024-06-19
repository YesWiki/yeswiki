<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
?>
    
    <form class="form-horizontal form-yeswiki-install" action="<?php echo myLocation(); ?>?PagePrincipale&installAction=install" method="post">

    <div class="row">
    <div class="col-md-4">
      <h3>
        <a class="pull-right btn btn-sm btn-info" data-toggle="collapse" data-parent="#accordion1" href="#collapseOne">
            <?php echo _t('MORE_INFOS'); ?>
        </a>
        <?php echo _t('GENERAL_CONFIGURATION'); ?>
      </h3>

        <div class="accordion" id="accordion1">
          <div class="accordion-group">
            <div id="collapseOne" class="accordion-body collapse">
              <div class="accordion-inner">
                <dl>
                    <dt><?php echo _t('DEFAULT_LANGUAGE'); ?></dt>
                    <dd><?php echo _t('DEFAULT_LANGUAGE_INFOS'); ?></dd>
                  </dl>
                  <dl>
                    <dt><?php echo _t('YOUR_WEBSITE_NAME'); ?></dt>
                    <dd><?php echo _t('YOUR_WEBSITE_NAME_INFOS'); ?></dd>
                  </dl>
                  <dl>
                    <dt><?php echo _t('DESCRIPTION'); ?></dt>
                    <dd><?php echo _t('DESCRIPTION_INFOS'); ?></dd>
                  </dl>
                  <dl>
                    <dt><?php echo _t('KEYWORDS'); ?></dt>
                    <dd><?php echo _t('KEYWORDS_INFOS'); ?></dd>
                  </dl>
                  <dl>
                    <dt><?php echo _t('HOMEPAGE'); ?></dt>
                    <dd><?php echo _t('HOMEPAGE_INFOS'); ?></dd>
                  </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('DEFAULT_LANGUAGE'); ?></label>
          <div class="col-sm-9">
            <select required autocomplete="off" class="form-control" name="config[default_language]" onchange="$(this).parents('.form-yeswiki-install').attr('action', '<?php echo myLocation(); ?>?installAction=default&lang='+$(this).val()).submit();">
                <?php
                foreach ($GLOBALS['available_languages'] as $value) {
                    echo '<option value="' . $value . '"' . (($value == $GLOBALS['prefered_language'] && (!isset($_GET['lang']) || $_GET['lang'] !== 'auto')) ? ' selected="selected"' : '') . '>' . ucfirst(htmlentities($GLOBALS['languages_list'][$value]['nativeName'], ENT_COMPAT | ENT_HTML401, 'UTF-8')) . "</option>\n";
                }
echo '<option value="auto"' . ((isset($_GET['lang']) && $_GET['lang'] === 'auto') ? ' selected="selected"' : '') . '>' . _t('NAVIGATOR_LANGUAGE') . "</option>\n";
?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('YOUR_WEBSITE_NAME'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="config[wakka_name]" value="<?php echo $wakkaConfig['wakka_name']; ?>" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('DESCRIPTION'); ?></label>
          <div class="col-sm-9">
            <input type="text" class="form-control" name="config[meta_description]" value="<?php echo $wakkaConfig['meta_description']; ?>" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('KEYWORDS'); ?></label>
          <div class="col-sm-9">
            <input type="text" class="form-control" name="config[meta_keywords]" value="<?php echo $wakkaConfig['meta_keywords']; ?>" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('HOMEPAGE'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="config[root_page]" value="<?php echo $wakkaConfig['root_page']; ?>" pattern="<?php echo WN_CAMEL_CASE_EVOLVED; ?>"/>
            <p class="help-block"><?php echo _t('MUST_BE_WIKINAME'); ?></p>
          </div>
        </div>
</div>
<div class="col-md-4">
    <h3>
        <a class="pull-right btn btn-sm btn-info" data-toggle="collapse" data-parent="#accordion2" href="#collapseTwo">
            <?php echo _t('MORE_INFOS'); ?>
        </a>
        <?php echo _t('DATABASE_CONFIGURATION'); ?>
    </h3>

        <div class="accordion" id="accordion2">
          <div class="accordion-group">
            <div id="collapseTwo" class="accordion-body collapse">
              <div class="accordion-inner">
                <dl>
                    <dt><?php echo _t('MYSQL_SERVER'); ?> </dt>
                    <dd><?php echo _t('MYSQL_SERVER_INFOS'); ?></dd>
                  </dl>
                  <dl>
                    <dt><?php echo _t('MYSQL_DATABASE'); ?> </dt>
                    <dd><?php echo _t('MYSQL_DATABASE_INFOS'); ?></dd>
                  </dl>
                  <dl>
                    <dt><?php echo _t('MYSQL_USERNAME'); ?> </dt>
                    <dd><?php echo _t('MYSQL_USERNAME_INFOS'); ?></dd>
                  </dl>
                  <dl>
                    <dt><?php echo _t('TABLE_PREFIX'); ?> </dt>
                    <dd><?php echo _t('TABLE_PREFIX_INFOS'); ?></dd>
                  </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('MYSQL_SERVER'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="config[mysql_host]" value="<?php echo $wakkaConfig['mysql_host']; ?>" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('MYSQL_DATABASE'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="config[mysql_database]" value=""/>
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('MYSQL_USERNAME'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="config[mysql_user]" value="" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('MYSQL_PASSWORD'); ?></label>
          <div class="col-sm-9">
            <input type="password"  class="form-control" name="config[mysql_password]" value="" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('TABLE_PREFIX'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="config[table_prefix]" value="<?php echo $wakkaConfig['table_prefix']; ?>" />
          </div>
        </div>
</div>
<div class="col-md-4">
    <h3>
        <a class="pull-right btn btn-sm btn-info" data-toggle="collapse" data-parent="#accordion3" href="#collapseThree">
            <?php echo _t('MORE_INFOS'); ?>
        </a>
        <?php echo _t('CREATION_OF_ADMIN_ACCOUNT'); ?>
    </h3>
        <div class="accordion" id="accordion3">
          <div class="accordion-group">
            <div id="collapseThree" class="accordion-body collapse">
              <div class="accordion-inner">
                <dl>
                  <dt class="admin"><?php echo _t('ADMIN_ACCOUNT_CAN'); ?></dt>
                  <dd class="droits-admin">
                    <ul>
                      <li><?php echo _t('MODIFY_AND_DELETE_ANY_PAGE'); ?></li>
                      <li><?php echo _t('MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE'); ?></li>
                      <li><?php echo _t('GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER'); ?></li>
                      <li><?php echo _t('GENERATE_GROUPS'); ?></li>
                    </ul>
                  </dd>
                </dl>
                <p><?php echo _t('ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE'); ?></p>
              </div>
            </div>
          </div>
        </div>

    <?php
    if ($wiki && $users = $wiki->LoadUsers()) {
        ?>
    <div class="col-sm-9"><p><?php echo _t('USE_AN_EXISTING_ACCOUNT'); ?> :</p><br>
        <select name="admin_login">
        <option selected="selected"><?php echo _t('NO'); ?></option>
    <?php
    foreach ($users as $user) {
        echo '<option value="', htmlspecialchars($user['name'], ENT_COMPAT, YW_CHARSET), '">', htmlspecialchars($user['name'], ENT_COMPAT, YW_CHARSET), "</option>\n";
    } ?>
      </select>
      <p><?php echo _t('OR_CREATE_NEW_ACCOUNT'); ?> :</p></div>
    <?php
    }
?>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('ADMIN'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="admin_name" value="WikiAdmin" />
            <p class="help-block"><?php echo _t('USERNAME_MUST_BE_WIKINAME'); ?></p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('PASSWORD'); ?></label>
          <div class="col-sm-9">
            <input type="password" required class="form-control" name="admin_password" value="" />
            <p class="help-block"><?php echo _t('PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM'); ?></p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('PASSWORD_CONFIRMATION'); ?></label>
          <div class="col-sm-9">
            <input type="password" required class="form-control" size="50" name="admin_password_conf" value="" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('EMAIL_ADDRESS'); ?></label>
          <div class="col-sm-9">
            <input type="email" required class="form-control" name="admin_email" value="" />
          </div>
        </div>
    </div>
</div>
        <div class="accordion-heading">
          <h3>
          <a class="" data-toggle="collapse" data-parent="#accordion4" href="#collapseFour"><?php echo _t('ADVANCED_CONFIGURATION'); ?></a>
          </h3>
        </div>

        <div class="accordion" id="accordion4">
          <div class="accordion-group">
            <div id="collapseFour" class="accordion-body collapse">
              <div class="accordion-inner">
                <div class="form-group">
                    <label class="col-sm-3 control-label"><?php echo _t('BASE_URL'); ?></label>
                    <div class="col-sm-9">
                        <input type="text" required class="form-control" name="config[base_url]" value="<?php echo $wakkaConfig['base_url']; ?>" />
                        <p class="help-block"><?php echo _t('PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION'); ?></p>
                    </div>
                </div>

                <div class="checkbox">
                  <label>
                    <input type="hidden" name="config[rewrite_mode]" value="0" />
                    <input type="checkbox" name="config[rewrite_mode]" value="1" <?php
              echo ($wakkaConfig['rewrite_mode'] ?? true) ? 'checked' : ''; ?> />
                    <span></span>
                    &nbsp;<?php echo _t('ACTIVATE_REDIRECTION_MODE'); ?>
                  </label>
                  <p class="help-block"><?php echo _t('REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI'); ?>.</p>
                </div>

                <div class="checkbox">
                  <label>
                    <input type="checkbox" name="config[allow_raw_html]" value="1" <?php
              echo ($wakkaConfig['allow_raw_html'] ?? true) ? 'checked' : ''; ?> />
                    <span></span>
                    &nbsp;<?php echo _t('AUTHORIZE_HTML_INSERTION'); ?>
                  </label>
                  <p class="help-block"><?php echo _t('HTML_INSERTION_HELP_TEXT'); ?>.</p>
                </div>

                <div class="checkbox">
                  <label>
                    <input type="checkbox" name="config[allow_robots]" value="1" <?php
              echo ($wakkaConfig['allow_robots'] ?? true) ? 'checked' : ''; ?> />
                    <span></span>
                    &nbsp;<?php echo _t('AUTHORIZE_INDEX_BY_ROBOTS'); ?>
                  </label>
                  <p class="help-block"><?php echo _t('INDEX_HELP_TEXT'); ?>.</p>
                </div>

              </div>
            </div>
          </div>
        </div>
    <div class="form-group">
      <div class="col-sm-offset-3 col-sm-9">
        <input class="btn btn-lg btn-primary" type="submit" value="<?php echo _t('CONTINUE'); ?>" />
      </div>
    </div>

    </fieldset>

  </form>
  
  <style>
    input:not(:placeholder-shown):invalid, textarea:not(:placeholder-shown):invalid {
        border-color: #DD2C00;
    }
  </style>
