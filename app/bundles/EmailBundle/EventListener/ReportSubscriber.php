<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Helper\Chart\PieChart;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\Event\ReportGraphEvent;
use Mautic\ReportBundle\ReportEvents;

/**
 * Class ReportSubscriber.
 */
class ReportSubscriber extends CommonSubscriber
{
    const CONTEXT_EMAILS      = 'emails';
    const CONTEXT_EMAIL_STATS = 'email.stats';

    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var CompanyReportData
     */
    private $companyReportData;

    /**
     * @var bool Property is used to avoid Joining DNC table more times
     */
    private $dncWasAddedToQb = false;

    /**
     * ReportSubscriber constructor.
     *
     * @param Connection        $db
     * @param CompanyReportData $companyReportData
     */
    public function __construct(Connection $db, CompanyReportData $companyReportData)
    {
        $this->db                = $db;
        $this->companyReportData = $companyReportData;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            ReportEvents::REPORT_ON_BUILD          => ['onReportBuilder', 0],
            ReportEvents::REPORT_ON_GENERATE       => ['onReportGenerate', 0],
            ReportEvents::REPORT_ON_GRAPH_GENERATE => ['onReportGraphGenerate', 0],
        ];
    }

    /**
     * Add available tables and columns to the report builder lookup.
     *
     * @param ReportBuilderEvent $event
     */
    public function onReportBuilder(ReportBuilderEvent $event)
    {
        if (!$event->checkContext([self::CONTEXT_EMAILS, self::CONTEXT_EMAIL_STATS])) {
            return;
        }

        $prefix               = 'e.';
        $variantParent        = 'vp.';
        $channelUrlTrackables = 'cut.';
        $doNotContact         = 'dnc.';
        $columns              = [
            $prefix.'subject' => [
                'label' => 'le.email.subject',
                'type'  => 'string',
            ],
            $prefix.'email_type' => [
                'label' => 'le.email.send.emailtype',
                'type'  => 'string',
            ],
            $prefix.'lang' => [
                'label' => 'mautic.core.language',
                'type'  => 'string',
            ],
            $prefix.'read_count' => [
                'label' => 'le.email.report.read_count',
                'type'  => 'int',
            ],
            'read_ratio' => [
                'alias'   => 'read_ratio',
                'label'   => 'le.email.report.read_ratio',
                'type'    => 'string',
                'formula' => 'CONCAT(ROUND(('.$prefix.'read_count/'.$prefix.'sent_count)*100, 1),\'%\')',
            ],
            $prefix.'sent_count' => [
                'label' => 'le.email.report.sent_count',
                'type'  => 'int',
            ],
            'hits' => [
                'alias'   => 'hits',
                'label'   => 'le.email.report.hits_count',
                'type'    => 'string',
                'formula' => $channelUrlTrackables.'hits',
            ],
            'unique_hits' => [
                'alias'   => 'unique_hits',
                'label'   => 'le.email.report.unique_hits_count',
                'type'    => 'string',
                'formula' => $channelUrlTrackables.'unique_hits',
            ],
            'hits_ratio' => [
                'alias'   => 'hits_ratio',
                'label'   => 'le.email.report.hits_ratio',
                'type'    => 'string',
                'formula' => 'CONCAT(ROUND('.$channelUrlTrackables.'hits/('.$prefix.'sent_count)*100, 1),\'%\')',
            ],
            'unique_ratio' => [
                'alias'   => 'unique_ratio',
                'label'   => 'le.email.report.unique_ratio',
                'type'    => 'string',
                'formula' => 'CONCAT(ROUND('.$channelUrlTrackables.'unique_hits/('.$prefix.'sent_count)*100, 1),\'%\')',
            ],
            'unsubscribed' => [
                'alias'   => 'unsubscribed',
                'label'   => 'le.email.report.unsubscribed',
                'type'    => 'string',
                'formula' => 'SUM(IF('.$doNotContact.'id IS NOT NULL AND dnc.reason='.DoNotContact::UNSUBSCRIBED.' , 1, 0))',
            ],
            'unsubscribed_ratio' => [
                'alias'   => 'unsubscribed_ratio',
                'label'   => 'le.email.report.unsubscribed_ratio',
                'type'    => 'string',
                'formula' => 'CONCAT(ROUND((SUM(IF('.$doNotContact.'id IS NOT NULL AND dnc.reason='.DoNotContact::UNSUBSCRIBED.' , 1, 0))/'.$prefix.'sent_count)*100, 1),\'%\')',
            ],
            'bounced' => [
                'alias'   => 'bounced',
                'label'   => 'le.email.report.bounced',
                'type'    => 'string',
                'formula' => 'SUM(IF('.$doNotContact.'id IS NOT NULL AND dnc.reason='.DoNotContact::BOUNCED.' , 1, 0))',
            ],
            'bounced_ratio' => [
                'alias'   => 'bounced_ratio',
                'label'   => 'le.email.report.bounced_ratio',
                'type'    => 'string',
                'formula' => 'CONCAT(ROUND((SUM(IF('.$doNotContact.'id IS NOT NULL AND dnc.reason='.DoNotContact::BOUNCED.' , 1, 0))/'.$prefix.'sent_count)*100, 1),\'%\')',
            ],
            $prefix.'revision' => [
                'label' => 'le.email.report.revision',
                'type'  => 'int',
            ],
            $variantParent.'id' => [
                'label' => 'le.email.report.variant_parent_id',
                'type'  => 'int',
            ],
            $variantParent.'subject' => [
                'label' => 'le.email.report.variant_parent_subject',
                'type'  => 'string',
            ],
            $prefix.'variant_start_date' => [
                'label'          => 'le.email.report.variant_start_date',
                'type'           => 'datetime',
                'groupByFormula' => 'DATE('.$prefix.'variant_start_date)',
            ],
            $prefix.'variant_sent_count' => [
                'label' => 'le.email.report.variant_sent_count',
                'type'  => 'int',
            ],
            $prefix.'variant_read_count' => [
                'label' => 'le.email.report.variant_read_count',
                'type'  => 'int',
            ],
        ];

        $columns = array_merge(
            $columns,
            $event->getStandardColumns($prefix, [], 'le_email_action'),
            $event->getCategoryColumns()
        );
        $data = [
            'display_name' => 'le.email.emails',
            'columns'      => $columns,
        ];
        $event->addTable(self::CONTEXT_EMAILS, $data);
        $context = self::CONTEXT_EMAILS;
        $event->addGraph($context, 'pie', 'le.email.graph.pie.read.ingored.unsubscribed.bounced');

        if ($event->checkContext(self::CONTEXT_EMAIL_STATS)) {
            // Ratios are not applicable for individual stats
            unset($columns['read_ratio'], $columns['unsubscribed_ratio'], $columns['bounced_ratio'], $columns['hits_ratio'], $columns['unique_ratio']);

            // Email counts are not applicable for individual stats
            unset($columns[$prefix.'read_count'], $columns[$prefix.'variant_sent_count'], $columns[$prefix.'variant_read_count']);

            // Prevent null DNC records from filtering the results
            $columns['unsubscribed']['type']    = 'bool';
            $columns['unsubscribed']['formula'] = 'IF(dnc.id IS NOT NULL AND dnc.reason='.DoNotContact::UNSUBSCRIBED.', 1, 0)';

            $columns['bounced']['type']    = 'bool';
            $columns['bounced']['formula'] = 'IF(dnc.id IS NOT NULL AND dnc.reason='.DoNotContact::BOUNCED.', 1, 0)';

            // clicked column for individual stats
            $columns['is_hit'] = [
                'alias'   => 'is_hit',
                'label'   => 'le.email.report.is_hit',
                'type'    => 'bool',
                'formula' => 'IF('.$channelUrlTrackables.'hits is NULL, 0, 1)',
            ];

            // time between sent and read
            $columns['read_delay'] = [
                'alias'   => 'read_delay',
                'label'   => 'le.email.report.read.delay',
                'type'    => 'string',
                'formula' => 'IF(es.date_read IS NOT NULL, TIMEDIFF(es.date_read, es.date_sent), \'-\')',
            ];

            $statPrefix  = 'es.';
            $statColumns = [
                $statPrefix.'email_address' => [
                    'label' => 'le.email.report.stat.email_address',
                    'type'  => 'email',
                ],
                $statPrefix.'date_sent' => [
                    'label'          => 'le.email.report.stat.date_sent',
                    'type'           => 'datetime',
                    'groupByFormula' => 'DATE('.$statPrefix.'date_sent)',
                ],
                $statPrefix.'is_read' => [
                    'label' => 'le.email.report.stat.is_read',
                    'type'  => 'bool',
                ],
                $statPrefix.'is_failed' => [
                    'label' => 'le.email.report.stat.is_failed',
                    'type'  => 'bool',
                ],
                $statPrefix.'viewed_in_browser' => [
                    'label' => 'le.email.report.stat.viewed_in_browser',
                    'type'  => 'bool',
                ],
                $statPrefix.'date_read' => [
                    'label'          => 'le.email.report.stat.date_read',
                    'type'           => 'datetime',
                    'groupByFormula' => 'DATE('.$statPrefix.'date_read)',
                ],
                $statPrefix.'retry_count' => [
                    'label' => 'le.email.report.stat.retry_count',
                    'type'  => 'int',
                ],
                $statPrefix.'source' => [
                    'label' => 'mautic.report.field.source',
                    'type'  => 'string',
                ],
                $statPrefix.'source_id' => [
                    'label' => 'mautic.report.field.source_id',
                    'type'  => 'int',
                ],
            ];

            $companyColumns = $this->companyReportData->getCompanyData();

            $data = [
                'display_name' => 'le.email.stats.report.table',
                'columns'      => array_merge(
                    $columns,
                    $statColumns,
                    $event->getCampaignByChannelColumns(),
                    $event->getLeadColumns(),
                    $event->getIpColumn(),
                    $companyColumns
                ),
            ];
            $event->addTable(self::CONTEXT_EMAIL_STATS, $data, self::CONTEXT_EMAILS);

            // Register Graphs
            $context = self::CONTEXT_EMAIL_STATS;
            $event->addGraph($context, 'line', 'le.email.graph.line.stats');
            $event->addGraph($context, 'pie', 'le.email.graph.pie.ignored.read.failed');
            $event->addGraph($context, 'table', 'le.email.table.most.emails.sent');
            $event->addGraph($context, 'table', 'le.email.table.most.emails.read');
            $event->addGraph($context, 'table', 'le.email.table.most.emails.read.percent');
            $event->addGraph($context, 'table', 'le.email.table.most.emails.unsubscribed');
            $event->addGraph($context, 'table', 'le.email.table.most.emails.bounced');
            $event->addGraph($context, 'table', 'le.email.table.most.emails.failed');
        }
    }

    /**
     * Initialize the QueryBuilder object to generate reports from.
     *
     * @param ReportGeneratorEvent $event
     */
    public function onReportGenerate(ReportGeneratorEvent $event)
    {
        $context    = $event->getContext();
        $qb         = $event->getQueryBuilder();
        $hasGroupBy = $event->hasGroupBy();

        // channel_url_trackables subquery
        $qbcut        = $this->db->createQueryBuilder();
        $clickColumns = ['hits', 'unique_hits', 'hits_ratio', 'unique_ratio', 'is_hit'];
        $dncColumns   = ['unsubscribed', 'unsubscribed_ratio', 'bounced', 'bounced_ratio'];

        switch ($context) {
            case self::CONTEXT_EMAILS:
                $qb->from(MAUTIC_TABLE_PREFIX.'emails', 'e')
                    ->leftJoin('e', MAUTIC_TABLE_PREFIX.'emails', 'vp', 'vp.id = e.variant_parent_id');

                $event->addCategoryLeftJoin($qb, 'e')
                    ->applyDateFilters($qb, 'date_added', 'e');

                if (!$hasGroupBy) {
                    $qb->groupBy('e.id');
                }
                if ($event->hasColumn($clickColumns) || $event->hasFilter($clickColumns)) {
                    $qbcut->select(
                        'COUNT(cut2.channel_id) AS trackable_count, SUM(cut2.hits) AS hits',
                        'SUM(cut2.unique_hits) AS unique_hits',
                        'cut2.channel_id'
                    )
                        ->from(MAUTIC_TABLE_PREFIX.'channel_url_trackables', 'cut2')
                        ->where('cut2.channel = \'email\'')
                        ->groupBy('cut2.channel_id');
                    $qb->leftJoin('e', sprintf('(%s)', $qbcut->getSQL()), 'cut', 'e.id = cut.channel_id');
                }
               if ($event->hasColumn($dncColumns) || $event->hasFilter($dncColumns)) {
                   $qb->leftJoin(
                        'e',
                        MAUTIC_TABLE_PREFIX.'lead_donotcontact',
                        'dnc',
                        'e.id = dnc.channel_id AND dnc.channel=\'email\''
                    );
               }

                break;
            case self::CONTEXT_EMAIL_STATS:
                $qb->from(MAUTIC_TABLE_PREFIX.'email_stats', 'es')
                    ->leftJoin('es', MAUTIC_TABLE_PREFIX.'emails', 'e', 'e.id = es.email_id')
                    ->leftJoin('e', MAUTIC_TABLE_PREFIX.'emails', 'vp', 'vp.id = e.variant_parent_id');

                $event->addCategoryLeftJoin($qb, 'e')
                    ->addLeadLeftJoin($qb, 'es')
                    ->addIpAddressLeftJoin($qb, 'es')
                    ->applyDateFilters($qb, 'date_sent', 'es');

                if ($event->hasColumn($clickColumns) || $event->hasFilter($clickColumns)) {
                    $qbcut->select(
                        'COUNT(ph.id) AS hits',
                        'COUNT(DISTINCT(ph.redirect_id)) AS unique_hits',
                        'cut2.channel_id',
                        'ph.lead_id'
                    )
                        ->from(MAUTIC_TABLE_PREFIX.'channel_url_trackables', 'cut2')
                        ->join(
                            'cut2',
                            MAUTIC_TABLE_PREFIX.'page_hits',
                            'ph',
                            'cut2.redirect_id = ph.redirect_id AND cut2.channel_id = ph.source_id'
                        )
                        ->where('cut2.channel = \'email\' AND ph.source = \'email\'')
                        ->groupBy('cut2.channel_id, ph.lead_id');
                    $qb->leftJoin(
                        'e',
                        sprintf('(%s)', $qbcut->getSQL()),
                        'cut',
                        'e.id = cut.channel_id AND es.lead_id = cut.lead_id'
                    );
                }

               if ($event->hasColumn($dncColumns) || $event->hasFilter($dncColumns)) {
                   $this->addDNCTable($qb);
               }

                $event->addCampaignByChannelJoin($qb, 'e', 'email');

                if ($this->companyReportData->eventHasCompanyColumns($event)) {
                    $event->addCompanyLeftJoin($qb);
                }

                break;
        }

        $event->setQueryBuilder($qb);
    }

    /**
     * Initialize the QueryBuilder object to generate reports from.
     *
     * @param ReportGraphEvent $event
     */
    public function onReportGraphGenerate(ReportGraphEvent $event)
    {
        $dncColumns   = ['unsubscribed', 'unsubscribed_ratio', 'bounced', 'bounced_ratio'];
        $graphs       = $event->getRequestedGraphs();

        if (!$event->checkContext(self::CONTEXT_EMAIL_STATS) || ($event->checkContext(self::CONTEXT_EMAILS) && !in_array('le.email.graph.pie.read.ingored.unsubscribed.bounced', $graphs))) {
            return;
        }

        $qb       = $event->getQueryBuilder();
        if (!$event->hasColumn($dncColumns) && !$event->hasFilter($dncColumns)) {
            $this->addDNCTable($qb);
        }
        $statRepo = $this->em->getRepository(Stat::class);
        foreach ($graphs as $g) {
            $options      = $event->getOptions($g);
            $queryBuilder = clone $qb;
            /** @var ChartQuery $chartQuery */
            $chartQuery   = clone $options['chartQuery'];
            $origQuery    = clone $queryBuilder;
            // just limit date for contacts emails
            if ($event->checkContext(self::CONTEXT_EMAIL_STATS)) {
                $chartQuery->applyDateFilters($queryBuilder, 'date_sent', 'es');
            }

            switch ($g) {
                case 'le.email.graph.line.stats':
                    $chart     = new LineChart(null, $options['dateFrom'], $options['dateTo']);
                    $sendQuery = clone $queryBuilder;
                    $readQuery = clone $origQuery;
                    $readQuery->andWhere($qb->expr()->isNotNull('date_read'));
                    $failedQuery = clone $queryBuilder;
                    $bouncedQuery = clone $queryBuilder;
                    $clquery = clone $queryBuilder;
                    $chartQuery->applyDateFilters($readQuery, 'date_read', 'es');
                    $chartQuery->modifyTimeDataQuery($sendQuery, 'date_sent', 'es');
                    $chartQuery->modifyTimeDataQuery($readQuery, 'date_read', 'es');
                    $chartQuery->modifyTimeDataQuery($failedQuery, 'is_unsubscribe', 'es');
                    $chartQuery->modifyTimeDataQuery($bouncedQuery, 'is_bounce', 'es');
                    //$clquery=$chartQuery->getTableQuery('page_hits','ph');
                    $clquery->leftJoin('e','page_hits','ph','e.id=ph.email_id');
                    $chartQuery->modifyTimeDataQuery($clquery,'date_hit','ph');
                    $sends  = $chartQuery->loadAndBuildTimeData($sendQuery);
                    $reads  = $chartQuery->loadAndBuildTimeData($readQuery);
                    $failes = $chartQuery->loadAndBuildTimeData($failedQuery);
                    $bounes = $chartQuery->loadAndBuildTimeData($bouncedQuery);
                    $clicks = $chartQuery->loadAndBuildTimeData($clquery);
                    $chart->setDataset($options['translator']->trans('le.email.sent.emails'), $sends);
                    $chart->setDataset($options['translator']->trans('le.email.read.emails'), $reads);
                    $chart->setDataset($options['translator']->trans('le.email.report.is_hit'), $clicks);
                    $chart->setDataset($options['translator']->trans('le.email.bounce.emails'), $bounes);
                    $chart->setDataset($options['translator']->trans('le.email.unsubscribe.emails'), $failes);
                    $data         = $chart->render();
                    $data['name'] = $g;

                    $event->setGraph($g, $data);
                    break;

                case 'le.email.graph.pie.ignored.read.failed':
                    $counts = $statRepo->getIgnoredReadFailed($queryBuilder);
                    $chart  = new PieChart();
                    $chart->setDataset($options['translator']->trans('le.email.read.emails'), $counts['read']);
                    $chart->setDataset($options['translator']->trans('le.email.failed.emails'), $counts['failed']);
                    $chart->setDataset(
                        $options['translator']->trans('le.email.ignored.emails'),
                        $counts['ignored']
                    );

                    $data =$chart->render();

                    $data['name']     =$g;
                    $data['iconClass']='fa-flag-checkered';

                    $event->setGraph($g, $data);

                    break;

                case 'le.email.graph.pie.read.ingored.unsubscribed.bounced':
                    $queryBuilder->select(
                        'SUM(DISTINCT e.sent_count) as sent_count, SUM(DISTINCT e.read_count) as read_count, count(CASE WHEN dnc.id  and dnc.reason = '.DoNotContact::UNSUBSCRIBED.' THEN 1 ELSE null END) as unsubscribed, count(CASE WHEN dnc.id  and dnc.reason = '.DoNotContact::BOUNCED.' THEN 1 ELSE null END) as bounced'
                    );
                    $queryBuilder->resetQueryPart('groupBy');
                    $counts = $queryBuilder->execute()->fetch();
                    $chart  = new PieChart();
                    $chart->setDataset($options['translator']->trans('le.email.stat.read'), $counts['read_count']);
                    $chart->setDataset(
                        $options['translator']->trans('le.email.graph.pie.ignored.read.failed.ignored'),
                        ($counts['sent_count'] - $counts['read_count'])
                    );
                    $chart->setDataset(
                        $options['translator']->trans('le.email.unsubscribed'),
                        $counts['unsubscribed']
                    );
                    $chart->setDataset($options['translator']->trans('le.email.bounced'), $counts['bounced']);

                    $event->setGraph(
                        $g,
                        [
                            'data'      => $chart->render(),
                            'name'      => $g,
                            'iconClass' => 'fa-flag-checkered',
                        ]
                    );
                    break;

                case 'le.email.table.most.emails.sent':
                    $queryBuilder->select('e.id as id,e.subject as title, SUM(DISTINCT e. sent_count) as sent')
                        ->groupBy('e.id, e.subject')
                        ->orderBy('sent', 'DESC');
                    $limit                  = 10;
                    $offset                 = 0;
                    $items                  = $statRepo->getMostEmails($queryBuilder, $limit, $offset);
                    $graphData              = [];
                    $email                  = [];
                    foreach ($items as $item) {
                        $formUrl = $this->router->generate('le_email_action', ['objectAction' => 'view', 'objectId' => $item['id']]);
                        $row     = [
                            'mautic.dashboard.label.title' => [
                                'value' => $item['title'],
                                'type'  => 'link',
                                'link'  => $formUrl,
                            ],
                            'le.email.table.most.emails.sent' => [
                                'value' => $item['sent'],
                            ],
                        ];
                        $graphData[]      = $row;
                    }
                    $graphData['name']       = $g;
                    $graphData['data']       = $email;
                    $graphData['value']      = $g;
                    $graphData['iconClass']  = 'fa-paper-plane-o';
                    $event->setGraph($g, $graphData);
                    break;

                case 'le.email.table.most.emails.read':
                    $queryBuilder->select('e.id as id,e.subject as title, SUM(DISTINCT e. read_count) as opens')
                        ->groupBy('e.id, e.subject')
                        ->orderBy('opens', 'DESC');
                    $limit                  = 10;
                    $offset                 = 0;
                    $items                  = $statRepo->getMostEmails($queryBuilder, $limit, $offset);
                    $graphData              = [];
                    foreach ($items as $item) {
                        $formUrl = $this->router->generate('le_email_action', ['objectAction' => 'view', 'objectId' => $item['id']]);
                        $row     = [
                            'mautic.dashboard.label.title' => [
                                'value' => $item['title'],
                                'type'  => 'link',
                                'link'  => $formUrl,
                            ],
                            'le.email.table.most.emails.read' => [
                                'value' => $item['opens'],
                            ],
                        ];
                        $graphData[]      = $row;
                    }
                    $graphData['name']       = $g;
                    $graphData['data']       = $items;
                    $graphData['value']      = $g;
                    $graphData['iconClass']  = 'fa-paper-plane-o';
                    $event->setGraph($g, $graphData);
                    break;

                case 'le.email.table.most.emails.failed':
                    $queryBuilder->select(
                        'e.id as id,e.subject as title, count(CASE WHEN es.is_failed THEN 1 ELSE null END) as failed'
                    )
                        ->having('count(CASE WHEN es.is_failed THEN 1 ELSE null END) > 0')
                        ->groupBy('e.id, e.subject')
                        ->orderBy('failed', 'DESC');
                    $limit                  = 10;
                    $offset                 = 0;
                    $items                  = $statRepo->getMostEmails($queryBuilder, $limit, $offset);
                    $graphData              = [];
                    foreach ($items as $item) {
                        $formUrl = $this->router->generate('le_email_action', ['objectAction' => 'view', 'objectId' => $item['id']]);
                        $row     = [
                            'mautic.dashboard.label.title' => [
                                'value' => $item['title'],
                                'type'  => 'link',
                                'link'  => $formUrl,
                            ],
                            'le.email.table.most.emails.failed'=> [
                                'value' => $item['failed'],
                            ],
                        ];
                        $graphData[]      = $row;
                    }
                    $graphData['name']       = $g;
                    $graphData['data']       = $items;
                    $graphData['value']      = $g;
                    $graphData['iconClass']  = 'fa-paper-plane-o';
                    $event->setGraph($g, $graphData);
                    break;

                case 'le.email.table.most.emails.unsubscribed':
                   // $this->addDNCTable($queryBuilder);
                    $queryBuilder->select(
                        'e.id, e.subject as title, count(CASE WHEN dnc.id  and dnc.reason = '.DoNotContact::UNSUBSCRIBED.' THEN 1 ELSE null END) as unsubscribed'
                    )
                        ->having(
                            'count(CASE WHEN dnc.id and dnc.reason = '.DoNotContact::UNSUBSCRIBED.' THEN 1 ELSE null END) > 0'
                        )
                        ->groupBy('e.id, e.subject')
                        ->orderBy('unsubscribed', 'DESC');

                    $limit                  = 10;
                    $offset                 = 0;
                    $items                  = $statRepo->getMostEmails($queryBuilder, $limit, $offset);
                    $graphData              = [];
                    $graphData['data']      = $items;
                    $graphData['name']      = $g;
                    $graphData['iconClass'] = 'fa-exclamation-triangle';
                    $graphData['link']      = 'le_email_action';
                    $event->setGraph($g, $graphData);
                    break;

                case 'le.email.table.most.emails.bounced':
                  //  $this->addDNCTable($queryBuilder);
                    $queryBuilder->select(
                        'e.id, e.subject as title, count(CASE WHEN dnc.id  and dnc.reason = '.DoNotContact::BOUNCED.' THEN 1 ELSE null END) as bounced'
                    )
                        ->having(
                            'count(CASE WHEN dnc.id and dnc.reason = '.DoNotContact::BOUNCED.' THEN 1 ELSE null END) > 0'
                        )
                        ->groupBy('e.id, e.subject')
                        ->orderBy('bounced', 'DESC');
                    $limit                  = 10;
                    $offset                 = 0;
                    $items                  = $statRepo->getMostEmails($queryBuilder, $limit, $offset);
                    $graphData              = [];
                    $graphData['data']      = $items;
                    $graphData['name']      = $g;
                    $graphData['iconClass'] = 'fa-exclamation-triangle';
                    $graphData['link']      = 'le_email_action';
                    $event->setGraph($g, $graphData);
                    break;

                case 'le.email.table.most.emails.read.percent':
                    $queryBuilder->select('e.id as id,e.subject as title, round(e.read_count / e.sent_count * 100) as ratio')
                        ->groupBy('e.id, e.subject')
                        ->orderBy('ratio', 'DESC');
                    $limit                  = 10;
                    $offset                 = 0;
                    $items                  = $statRepo->getMostEmails($queryBuilder, $limit, $offset);
                    $graphData              = [];
                    foreach ($items as $item) {
                        $formUrl = $this->router->generate('le_email_action', ['objectAction' => 'view', 'objectId' => $item['id']]);
                        $row     = [
                            'mautic.dashboard.label.title' => [
                                'value' => $item['title'],
                                'type'  => 'link',
                                'link'  => $formUrl,
                            ],
                            'le.email.table.most.emails.read.percent' => [
                                'value' => $item['ratio'],
                            ],
                        ];
                        $graphData[]      = $row;
                    }
                    $graphData['name']       = $g;
                    $graphData['data']       = $items;
                    $graphData['value']      = $g;
                    $graphData['iconClass']  = 'fa-paper-plane-o';
                    $event->setGraph($g, $graphData);
                    break;
            }
            unset($queryBuilder);
        }
    }

    /**
     * Add the Do Not Contact table to the query builder.
     *
     * @param QueryBuilder $qb
     */
    private function addDNCTable(QueryBuilder $qb)
    {
        if ($this->dncWasAddedToQb) {
            return;
        }

        $qb->leftJoin(
            'e',
            MAUTIC_TABLE_PREFIX.'lead_donotcontact',
            'dnc',
            'e.id = dnc.channel_id AND dnc.channel=\'email\' AND es.lead_id = dnc.lead_id'
        );

        $this->dncWasAddedToQb = true;
    }
}
