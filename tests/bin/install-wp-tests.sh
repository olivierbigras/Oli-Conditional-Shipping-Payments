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
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	WP_TESTS_TAG="tags/$WP_VERSION"
fi

set -ex

install_wp() {
	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p /tmp/wordpress-nightly
		download https://wordpress.org/nightly-builds/wordpress-latest.zip  /tmp/wordpress-nightly/wordpress-nightly.zip
		unzip -q /tmp/wordpress-nightly/wordpress-nightly.zip -d /tmp/wordpress-nightly/
		mv /tmp/wordpress-nightly/wordpress/* $WP_CORE_DIR
	else
		if [ $WP_VERSION == 'latest' ]; then
			local ARCHIVE_NAME='latest'
		else
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi
		download https://wordpress.org/${ARCHIVE_NAME}.tar.gz  /tmp/wordpress.tar.gz
		tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR
	fi

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

install_test_suite() {
	if [ -d $WP_TESTS_DIR ]; then
		return;
	fi

	mkdir -p $WP_TESTS_DIR
	svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
	svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data

	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		WP_CORE_DIR=$(echo $WP_CORE_DIR | sed 's:/\+$::')
		sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed -i "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi
}

install_db() {
	if [ ${SKIP_DB_CREATE} = "true" ]; then
		return 0
	fi

	EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(awk -F. '{print $1}' <<< "$DB_HOSTNAME") = "127" ]; then
			EXTRA=" --host $DB_HOSTNAME --protocol=tcp --port=$DB_PORT --user=$DB_USER --password=$DB_PASS"
		elif [ -n "$(command -v nc)" ] && [ $(nc -z -w1 "$DB_HOSTNAME" $DB_PORT) ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_PORT --user=$DB_USER --password=$DB_PASS"
		fi
	fi

	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA --host="$DB_HOST" --protocol=tcp
}

install_wp
install_test_suite
install_db
