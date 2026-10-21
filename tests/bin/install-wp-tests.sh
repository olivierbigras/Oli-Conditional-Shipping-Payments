#!/usr/bin/env bash

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

download() {
	if [ "$(command -v curl)" ]; then
		curl -sL "$1" > "$2"
	elif [ "$(command -v wget)" ]; then
		wget -nv -O "$2" "$1"
	else
		echo "Need curl or wget"
		exit 1
	fi
}

if [ "$WP_VERSION" = "latest" ]; then
	WP_VERSION=$(curl -fsSL https://api.wordpress.org/core/version-check/1.7/ | python3 -c "import sys,json; print(json.load(sys.stdin)['offers'][0]['version'])")
fi

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
	WP_GIT_REF="$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	WP_TESTS_TAG="tags/$WP_VERSION"
	WP_GIT_REF="$WP_VERSION"
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
	WP_GIT_REF="trunk"
else
	WP_TESTS_TAG="tags/$WP_VERSION"
	WP_GIT_REF="$WP_VERSION"
fi

set -ex

install_wp() {
	if [ -d "$WP_CORE_DIR" ]; then
		return
	fi

	mkdir -p "$WP_CORE_DIR"

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p /tmp/wordpress-nightly
		download https://wordpress.org/nightly-builds/wordpress-latest.zip /tmp/wordpress-nightly/wordpress-nightly.zip
		unzip -q /tmp/wordpress-nightly/wordpress-nightly.zip -d /tmp/wordpress-nightly/
		mv /tmp/wordpress-nightly/wordpress/* "$WP_CORE_DIR"
	else
		local ARCHIVE_NAME="wordpress-$WP_VERSION"
		download "https://wordpress.org/${ARCHIVE_NAME}.tar.gz" /tmp/wordpress.tar.gz
		tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
	fi
}

install_test_suite() {
	if [ -d "$WP_TESTS_DIR/includes" ]; then
		return
	fi

	mkdir -p "$WP_TESTS_DIR"

	if command -v svn >/dev/null 2>&1; then
		svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
		svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
		download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
	else
		rm -rf /tmp/wordpress-develop
		git clone --depth=1 --branch "$WP_GIT_REF" https://github.com/WordPress/wordpress-develop.git /tmp/wordpress-develop
		cp -R /tmp/wordpress-develop/tests/phpunit/includes "$WP_TESTS_DIR/includes"
		cp -R /tmp/wordpress-develop/tests/phpunit/data "$WP_TESTS_DIR/data"
		cp /tmp/wordpress-develop/wp-tests-config-sample.php "$WP_TESTS_DIR/wp-tests-config.php"
	fi

	WP_CORE_DIR=$(echo "$WP_CORE_DIR" | sed 's:/\+$::')
	sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s:dirname( __FILE__ ) . '/public/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s:__DIR__ . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
}

install_db() {
	if [ "$SKIP_DB_CREATE" = "true" ]; then
		return 0
	fi

	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" --protocol=tcp || true
}

install_wp
install_test_suite
install_db
