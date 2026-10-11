NIX_DEVELOP ?= nix develop -c

.PHONY: help shell install dev test

help:
	@printf '%s\n' \
	  'make shell   # vao dev shell Nix' \
	  'make install # cai dependency PHP va JavaScript' \
	  'make dev     # chay Laravel dev server' \
	  'make test    # chay PHPUnit tests' \
	  'make storage-up # chay RustFS va tao bucket media public' \
	  'make storage-down # dung RustFS, giu nguyen du lieu'

shell:
	nix develop

install:
	$(NIX_DEVELOP) composer install
	npm install --ignore-scripts

dev:
	$(NIX_DEVELOP) php artisan dev

test:
	$(NIX_DEVELOP) php artisan test --compact

.PHONY: storage-up storage-down

storage-up:
	docker compose up -d rustfs
	docker compose run --rm rustfs-init

storage-down:
	docker compose stop rustfs
