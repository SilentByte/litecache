
.PHONY: all
all:
	@echo 'LiteCache Makefile'
	@echo '  make clean        Delete docs and cache files.'
	@echo '  make docs         Generate PHPDocs documentation.'

.PHONY: clear
clean:
	rm -rf docs/
	rm -rf .litecache/*.php
	rm -rf examples/.litecache/*.php

.PHONY: docs
docs:
	phpdoc

check:
	@echo '---- (Checking unit tests) --------------------'
	phpunit
