#!/usr/bin/env bash
#
# Install WordPress and its PHPUnit test library so the integration and unit
# suites can run. Mirrors the environment `composer test` expects.
#
# Usage: tools/ci/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]

set -euo pipefail

DB_NAME=${1:?database name required}
DB_USER=${2:?database user required}
DB_PASS=${3:?database password required}
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}

if [ "$WP_VERSION" = "latest" ]; then
    WP_VERSION=$(curl -sS https://api.wordpress.org/core/version-check/1.7/ \
        | sed -n 's/.*"version":"\([0-9.]*\)".*/\1/p' | head -n1)
    echo "Resolved latest WordPress to ${WP_VERSION}"
fi

install_wp() {
    if [ -d "$WP_CORE_DIR" ]; then
        echo "WordPress already present at ${WP_CORE_DIR}"
        return
    fi
    mkdir -p "$WP_CORE_DIR"
    echo "Downloading WordPress ${WP_VERSION}..."
    curl -sS -o /tmp/wordpress.tar.gz "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
    curl -sS -o "${WP_CORE_DIR}/wp-content/db.php" \
        https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php
}

install_test_suite() {
    if [ -d "${WP_TESTS_DIR}/includes" ]; then
        echo "Test suite already present at ${WP_TESTS_DIR}"
        return
    fi
    mkdir -p "$WP_TESTS_DIR"

    # The test library only exists in the develop repository, not in the release tarball.
    echo "Downloading the WordPress ${WP_VERSION} test library..."
    curl -sS -o /tmp/wp-develop.tar.gz \
        "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${WP_VERSION}.tar.gz"
    mkdir -p /tmp/wp-develop
    tar --strip-components=1 -zxmf /tmp/wp-develop.tar.gz -C /tmp/wp-develop

    cp -r /tmp/wp-develop/tests/phpunit/includes "${WP_TESTS_DIR}/includes"
    cp -r /tmp/wp-develop/tests/phpunit/data "${WP_TESTS_DIR}/data"
    cp /tmp/wp-develop/wp-tests-config-sample.php "${WP_TESTS_DIR}/wp-tests-config.php"

    local config="${WP_TESTS_DIR}/wp-tests-config.php"
    # BSD/GNU sed differ on -i; write through a temp file instead.
    sed \
        -e "s#dirname( __FILE__ ) . '/src/'#'${WP_CORE_DIR}/'#" \
        -e "s/youremptytestdbnamehere/${DB_NAME}/" \
        -e "s/yourusernamehere/${DB_USER}/" \
        -e "s/yourpasswordhere/${DB_PASS}/" \
        -e "s|localhost|${DB_HOST}|" \
        "$config" > "${config}.tmp"
    mv "${config}.tmp" "$config"
}

create_db() {
    echo "Creating database ${DB_NAME} on ${DB_HOST}..."
    mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" \
        --host="${DB_HOST%%:*}" --protocol=tcp 2>/dev/null \
        || echo "Database already exists; continuing."
}

install_wp
install_test_suite
create_db

echo
echo "Done. Export these before running PHPUnit:"
echo "  export WP_TESTS_DIR=${WP_TESTS_DIR}"
echo "  export WP_CORE_DIR=${WP_CORE_DIR}"
