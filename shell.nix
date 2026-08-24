{ pkgs ? import <nixpkgs> { } }:

let
  manifest = builtins.fromJSON (builtins.readFile ./composer.json);

  named = set:
    map (n: builtins.substring 4 (builtins.stringLength n - 4) n)
      (builtins.filter (n: builtins.match "ext-.+" n != null) (builtins.attrNames set));

  alwaysCompiledIn = [ "hash" "json" "pcre" "libxml" "filter" "session" "xml" "ctype" ];

  unavailable = [ "imap" ];

  nixName = n: if n == "zend-opcache" then "opcache" else n;

  wanted = map nixName (builtins.filter
    (n: !(builtins.elem n alwaysCompiledIn) && !(builtins.elem n unavailable))
    (named manifest.require ++ named (manifest.suggest or { })));

  php = pkgs.php.withExtensions ({ enabled, all }:
    enabled ++ map (n: all.${n}) (builtins.filter (n: all ? ${n}) wanted));
in
pkgs.mkShell {
  packages = [
    php
    php.packages.composer
    pkgs.nodejs_24
    pkgs.yarn
    pkgs.git
    pkgs.gnumake
    pkgs.playwright-driver.browsers
  ];

  shellHook = ''
    export PLAYWRIGHT_BROWSERS_PATH="${pkgs.playwright-driver.browsers}"
    export PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
    echo "yeswiki dev — php $(php --version | head -1 | cut -d' ' -f2), $(composer --version 2>/dev/null | cut -d' ' -f1-3)"
    echo "  make test · make analyse · make lint · make help"
    echo "  binary: nix-shell binary/dev-shell.nix --run ./binary/build-dynamic.sh"
  '';
}
