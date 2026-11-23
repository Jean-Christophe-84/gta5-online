# Executables
EXEC_PHP  = php
DDEV_EXEC = ddev exec
COMPOSER  = composer

# Alias
SYMFONY = $(EXEC_PHP) bin/console
# if you use Docker you can replace with: "docker-compose exec my_php_container $(EXEC_PHP) bin/console"

# Executables: vendors
PHPUNIT = vendor/phpunit/phpunit/phpunit
RECTOR  = vendor/bin/rector

# Executables: local only
SYMFONY_BIN = symfony

# Executables: prod only
CERTBOT = certbot

# Misc
.DEFAULT_GOAL = help
.PHONY        : # Not needed here, but you can put your all your targets to be sure
                # there is no name conflict between your files and your targets.

## —— Harder, Better, Faster, Stronger  ———————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

composer-install: composer.lock ## Install vendors according to the current composer.lock file
	ddev $(COMPOSER) install --no-progress --prefer-dist --optimize-autoloader

composer-update: ## Update dependency for php
	ddev $(COMPOSER) update

## —— Symfony ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands
	ddev $(SYMFONY)

cc: ## Clear the cache. DID YOU CLEAR YOUR CACHE????
	ddev $(SYMFONY) c:c

warmup: ## Warmup the cache
	ddev $(SYMFONY) cache:warmup

purge: ## Purge cache and logs
	rm -rf var/cache/*

migration: ## Doctrine Migration
	ddev $(SYMFONY) doctrine:migration:diff --no-interaction
	ddev $(SYMFONY) doctrine:migration:migrate --no-interaction

messenger: ## launch messenger
	ddev $(SYMFONY) messenger:consume async -vv

## —— Symfony binary  ————————————————————————————————————————————————————————
cert-install: ## Install the local HTTPS certificates
	ddev $(SYMFONY) server:ca:install

serve: ## Serve the application with HTTPS support (add "--no-tls" to disable https)
	ddev $(SYMFONY) serve --daemon --port=$(HTTP_PORT)

unserve: ## Stop the webserver
	ddev $(SYMFONY) server:stop

## —— Project ———————————————————————————————————————————————————————————————
# backup/ is also a directory: without .PHONY, make would consider this target up to date
.PHONY: backup
backup: ## Save the database now in backup/ (latest state + history)
	ddev $(SYMFONY) app:backup

reset: ## Reset data project from backup/gta5_online.sql (FILE=backup/history/… to restore an older backup)
	ddev $(SYMFONY) doctrine:database:drop --force --if-exists
	ddev $(SYMFONY) doctrine:database:create --if-not-exists
	ddev import-db --file=$(or $(FILE),backup/gta5_online.sql)
	$(MAKE) purge

## —— Rector ————————————————————————————————————————————————————————————————
rector-list:
	$(DDEV_EXEC) $(RECTOR) process --dry-run

rector:
	$(DDEV_EXEC) $(RECTOR) process
