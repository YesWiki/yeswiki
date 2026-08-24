{ pkgs ? import <nixpkgs> { } }:

let
  manifest = builtins.fromJSON (builtins.readFile ../composer.json);

  named = set:
    map (n: builtins.substring 4 (builtins.stringLength n - 4) n)
      (builtins.filter (n: builtins.match "ext-.+" n != null) (builtins.attrNames set));

  alwaysCompiledIn = [ "hash" "json" "pcre" "libxml" "filter" "session" "xml" "ctype" ];

  unavailable = [ "imap" ];

  nixName = n: if n == "zend-opcache" then "opcache" else n;

  wanted = map nixName (builtins.filter
    (n: !(builtins.elem n alwaysCompiledIn) && !(builtins.elem n unavailable))
    (named manifest.require ++ named (manifest.suggest or { })));

  php = (pkgs.php.override {
    embedSupport = true;
    ztsSupport = true;
    zendSignalsSupport = false;
  }).withExtensions ({ enabled, all }:
    enabled ++ map (n: all.${n}) (builtins.filter (n: all ? ${n}) wanted));
in
pkgs.mkShell {
  inputsFrom = [ php.unwrapped ];

  packages = [
    php
    php.unwrapped.dev
    pkgs.xcaddy
    pkgs.go
    pkgs.pkg-config
    pkgs.watcher
    pkgs.brotli
    pkgs.brotli.dev
  ];

  shellHook = ''
    export PHP_INI_SCAN_DIR="${php}/lib"
    echo "php $(php-config --version) (ZTS, embed) — ./binary/build-dynamic.sh"
  '';
}
