<?php

/**
 * NewBB 5.0x,  the forum module for XOOPS project
 *
 * @copyright      XOOPS Project (https://xoops.org)
 * @license        GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @author         Taiwen Jiang (phppp or D.J.) <phppp@users.sourceforge.net>
 * @since          4.00
 * @package        module::newbb
 */

class Digest extends XoopsObject
{
    public $digest_id;
    public $digest_time;
    public $digest_content;

    public $items;
    public $isHtml    = false;
    public $isSummary = true;

    /**
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->initVar('digest_id', XOBJ_DTYPE_INT);
        $this->initVar('digest_time', XOBJ_DTYPE_INT);
        $this->initVar('digest_content', XOBJ_DTYPE_TXTAREA);
        $this->items = [];
    }

    public function setHtml()
    {
        $this->isHtml = true;
    }

    public function setSummary()
    {
        $this->isSummary = true;
    }

    /**
     * @param        $title
     * @param        $link
     * @param        $author
     * @param string $summary
     */
    public function addItem($title, $link, $author, $summary = '')
    {
        $title  = $this->cleanup($title);
        $author = $this->cleanup($author);
        if (!empty($summary)) {
            $summary = $this->cleanup($summary);
        }
        $this->items[] = ['title' => $title, 'link' => $link, 'author' => $author, 'summary' => $summary];
    }

    /**
     * @param $text
     * @return mixed|string
     */
    public function cleanup($text)
    {
        global $myts;

        $clean = stripslashes($text);
        $clean =& $myts->displayTarea($clean, 1, 0, 1);
        $clean = strip_tags($clean);
        $clean = htmlspecialchars($clean, ENT_QUOTES);

        return $clean;
    }

    /**
     * @param  bool $isSummary
     * @param  bool $isHtml
     * @return bool
     */
    public function buildContent($isSummary = true, $isHtml = false)
    {
        $digest_count = count($this->items);
        $content      = '';
        if ($digest_count > 0) {
            $linebreak = $isHtml ? '<br>' : "\n";
            for ($i = 0; $i < $digest_count; ++$i) {
                if ($isHtml) {
                    $content .= ($i + 1) . '. <a href=' . $this->items[$i]['link'] . '>' . $this->items[$i]['title'] . '</a>';
                } else {
                    $content .= ($i + 1) . '. ' . $this->items[$i]['title'] . $linebreak . $this->items[$i]['link'];
                }

                $content .= $linebreak . $this->items[$i]['author'];
                if ($isSummary) {
                    $content .= $linebreak . $this->items[$i]['summary'];
                }
                $content .= $linebreak . $linebreak;
            }
        }
        $this->setVar('digest_content', $content);

        return true;
    }
}

/**
 * Class NewbbDigestHandler
 */
class NewbbDigestHandler extends XoopsPersistableObjectHandler
{
    public $last_digest;

    /**
     * Constructor
     *
     * @param null|XoopsDatabase $db             database connection
     */
    public function __construct(XoopsDatabase $db)
    {
        parent::__construct($db, 'newbb_digest', 'Digest', 'digest_id');
    }

    /**
     * @param  bool $isForced
     * @return int
     */
    public function process($isForced = false)
    {
        $this->getLastDigest();
        if (!$isForced) {
            $status = $this->checkStatus();
            if ($status < 1) {
                return 1;
            }
        }
        $digest = $this->create();
        $status = $this->buildDigest($digest);
        if (!$status) {
            return 2;
        }
        $status = $this->insert($digest);
        if (!$status) {
            return 3;
        }
        $status = $this->notify($digest);
        if (!$status) {
            return 4;
        }

        return 0;
    }

    /**
     * @param  XoopsObject $digest
     * @return bool
     */
    public function notify(XoopsObject $digest)
    {
        //$content                = $digest->getVar('digest_content');
        /** @var \XoopsNotificationHandler $notificationHandler */
        $notificationHandler    = xoops_getHandler('notification');
        $tags['DIGEST_ID']      = $digest->getVar('digest_id');
        $tags['DIGEST_CONTENT'] = $digest->getVar('digest_content', 'E');
        $notificationHandler->triggerEvent('global', 0, 'digest', $tags);

        return true;
    }

    /**
     * @param        $start
     * @param  int   $perpage
     * @return array
     */
    public function getAllDigests($start = 0, $perpage = 5)
    {
        //        if (empty($start)) {
        //            $start = 0;
        //        }

        $sql    = 'SELECT * FROM ' . $this->db->prefix('newbb_digest') . ' ORDER BY digest_id DESC';
        $result = $this->db->query($sql, $perpage, $start);
        $ret    = [];
        //        $reportHandler = xoops_getModuleHandler('report', 'newbb');
        while ($myrow = $this->db->fetchArray($result)) {
            $ret[] = $myrow; // return as array
        }

        return $ret;
    }

    /**
     * @return int
     */
    public function getDigestCount()
    {
        $sql    = 'SELECT COUNT(*) AS count FROM ' . $this->db->prefix('newbb_digest');
        $result = $this->db->query($sql);
        if (!$result) {
            return 0;
        } else {
            $array = $this->db->fetchArray($result);

            return $array['count'];
        }
    }

    public function getLastDigest()
    {
        $sql    = 'SELECT MAX(digest_time) AS last_digest FROM ' . $this->db->prefix('newbb_digest');
        $result = $this->db->query($sql);
        if (!$result) {
            $this->last_digest = 0;
            // echo "<br>no data:".$query;
        } else {
            $array             = $this->db->fetchArray($result);
            $this->last_digest = isset($array['last_digest']) ? $array['last_digest'] : 0;
        }
    }

    /**
     * @return int
     */
    public function checkStatus()
    {
        if (!isset($this->last_digest)) {
            $this->getLastDigest();
        }
        $deadline  = (1 == $GLOBALS['xoopsModuleConfig']['email_digest']) ? 60 * 60 * 24 : 60 * 60 * 24 * 7;
        $time_diff = time() - $this->last_digest;

        return $time_diff - $deadline;
    }

    /**
     * @param  XoopsObject $digest
     * @param  bool        $force  flag to force the query execution despite security settings
     * @return mixed       object ID or false
     */
    public function insert(XoopsObject $digest, $force = true)
    {
        $digest->setVar('digest_time', time());
        return parent::insert($digest, $force);
        /*
        $content = $digest->getVar('digest_content', 'E');

        $id  = $this->db->genId($digest->table . '_digest_id_seq');
        $sql = 'INSERT INTO ' . $digest->table . ' (digest_id, digest_time, digest_content)    VALUES (' . $id . ', ' . time() . ', ' . $this->db->quoteString($content) . ' )';

        if (!$this->db->queryF($sql)) {
            //echo "<br>digest insert error::" . $sql;
            return false;
        }
        if (empty($id)) {
            $id = $this->db->getInsertId();
        }
        $digest->setVar('digest_id', $id);

        return true;
        */
    }

    /**
     * @param  XoopsObject $digest
     * @param  bool        $force (ignored)
     * @return bool        FALSE if failed.
     */
    public function delete(XoopsObject $digest, $force = false)
    {
        $digest_id = $digest->getVar('digest_id');

        if (!isset($this->last_digest)) {
            $this->getLastDigest();
        }
        if ($this->last_digest == $digest_id) {
            return false;
        } // It is not allowed to delete the last digest

        return parent::delete($digest, true);
    }

    /**
     * @param  XoopsObject|\Digest $digest
     * @return bool
     */
    public function buildDigest(XoopsObject $digest)
    {
        global $xoopsModule;

        if (!defined('SUMMARY_LENGTH')) {
            define('SUMMARY_LENGTH', 100);
        }

        /** @var \NewbbForumHandler $forumHandler */
        $forumHandler         = xoops_getModuleHandler('forum', 'newbb');
        $thisUser             = $GLOBALS['xoopsUser'];
        $GLOBALS['xoopsUser'] = null; // To get posts accessible by anonymous
        $GLOBALS['xoopsUser'] = $thisUser;

        $accessForums    = $forumHandler->getIdsByPermission(); // get all accessible forums
        $forumCriteria   = ' AND t.forum_id IN (' . implode(',', $accessForums) . ')';
        $approveCriteria = ' AND t.approved = 1 AND p.approved = 1';
        $time_criteria   = ' AND t.digest_time > ' . $this->last_digest;

        $karma_criteria = $GLOBALS['xoopsModuleConfig']['enable_karma'] ? ' AND p.post_karma=0' : '';
        $reply_criteria = $GLOBALS['xoopsModuleConfig']['allow_require_reply'] ? ' AND p.require_reply=0' : '';

        $query = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_time, t.digest_time, p.uid, p.poster_name, pt.post_text FROM '
                 . $this->db->prefix('newbb_topics')
                 . ' t, '
                 . $this->db->prefix('newbb_posts_text')
                 . ' pt, '
                 . $this->db->prefix('newbb_posts')
                 . ' p WHERE t.topic_digest = 1 AND p.topic_id=t.topic_id AND p.pid=0 '
                 . $forumCriteria
                 . $approveCriteria
                 . $time_criteria
                 . $karma_criteria
                 . $reply_criteria
                 . ' AND pt.post_id=p.post_id ORDER BY t.digest_time DESC';
        if (!$result = $this->db->query($query)) {
            //echo "<br>No result:<br>$query";
            return false;
        }
        $rows  = [];
        $users = [];
        while ($row = $this->db->fetchArray($result)) {
            $users[$row['uid']] = 1;
            $rows[]             = $row;
        }
        if (count($rows) < 1) {
            return false;
        }
        $uids = array_keys($users);
        if (count($uids) > 0) {
            /** @var \XoopsMemberHandler $memberHandler */
            $memberHandler = xoops_getHandler('member');
            $user_criteria = new Criteria('uid', '(' . implode(',', $uids) . ')', 'IN');
            $users         = $memberHandler->getUsers($user_criteria, true);
        } else {
            $users = [];
        }

        foreach ($rows as $topic) {
            if ($topic['uid'] > 0) {
                if (isset($users[$topic['uid']]) && is_object($users[$topic['uid']])
                    && $users[$topic['uid']]->isActive()) {
                    $topic['uname'] = $users[$topic['uid']]->getVar('uname');
                } else {
                    $topic['uname'] = $GLOBALS['xoopsConfig']['anonymous'];
                }
            } else {
                $topic['uname'] = $topic['poster_name'] ?: $GLOBALS['xoopsConfig']['anonymous'];
            }
            $summary = \Xmf\Metagen::generateDescription($topic['post_text'], SUMMARY_LENGTH);
            $author  = $topic['uname'] . ' (' . formatTimestamp($topic['topic_time']) . ')';
            $link    = XOOPS_URL . '/modules/' . $xoopsModule->dirname() . '/viewtopic.php?topic_id=' . $topic['topic_id'] . '&amp;forum=' . $topic['forum_id'];
            $title   = $topic['topic_title'];
            $digest->addItem($title, $link, $author, $summary);
        }
        $digest->buildContent();

        return true;
    }
}
