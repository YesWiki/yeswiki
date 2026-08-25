# -- Setup ——
COMPOSER      = composer
GIT           = git
YARN          = yarn

## —— Yeswiki Makefile ——
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Project —————————————
install: composer-install yarn-install ## Install vendors

## —— Composer ————————————
composer-install: composer.lock ## Install Composer vendors according to the current composer.lock file
	$(COMPOSER) install

composer-update: composer.json ## Update vendors according to the composer.json file
	$(COMPOSER) update

## —— Yarn —————————————————
yarn-install: yarn.lock ## Install npm vendors according to the current yarn.lock file
	$(YARN) install

## —— Theme CSS ———————————
build-theme: ## Compile themes/yeswiki/scss into themes/yeswiki/styles (commit the result)
	$(YARN) run build-theme

watch-theme: ## Same, but recompile on every save
	$(YARN) run watch-theme

## —— Docker ——————————————
## use the docker/ folder README.md to find commands to launch docker

## —— Tests ———————————————
test: ## Launch PHP unit tests
	bash tests/tests.sh

test-js: ## Launch JS unit tests (Node's built-in runner, no browser)
	$(YARN) run test-js

# Runs against a wiki you already have running -- https://yeswiki.test by default. Override
# with YESWIKI_BASE_URL. Local only: there is no CI budget for browsers.
#
# Scoped to the boosted-navigation suite, which is the one that runs anywhere. The older specs
# under tests/e2e/tests/ assume the docker image (they shell out to /var/www/html/.../reset.sh
# and log in as a fixture admin), so they fail on a developer machine for reasons that say
# nothing about the code -- `test-e2e-all` runs those too when you are in that environment.
test-e2e: ## Browser tests for htmx navigation, against a running wiki
	$(YARN) run test-e2e

test-e2e-all: ## Every browser test, including the ones that require the docker fixture
	$(YARN) run test-e2e-all

## —— Static analysis ————————————————————
analyse: ## Run PHPStan (level 8, errors outside phpstan/baseline.neon fail)
	./vendor/bin/phpstan analyse -c phpstan/phpstan.neon --no-progress

# There is deliberately no `analyse-baseline` target any more. The baseline is empty (ticket 40)
# and PhpstanBaselineRatchetTest fails if anything is added to it, so regenerating it would only
# hide a fresh error behind a suppression nobody reads. Fix the code instead. To work on a few
# files at a time without the rest of the project's reports in the way:
#
#   ./vendor/bin/phpstan analyse -c phpstan/onefile.neon --no-progress src/Some/File.php

wave-counters: ## Report the ectoplasme wave-two progress counters
	php phpstan/wave-counters.php

## —— Linters & Formatters ———————————————
# `lint-*` reports and writes nothing; `fix-*` applies the fixes. Keeping them apart is what
# lets you check a branch without a formatter rewriting files nobody asked you to touch: the
# old `lint` targets ran `eslint --fix .` and `php-cs-fixer fix` across the whole repo, and
# `lint-other` additionally ran the JS fixer a second time.
#
# One tool per question since 2026-08: Prettier decides how every file is *formatted* (JS
# included, which used to be eslint's under airbnb-base), and eslint only judges whether the
# code is *right*. `eslint-config-prettier` sits last in eslint.config.mjs to keep it that way.
lint: lint-php lint-js lint-format ## Check formatting and lint rules (writes nothing)

lint-php: ## Check PHP formatting
	PHP_CS_FIXER_IGNORE_ENV=false ./vendor/bin/php-cs-fixer fix --dry-run --diff
lint-js: ## Check JS lint rules (correctness, not formatting)
	$(YARN) run lint-js
lint-format: ## Check JS/CSS/JSON/MD/YAML formatting
	$(YARN) run lint-format

fix: fix-php fix-js fix-format ## Apply every formatter and auto-fixable lint rule (rewrites files)

fix-php: ## Format PHP
	PHP_CS_FIXER_IGNORE_ENV=false ./vendor/bin/php-cs-fixer fix
fix-js: ## Auto-fix JS lint rules (correctness, not formatting)
	$(YARN) run fix-js
fix-format: ## Format JS/CSS/JSON/MD/YAML
	$(YARN) run fix-format

## —— Binary ——————————————————————————————

binary-local: ## Build against the machine's libphp (minutes; serves a real wiki, not shippable)
	nix-shell binary/dev-shell.nix --run ./binary/build-dynamic.sh

binary: ## Build the shipped static binary (half an hour; needs Docker)
	./binary/build-static.sh

binary-check: ## Assert a built binary carries every extension composer.json names
	./binary/check-binary.sh binary/dist/yeswiki-linux-$(shell uname -m)

binary-smoke: ## Setup, serve, fetch, migrate and upgrade a throwaway wiki with the built binary
	./binary/smoke.sh binary/dist/yeswiki-linux-$(shell uname -m)

test-e2e-binary: ## Run the browser suite against the binary in worker mode, not against php-fpm
	YESWIKI_TEST_RUNTIME=binary bash tests/e2e/reset.sh
	YESWIKI_TEST_RUNTIME=binary bash tests/e2e/runtime.sh start
	YESWIKI_BASE_URL=http://127.0.0.1:8081 yarn run test-e2e-all; \
		status=$$?; bash tests/e2e/runtime.sh stop; exit $$status
