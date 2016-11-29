<?php
/*
default.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002 Patrick PAUL
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}
?>
    <div class="jumbotron"><h1><?php echo _t('INSTALLATION_OF_YESWIKI'); ?></h1>

    <?php
    if ($wakkaConfig['yeswiki_version'] || $wakkaConfig['wakka_version'] || $wakkaConfig['wikini_version']) {
        if ($wakkaConfig['yeswiki_version']) {
            $prog = 'YesWiki';
            $config = $wakkaConfig['yeswiki_version'];
        } elseif ($wakkaConfig['wikini_version']) {
            $prog = 'Wikini';
            $config = $wakkaConfig['wikini_version'];
        } else {
            $prog = 'Wikini';
            $config = $wakkaConfig['wakka_version'];
        }
        echo '<div class="alert alert-info">'._t('YOUR_SYSTEM').' '.$prog.' '._t('EXISTENT_SYSTEM_RECOGNISED_AS_VERSION').' ',$config,
            '. '._t('YOU_ARE_UPDATING_YESWIKI_TO_VERSION').' ',YESWIKI_VERSION,
            '. '._t('CHECK_YOUR_CONFIG_INFORMATION_BELOW').".</div>\n";
        $wiki = new Wiki($wakkaConfig);
    } else {
        echo '<h4>('.YESWIKI_VERSION.' - '.YESWIKI_RELEASE.')</h4><p>'._t('FILL_THE_FORM_BELOW')."</p>\n";
        $wiki = null;
    }
    ?>

    </div>
    <form class="form-horizontal form-yeswiki-install" action="<?php echo  myLocation() ?>?installAction=install" method="post">

    <fieldset>

      <legend><?php echo _t('GENERAL_CONFIGURATION'); ?></legend>

        <div class="accordion-heading">
          <a class="plusinfos btn btn-mini btn-info" data-toggle="collapse" data-parent="#accordion1" href="#collapseOne"><?php echo _t('MORE_INFOS'); ?></a>
        </div>

        <div class="accordion" id="accordion1">
          <div class="accordion-group">
            <div id="collapseOne" class="accordion-body collapse">
              <div class="accordion-inner">
                <dl class="dl-horizontal">
                    <dt><?php echo _t('DEFAULT_LANGUAGE'); ?></dt>
                    <dd><?php echo _t('DEFAULT_LANGUAGE_INFOS'); ?></dd>
                  </dl>
                  <dl class="dl-horizontal">
                    <dt><?php echo _t('YOUR_WEBSITE_NAME'); ?></dt>
                    <dd><?php echo _t('YOUR_WEBSITE_NAME_INFOS'); ?></dd>
                  </dl>
                  <dl class="dl-horizontal">
                    <dt><?php echo _t('DESCRIPTION'); ?></dt>
                    <dd><?php echo _t('DESCRIPTION_INFOS'); ?></dd>
                  </dl>
                  <dl class="dl-horizontal">
                    <dt><?php echo _t('KEYWORDS'); ?></dt>
                    <dd><?php echo _t('KEYWORDS_INFOS'); ?></dd>
                  </dl>
                  <dl class="dl-horizontal">
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
            <select required autocomplete="off" class="form-control" name="config[default_language]" onchange="$(this).parents('.form-yeswiki-install').attr('action', '<?php echo  myLocation() ?>?installAction=default&lang='+$(this).val()).submit();">
                <?php
                foreach ($GLOBALS['available_languages'] as $value) {
                    echo '<option value="'.$value.'"'.(($value == $GLOBALS['prefered_language']) ? ' selected="selected"' : '').'>'.ucfirst(htmlentities($GLOBALS['languages_list'][$value]['nativeName'], ENT_COMPAT | ENT_HTML401, 'UTF-8'))."</option>\n";
                }
                ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('YOUR_WEBSITE_NAME'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="config[wakka_name]" value="<?php echo $wakkaConfig['wakka_name'] ?>" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('DESCRIPTION'); ?></label>
          <div class="col-sm-9">
            <input type="text" class="form-control" name="config[meta_description]" value="<?php echo $wakkaConfig['meta_description'] ?>" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('KEYWORDS'); ?></label>
          <div class="col-sm-9">
            <input type="text" class="form-control" name="config[meta_keywords]" value="<?php echo $wakkaConfig['meta_keywords'] ?>" />
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('HOMEPAGE'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="config[root_page]" value="<?php echo $wakkaConfig['root_page'] ?>" /> (<?php echo _t('MUST_BE_WIKINAME'); ?>)
          </div>
        </div>

      <legend><?php echo _t('DATABASE_CONFIGURATION'); ?></legend>

        <div class="accordion-heading">
          <a class="plusinfos btn btn-mini btn-info" data-toggle="collapse" data-parent="#accordion2" href="#collapseTwo"><?php echo _t('MORE_INFOS'); ?></a>
        </div>

        <div class="accordion" id="accordion2">
          <div class="accordion-group">
            <div id="collapseTwo" class="accordion-body collapse">
              <div class="accordion-inner">
                <dl class="dl-horizontal">
                    <dt><?php echo _t('MYSQL_SERVER'); ?> </dt>
                    <dd><?php echo _t('MYSQL_SERVER_INFOS'); ?></dd>
                  </dl>
                  <dl class="dl-horizontal">
                    <dt><?php echo _t('MYSQL_DATABASE'); ?> </dt>
                    <dd><?php echo _t('MYSQL_DATABASE_INFOS'); ?></dd>
                  </dl>
                  <dl class="dl-horizontal">
                    <dt><?php echo _t('MYSQL_USERNAME'); ?> </dt>
                    <dd><?php echo _t('MYSQL_USERNAME_INFOS'); ?></dd>
                  </dl>
                  <dl class="dl-horizontal">
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
            <input type="text" required class="form-control" name="config[mysql_host]" value="<?php echo $wakkaConfig['mysql_host'] ?>" />
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
            <input type="text" required class="form-control" name="config[table_prefix]" value="<?php echo $wakkaConfig['table_prefix'] ?>" />
          </div>
        </div>

      <legend><?php echo _t('CREATION_OF_ADMIN_ACCOUNT'); ?></legend>

        <div class="accordion-heading">
          <a class="plusinfos btn btn-mini btn-info" data-toggle="collapse" data-parent="#accordion3" href="#collapseThree"><?php echo _t('MORE_INFOS'); ?></a>
        </div>

        <div class="accordion" id="accordion3">
          <div class="accordion-group">
            <div id="collapseThree" class="accordion-body collapse">
              <div class="accordion-inner">
                <dl class="dl-horizontal">
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
                <div class="form-group">
                  <p><?php echo _t('ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE'); ?></p>
                </div>
              </div>
            </div>
          </div>
        </div>

    <?php
    if ($wiki && $users = $wiki->LoadUsers()) {
                ?>
    <div class="col-sm-9"><p><?php echo _t('USE_AN_EXISTING_ACCOUNT');
                ?> :</p><br>
        <select name="admin_login">
        <option selected="selected"><?php echo _t('NO');
                ?></option>
    <?php
    foreach ($users as $user) {
          echo '<option value="', htmlspecialchars($user['name'], ENT_COMPAT, YW_CHARSET), '">', htmlspecialchars($user['name'], ENT_COMPAT, YW_CHARSET), "</option>\n";
    }
    ?>
      </select>
      <p><?php echo _t('OR_CREATE_NEW_ACCOUNT');
                ?> :</p></div>
    <?php
    }
        ?>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('ADMIN'); ?></label>
          <div class="col-sm-9">
            <input type="text" required class="form-control" name="admin_name" value="WikiAdmin" /> (<?php echo _t('MUST_BE_WIKINAME'); ?>)
          </div>
        </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"><?php echo _t('PASSWORD'); ?></label>
          <div class="col-sm-9">
            <input type="password" required class="form-control" name="admin_password" value="" /> (<?php echo _t('PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM'); ?>)
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

         <legend><?php echo _t('MORE_OPTIONS'); ?></legend>

        <div class="accordion-heading">
          <a class="plusinfos btn btn-mini btn-info" data-toggle="collapse" data-parent="#accordion4" href="#collapseFour"><?php echo _t('ADVANCED_CONFIGURATION'); ?></a>
        </div>

        <div class="accordion" id="accordion4">
          <div class="accordion-group">
            <div id="collapseFour" class="accordion-body collapse">
              <div class="accordion-inner">

              <p><legend><span><?php echo _t('URL_REDIRECTION'); ?></span></legend></p>
              <?php
              if (!$wakkaConfig['yeswiki_version']) {
                  echo '<p class="alert">'._t('NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING').'.</p>';
              }
              ?>

              <p><?php echo _t('PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION'); ?>.</p>

                <div class="form-group">
                  <label class="col-sm-3 control-label"><?php echo _t('BASE_URL'); ?> </label>
                  <div class="col-sm-9">
                    <input type="text" class="input-xxlarge" name="config[base_url]" value="<?php echo $wakkaConfig['base_url'] ?>" />
                  </div>
                </div>

              <p><?php echo _t('REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI'); ?>.</p>

                <div class="form-group">
                  <label class="checkbox">
                    <input type="hidden" name="config[rewrite_mode]" value="0" />
                    <input type="checkbox" name="config[rewrite_mode]" value="1" <?php echo $wakkaConfig['rewrite_mode'] ? 'checked' : '' ?> >  &nbsp;<?php echo _t('ACTIVATE_REDIRECTION_MODE'); ?>
                  </label>
                </div>

              <p><legend><span><?php echo _t('OTHER_OPTIONS'); ?></span></legend></p>

                <!-- option apercu avant sauvegarde de page -->
                <!--<div class="form-group">
                  <label class="checkbox">
                    <input type="hidden" name="config[preview_before_save]" value="0" />
                    <input type="checkbox" name="config[preview_before_save]" value="1" <?php //echo $wakkaConfig["preview_before_save"] ? "checked" : "" ?> />
                     &nbsp;<?php echo _t('OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE'); ?>
                  </label>
                </div>-->

                <div class="form-group">
                  <label class="checkbox">
                    <input type="checkbox" name="config[allow_raw_html]" value="1" <?php echo $wakkaConfig['allow_raw_html'] ? '' : 'checked' ?> />
                     &nbsp;<?php echo _t('AUTHORIZE_HTML_INSERTION'); ?><br />
                  </label>
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
