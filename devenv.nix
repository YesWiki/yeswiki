{ pkgs, config, ... }:
{
  packages = [
    pkgs.git
    pkgs.yarn
    pkgs.symfony-cli
    pkgs.php82Packages.composer
  ];

  certificates = [
    "yeswiki.test"
  ];

  hosts."yeswiki.test" = "127.0.0.1";

  # Javascript is used for yarn
  languages.javascript.enable = true;

  # PHP is the main language used to code YesWiki
  languages.php = {
    enable = true;
    version = "8.2";
    ini = ''
      memory_limit = 256M
    '';
    fpm.pools.web = {
      settings = {
        "clear_env" = "no";
        "pm" = "dynamic";
        "pm.max_children" = 10;
        "pm.start_servers" = 2;
        "pm.min_spare_servers" = 1;
        "pm.max_spare_servers" = 10;
      };
    };
  };

  # mail catcher
  services.mailhog.enable = true;

  # Mysql server with initial user and database
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

  # web server
  services.nginx = {
    enable = true;
    httpConfig = ''
    include ${pkgs.nginx}/conf/mime.types;
    default_type application/octet-stream;
    server {
      listen 8000 ssl http2;
      server_name yeswiki.test;
      root ${config.env.DEVENV_ROOT};
      index index.php;
      ssl_certificate ${config.env.DEVENV_STATE}/mkcert/yeswiki.test.pem;
      ssl_certificate_key ${config.env.DEVENV_STATE}/mkcert/yeswiki.test-key.pem;
      ssl_session_timeout 1d;
      ssl_session_cache shared:MozSSL:10m;  # about 40000 sessions
      ssl_session_tickets off;

      # modern configuration
      ssl_protocols TLSv1.3;
      ssl_prefer_server_ciphers off;

      # HSTS (ngx_http_headers_module is required) (63072000 seconds)
      add_header Strict-Transport-Security "max-age=63072000" always;

      access_log ${config.env.DEVENV_DOTFILE}/yeswiki.test-access.log;
      error_log  ${config.env.DEVENV_DOTFILE}/yeswiki.test-error.log error;
      location / {
        try_files $uri $uri/ /index.php$is_args$args;
      }
      location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:${config.languages.php.fpm.pools.web.socket};
        fastcgi_index index.php;
        include ${pkgs.nginx}/conf/fastcgi_params;
        include ${pkgs.nginx}/conf/fastcgi.conf;
      }
      location ~* /(.*/)?private/ {
        deny all;
        return 403;
      }
      location ~ /\.env {
        deny all;
        return 403;
      }
    }
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
  env.BASE_URL = "https://yeswiki.test:8000/?";
  env.TIMEZONE = "Europe/Paris";
  env.ROOT_PAGE = "PagePrincipale";
  env.YESWIKI_NAME = "Local YesWiki";
  env.CONTACT_MAIL_FUNC = "smtp";
  env.CONTACT_SMTP_HOST = "127.0.0.1:1025";
  env.CONTACT_SMTP_USER = "";
  env.CONTACT_SMTP_PASS = "";
  env.DEBUG = true;
  env.GREET = "YesWiki Dev : run `devenv up` to start webserver, database and email services";
  enterShell = ''
    echo $GREET
  '';
}
