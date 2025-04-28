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

