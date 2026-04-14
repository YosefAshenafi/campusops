.PHONY: up down logs restart build shell-php shell-mysql db-fresh migrate seed install setup layui auto-cancel

# ===== Docker =====

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

restart:
	docker compose restart

build:
	docker compose build --no-cache

# ===== Shell Access =====

shell-php:
	docker compose exec php bash

# Root password is whatever initialized the mysql_data volume (often root_secret if no .env yet).
shell-mysql:
	docker compose exec mysql mysql -u root -proot_secret campusops

# Drop MySQL volume and recreate containers. Use when DB_* in .env changed after first boot.
db-fresh:
	docker compose down -v
	docker compose up -d
	@echo "When mysql is healthy: make migrate (and make install if vendor is empty)."

# ===== Backend =====

install:
	docker compose exec php composer install

migrate:
	docker compose exec php php think migrate:run

seed:
	docker compose exec php php think seed:run

# ===== Setup (first time) =====

setup: build up install layui
	@echo ""
	@echo "========================================="
	@echo "  CampusOps is ready!"
	@echo "  Open http://localhost:8080 in browser"
	@echo "========================================="

# ===== Scheduled Commands =====

auto-cancel:
	docker compose exec php php think orders:auto-cancel

index-cleanup:
	docker compose exec php php think index:cleanup

# ===== Layui =====

layui:
	@echo "Downloading Layui 2.9.x..."
	docker compose run --rm node sh -c "\
		cd /var/www/frontend && \
		npm init -y > /dev/null 2>&1 && \
		npm install layui@2.9.21 && \
		mkdir -p src/lib/layui && \
		cp -r node_modules/layui/dist/* src/lib/layui/ && \
		rm -rf node_modules package.json package-lock.json"
	@echo "Layui installed to frontend/src/lib/layui/"
