<?php

/*
 * @copyright   2014 Mautic Contributorcomp. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SubscriptionBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\UserBundle\Entity\User;

/**
 * Class AccountRepository.
 */
class AccountRepository extends CommonRepository
{
    /**
     * @var User
     */
    protected $currentUser;

    /**
     * {@inhertidoc}.
     *
     * @param array $args
     *
     * @return Paginator
     */
    public function getEntities(array $args = [])
    {
        $q = $this
            ->createQueryBuilder('a')
            ->select('a');
        $args['qb'] = $q;

        return parent::getEntities($args);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getTableAlias()
    {
        return 'a';
    }

    /**
     * Set the current user (i.e. from security context) for use within repositories.
     *
     * @param User $user
     */
    public function setCurrentUser($user)
    {
        if (!$user instanceof User) {
            //just create a blank user entity
            $user = new User();
        }
        $this->currentUser = $user;
    }

    public function getTotalSentCounts()
    {
        $fromdate = date('Y-m-d', strtotime('-29 days'));
        $q        = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( e.id) as sentcount')
            ->from(MAUTIC_TABLE_PREFIX.'email_stats', 'es')
            ->leftJoin('es', MAUTIC_TABLE_PREFIX.'emails', 'e', 'e.id = es.email_id')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('es.is_failed', ':false')
                )
            )->setParameter('false', false, 'boolean');

        if ($fromdate !== null) {
            $q->andWhere(
                $q->expr()->gte('es.date_sent', $q->expr()->literal($fromdate))
            );
        }
        $q->andWhere(
            $q->expr()->neq('e.email_type', ':emailType')
        )->setParameter('emailType', 'template');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('e.created_by', ':id'))
                ->setParameter('id', '1');
        }
        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['sentcount'];
    }

    public function getTotalUniqueOpenCounts()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( e.id) as readcount')
            ->from(MAUTIC_TABLE_PREFIX.'email_stats', 'es')
            ->leftJoin('es', MAUTIC_TABLE_PREFIX.'emails', 'e', 'e.id = es.email_id')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('es.is_failed', ':false')
                )
            )->setParameter('false', false, 'boolean');
        $q->andWhere(
            $q->expr()->eq('es.is_read', 1)
        );
        $q->andWhere(
            $q->expr()->isNotNull('es.email_id')
        );
        $fromdate = date('Y-m-d', strtotime('-29 days'));
        if ($fromdate !== null) {
            $q->andWhere(
                $q->expr()->gte('es.date_sent', $q->expr()->literal($fromdate))
            );
        }
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('e.created_by', ':id'))
                ->setParameter('id', '1');
        }
        $q->andWhere(
            $q->expr()->neq('e.email_type', ':emailType')
        )->setParameter('emailType', 'template');
        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['readcount'];
    }

    public function getTotalOpenCounts()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('es.open_count as opencount')
            ->from(MAUTIC_TABLE_PREFIX.'email_stats', 'es')
            ->leftJoin('es', MAUTIC_TABLE_PREFIX.'emails', 'e', 'e.id = es.email_id')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('es.is_failed', ':false')
                )
            )->setParameter('false', false, 'boolean');
        $q->andWhere(
            $q->expr()->eq('es.is_read', 1)
        );
        $q->andWhere(
            $q->expr()->isNotNull('es.email_id')
        );
        $fromdate = date('Y-m-d', strtotime('-29 days'));
        if ($fromdate !== null) {
            $q->andWhere(
                $q->expr()->gte('es.date_sent', $q->expr()->literal($fromdate))
            );
        }
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('e.created_by', ':id'))
                ->setParameter('id', '1');
        }
        $q->andWhere(
            $q->expr()->neq('e.email_type', ':emailType')
        )->setParameter('emailType', 'template');

        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        $count = 0;
        for ($i = 0; $i < sizeof($results); ++$i) {
            $count += $results[$i]['opencount'];
        }

        return $count;
    }

    public function getEmailClickCounts()
    {
        $sq = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $sq->select('distinct e.id')
            ->from(MAUTIC_TABLE_PREFIX.'emails', 'e')
            ->leftJoin('e', MAUTIC_TABLE_PREFIX.'email_stats', 'es',
                $sq->expr()->andX(
                    $sq->expr()->eq('e.id', 'es.email_id')
                ))
            ->andWhere(
                $sq->expr()->isNotNull('es.email_id')
            );
        $sq->andWhere(
            $sq->expr()->neq('e.email_type', ':emailType')
        );
        $fromdate = date('Y-m-d', strtotime('-29 days'));
        if ($fromdate !== null) {
            $sq->andWhere(
                $sq->expr()->gte('es.date_sent', $sq->expr()->literal($fromdate))
            );
        }
        if ($this->currentUser->getId() != 1) {
            $sq->andWhere($sq->expr()->neq('e.created_by', ':id'));
        }
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();

        $q->select('sum(t.unique_hits) as clickcount')
            ->from(MAUTIC_TABLE_PREFIX.'page_redirects', 'r')
            ->leftJoin('r', MAUTIC_TABLE_PREFIX.'channel_url_trackables', 't',
                $q->expr()->andX(
                    $q->expr()->eq('r.id', 't.redirect_id'),
                    $q->expr()->eq('t.channel', ':channel')
                )
            )
            ->setParameter('channel', 'email')
            ->setParameter('emailType', 'template')
            ->setParameter('id', '1');
        $q->andWhere('t.channel_id IN('.$sq->getSQL().')');
        $q->orderBy('r.url');

        $results = $q->execute()->fetchAll();
        $count   = $results[0]['clickcount'];
        if (empty($count)) {
            $count=0;
        }

        return $count;
    }

    public function getTotalUnsubscribedCounts()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( e.id) as unsubscribecount')
            ->from(MAUTIC_TABLE_PREFIX.'email_stats', 'es')
            ->leftJoin('es', MAUTIC_TABLE_PREFIX.'emails', 'e', 'e.id = es.email_id')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('es.is_failed', ':false')
                )
            )->setParameter('false', false, 'boolean');
        $q->andWhere(
            $q->expr()->eq('es.is_unsubscribe', 1)
        );
        $q->andWhere(
            $q->expr()->neq('e.email_type', ':emailType')
        )->setParameter('emailType', 'template');
        $q->andWhere(
            $q->expr()->isNotNull('es.email_id')
        );
        $fromdate = date('Y-m-d', strtotime('-29 days'));
        if ($fromdate !== null) {
            $q->andWhere(
                $q->expr()->gte('es.date_sent', $q->expr()->literal($fromdate))
            );
        }
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('e.created_by', ':id'))
                ->setParameter('id', '1');
        }
        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['unsubscribecount'];
    }

    public function getTotalBounceCounts()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( e.id) as bouncecount')
            ->from(MAUTIC_TABLE_PREFIX.'email_stats', 'es')
            ->leftJoin('es', MAUTIC_TABLE_PREFIX.'emails', 'e', 'e.id = es.email_id')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('es.is_failed', ':false')
                )
            )->setParameter('false', false, 'boolean');
        $q->andWhere(
            $q->expr()->eq('es.is_bounce', 1)
        );
        $q->andWhere(
            $q->expr()->neq('e.email_type', ':emailType')
        )->setParameter('emailType', 'template');
        $q->andWhere(
            $q->expr()->isNotNull('es.email_id')
        );

        $fromdate = date('Y-m-d', strtotime('-29 days'));
        if ($fromdate !== null) {
            $q->andWhere(
                $q->expr()->gte('es.date_sent', $q->expr()->literal($fromdate))
            );
        }
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('e.created_by', ':id'))
                ->setParameter('id', '1');
        }

        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['bouncecount'];
    }

    public function getTotalSpamCounts()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( e.id) as spamcount')
            ->from(MAUTIC_TABLE_PREFIX.'email_stats', 'es')
            ->leftJoin('es', MAUTIC_TABLE_PREFIX.'emails', 'e', 'e.id = es.email_id')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('es.is_failed', ':false')
                )
            )->setParameter('false', false, 'boolean');
        $q->andWhere(
            $q->expr()->eq('es.is_spam', 1)
        );
        $q->andWhere(
            $q->expr()->neq('e.email_type', ':emailType')
        )->setParameter('emailType', 'template');

        $fromdate = date('Y-m-d', strtotime('-29 days'));
        if ($fromdate !== null) {
            $q->andWhere(
                $q->expr()->gte('es.date_sent', $q->expr()->literal($fromdate))
            );
        }
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('e.created_by', ':id'))
                ->setParameter('id', '1');
        }

        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['spamcount'];
    }

    public function getTotalAllLeads()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();

        $q->select('count( l.id) as leadcount')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 'l');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('l.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('l.created_by'));
        }
        $results = $q->execute()->fetchAll();

        return $results[0]['leadcount'];
    }

    public function getAllActiveLeads()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( l.id) as activeleads')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 'l')
            ->leftJoin('l', MAUTIC_TABLE_PREFIX.'lead_donotcontact', 'd', 'd.lead_id = l.id');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('l.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('l.created_by'));
        }
        $q->andWhere(
            $q->expr()->isNull('d.lead_id')
        );
        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['activeleads'];
    }

    public function getLeadsByStatus($status)
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( l.id) as leadcount')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 'l');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('l.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('l.created_by'));
        }
        $q->andWhere(
            $q->expr()->eq('l.status', ':status')
        )->setParameter('status', $status);
        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['leadcount'];
    }

    public function getAllInActiveLeads()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( l.id) as inactiveleads')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 'l');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('l.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('l.created_by'));
        }
        $q->andWhere(
            $q->expr()->in('l.status', ['3', '4', '5', '6'])
        );
        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['inactiveleads'];
    }

    public function getRecentlyAddedLeadsCount()
    {
        $q                   = $this->_em->getConnection()->createQueryBuilder();
        $last7daysAddedLeads = date('Y-m-d', strtotime('-29 days'));

        $q->select('count(*) as recentlyadded')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 'l');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('l.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('l.created_by'));
        }
        $q->andWhere($q->expr()->gte('l.date_added', ':dateAdded'))
            ->setParameter('dateAdded', $last7daysAddedLeads);
        $results = $q->execute()->fetchAll();

        return $results[0]['recentlyadded'];
    }

    public function getRecentActiveLeadCount()
    {
        $last7daysActiveLeads = date('Y-m-d', strtotime('-29 days'));

        $q = $this->_em->getConnection()->createQueryBuilder();

        $q->select('count(*) as activeleads')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 'l');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('l.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('l.created_by'));
        }
        $q->andWhere($q->expr()->gte('l.last_active', ':last7daysActive'))
            ->setParameter('last7daysActive', $last7daysActiveLeads);
        $results = $q->execute()->fetchAll();

        return $results[0]['activeleads'];
    }

    public function getActiveWorkflows()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( c.id) as activeworkflow')
            ->from(MAUTIC_TABLE_PREFIX.'campaigns', 'c');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('c.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('c.created_by'));
        }
        $q->andWhere(
            $q->expr()->andX(
                $q->expr()->eq('c.is_published', 1)
            )
        );
        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['activeworkflow'];
    }

    public function getActiveForms()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( f.id) as activeforms')
            ->from(MAUTIC_TABLE_PREFIX.'forms', 'f');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('f.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('f.created_by'));
        }
        $q->andWhere(
            $q->expr()->andX(
                $q->expr()->eq('f.is_published', 1)
            )
        );
        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['activeforms'];
    }

    public function getActiveAssets()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( a.id) as activeasset')
            ->from(MAUTIC_TABLE_PREFIX.'assets', 'a');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('a.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('a.created_by'));
        }
        $q->andWhere(
            $q->expr()->andX(
                $q->expr()->eq('a.is_published', 1)
            )
        );
        //get a total number of sent emails
        $results = $q->execute()->fetchAll();

        return $results[0]['activeasset'];
    }

    public function getGoalsAchived()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( l.id) as goalsachived')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 'l');
        $q->innerJoin('l', MAUTIC_TABLE_PREFIX.'campaign_lead_event_log', 'cl', 'l.id = cl.lead_id');
        $q->innerJoin('cl', MAUTIC_TABLE_PREFIX.'campaign_events', 'ce', 'cl.event_id = ce.id');
        if ($this->currentUser->getId() != 1) {
            $q->andWhere($q->expr()->neq('l.created_by', ':id'))
                ->setParameter('id', '1');
            $q->orWhere($q->expr()->isNull('l.created_by'));
        }
        $q->andWhere(
            $q->expr()->andX(
                $q->expr()->eq('ce.trigger_mode', ':triggerMode')
            )
        );
        $fromdate = date('Y-m-d', strtotime('-29 days'));
        if ($fromdate !== null) {
            $q->andWhere(
                $q->expr()->gte('cl.date_triggered', $q->expr()->literal($fromdate))
            );
        }

        $q->setParameter('triggerMode', 'interrupt');
        $results = $q->execute()->fetchAll();

        return $results[0]['goalsachived'];
    }

    public function getFormSubmissionCount()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( fs.id) as submissions')
            ->from(MAUTIC_TABLE_PREFIX.'form_submissions', 'fs');
        $fromdate = date('Y-m-d', strtotime('-29 days'));
        if ($fromdate !== null) {
            $q->andWhere(
                $q->expr()->gte('fs.date_submitted', $q->expr()->literal($fromdate))
            );
        }
        $results = $q->execute()->fetchAll();

        return $results[0]['submissions'];
    }

    public function getAssetDownloadCount()
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('count( ad.id) as downloads')
            ->from(MAUTIC_TABLE_PREFIX.'asset_downloads', 'ad');
        $fromdate = date('Y-m-d', strtotime('-29 days'));
        if ($fromdate !== null) {
            $q->andWhere(
                $q->expr()->gte('ad.date_download', $q->expr()->literal($fromdate))
            );
        }
        $results = $q->execute()->fetchAll();

        return $results[0]['downloads'];
    }
}
