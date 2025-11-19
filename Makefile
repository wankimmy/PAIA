.PHONY: help up down build restart logs migrate fresh seed install-backend install-frontend

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

up: ## Start all containers
	docker-compose up -d

down: ## Stop all containers
	docker-compose down

build: ## Build all containers
	docker-compose build

restart: ## Restart all containers
	docker-compose restart

logs: ## Show logs from all containers
	docker-compose logs -f

logs-backend: ## Show backend logs
	docker-compose logs -f backend

logs-frontend: ## Show frontend logs
	docker-compose logs -f frontend

migrate: ## Run database migrations
	docker-compose exec backend php artisan migrate

fresh: ## Fresh migration (drop all tables and re-run)
	docker-compose exec backend php artisan migrate:fresh

seed: ## Run database seeders
	docker-compose exec backend php artisan db:seed

install-backend: ## Install backend dependencies
	docker-compose exec backend composer install

install-frontend: ## Install frontend dependencies
	docker-compose exec frontend npm install

build-frontend: ## Build frontend for production
	docker-compose exec frontend npm run build

key-generate: ## Generate Laravel application key
	docker-compose exec backend php artisan key:generate

tinker: ## Open Laravel Tinker
	docker-compose exec backend php artisan tinker

cache-clear: ## Clear Laravel cache
	docker-compose exec backend php artisan cache:clear
	docker-compose exec backend php artisan config:clear
	docker-compose exec backend php artisan route:clear
	docker-compose exec backend php artisan view:clear

test: ## Run backend tests
	docker-compose exec backend php artisan test

schedule: ## Run scheduled tasks (for testing)
	docker-compose exec backend php artisan schedule:run

reminders: ## Manually trigger reminder sending
	docker-compose exec backend php artisan reminders:send

setup: ## Initial setup (install deps, generate key, migrate)
	make install-backend
	make install-frontend
	make key-generate
	make migrate

