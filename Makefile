.PHONY: help setup-php83 setup-php84 setup-php85 shell static-analysis start-containers stop-containers kill-containers composer-install

# List all available Makefile commands.
help:
	@echo "Available commands:"
	@echo "   make help                  : List all available Makefile commands"
	@echo "   make setup-php83           : Start the dev environment with PHP 8.3"
	@echo "   make setup-php84           : Start the dev environment with PHP 8.4"
	@echo "   make setup-php85           : Start the dev environment with PHP 8.5"
	@echo "   make shell                 : Get an interactive shell on the PHP container"
	@echo "   make static-analysis       : Run Static Analysis (PHPStan)"
	@echo "   make start-containers      : Start the dev environment"
	@echo "   make stop-containers       : Stop the dev environment"
	@echo "   make kill-containers       : Stop and remove all containers"
	@echo "   make composer-install      : Install composer dependencies"


# Typing 'make setup-php83' will start the dev environment with PHP 8.3
setup-php83: stop-containers --prep-dockerfile-php83 start-containers --remove-packages composer-install

# Typing 'make setup-php84' will start the dev environment with PHP 8.4
setup-php84: stop-containers --prep-dockerfile-php84 start-containers --remove-packages composer-install

# Typing 'make setup-php85' will start the dev environment with PHP 8.5
setup-php85: stop-containers --prep-dockerfile-php85 start-containers --remove-packages composer-install

# Get a shell on the PHP container
shell:
	docker compose exec -it app /bin/bash

# Run Static Analysis (PHPStan)
static-analysis:
	docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G

# Start the dev environment
start-containers:
	docker compose up -d --build

# Stop the dev environment
stop-containers:
	if [ -f "./docker-compose.yml" ]; then docker compose down; else echo ''; fi

# Stop and remove all containers
kill-containers:
	docker compose kill
	docker compose rm --force

# Install composer dependencies
composer-install:
	docker compose exec app composer install --no-interaction

# Copy Dockerfile for PHP 8.3
--prep-dockerfile-php83: --remove-dockerfile --prep-docker-compose-file
	cp "./.docker/Dockerfile.php83" "./.docker/Dockerfile"

# Copy Dockerfile for PHP 8.4
--prep-dockerfile-php84: --remove-dockerfile --prep-docker-compose-file
	cp "./.docker/Dockerfile.php84" "./.docker/Dockerfile"

# Copy Dockerfile for PHP 8.5
--prep-dockerfile-php85: --remove-dockerfile --prep-docker-compose-file
	cp "./.docker/Dockerfile.php85" "./.docker/Dockerfile"

# Copy docker-compose.yml file
--prep-docker-compose-file:
	[ -f "./docker-compose.yml" ] || cp "./docker-compose.yml.example" "./docker-compose.yml"

# Remove Dockerfile
--remove-dockerfile:
	rm -f ./docker/Dockerfile

# Remove composer related files
--remove-packages: --remove-lockfile --remove-vendor

# Remove composer.lock file
--remove-lockfile:
	docker compose exec app rm -f ./composer.lock

# Remove vendor directory
--remove-vendor:
	docker compose exec app rm -rf ./vendor
