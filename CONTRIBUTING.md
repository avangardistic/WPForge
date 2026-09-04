# Contributing to WPForge

## Development Setup

```bash
git clone https://github.com/wpforge/wpforge.git
cd wpforge
composer install
```

## Running Tests

```bash
# Set WP_TESTS_DIR to your WordPress test installation
export WP_TESTS_DIR=/path/to/wordpress-tests-lib

composer test
composer test:unit
composer test:security
```

## Code Standards

- Follow WordPress Coding Standards (WPCS)
- Use PSR-12 for new classes
- All public methods must have PHPDoc blocks
- No TODO or FIXME in production code
- All mutations must be logged

## Pull Request Process

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Update documentation
6. Submit a pull request

## Security

If you discover a security vulnerability, please report it responsibly via GitHub Issues (private) or email.
