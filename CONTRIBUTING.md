# Contributing to TYPO3 Monitoring

Thank you for considering contributing to this project! This guide outlines the process for contributing.

## 🚀 Quick Start

1. **Fork** the repository on GitHub
2. **Create** a feature branch: `git checkout -b feature/your-feature-name`
3. **Make** your changes and ensure quality standards
4. **Test** your changes thoroughly
5. **Submit** a pull request

## 📋 Development Workflow

### 1. Set Up Development Environment

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/typo3-monitoring.git
cd typo3-monitoring

# Install dependencies
composer install
```

### 2. Create Feature Branch

```bash
# Create and switch to feature branch
git checkout -b feature/your-feature-name

# Or for bug fixes
git checkout -b bugfix/issue-description
```

### 3. Quality Assurance Commands

**Before submitting any pull request, ensure all quality checks pass:**

#### Testing Commands
```bash
# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run only functional tests
composer test:functional
```

#### Code Quality (CGL) Commands
```bash
# Check code style compliance
composer lint

# Automatically fix code style issues
composer fix
```

#### Static Code Analysis (SCA) Commands
```bash
# Run PHPStan analysis with 4GB memory limit
composer sca:php

# Alternative direct command
./vendor/bin/phpstan analyse -c Tests/CGL/phpstan.neon --memory-limit=4G
```

#### Complete Quality Check
```bash
# Run all quality checks in sequence
composer lint && composer sca:php && composer test
```

### 4. Testing Requirements

- **Unit Tests**: Required for new classes, methods, or core functionality
- **Functional Tests**: Required for features involving caching, providers, or integration behavior

## 🔧 Code Standards

### PHP Standards
- Follow **PSR-12** coding standard
- Use **strict types**: `declare(strict_types=1);`
- Add **PHPDoc blocks** with proper type annotations
- Include **copyright headers** in all new files

### Testing Standards
- Use **PHPUnit 11+** attributes (`#[Test]`, `#[CoversClass]`)
- Add `#[CoversClass(YourClass::class)]` to test classes
- Follow existing test patterns and fixture usage
- Ensure tests are deterministic and isolated

## 📤 Submitting Changes

### 1. Commit Guidelines
Use clear, descriptive commit messages. Follow to [TYPO3's Commit Message Rules](https://docs.typo3.org/m/typo3/guide-contributionworkflow/main/en-us/Appendix/CommitMessage.html).
```bash
git commit -m "[TASK] Add cache expiration handling for providers"
git commit -m "[BUGFIX]  Fix memory leak in monitoring execution"
git commit -m "[DOCS] Update documentation for caching behavior"
```

### 2. Push and Create PR
```bash
# Push your feature branch
git push origin feature/your-feature-name

# Create pull request on GitHub with:
# - Clear title describing the change
# - Description of what was changed and why
# - Reference to any related issues
# - Confirmation that all quality checks pass
```

### 3. Pull Request Checklist

Before submitting, ensure:

- [ ] **All tests pass**: `composer test`
- [ ] **Code style compliant**: `composer lint`
- [ ] **Static analysis clean**: `composer sca:php`
- [ ] **New features have tests** (unit or functional)
- [ ] **Documentation updated** if needed
- [ ] **Commit messages** are clear and descriptive

## 🧪 Testing New Features

### Required Test Coverage

**For New Providers:**
- Unit tests for provider logic
- Functional tests if using caching
- Test fixtures for reusable test objects

**For New Core Features:**
- Unit tests for business logic
- Functional tests for integration behavior
- Edge case and error condition testing

**For Bug Fixes:**
- Regression tests to prevent future occurrences
- Test the specific scenario that was broken

### Test File Locations
- **Unit Tests**: `Tests/Unit/`
- **Functional Tests**: `Tests/Functional/`
- **Test Fixtures**: `Tests/Functional/Fixtures/`

## 🐛 Reporting Issues

When reporting bugs, please include:
- TYPO3 version
- PHP version
- Extension version
- Steps to reproduce
- Expected vs actual behavior
- Any relevant error messages

## 💡 Questions?

- Check existing [Documentation](Documentation/README.md)
- Review [Provider Development Guide](Documentation/providers.md)
- Look at existing code for patterns
- Open an issue for discussion
