<!--
SPDX-FileCopyrightText: Netresearch DTT GmbH
SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use
-->
# Security assurance

This document states what the `universal_messenger` TYPO3 extension protects, what it relies on, and where its guarantees end. Every statement refers to the code at the path it names. Vulnerability reports go through [SECURITY.md](../SECURITY.md); the component map is in [ARCHITECTURE.md](ARCHITECTURE.md).

## Security requirements

1. Only a backend user who is permitted for a newsletter channel can send a newsletter page to that channel.
2. A send reaches the Universal Messenger (UM) API only through an explicitly submitted form, never through navigation, a bookmarked URL or a replayed request.
3. The UM API key and secret are not shown in the TYPO3 backend and are not written to the API log.
4. The server-side request that renders a newsletter only fetches pages from the same TYPO3 instance.

## What users can expect

- **Channel authorization.** The backend module lets a user send a page only when the page has the configured newsletter doktype, is not hidden, has a channel assigned, and the submitted channel equals the page's channel and is listed in the `universal_messenger_channels` field of the user or one of the user's groups (`UniversalMessengerController::getChannelAuthorizationFailure()`, `Configuration/TCA/Overrides/be_users.php`, `Configuration/TCA/Overrides/be_groups.php`). The same check runs before the send form is rendered (`indexAction()`) and again on the submitted request before any UM API call (`createAction()`).
- **Module access.** The backend module is registered with `'access' => 'user'` (`Configuration/Backend/Modules.php`), so TYPO3's backend user and group module permissions decide who can open it.
- **Credential handling.** The API URL, key and secret are read from `$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['universal_messenger']` (`Classes/WebserviceConfiguration.php`, README section "API endpoint"). The key and secret are masked in the backend "Configuration" module (`Classes/Backend/EventListener/ModifyBlindedConfigurationOptionsEventListener.php`). When API logging is enabled, the SDK logs requests through `RedactAuthorizationHeaderFormatter`, which replaces the `Authorization` header with `****` (`netresearch/sdk-api-universal-messenger`, wired in `Classes/Service/UniversalMessengerService.php`).
- **Supported versions and reporting.** Security fixes are provided for the latest release (`SECURITY.md`).

## What users cannot expect

- **Protection of the credentials at rest.** The key and secret are stored in plain text in the TYPO3 configuration files of the installation. Protecting those files and the server is the operator's responsibility.
- **Log confidentiality.** With `enableLogging` set (`ext_conf_template.txt`), the SDK writes complete API requests and responses, including the newsletter HTML, to the configured log writer. Only the `Authorization` header is redacted.
- **Sanitising editor content.** Editors are trusted. The mail templates output rich-text and HTML content unescaped (`<f:format.raw>` in `Resources/Private/FluidStyledMailContent/Templates/`). UM control structures entered in the "Control structure" content element are passed to UM unchanged.
- **Recipient data protection.** Recipient lists, personalisation and delivery are handled by the UM server. The extension sends only the rendered HTML, subject, sender and channel identifiers (`createAction()`).
- **Image embedding.** With "Embed images" set to `all`, the UM server downloads images from the site's public URLs; access to them is controlled by the UM server's allow list, not by this extension (README section "Basic").

## Threat model

| Actor | Capability | Relevant assets |
| --- | --- | --- |
| Anonymous website visitor | Sends frontend requests, including to the newsletter preview page type | Page content, server resources |
| Backend editor | Edits pages and content, opens the backend module, submits forms | Newsletter channels the editor is not permitted for |
| Backend administrator / integrator | Configures the extension, TypoScript, site configuration and user permissions | API credentials, all channels |
| UM API | Answers API requests | Channel list, send status |

Threats considered:

1. An editor sends to a channel they are not permitted for, by tampering with the submitted channel UID (GH-139).
2. A live send is triggered without an explicit confirmation, for example by reloading a URL or by TYPO3 replaying a pending request after re-login.
3. A forged `Host` header or a redirect turns the server-side render request into a request to an attacker-chosen target (GH-174).
4. API credentials leak through the backend configuration view or the API log.

## Trust boundaries

1. **Browser to TYPO3 backend.** Authentication, sessions and module permissions are enforced by TYPO3 core. The extension adds the channel authorization check above.
2. **Browser to TYPO3 frontend.** The preview plugin (`Classes/Controller/NewsletterPreviewController.php`) is reachable through a frontend page type and takes a `pageId` argument. It renders the page through a server-side request that carries no cookies or credentials (`NewsletterRenderService::getContentFromUrl()`), so it returns only content TYPO3 serves to an anonymous visitor.
3. **TYPO3 to itself.** `NewsletterRenderService` fetches the rendered newsletter page over HTTP from the same instance. The target URL is built by the site router (`generatePageUri()`, `UniversalMessengerController::getNewsletterUrl()`).
4. **TYPO3 to UM API.** All API traffic goes through `netresearch/sdk-api-universal-messenger` with HTTP basic authentication (`Classes/Service/UniversalMessengerService.php`, `Classes/Repository/`).

## Secure design principles applied

- **Complete mediation.** Authorization is evaluated on every request, in one method shared by the display and the send path (`getChannelAuthorizationFailure()`), so the two cannot drift apart.
- **Fail-safe defaults.** A page without a channel, a hidden page, or a user without an explicit channel grant is rejected. The `Host` re-validation fails closed on any exception (`UriUtility::isRequestHostTrusted()`).
- **Least information.** On the send path every authorization failure returns the same message (`error.accessNotAllowed`), so a crafted request cannot probe which check failed (`createAction()`).
- **Least privilege.** Channel permissions are granted per user or group and are empty until an administrator grants a channel (`universal_messenger_channels` in `Configuration/TCA/Overrides/be_users.php` and `be_groups.php`, marked `exclude => true`).
- **Layering.** Architecture rules enforced by PHPat keep controllers at the outermost layer and the domain model free of other layers (`Tests/Architecture/ArchitectureTest.php`, described in `docs/ARCHITECTURE.md`).

## Countermeasures against common weaknesses

| Weakness | Countermeasure | Evidence |
| --- | --- | --- |
| Broken access control / IDOR (OWASP A01, CWE-639, CWE-862) | Submitted channel must equal the page's channel and be granted to the user | `UniversalMessengerController::getChannelAuthorizationFailure()`; `Tests/Unit/Controller/UniversalMessengerControllerTest.php` (`authorizationFails*`, `createActionRejects*`); `Tests/E2E/tests/gh-139-idor.spec.ts` |
| Unintended state change via GET (CWE-352 class) | `createAction()` refuses every method except POST | `UniversalMessengerController::createAction()`; `doesNotSendTheNewsletterForANonPostRequest` in `Tests/Unit/Controller/UniversalMessengerControllerTest.php` |
| Server-side request forgery (OWASP A10, CWE-918) | Host-less URIs are completed only with a `Host` that passes TYPO3's `trustedHostsPattern`; redirects are not followed | `Classes/Utility/UriUtility.php`; `NewsletterRenderService::getContentFromUrl()` (`allow_redirects => false`); `Tests/Unit/Utility/UriUtilityTest.php`; `Tests/Unit/Service/NewsletterRenderServiceSelfFetchTest.php` |
| Exposure of sensitive information (CWE-200, CWE-532) | Key and secret blinded in the configuration module; `Authorization` header redacted in the API log | `ModifyBlindedConfigurationOptionsEventListener.php`; SDK `RedactAuthorizationHeaderFormatter` |
| Cross-site scripting (OWASP A03, CWE-79) | Fluid escapes variables by default; raw output is limited to editor-supplied rich text and rendered content | `Resources/Private/` templates; see "What users cannot expect" |
| SQL injection (CWE-89) | Database access goes through the Extbase repository and persistence manager; the extension contains no hand-written SQL | `Classes/Domain/Repository/NewsletterChannelRepository.php`, `Classes/Command/ImportCommand.php` |
| Vulnerable dependencies (OWASP A06) | Dependency review, Composer Audit and Renovate updates | `.github/workflows/checks.yml`, `renovate.json` |

## Verification

The checks that run on every pull request are listed in [CONTRIBUTING.md](../CONTRIBUTING.md#governance-and-policies). They include PHPStan level 8 with strict rules (`Build/phpstan.neon`), Opengrep, CodeQL, Betterleaks, Composer Audit, and the unit, acceptance, functional and end-to-end test suites.
