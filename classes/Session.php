<?php
/**
 * Table Definition for session
 *
 * LShai - a distributed microblogging tool
 */

if (!defined('SHAISHAI')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Session extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'session';                         // table name
    public $id;                              // varchar(32)  primary_key not_null
    public $session_data;                    // text()
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Session',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function logdeb($msg)
    {
        if (common_config('sessions', 'debug')) {
            common_debug("Session: " . $msg);
        }
    }

    static function open($save_path, $session_name)
    {
        return true;
    }

    static function close()
    {
        return true;
    }

    static function read($id)
    {
        self::logdeb("Fetching session '$id'");

        $session = Session::staticGet('id', $id);

        if (empty($session)) {
            return '';
        } else {
            return (string)$session->session_data;
        }
    }

    static function write($id, $session_data)
    {
        self::logdeb("Writing session '$id'");

        $session = Session::staticGet('id', $id);

        if (empty($session)) {
            $session = new Session();

            $session->id           = $id;
            $session->session_data = $session_data;
            $session->created      = common_sql_now();

            return $session->insert();
        } else {
            $session->session_data = $session_data;

            return $session->update();
        }
    }

    static function destroy($id)
    {
        self::logdeb("Deleting session $id");

        $session = Session::staticGet('id', $id);

        if (!empty($session)) {
            return $session->delete();
        }
    }

    static function gc($maxlifetime)
    {
        self::logdeb("garbage collection (maxlifetime = $maxlifetime)");

        $epoch = time() - $maxlifetime;

        $qry = 'DELETE FROM session ' .
          'WHERE modified < "'.$epoch.'"';

        $session = new Session();

        $result = $session->query($qry);

        self::logdeb("garbage collection result = $result");
    }

    static function setSaveHandler()
    {
        self::logdeb("setting save handlers");
        $result = session_set_save_handler('Session::open', 'Session::close', 'Session::read',
                                           'Session::write', 'Session::destroy', 'Session::gc');
        self::logdeb("save handlers result = $result");
        return $result;
    }
}
