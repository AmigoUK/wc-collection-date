#!/bin/bash

# WordPress Collection Date Plugin Test Runner
# This script runs the complete test suite for the plugin

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print header
echo -e "${BLUE}===================================${NC}"
echo -e "${BLUE}WC Collection Date Plugin Test Suite${NC}"
echo -e "${BLUE}===================================${NC}"
echo

# Check if we're in the plugin directory
if [ ! -f "wc-collection-date.php" ]; then
    echo -e "${RED}Error: This script must be run from the plugin root directory${NC}"
    echo "Please navigate to the plugin directory and run: ./tests/run-tests.sh"
    exit 1
fi

# Set up environment variables
export WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
export WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

# Check if WordPress test environment exists
if [ ! -d "$WP_TESTS_DIR" ]; then
    echo -e "${YELLOW}WordPress test environment not found.${NC}"
    echo "Setting up test environment..."

    # Download and set up WordPress test suite
    if command -v composer &> /dev/null; then
        echo "Using Composer to install WordPress test suite..."
        composer require --dev phpunit/phpunit wordpress/wpunit
    else
        echo -e "${RED}Composer not found. Please install Composer first.${NC}"
        echo "Visit: https://getcomposer.org/"
        exit 1
    fi
fi

# Install WordPress test environment if needed
if [ ! -d "$WP_TESTS_DIR" ] || [ ! -f "$WP_TESTS_DIR/includes/bootstrap.php" ]; then
    echo -e "${YELLOW}Installing WordPress test environment...${NC}"

    # Create directories
    mkdir -p "$WP_TESTS_DIR"
    mkdir -p "$WP_CORE_DIR"

    # Download WordPress
    if command -v wget &> /dev/null; then
        wget -O /tmp/wordpress.tar.gz https://wordpress.org/latest.tar.gz
    elif command -v curl &> /dev/null; then
        curl -o /tmp/wordpress.tar.gz https://wordpress.org/latest.tar.gz
    else
        echo -e "${RED}Neither wget nor curl found. Please install one of them.${NC}"
        exit 1
    fi

    tar -xzf /tmp/wordpress.tar.gz -C /tmp
    mv /tmp/wordpress/* "$WP_CORE_DIR/"

    # Download WordPress test suite
    if command -v svn &> /dev/null; then
        svn co https://develop.svn.wordpress.org/tags/6.4.1/tests/phpunit/includes/ "$WP_TESTS_DIR/includes/"
        svn co https://develop.svn.wordpress.org/tags/6.4.1/tests/phpunit/data/ "$WP_TESTS_DIR/data/"
    else
        echo -e "${RED}Subversion (svn) not found. Please install it to set up WordPress test environment.${NC}"
        exit 1
    fi
fi

# Install dependencies
if [ -f "composer.json" ]; then
    echo -e "${YELLOW}Installing Composer dependencies...${NC}"
    composer install --no-dev --optimize-autoloader

    if [ -f "composer.json" ] && grep -q "phpunit" composer.json; then
        composer install --dev
    fi
fi

# Create test database configuration
TEST_DB_NAME="wc_collection_date_tests"
TEST_DB_USER="${TEST_DB_USER:-root}"
TEST_DB_PASS="${TEST_DB_PASS:-}"
TEST_DB_HOST="${TEST_DB_HOST:-localhost}"

echo -e "${YELLOW}Creating test database...${NC}"
mysql -u"$TEST_DB_USER" -p"$TEST_DB_PASS" -h"$TEST_DB_HOST" -e "DROP DATABASE IF EXISTS $TEST_DB_NAME;" || true
mysql -u"$TEST_DB_USER" -p"$TEST_DB_PASS" -h"$TEST_DB_HOST" -e "CREATE DATABASE $TEST_DB_NAME;"

# Configure WordPress test environment
cat > /tmp/wp-tests-config.php <<EOF
<?php
// Test database settings
define( 'DB_NAME', '$TEST_DB_NAME' );
define( 'DB_USER', '$TEST_DB_USER' );
define( 'DB_PASSWORD', '$TEST_DB_PASS' );
define( 'DB_HOST', '$TEST_DB_HOST' );

// WordPress test environment constants
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

// WordPress directories
define( 'ABSPATH', '$WP_CORE_DIR/' );
define( 'WP_CONTENT_DIR', dirname( dirname( __FILE__ ) ) . '/' );

// Debug settings
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Collection Date debug
define( 'WC_COLLECTION_DATE_DEBUG', true );

// Database table prefix
\$table_prefix = 'wp_';

// Disable HTTP requests during tests
define( 'WP_HTTP_BLOCK_EXTERNAL', true );

// Speed up tests
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );
EOF

# Set environment variables for tests
export WP_TESTS_CONFIG_PATH="/tmp/wp-tests-config.php"

# Run tests
echo -e "${GREEN}Running test suite...${NC}"
echo

# Check if PHPUnit is available
if command -v ./vendor/bin/phpunit &> /dev/null; then
    PHPUNIT_CMD="./vendor/bin/phpunit"
elif command -v phpunit &> /dev/null; then
    PHPUNIT_CMD="phpunit"
else
    echo -e "${RED}PHPUnit not found. Please install it using: composer require --dev phpunit/phpunit${NC}"
    exit 1
fi

# Run PHPUnit with our configuration
echo -e "${BLUE}Running PHPUnit...${NC}"
$PHPUNIT_CMD --configuration phpunit.xml --verbose --stop-on-failure

# Check test results
TEST_EXIT_CODE=$?

echo
echo -e "${BLUE}===================================${NC}"

if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed successfully!${NC}"
    echo -e "${GREEN}Test suite completed successfully.${NC}"
else
    echo -e "${RED}❌ Some tests failed. Exit code: $TEST_EXIT_CODE${NC}"
    echo -e "${RED}Please check the test output above for details.${NC}"
fi

echo -e "${BLUE}===================================${NC}"

# Generate coverage report if requested
if [ "$1" = "--coverage" ]; then
    echo -e "${YELLOW}Generating coverage report...${NC}"
    $PHPUNIT_CMD --configuration phpunit.xml --coverage-html tests/coverage --coverage-clover tests/coverage.xml
    echo -e "${GREEN}Coverage report generated in tests/coverage/${NC}"
fi

# Exit with the same code as PHPUnit
exit $TEST_EXIT_CODE