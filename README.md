# nrc-universal-messenger
Extension providing a TYPO3 backend module to send newsletters via Universal Messenger API. 


## Installation

### Composer
``composer require netresearch/nrc-universal-messenger``

### GIT
``git clone git@github.com:netresearch/sdk-api-universal-messenger.git``


## Testing
```bash
composer install

composer ci:cgl
composer ci:test
composer ci:test:php:phplint
composer ci:test:php:phpstan
composer ci:test:php:rector
```
