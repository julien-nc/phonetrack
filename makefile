app_name=phonetrack
app_version=$(version)
project_dir=$(CURDIR)/../$(app_name)
build_dir=/tmp/build
sign_dir=/tmp/sign
build_dir_own=/tmp/build_own
sign_dir_own=/tmp/sign_own
cert_dir=$(HOME)/.nextcloud/certificates
cert_dir_own=$(HOME)/.owncloud/certificates
webserveruser ?= www-data
occ_dir ?= /var/www/html/n12
occ_dir_own ?= /var/www/html/owncloud

all: appstore

clean:
	rm -rf $(build_dir)
	rm -rf $(build_dir_own)
	rm -rf $(sign_dir)
	rm -rf $(sign_dir_own)

appstore: clean
	mkdir -p $(sign_dir)
	mkdir -p $(sign_dir)_own
	mkdir -p $(build_dir)
	mkdir -p $(build_dir)_own
	rsync -a \
	--exclude=.git \
	--exclude=appinfo/signature.json \
	--exclude=*.swp \
	--exclude=build \
	--exclude=README.md \
	--exclude=.gitignore \
	--exclude=.travis.yml \
	--exclude=.scrutinizer.yml \
	--exclude=CONTRIBUTING.md \
	--exclude=composer.json \
	--exclude=composer.lock \
	--exclude=composer.phar \
	--exclude=crowdin.yml \
	--exclude=tools \
	--exclude=l10n/.tx \
	--exclude=l10n/l10n.pl \
	--exclude=l10n/templates \
	--exclude=l10n/*.sh \
	--exclude=l10n/[a-z][a-z] \
	--exclude=l10n/[a-z][a-z]_[A-Z][A-Z] \
	--exclude=l10n/no-php \
	--exclude=makefile \
	--exclude=screenshots \
	--exclude=phpunit*xml \
	--exclude=tests \
	--exclude=vendor/bin \
	$(project_dir) $(sign_dir)
	cp -r $(sign_dir)/$(app_name) $(sign_dir_own)/
	# adapt info.xml
	sed -i '/[^<][oO]wn[cC]loud[^>]/d' $(sign_dir)/$(app_name)/appinfo/info.xml
	sed -i '/[nN]extcloud/d' $(sign_dir_own)/$(app_name)/appinfo/info.xml
	# give the webserver user the right to create signature file
	sudo chown $(webserveruser) $(sign_dir)/$(app_name)/appinfo $(sign_dir_own)/$(app_name)/appinfo
	sudo -u $(webserveruser) php $(occ_dir)/occ integrity:sign-app --privateKey=$(cert_dir)/$(app_name).key --certificate=$(cert_dir)/$(app_name).crt --path=$(sign_dir)/$(app_name)/
	sudo -u $(webserveruser) php $(occ_dir_own)/occ integrity:sign-app --privateKey=$(cert_dir_own)/$(app_name).key --certificate=$(cert_dir_own)/$(app_name).crt --path=$(sign_dir_own)/$(app_name)/
	sudo chown -R $(USER) $(sign_dir)/$(app_name)/appinfo $(sign_dir_own)/$(app_name)/appinfo
	tar -czf $(build_dir)/$(app_name)-$(app_version).tar.gz \
		-C $(sign_dir) $(app_name)
	tar -czf $(build_dir_own)/$(app_name)-$(app_version).tar.gz \
		-C $(sign_dir_own) $(app_name)
	echo NEXTCLOUD------------------------------------------
	openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(app_version).tar.gz | openssl base64
	echo OWNCLOUD-------------------------------------------
	openssl dgst -sha512 -sign $(cert_dir_own)/$(app_name).key $(build_dir_own)/$(app_name)-$(app_version).tar.gz | openssl base64
