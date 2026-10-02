NIX_DEVELOP ?= nix develop -c

.PHONY: help shell install dev test

help:
	@printf '%s\n' \
	  'make shell   # vao dev shell Nix' \
	  'make install # cai dependency PHP va JavaScript' \
	  'make dev     # chay Laravel dev server' \
	  'make test    # chay PHPUnit tests'

shell:
	nix develop

install:
	$(NIX_DEVELOP) composer install
	npm install --ignore-scripts

dev:
	$(NIX_DEVELOP) php artisan dev

test:
	$(NIX_DEVELOP) php artisan test --compact
