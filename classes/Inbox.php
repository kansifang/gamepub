<?php

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Inbox extends Memcached_DataObject
{
    const BOXCAR = 128;
    const MAX_NOTICES = 1024;

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'inbox';                           // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $notice_ids;                      // blob

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Inbox',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Create a new inbox from existing Notice_inbox stuff
     */

    static function initialize($user_id)
    {
        $inbox = Inbox::fromNoticeInbox($user_id);

        unset($inbox->fake);

        $result = $inbox->insert();

        if (!$result) {
            common_log_db_error($inbox, 'INSERT', __FILE__);
            return null;
        }

        return $inbox;
    }

    static function fromNoticeInbox($user_id)
    {
        $ids = array();

        $ni = new Notice_inbox();

        $ni->user_id = $user_id;
        $ni->selectAdd();
        $ni->selectAdd('notice_id');
        $ni->orderBy('notice_id DESC');
        $ni->limit(0, self::MAX_NOTICES);

        if ($ni->find()) {
            while($ni->fetch()) {
                $ids[] = $ni->notice_id;
            }
        }

        $ni->free();
        unset($ni);

        $inbox = new Inbox();

        $inbox->user_id = $user_id;
        $inbox->notice_ids = call_user_func_array('pack', array_merge(array('N*'), $ids));
        $inbox->fake = true;

        return $inbox;
    }

    static function insertNotice($user_id, $notice_id)
    {
        $inbox = Memcached_DataObject::staticGet('inbox', 'user_id', $user_id);

        if (empty($inbox)) {
            $inbox = Inbox::initialize($user_id);
        }

        if (empty($inbox)) {
            return false;
        }

        $result = $inbox->query(sprintf('UPDATE inbox '.
                                        'set notice_ids = concat(cast(0x%08x as binary(4)), '.
                                        'substr(notice_ids, 1, %d)) '.
                                        'WHERE user_id = %d',
                                        $notice_id,
                                        4 * (self::MAX_NOTICES - 1),
                                        $user_id));

        if ($result) {
            self::blow('inbox:user_id:%d', $user_id);
        }

        return $result;
    }

    static function bulkInsert($notice_id, $user_ids)
    {
        foreach ($user_ids as $user_id)
        {
            Inbox::insertNotice($user_id, $notice_id);
        }
    }

    function stream($user_id, $offset, $limit, $since_id, $max_id, $own=false)
    {
        $inbox = Inbox::staticGet('user_id', $user_id);

        if (empty($inbox)) {
            $inbox = Inbox::fromNoticeInbox($user_id);
            if (empty($inbox)) {
                return array();
            } else {
                $inbox->encache();
            }
        }

        $ids = unpack('N*', $inbox->notice_ids);

        if (!empty($since_id)) {
            $newids = array();
            foreach ($ids as $id) {
                if ($id > $since_id) {
                    $newids[] = $id;
                }
            }
            $ids = $newids;
        }

        if (!empty($max_id)) {
            $newids = array();
            foreach ($ids as $id) {
                if ($id <= $max_id) {
                    $newids[] = $id;
                }
            }
            $ids = $newids;
        }

        $ids = array_slice($ids, $offset, $limit);

        return $ids;
    }

    /**
     * Wrapper for Inbox::stream() and Notice::getStreamByIds() returning
     * additional items up to the limit if we were short due to deleted
     * notices still being listed in the inbox.
     *
     * The fast path (when no items are deleted) should be just as fast; the
     * offset parameter is applied *before* lookups for maximum efficiency.
     *
     * This means offset-based paging may show duplicates, but similar behavior
     * already exists when new notices are posted between page views, so we
     * think people will be ok with this until id-based paging is introduced
     * to the user interface.
     *
     * @param int $user_id
     * @param int $offset skip past the most recent N notices (after since_id checks)
     * @param int $limit
     * @param mixed $since_id return only notices after but not including this id
     * @param mixed $max_id return only notices up to and including this id
     * @param mixed $own ignored?
     * @return array of Notice objects
     *
     * @todo consider repacking the inbox when this happens?
     * @fixme reimplement $own if we need it?
     */
    function streamNotices($user_id, $offset, $limit, $since_id, $max_id, $own=false)
    {
        $ids = self::stream($user_id, $offset, self::MAX_NOTICES, $since_id, $max_id, $own);

        // Do a bulk lookup for the first $limit items
        // Fast path when nothing's deleted.
        $firstChunk = array_slice($ids, 0, $limit);
        $notices = Notice::getStreamByIds($firstChunk);

        $wanted = count($firstChunk); // raw entry count in the inbox up to our $limit
        if ($notices->N >= $wanted) {
            return $notices;
        }

        // There were deleted notices, we'll need to look for more.
        assert($notices instanceof ArrayWrapper);
        $items = $notices->_items;
        $remainder = array_slice($ids, $limit);

        while (count($items) < $wanted && count($remainder) > 0) {
            $notice = Notice::staticGet(array_shift($remainder));
            if ($notice) {
                $items[] = $notice;
            } else {
            }
        }
        return new ArrayWrapper($items);
    }
}
