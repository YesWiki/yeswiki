{ pkgs, config, ... }:
{
  packages = [
    pkgs.yarn
    pkgs.symfony-cli
    pkgs.php82Packages.composer
  ];

  # TODO find a way to change this without begin root
  #hosts."yeswiki.test" = "127.0.0.1";

  languages.javascript.enable = true;
  languages.php = {
    enable = true;
    version = "8.2";
    ini = ''
      memory_limit = 256M
    '';
  };

  services.mailhog.enable = true;
  services.mysql = {
    enable = true;
    package = pkgs.mariadb;
    initialDatabases = [
      { name = "yeswikidb"; }
    ];
    ensureUsers = [
      {
        name = "yeswikidbuser";
        password = "secret";
        ensurePermissions = {
          "yeswikidb.*" = "ALL PRIVILEGES";
        };
      }
    ];
  };

  processes = {
    run-symfony-server.exec = ''
      if [[ ! -d vendor ]]; then
          composer install
      fi
      
      if [[ ! -d node_modules ]]; then
          yarn
      fi

      echo "yeswiki url : https://127.0.0.1:8000/

mailhog url : http://127.0.0.1:8025/
mailhog smtp server : 127.0.0.1:1025 (empty username and empty password)"
      symfony server:start -d --port=8000
    '';
  };

  #TODO : make nix aware of .env file, for now it makes an error "infinite recursion encountered" for now `env.YESWIKI_NAME = config.env.YESWIKI_NAME or "Local YesWiki";` isn't working
  # dotenv.enable = true; 
  dotenv.disableHint = true; 

  # Important YesWiki environment configuration keys
  env.MYSQL_HOST = "localhost";
  env.MYSQL_DATABASE = "yeswikidb";
  env.MYSQL_USER = "yeswikidbuser";
  env.MYSQL_PASSWORD = "secret";
  env.TABLE_PREFIX = "yeswiki_";
  env.BASE_URL = "https://127.0.0.1:8000/?";
  env.TIMEZONE = "Europe/Paris";
  env.ROOT_PAGE = "PagePrincipale";
  env.YESWIKI_NAME = "Local YesWiki";
  env.CONTACT_MAIL_FUNC = "smtp";
  env.CONTACT_SMTP_HOST = "127.0.0.1:1025";
  env.CONTACT_SMTP_USER = "";
  env.CONTACT_SMTP_PASS = "";
  env.GREET = "YesWiki Dev : run `devenv up` to start webserver, database and email services";
  enterShell = ''
    echo $GREET
  '';
}