# -- Setup ——
COMPOSER      = composer
GIT           = git
YARN          = yarn
DOCKER        = docker-compose
DOCKER_COMPOSE= $$( if [ -f docker-compose.local.yml ]; then \
		echo docker-compose.local.yml; \
	else \
		echo docker-compose.yml; \
	fi )
.DEFAULT_GOAL = help

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
docker-build: ## Build Docker images
	$(DOCKER) -f $(DOCKER_COMPOSE) build --pull

up: ## Start the Docker hub
	$(DOCKER) -f $(DOCKER_COMPOSE) up -d

stop: ## Stop the Docker hub
	$(DOCKER) -f $(DOCKER_COMPOSE) stop

down: ## Down the Docker hub
	$(DOCKER) -f $(DOCKER_COMPOSE) down --remove-orphans

shell: ## Start shell inside Docker
	$(DOCKER) -f $(DOCKER_COMPOSE) exec yeswiki bash

## —— Tests ———————————————
test: ## Launch unit tests
	./vendor/bin/phpunit --strerr tests
