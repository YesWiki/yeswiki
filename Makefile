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
	./vendor/bin/phpunit --do-not-cache-result --stderr tests

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

analyse-baseline: ## Regenerate phpstan/baseline.neon (it may shrink, never grow)
	./vendor/bin/phpstan analyse -c phpstan/phpstan.neon --no-progress --generate-baseline=phpstan/baseline.neon

wave-counters: ## Report the ectoplasme wave-two progress counters
	php phpstan/wave-counters.php

## —— Linters & Formatters ———————————————
# `lint-*` reports and writes nothing; `fix-*` applies the fixes. Keeping them apart is what
# lets you check a branch without a formatter rewriting files nobody asked you to touch: the
# old `lint` targets ran `eslint --fix .` and `php-cs-fixer fix` across the whole repo, and
# `lint-other` additionally ran the JS fixer a second time.
lint: lint-php lint-js lint-other ## Check formatting and lint rules (writes nothing)

lint-php: ## Check PHP formatting
	PHP_CS_FIXER_IGNORE_ENV=false ./vendor/bin/php-cs-fixer fix --dry-run --diff
lint-js: ## Check JS lint rules
	$(YARN) run lint-js
lint-other: ## Check CSS/JSON/MD/YAML formatting
	$(YARN) run lint-other

fix: fix-php fix-js fix-other ## Apply every formatter and auto-fixable lint rule (rewrites files)

fix-php: ## Format PHP
	PHP_CS_FIXER_IGNORE_ENV=false ./vendor/bin/php-cs-fixer fix
fix-js: ## Auto-fix JS lint rules
	$(YARN) run fix-js
fix-other: ## Format CSS/JSON/MD/YAML
	$(YARN) run fix-other
