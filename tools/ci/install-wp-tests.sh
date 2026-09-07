#!/usr/bin/env bash
#
# Install WordPress and its PHPUnit test library so the test suites can run.
# Every suite extends WP_UnitTestCase, so this is a prerequisite for all of them.
#
# Usage: tools/ci/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
# Example:
#   tools/ci/install-wp-tests.sh wordpress_test root root 127.0.0.1 6.7

set -euo pipefail

DB_NAME=${1:?database name required}
DB_USER=${2:?database user required}
DB_PASS=${3:?database password required}
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}

if [ "$WP_VERSION" = "latest" ]; then
    WP_VERSION=$(curl -fsS https://api.wordpress.org/core/stable-check/1.0/ \
        | tr ',' '\n' | grep '"latest"' | cut -d'"' -f2 | head -n1)
    if [ -z "$WP_VERSION" ]; then
        echo "Could not resolve the latest WordPress version." >&2
        exit 1
    fi
    echo "Resolved latest WordPress to ${WP_VERSION}"
fi

# The release tarball is named for the two-part version (wordpress-6.7.tar.gz),
# but wordpress-develop tags are always three-part (6.7.0). Derive both.
DEVELOP_TAG="$WP_VERSION"
if [[ "$DEVELOP_TAG" =~ ^[0-9]+\.[0-9]+$ ]]; then
    DEVELOP_TAG="${DEVELOP_TAG}.0"
fi

install_wp() {
    if [ -d "${WP_CORE_DIR}/wp-includes" ]; then
        echo "WordPress already present at ${WP_CORE_DIR}"
        return
    fi

    echo "Downloading WordPress ${WP_VERSION}..."
    mkdir -p "$WP_CORE_DIR"
    curl -fsSL -o /tmp/wordpress.tar.gz "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
}

install_test_suite() {
    if [ -d "${WP_TESTS_DIR}/includes" ]; then
        echo "Test suite already present at ${WP_TESTS_DIR}"
        return
    fi

    # The PHPUnit test library ships only in the develop repository, never in
    # the release tarball.
    echo "Downloading the WordPress ${DEVELOP_TAG} test library..."
    mkdir -p "$WP_TESTS_DIR" /tmp/wp-develop
    curl -fsSL -o /tmp/wp-develop.tar.gz \
        "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${DEVELOP_TAG}.tar.gz"
    tar --strip-components=1 -zxmf /tmp/wp-develop.tar.gz -C /tmp/wp-develop

    cp -r /tmp/wp-develop/tests/phpunit/includes "${WP_TESTS_DIR}/includes"
    cp -r /tmp/wp-develop/tests/phpunit/data "${WP_TESTS_DIR}/data"
    cp /tmp/wp-develop/wp-tests-config-sample.php "${WP_TESTS_DIR}/wp-tests-config.php"

    local config="${WP_TESTS_DIR}/wp-tests-config.php"
    # GNU and BSD sed disagree about -i, so write through a temporary file.
    sed \
        -e "s#dirname( __FILE__ ) . '/src/'#'${WP_CORE_DIR}/'#" \
        -e "s/youremptytestdbnamehere/${DB_NAME}/" \
        -e "s/yourusernamehere/${DB_USER}/" \
        -e "s/yourpasswordhere/${DB_PASS}/" \
        -e "s/'localhost'/'${DB_HOST}'/" \
        "$config" > "${config}.tmp"
    mv "${config}.tmp" "$config"
}

create_db() {
    echo "Creating database ${DB_NAME} on ${DB_HOST}..."
    if mysqladmin create "$DB_NAME" \
        --user="$DB_USER" --password="$DB_PASS" \
        --host="${DB_HOST%%:*}" --protocol=tcp 2>/dev/null; then
        echo "Database created."
    else
        echo "Database already exists (or could not be created); continuing."
    fi
}

install_wp
install_test_suite
create_db

echo
echo "Ready. PHPUnit expects:"
echo "  WP_TESTS_DIR=${WP_TESTS_DIR}"
echo "  WP_CORE_DIR=${WP_CORE_DIR}"
