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

## —— Docker ——————————————
## use the docker/ folder README.md to find commands to launch docker

## —— Tests ———————————————
test: ## Launch unit tests
	./vendor/bin/phpunit --do-not-cache-result --stderr tests

## —— Linters & Formatters ———————————————
lint: lint-php lint-js lint-other ## run all linters and formatters

lint-php: ## Lint php
	PHP_CS_FIXER_IGNORE_ENV=false ./vendor/bin/php-cs-fixer fix
lint-js: ## Lint JS
	yarn run lint-js
lint-other: ## Lint other files
	yarn run lint-js
	yarn run lint-other
