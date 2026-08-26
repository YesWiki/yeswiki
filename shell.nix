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
    pkgs.playwright-driver
    pkgs.playwright-driver.browsers
  ];

  shellHook = ''
    export PLAYWRIGHT_BROWSERS_PATH="${pkgs.playwright-driver.browsers}"
    export PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
    # `npx playwright install` cannot work here: it downloads browsers linked against an FHS
    # that NixOS does not have. Nix supplies them instead, which is why the two versions below
    # have to agree -- Playwright looks for a browser build number belonging to its own release,
    # and a mismatch reads as "Executable doesn't exist at .../chromium_headless_shell-<n>".
    export PLAYWRIGHT_SKIP_VALIDATE_HOST_REQUIREMENTS=true

    ywPlaywrightNix="${pkgs.playwright-driver.version}"
    ywPlaywrightNpm="$(node -p "require('@playwright/test/package.json').version" 2>/dev/null || echo none)"
    if [ "$ywPlaywrightNpm" != none ] && [ "$ywPlaywrightNpm" != "$ywPlaywrightNix" ]; then
      echo "playwright mismatch: package.json has $ywPlaywrightNpm, nix browsers are $ywPlaywrightNix"
      echo "  pin @playwright/test to $ywPlaywrightNix in package.json, or the browsers will not be found"
    fi

    echo "yeswiki dev — php $(php --version | head -1 | cut -d' ' -f2), $(composer --version 2>/dev/null | cut -d' ' -f1-3)"
    echo "  playwright $ywPlaywrightNix with its browsers -- no 'npx playwright install' needed"
    echo "  make test · make analyse · make lint · make help"
    echo "  binary: nix-shell binary/dev-shell.nix --run ./binary/build-dynamic.sh"
  '';
}
