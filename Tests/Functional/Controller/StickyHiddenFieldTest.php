<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Locks in the exact Fluid mechanism behind the "TEST button sends LIVE" bug
 * (#136): `f:form.hidden` defaults `respectSubmittedDataValue` to TRUE, so once
 * Extbase has an "original request" on the current request (which it has after
 * ANY ForwardResponse, not only after a validation error), every hidden field
 * silently falls back to whatever value was submitted for an argument of the
 * same name on that original request, discarding the template's own literal
 * `value`. `UniversalMessengerController::createAction()` returns exactly such
 * a ForwardResponse to "index", and both the TEST and the LIVE form share the
 * argument name "send" — so submitting one silently overwrites the other's
 * hidden field the next time the index view is rendered.
 *
 * This is not specific to the production template: it reproduces the Fluid
 * behavior itself against a minimal fixture, so the test fails again if the
 * `respectSubmittedDataValue="false"` fix is ever removed from a hidden field
 * that must never mirror an unrelated previous submission.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class StickyHiddenFieldTest extends FunctionalTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    #[Test]
    public function respectSubmittedDataValueFalseKeepsTheTemplateLiteralAfterAForward(): void
    {
        $renderedHtml = $this->renderFixtureWithOriginalRequestArguments([
            'protectedField' => 'live',
            'stickyField'    => 'live',
        ]);

        self::assertStringContainsString(
            'name="protectedField" value="protected-literal"',
            $renderedHtml,
            'A hidden field with respectSubmittedDataValue="false" must keep its template'
            . ' literal, regardless of what an unrelated previous request submitted for an'
            . ' argument of the same name.',
        );
    }

    #[Test]
    public function respectSubmittedDataValueDefaultTrueReproducesTheStickyValueFootgun(): void
    {
        $renderedHtml = $this->renderFixtureWithOriginalRequestArguments([
            'protectedField' => 'live',
            'stickyField'    => 'live',
        ]);

        self::assertStringContainsString(
            'name="stickyField" value="live"',
            $renderedHtml,
            'This documents the footgun itself: without respectSubmittedDataValue="false",'
            . ' Fluid silently replaces the template literal "sticky-literal" with the'
            . " original request's argument value. If this assertion ever fails, Fluid's"
            . ' default changed and the fix in Index.html may no longer be necessary'
            . ' (or may need to be applied differently).',
        );
    }

    /**
     * Renders the fixture template against a request whose Extbase "original request"
     * carries the given arguments, simulating the state right after
     * `ActionController` processed a `ForwardResponse`.
     *
     * @param array<string, string> $originalRequestArguments
     *
     * @return string
     */
    private function renderFixtureWithOriginalRequestArguments(array $originalRequestArguments): string
    {
        $originalExtbaseParameters = new ExtbaseRequestParameters();
        $originalExtbaseParameters->setArguments($originalRequestArguments);

        $originalRequest = new Request(
            (new ServerRequest())->withAttribute('extbase', $originalExtbaseParameters),
        );

        $currentExtbaseParameters = new ExtbaseRequestParameters();
        $currentExtbaseParameters->setOriginalRequest($originalRequest);

        $currentRequest = new Request(
            (new ServerRequest())
                ->withAttribute('extbase', $currentExtbaseParameters)
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE),
        );

        $view = $this->get(ViewFactoryInterface::class)->create(
            new ViewFactoryData(
                templatePathAndFilename: __DIR__ . '/Fixtures/StickyHiddenFieldTemplate.html',
                request: $currentRequest,
            ),
        );

        return $view->render();
    }
}
