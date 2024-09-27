<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Domain\Repository;

use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * The page repository.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class PageRepository extends Repository
{
    /**
     * @return ConnectionPool
     */
    private function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @return QueryBuilder
     */
    private function getQueryBuilder(): QueryBuilder
    {
        return $this
            ->getConnectionPool()
            ->getQueryBuilderForTable('pages');
    }

    /**
     * Fetch all records of the current page ID. Does not check permissions.
     *
     * @see \TYPO3\CMS\Backend\Controller\PageLayoutController::getExistingPageTranslations
     *
     * @param int                       $pageId
     * @param BackendUserAuthentication $backendUserAuthentication
     *
     * @return array
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getExistingPageTranslations(
        int $pageId,
        BackendUserAuthentication $backendUserAuthentication,
    ): array {
        if ($pageId === 0) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(
                GeneralUtility::makeInstance(
                    WorkspaceRestriction::class,
                    $backendUserAuthentication->workspace
                )
            );

        $result = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    $GLOBALS['TCA']['pages']['ctrl']['transOrigPointerField'],
                    $queryBuilder->createNamedParameter(
                        $pageId,
                        Connection::PARAM_INT
                    )
                )
            )
            ->executeQuery();

        $rows = [];

        while ($row = $result->fetchAssociative()) {
            BackendUtility::workspaceOL(
                'pages',
                $row,
                $backendUserAuthentication->workspace
            );

            if ($row && !VersionState::cast($row['t3ver_state'])->equals(VersionState::DELETE_PLACEHOLDER)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
