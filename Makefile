# Docker Environment Management
.PHONY: help local dev staging prod test build stop clean logs install

# Default target
.DEFAULT_GOAL := help

# Colors
CYAN := \033[36m
GREEN := \033[32m
YELLOW := \033[33m
NC := \033[0m

help: ## Show this help message
	@echo "$(CYAN)Docker Environment Management$(NC)"
	@echo ""
	@echo "$(GREEN)Available commands:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(CYAN)%-15s$(NC) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Examples:$(NC)"
	@echo "  make local          - Start local development"
	@echo "  make test           - Run full test suite"
	@echo "  make logs service=app - Show logs for app service"

install: ## Install and setup the project
	@echo "$(GREEN)Setting up the project...$(NC)"
	@./scripts/docker-setup.sh local --fresh --build

local: ## Start local development environment
	@echo "$(GREEN)Starting local development environment...$(NC)"
	@./scripts/docker-setup.sh local

local-fresh: ## Start local development with fresh database
	@echo "$(GREEN)Starting local development with fresh database...$(NC)"
	@./scripts/docker-setup.sh local --fresh

dev: ## Deploy to development environment
	@echo "$(GREEN)Deploying to development environment...$(NC)"
	@./scripts/docker-setup.sh dev

staging: ## Deploy to staging environment
	@echo "$(GREEN)Deploying to staging environment...$(NC)"
	@./scripts/docker-setup.sh staging

prod: ## Deploy to production environment
	@echo "$(GREEN)Deploying to production environment...$(NC)"
	@./scripts/docker-setup.sh prod

prod-build: ## Build and deploy to production environment
	@echo "$(GREEN)Building and deploying to production environment...$(NC)"
	@./scripts/docker-setup.sh prod --build

test: ## Run complete test suite
	@echo "$(GREEN)Running test suite...$(NC)"
	@./scripts/docker-setup.sh test

test-unit: ## Run unit tests only
	@echo "$(GREEN)Running unit tests...$(NC)"
	@docker-compose -f docker-compose.test.yml --profile unit-tests up --exit-code-from pest-unit

test-feature: ## Run feature tests only
	@echo "$(GREEN)Running feature tests...$(NC)"
	@docker-compose -f docker-compose.test.yml --profile feature-tests up --exit-code-from pest-feature

test-browser: ## Run browser tests
	@echo "$(GREEN)Running browser tests...$(NC)"
	@docker-compose -f docker-compose.test.yml --profile browser-tests up --exit-code-from app-browser-test

lint: ## Run code quality checks
	@echo "$(GREEN)Running code quality checks...$(NC)"
	@docker-compose -f docker-compose.test.yml --profile code-quality up --exit-code-from pint
	@docker-compose -f docker-compose.test.yml --profile code-quality up --exit-code-from eslint
	@docker-compose -f docker-compose.test.yml --profile code-quality up --exit-code-from typescript

build: ## Build all Docker images
	@echo "$(GREEN)Building Docker images...$(NC)"
	@./scripts/docker-setup.sh build

build-local: ## Build images and start local environment
	@echo "$(GREEN)Building and starting local environment...$(NC)"
	@./scripts/docker-setup.sh local --build

stop: ## Stop all services
	@echo "$(GREEN)Stopping all services...$(NC)"
	@./scripts/docker-setup.sh stop

clean: ## Clean up containers, volumes, and images
	@echo "$(YELLOW)Cleaning up Docker resources...$(NC)"
	@./scripts/docker-setup.sh clean

logs: ## Show logs for services (use service=<name> for specific service)
ifdef service
	@docker-compose logs -f $(service)
else
	@docker-compose logs -f
endif

shell: ## Open shell in app container
	@docker-compose exec app sh

shell-db: ## Open PostgreSQL shell
	@docker-compose exec postgres psql -U laravel -d baselaravel12react

migrate: ## Run database migrations
	@docker-compose exec app php artisan migrate

migrate-fresh: ## Fresh migration with seeding
	@docker-compose exec app php artisan migrate:fresh --seed

artisan: ## Run artisan commands (use cmd="<command>" for specific command)
ifdef cmd
	@docker-compose exec app php artisan $(cmd)
else
	@echo "$(YELLOW)Usage: make artisan cmd=\"<artisan-command>\"$(NC)"
	@echo "Example: make artisan cmd=\"make:model User\""
endif

npm: ## Run npm commands (use cmd="<command>" for specific command)
ifdef cmd
	@docker-compose exec app npm $(cmd)
else
	@echo "$(YELLOW)Usage: make npm cmd=\"<npm-command>\"$(NC)"
	@echo "Example: make npm cmd=\"run dev\""
endif

backup-db: ## Backup database (staging environment)
	@docker-compose -f docker-compose.staging.yml --profile backup up backup

status: ## Show status of all services
	@echo "$(GREEN)Local Status:$(NC)"
	@docker-compose ps
	@echo ""
	@echo "$(GREEN)Development Status:$(NC)"
	@docker-compose -f docker-compose.dev.yml ps
	@echo ""
	@echo "$(GREEN)Staging Status:$(NC)"
	@docker-compose -f docker-compose.staging.yml ps
	@echo ""
	@echo "$(GREEN)Production Status:$(NC)"
	@docker stack services laravel-app-prod 2>/dev/null || echo "Production stack not running"