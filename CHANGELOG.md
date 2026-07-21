# 3.0.1

## FIXES

- 21b66f5 Never send a newsletter from a replayable request (#93) — a send was
  triggered by a plain GET carrying the channel and the send type. Such a URL can
  be bookmarked and reloaded, and TYPO3 replays it after the editor logs in again,
  so clicking *live send* into an expired session and then logging in fired an
  irreversible send without asking. Sending now requires a submitted form.
- 7d0e87b Spell out the live button data attributes individually (#95) — the modal
  configuration was written in the Fluid ViewHelper array form, which a plain HTML
  button does not evaluate, leaving the confirmation dialog without its channel
  specific warning and without the form to submit.
- b712440 Let the controller reject a missing channel instead of Extbase (#98) — a
  request without the channel died with a RequiredArgumentMissingException before
  the guard could answer, because Extbase maps and validates arguments before it
  calls the action and treats an argument without a default value as required.
- 8d8c1cb Correct the basic auth server requirement to UM 7.56.0 (#86)

## MISC

- fa4e875 Skip the docs.typo3.org verification until documentation exists (#91)
- b712440 Cover the argument classification through the real Extbase chain (#98)

## Contributors

- Rico Sonntag

# 3.0.0

## BREAKING

- 3f61e32 Raise the extension to TYPO3 v14 and drop support for v13 (#62)
- 27c049c Adopt SDK v3 HTTP basic authentication (#82) — the Universal Messenger REST API
  no longer accepts the deprecated `umopen`/`open` token. Configure an API key in the
  Universal Messenger backend and fill both the new `apiSecret` setting (secret key)
  and the existing `apiKey` setting (public key). Requires a Universal Messenger
  server 7.56.0 or later.

## FEATURES

- 17587f7 Add a TYPO3 v14 Site Set for site-set-based sites (#67)
- 17add34 Render newsletter content without a full sitepackage (#80)
- b2fa147 Render newsletter content via a package-internal CONTENT path (#74)
- 9ff59aa Complete the dev tooling — Fractor, PHPUnit tests, PHPat (#76)

## FIXES

- 4601d88 Show a clear message when the module TypoScript is missing (#78)
- 0e18cc5 Do not run the request chain twice in InlineCssMiddleware (#70)
- 88f91be Replace the deprecated getRecordLocalization with LocalizationRepository (#69)
- 5f5eedc Replace the deprecated getExistingPageTranslations with LocalizationRepository (#68)
- db2294a Build an absolute newsletter preview URL for relative site bases (#65)
- 089a326 Register the control_structure FlexForm via columnsOverrides (#64)
- 22a7a70 Use the Record API in the preview listener (#63)
- b946f9f Remove the invalid eval=int from the RTE FlexForm field (#66)
- 8b721e2 Drop the deprecated doktypesToShowInNewPageDragArea registration (#75)
- 3c121c5 Replace the removed addUserTSConfig() with the defaultUserTSconfig global (#38)

## MISC

- 7c8163f Use FlexFormTools instead of the deprecated FlexFormService alias (#71)
- 6bc5dc7 Drop auto-managed system columns from ext_tables.sql (#73)
- 4bb69fd Remove the dead classic addService() registration (#72)
- 8d8c1cb Document the server requirement for basic authentication, UM 7.56.0 (#84, #86)
- 5d936ae Adopt the netresearch/.github typo3-extension CI template

## Contributors

- Rico Sonntag

# 2.0.3

## MISC

- 23a41bc Add Github action for publishing to TER, change extension icon

## Contributors

- Sebastian Altenburg

# 2.0.2

## MISC

- 19a4925 Drop deprecated usage of TypoScriptFrontendController
- 059a146 Prevent still open issue-4497 of typo3-rector
- 3ee31f6 Add renovate.json
- 67d1f4a Add suggest for "typo3/cms-scheduler"
- a3835ed Update typo3 phpstan tools

## Contributors

- Rico Sonntag
- renovate[bot]

# 2.0.1

## MISC

- 799d40f Fix phpstan false positive issue

## Contributors

- Rico Sonntag

# 2.0.0

## MISC

- 6df24af Set download URL for HTML body content
- 2976a45 Use SiteInteface when querying attribute "site"
- f2efa40 Use TYPO3 v13.4
- c66064d Add prefix to subject for test newsletters
- 2eafe10 Move viewhelper in right place
- 4d3168e Fix backend layout configuration
- c72190c Update CS
- e66a2d6 Update rector rules
- 0468639 Rework extension, fix phpstan issues
- 69782d2 Update dev tools
- 43da5c9 Update TYPO3 to v13.4
- 6544889 Add NR logo to backend module
- 9444246 Update dev tools

## Contributors

- Rico Sonntag

# 1.0.1

## MISC

- 0854eca LOOE-12: Allow passing curly brackets inside URLs to UM

## Contributors

- Rico Sonntag

# 1.0.0

## MISC

- a91b91c Do not include example newsletter backend layout by default
- 0ec62aa Avoid usage of deprecated CompileWithRenderStatic
- a18c93b Use fixed version of php-cs-fixer for now
- e3f0351 Check hidden after record check
- 479012a Pass additional values to newsletter template
- f3e0e39 Add example newsletter layout/template
- 8a46ca7 Check if selected page is hidden
- 3deaf29 Create dependabot.yml
- f3d06b1 Update README
- 731d0fe Add language switch button
- bec8155 Handle language of selected page
- 1f93dab Apply php-cs-fixer, phpstan and rector rules
- 0171c05 Add typoscript to render fluid styled content elements
- 7316421 Update typoscript configuration loading
- f0ec584 Add preview renderer for content element "control structure"
- 432090e Update default preview template
- f59e7c1 Add viewhelpers to mimic zurb foundation mail structure
- 9e59d59 Move CSS inlining to middleware
- 8547c7a Add content element "control structure"
- d2b0a5b Update README
- bce3099 Update docblock
- 7efcae2 Add missing dependency
- 9ff5177 Update php-cs-fixer pipeline configuration
- ecf0909 Distinguish newsletter rendering between preview and mail
- 64c0fa5 Print a message while doing test sending
- 7e18f95 Add confirm dialog for live sending
- 8bca719 Respect storage PID for newsletter channels
- b335327 Remove API configuration from extension configuration, blind api key in system/configuration vieW
- 2b2d93d Add error message for missing channel configuration in page properties
- a254af6 Add label for channel 0 in pages table
- 7753595 Create newsletter event ID only for LIVE channel
- dcc26b3 Show newsletter status on loading of selected newsletter pageE
- 5d10dfa Update skidUsedIds label in newsletter channel TCA
- 02939e9 Change doktype icon
- 4f0b38d Create a unique newsletter event ID, use skipUsedIds for LIVE channels only
- 14565e6 Add backend user groups access rights for newsletter channels
- cfd1fe1 Remove redundant whitespaces and convert tabs to spaces to improve output in HTML and logger
- 28165c0 Remove obsolete template file
- f4b9ae2 Remove "LIVEVersand" from title
- b55dbdb Fix minor phpstan issue
- 5b9b13f Minimize doktype icon
- 9537050 Update page type registration
- 1f67929 Add missing default value for table field
- 625427c Render newsletter using existing page layouts
- 319c20c Update README
- cbd329e Render newsletter status message
- be528a4 Improve exception handling
- 1b862bb Make channel suffix configurable
- 3351580 Minor code adjustments
- 5157832 Check incomplete site configuration
- 326967d Rename repo
- cf1fb4e Update README
- cc59859 Create LICENSE
- 64e13ad Apply rector/php-cs-fixer rules
- 57ab4d0 Fix phpstan issues
- 531b0ca Remove embedding of images as it is done by the UM itself
- d0097e3 Use package "pelago/emogrifier" to inline CSS
- 64907b7 Send newsletter event to UM
- 8f62570 Render a preview of the newsletter page in backend
- 3f82771 Add access check for backend users
- 78e3c69 Add backend user assignment of newsletter channels allowed to send newsletters for
- 94facfc Add newsletter channel selector to pages
- 5f66d14 Import only the logical newsletter channels, strip _Test and _Live from ID
- d13cb68 Add description column of newsletter channels
- 1dbc34b Add new page type for newsletter pages
- 1a76564 Remove not required values from newsletter channel
- def1282 Add import command
- ffbe9be Initial commit

## Contributors

- Rico Sonntag
- Sebastian Mendel

