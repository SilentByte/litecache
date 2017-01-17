
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
	./vendor/sami/sami/sami.php update sami.php

check:
	@echo '---- (Running Psalm) --------------------------'
	psalm
	@echo '---- (Running Unit Tests) .--------------------'
	phpunit
