[![Latest version](https://img.shields.io/github/v/release/netresearch/universal-messenger?sort=semver)](https://github.com/netresearch/universal-messenger/releases/latest)
[![License](https://img.shields.io/github/license/netresearch/universal-messenger)](https://github.com/netresearch/universal-messenger/blob/main/LICENSE)
[![CI](https://github.com/netresearch/universal-messenger/actions/workflows/ci.yml/badge.svg)](https://github.com/netresearch/universal-messenger/actions/workflows/ci.yml)

# universal-messenger
Extension providing a TYPO3 backend module to send newsletters via Universal Messenger API. 


## Installation

### Composer
``composer require netresearch/universal-messenger``

### GIT
``git clone git@github.com:netresearch/universal-messenger.git``


## Testing
```bash
composer install

composer ci:cgl
composer ci:test
composer ci:test:php:phplint
composer ci:test:php:phpstan
composer ci:test:php:rector
```
