
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
docs: .sami/themes/sami-silentbyte
	./vendor/sami/sami/sami.php update sami.php

.PHONY: check
check:
	@echo '---- (Running Psalm) --------------------------'
	psalm
	@echo '---- (Running Unit Tests) .--------------------'
	phpunit

.sami/themes/sami-silentbyte:
	git clone https://github.com/SilentByte/sami-silentbyte.git .sami/themes/sami-silentbyte

