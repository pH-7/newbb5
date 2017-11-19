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

// defined('XOOPS_ROOT_PATH') || exit('Restricted access.');

defined('NEWBB_FUNCTIONS_INI') || include $GLOBALS['xoops']->path('modules/newbb/include/functions.ini.php');

/**
 * Class Topic
 */
class Topic extends XoopsObject
{
    /**
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->initVar('topic_id', XOBJ_DTYPE_INT);
        $this->initVar('topic_title', XOBJ_DTYPE_TXTBOX);
        $this->initVar('topic_poster', XOBJ_DTYPE_INT);
        $this->initVar('topic_time', XOBJ_DTYPE_INT);
        $this->initVar('topic_views', XOBJ_DTYPE_INT);
        $this->initVar('topic_replies', XOBJ_DTYPE_INT);
        $this->initVar('topic_last_post_id', XOBJ_DTYPE_INT);
        $this->initVar('forum_id', XOBJ_DTYPE_INT);
        $this->initVar('topic_status', XOBJ_DTYPE_INT);
        $this->initVar('type_id', XOBJ_DTYPE_INT);
        $this->initVar('topic_sticky', XOBJ_DTYPE_INT);
        $this->initVar('topic_digest', XOBJ_DTYPE_INT);
        $this->initVar('digest_time', XOBJ_DTYPE_INT);
        $this->initVar('approved', XOBJ_DTYPE_INT);
        $this->initVar('poster_name', XOBJ_DTYPE_TXTBOX);
        $this->initVar('rating', XOBJ_DTYPE_OTHER);
        $this->initVar('votes', XOBJ_DTYPE_INT);
        $this->initVar('topic_haspoll', XOBJ_DTYPE_INT);
        $this->initVar('poll_id', XOBJ_DTYPE_INT);
        $this->initVar('topic_tags', XOBJ_DTYPE_SOURCE);
    }

    // irmtfan add LAST_INSERT_ID to enhance the mysql performances
    public function incrementCounter()
    {
        $sql = 'UPDATE ' . $GLOBALS['xoopsDB']->prefix('newbb_topics') . ' SET topic_views = LAST_INSERT_ID(topic_views + 1) WHERE topic_id =' . $this->getVar('topic_id');
        $GLOBALS['xoopsDB']->queryF($sql);
    }

    /**
     * Create full title of the topic
     *
     * the title is composed of [type_name] if type_id is greater than 0 plus topic_title
     *
     */
    public function getFullTitle()
    {
        $topic_title = $this->getVar('topic_title');
        if (!$this->getVar('type_id')) {
            return $topic_title;
        }
        $typeHandler = xoops_getModuleHandler('type', 'newbb');
        if (!$typeObject = $typeHandler->get($this->getVar('type_id'))) {
            return $topic_title;
        }

        include_once __DIR__ . '/../include/functions.topic.php';

        return getTopicTitle($topic_title, $typeObject->getVar('type_name'), $typeObject->getVar('type_color'));
    }
    // START irmtfan loadOldPoll function

    /**
     * Load functions needed for old xoopspoll (older than version 1.4 by zyspec) and umfrage modules
     *
     * @access public
     * @param  string $pollModule dirname of the poll module
     * @return string|false $classPoll = the name of the old poll class eg: "XoopsPoll" | "Umfrage"
     */

    public function loadOldPoll($pollModule = null)
    {
        static $classPoll = false;
        if ($classPoll && empty($pollModule)) {
            return $classPoll;
        }
        $newbbConfig = newbbLoadConfig();
        if (!empty($pollModule)) {
            $newbbConfig['poll_module'] = $pollModule;
        }
        $relPath = $GLOBALS['xoops']->path('modules/' . $newbbConfig['poll_module'] . '/class/' . $newbbConfig['poll_module']);
        include_once $relPath . '.php';
        include_once $relPath . 'option.php';
        include_once $relPath . 'log.php';
        include_once $relPath . 'renderer.php';
        $classes = get_declared_classes();
        foreach (array_reverse($classes) as $class) {
            if (strtolower($class) == $newbbConfig['poll_module']) {
                $classPoll = $class;

                return $classPoll;
            }
        }

        return false;
    }
    // END irmtfan loadOldPoll function
    // START irmtfan add deletePoll function
    /**
     * delete a poll in database
     *
     * @access public
     * @param  int $poll_id
     * @return bool
     */
    public function deletePoll($poll_id)
    {
        if (empty($poll_id)) {
            return false;
        }
        /** @var \XoopsModuleHandler $moduleHandler */
        $moduleHandler     = xoops_getHandler('module');
        $newbbConfig       = newbbLoadConfig();
        $pollModuleHandler = $moduleHandler->getByDirname($newbbConfig['poll_module']);
        if (!is_object($pollModuleHandler) || !$pollModuleHandler->getVar('isactive')) {
            return false;
        }
        // new xoopspoll module
        if ($pollModuleHandler->getVar('version') >= 140) {
            /** @var \XoopsPollHandler $pollHandler */
            $pollHandler = xoops_getModuleHandler('poll', $newbbConfig['poll_module']);
            if (false !== $pollHandler->deleteAll(new Criteria('poll_id', $poll_id, '='))) {
                /** @var \XoopspollOptionHandler $optionHandler */
                $optionHandler = xoops_getModuleHandler('option', $newbbConfig['poll_module']);
                $optionHandler->deleteAll(new Criteria('poll_id', $poll_id, '='));
                /** @var \XoopspollLogHandler $logHandler */
                $logHandler = xoops_getModuleHandler('log', $newbbConfig['poll_module']);
                $logHandler->deleteAll(new Criteria('poll_id', $poll_id, '='));
                xoops_comment_delete($GLOBALS['xoopsModule']->getVar('mid'), $poll_id);
            }
            // old xoopspoll or umfrage or any clone from them
        } else {
            $classPoll = $this->loadOldPoll();
            /** @var \XoopsPoll $poll */
            $poll = new $classPoll($poll_id);
            if (false !== $poll->delete()) {
                $classOption = $classPoll . 'Option';
                $classOption::deleteByPollId($poll->getVar('poll_id'));
                $classLog = $classPoll . 'Log';
                $classLog::deleteByPollId($poll->getVar('poll_id'));
                xoops_comment_delete($GLOBALS['xoopsModule']->getVar('mid'), $poll->getVar('poll_id'));
            }
        } // end poll_module new or old

        return true;
    }
    // END irmtfan add deletePoll function

    // START irmtfan add getPoll function
    /**
     * get a poll object from a poll module.
     * note: can be used to find if a poll exist in a module
     * @access public
     * @param  int    $poll_id
     * @param  string $pollModule dirname of the poll module
     * @return bool|XoopsObject poll
     */
    public function getPoll($poll_id, $pollModule = null)
    {
        if (empty($poll_id)) {
            return false;
        }
        $moduleHandler = xoops_getHandler('module');
        $newbbConfig   = newbbLoadConfig();
        if (!empty($pollModule)) {
            $newbbConfig['poll_module'] = $pollModule;
        }

        $pollModuleHandler = $moduleHandler->getByDirname($newbbConfig['poll_module']);
        if (!is_object($pollModuleHandler) || !$pollModuleHandler->getVar('isactive')) {
            return false;
        }
        // new xoopspoll module
        if ($pollModuleHandler->getVar('version') >= 140) {
            $pollHandler = xoops_getModuleHandler('poll', $newbbConfig['poll_module']);
            $pollObject    = $pollHandler->get($poll_id);
            // old xoopspoll or umfrage or any clone from them
        } else {
            $classPoll = $this->loadOldPoll($newbbConfig['poll_module']);
            $pollObject  = new $classPoll($poll_id);
        } // end poll_module new or old

        return $pollObject;
    }
    // END irmtfan add getPoll function
}

/**
 * Class NewbbTopicHandler
 */
class NewbbTopicHandler extends XoopsPersistableObjectHandler
{
    /**
     * @param XoopsDatabase|null $db
     */
    public function __construct(XoopsDatabase $db)
    {
        parent::__construct($db, 'newbb_topics', 'Topic', 'topic_id', 'topic_title');
    }

    /**
     * @param  mixed $id
     * @param  null|array  $fields
     * @return mixed|null
     */
    public function get($id = null, $fields = null) //get($id, $var = null)
    {
        $var = $fields;
        $ret  = null;
        $tags = $var;
        if (!empty($var) && is_string($var)) {
            $tags = [$var];
        }
        if (!$topicObject = parent::get($id, $tags)) {
            return $ret;
        }
        $ret = $topicObject;
        if (!empty($var) && is_string($var)) {
            $ret = @$topicObject->getVar($var);
        }

        return $ret;
    }

    /**
     * @param  XoopsObject $object
     * @param  bool        $force
     * @return mixed
     */
    public function insert(XoopsObject $object, $force = true)
    {
        if (!$object->getVar('topic_time')) {
            $object->setVar('topic_time', time());
        }
        if (!parent::insert($object, $force) || !$object->getVar('approved')) {
            return $object->getVar('topic_id');
        }

        $newbbConfig = newbbLoadConfig();
        if (!empty($newbbConfig['do_tag'])
            && @include_once $GLOBALS['xoops']->path('modules/tag/include/functions.php')) {
            if ($tagHandler = tag_getTagHandler()) {
                $tagHandler->updateByItem($object->getVar('topic_tags', 'n'), $object->getVar('topic_id'), 'newbb');
            }
        }

        return $object->getVar('topic_id');
    }

    /**
     * @param       $object
     * @param  bool $force
     * @return bool
     */
    public function approve($object, $force = false)
    {
        $topic_id = $object->getVar('topic_id');
        if ($force) {
            $sql = 'UPDATE ' . $this->db->prefix('newbb_topics') . " SET approved = -1 WHERE topic_id = {$topic_id}";
        } else {
            $sql = 'UPDATE ' . $this->db->prefix('newbb_topics') . " SET approved = 1 WHERE topic_id = {$topic_id}";
        }
        if (!$result = $this->db->queryF($sql)) {
            //xoops_error($this->db->error());
            return false;
        }
        $postHandler = xoops_getModuleHandler('post', 'newbb');
        $postsObject   = $postHandler->getAll(new Criteria('topic_id', $topic_id));
        foreach (array_keys($postsObject) as $post_id) {
            $postHandler->approve($postsObject[$post_id]);
        }
        unset($postsObject);
        $statsHandler = xoops_getModuleHandler('stats', 'newbb');
        $statsHandler->update($object->getVar('forum_id'), 'topic');

        return true;
    }

    /**
     * get previous/next topic
     *
     * @param integer $topic_id current topic ID
     * @param integer $action
     *                          <ul>
     *                          <li> -1: previous </li>
     *                          <li> 0: current </li>
     *                          <li> 1: next </li>
     *                          </ul>
     * @param integer $forum_id the scope for moving
     *                          <ul>
     *                          <li> >0 : inside the forum </li>
     *                          <li> <= 0: global </li>
     *                          </ul>
     * @access public
     * @return mixed|null|XoopsObject
     */
    public function &getByMove($topic_id, $action, $forum_id = 0)
    {
        $topic = null;
        if (!empty($action)) {
            $sql = 'SELECT * FROM ' . $this->table . ' WHERE 1=1' . (($forum_id > 0) ? ' AND forum_id=' . (int)$forum_id : '') . ' AND topic_id ' . (($action > 0) ? '>' : '<') . (int)$topic_id . ' ORDER BY topic_id ' . (($action > 0) ? 'ASC' : 'DESC') . ' LIMIT 1';
            if ($result = $this->db->query($sql)) {
                if ($row = $this->db->fetchArray($result)) {
                    $topic = $this->create(false);
                    $topic->assignVars($row);

                    return $topic;
                }
            }
        }
        $topic = $this->get($topic_id);

        return $topic;
    }

    /**
     * @param $post_id
     * @return null|XoopsObject
     */
    public function &getByPost($post_id)
    {
        $topic  = null;
        $sql    = 'SELECT t.* FROM ' . $this->db->prefix('newbb_topics') . ' t, ' . $this->db->prefix('newbb_posts') . ' p
                WHERE t.topic_id = p.topic_id AND p.post_id = ' . (int)$post_id;
        $result = $this->db->query($sql);
        if (!$result) {
            //xoops_error($this->db->error());
            return $topic;
        }
        $row   = $this->db->fetchArray($result);
        $topic = $this->create(false);
        $topic->assignVars($row);

        return $topic;
    }

    /**
     * @param  Topic  $topic
     * @param  string $type
     * @return mixed
     */
    public function getPostCount(&$topic, $type = '')
    {
        switch ($type) {
            case 'pending':
                $approved = 0;
                break;
            case 'deleted':
                $approved = -1;
                break;
            default:
                $approved = 1;
                break;
        }
        $criteria = new CriteriaCompo(new Criteria('topic_id', $topic->getVar('topic_id')));
        $criteria->add(new Criteria('approved', $approved));
        /** @var \NewbbPostHandler $postHandler */
        $postHandler = xoops_getModuleHandler('post', 'newbb');
        $count       = $postHandler->getCount($criteria);

        return $count;
    }

    /**
     * @param $topic_id
     * @return null|\NewbbPost
     */
    public function &getTopPost($topic_id)
    {
        $post = null;
        $sql  = 'SELECT p.*, t.* FROM ' . $this->db->prefix('newbb_posts') . ' p,
            ' . $this->db->prefix('newbb_posts_text') . ' t
            WHERE
            p.topic_id = ' . $topic_id . ' AND p.pid = 0
            AND t.post_id = p.post_id';

        $result = $this->db->query($sql);
        if (!$result) {
            //xoops_error($this->db->error());
            return $post;
        }
        /** @var \NewbbPostHandler $postHandler */
        $postHandler = xoops_getModuleHandler('post', 'newbb');
        $myrow       = $this->db->fetchArray($result);
        /** @var \NewbbPost $post */
        $post = $postHandler->create(false);
        $post->assignVars($myrow);

        return $post;
    }

    /**
     * @param $topic_id
     * @return bool
     */
    public function getTopPostId($topic_id)
    {
        $sql    = 'SELECT MIN(post_id) AS post_id FROM ' . $this->db->prefix('newbb_posts') . ' WHERE topic_id = ' . $topic_id . ' AND pid = 0';
        $result = $this->db->query($sql);
        if (!$result) {
            //xoops_error($this->db->error());
            return false;
        }
        list($post_id) = $this->db->fetchRow($result);

        return $post_id;
    }

    /**
     * @param         $topic
     * @param  string $order
     * @param  int    $perpage
     * @param         $start
     * @param  int    $post_id
     * @param  string $type
     * @return array
     */
    public function &getAllPosts(&$topic, $order = 'ASC', $perpage = 10, &$start, $post_id = 0, $type = '')
    {
        $ret     = [];
        $perpage = ((int)$perpage > 0) ? (int)$perpage : (empty($GLOBALS['xoopsModuleConfig']['posts_per_page']) ? 10 : $GLOBALS['xoopsModuleConfig']['posts_per_page']);
        $start   = (int)$start;
        switch ($type) {
            case 'pending':
                $approveCriteria = ' AND p.approved = 0';
                break;
            case 'deleted':
                $approveCriteria = ' AND p.approved = -1';
                break;
            default:
                $approveCriteria = ' AND p.approved = 1';
                break;
        }

        if ($post_id) {
            if ('DESC' === $order) {
                $operator_for_position = '>';
            } else {
                $order                 = 'ASC';
                $operator_for_position = '<';
            }
            //$approveCriteria = ' AND approved = 1'; // any others?
            $sql    = 'SELECT COUNT(*) FROM ' . $this->db->prefix('newbb_posts') . ' AS p WHERE p.topic_id=' . (int)$topic->getVar('topic_id') . $approveCriteria . " AND p.post_id $operator_for_position $post_id";
            $result = $this->db->query($sql);
            if (!$result) {
                //xoops_error($this->db->error());
                return $ret;
            }
            list($position) = $this->db->fetchRow($result);
            $start = (int)($position / $perpage) * $perpage;
        }

        $sql    = 'SELECT p.*, t.* FROM ' . $this->db->prefix('newbb_posts') . ' p, ' . $this->db->prefix('newbb_posts_text') . ' t WHERE p.topic_id=' . $topic->getVar('topic_id') . ' AND p.post_id = t.post_id' . $approveCriteria . " ORDER BY p.post_id $order";
        $result = $this->db->query($sql, $perpage, $start);
        if (!$result) {
            //xoops_error($this->db->error());
            return $ret;
        }
        $postHandler = xoops_getModuleHandler('post', 'newbb');
        while ($myrow = $this->db->fetchArray($result)) {
            $post = $postHandler->create(false);
            $post->assignVars($myrow);
            $ret[$myrow['post_id']] = $post;
            unset($post);
        }

        return $ret;
    }

    /**
     * @param        $postArray
     * @param  int   $pid
     * @return mixed
     */
    public function &getPostTree(&$postArray, $pid = 0)
    {
        include_once $GLOBALS['xoops']->path('modules/newbb/class/newbbtree.php');
        $NewBBTree = new NewBBTree('newbb_posts');
        $NewBBTree->setPrefix('&nbsp;&nbsp;');
        $NewBBTree->setPostArray($postArray);
        $NewBBTree->getPostTree($postsArray, $pid);

        return $postsArray;
    }

    /**
     * @param $topic
     * @param $postArray
     * @return mixed
     */
    public function showTreeItem(&$topic, &$postArray)
    {
        global $viewtopic_users, $myts;

        $postArray['post_time'] = newbbFormatTimestamp($postArray['post_time']);

        if (!empty($postArray['icon'])) {
            $postArray['icon'] = '<img src="' . XOOPS_URL . '/images/subject/' . htmlspecialchars($postArray['icon']) . '" alt="" />';
        } else {
            $postArray['icon'] = '<a name="' . $postArray['post_id'] . '"><img src="' . XOOPS_URL . '/images/icons/no_posticon.gif" alt="" /></a>';
        }

        $postArray['subject'] = '<a href="viewtopic.php?viewmode=thread&amp;topic_id=' . $topic->getVar('topic_id') . '&amp;forum=' . $postArray['forum_id'] . '&amp;post_id=' . $postArray['post_id'] . '">' . $postArray['subject'] . '</a>';

        $isActiveUser = false;
        if (isset($viewtopic_users[$postArray['uid']]['name'])) {
            $postArray['poster'] = $viewtopic_users[$postArray['uid']]['name'];
            if ($postArray['uid'] > 0) {
                $postArray['poster'] = '<a href="' . XOOPS_URL . '/userinfo.php?uid=' . $postArray['uid'] . '">' . $viewtopic_users[$postArray['uid']]['name'] . '</a>';
            }
        } else {
            $postArray['poster'] = empty($postArray['poster_name']) ? $myts->htmlSpecialChars($GLOBALS['xoopsConfig']['anonymous']) : $postArray['poster_name'];
        }

        return $postArray;
    }

    /**
     * @param        $topic
     * @param  bool  $isApproved
     * @return array
     */
    public function &getAllPosters(&$topic, $isApproved = true)
    {
        $sql = 'SELECT DISTINCT uid FROM ' . $this->db->prefix('newbb_posts') . '  WHERE topic_id=' . $topic->getVar('topic_id') . ' AND uid>0';
        if ($isApproved) {
            $sql .= ' AND approved = 1';
        }
        $result = $this->db->query($sql);
        if (!$result) {
            //xoops_error($this->db->error());
            return [];
        }
        $ret = [];
        while ($myrow = $this->db->fetchArray($result)) {
            $ret[] = $myrow['uid'];
        }

        return $ret;
    }

    /**
     * @param  XoopsObject|Topic $topic
     * @param  bool              $force
     * @return bool
     */
    public function delete(XoopsObject $topic, $force = true)
    {
        $topic_id = is_object($topic) ? $topic->getVar('topic_id') : (int)$topic;
        if (empty($topic_id)) {
            return false;
        }
        $postObject = $this->getTopPost($topic_id);
        /** @var \NewbbPostHandler $postHandler */
        $postHandler = xoops_getModuleHandler('post', 'newbb');
        $postHandler->delete($postObject, false, $force);

        $newbbConfig = newbbLoadConfig();
        /** @var \TagTagHandler $tagHandler */
        if (!empty($newbbConfig['do_tag']) && $tagHandler = @xoops_getModuleHandler('tag', 'tag', true)) {
            $tagHandler->updateByItem([], $topic_id, 'newbb');
        }

        return true;
    }

    // get permission
    // parameter: $type: 'post', 'view',  'reply', 'edit', 'delete', 'addpoll', 'vote', 'attach'
    // $gperm_names = "'forum_can_post', 'forum_can_view', 'forum_can_reply', 'forum_can_edit', 'forum_can_delete', 'forum_can_addpoll', 'forum_can_vote', 'forum_can_attach', 'forum_can_noapprove'";
    /**
     * @param   \NewbbForum $forum
     * @param  int          $topic_locked
     * @param  string       $type
     * @return bool
     */
    public function getPermission($forum, $topic_locked = 0, $type = 'view')
    {
        static $_cachedTopicPerms;
        include_once __DIR__ . '/../include/functions.user.php';
        if (newbbIsAdmin($forum)) {
            return true;
        }

        $forum_id = is_object($forum) ? $forum->getVar('forum_id') : (int)$forum;
        if ($forum_id < 1) {
            return false;
        }

        if ($topic_locked && 'view' !== $type) {
            $permission = false;
        } else {
            /** @var \NewbbPermissionHandler $permHandler */
            $permHandler = xoops_getModuleHandler('permission', 'newbb');
            $permission  = $permHandler->getPermission('forum', $type, $forum_id);
        }

        return $permission;
    }

    /**
     * clean orphan items from database
     *
     * @param  string $table_link
     * @param  string $field_link
     * @param  string $field_object
     * @return bool   true on success
     */
    public function cleanOrphan($table_link = '', $field_link = '', $field_object = '') //cleanOrphan()
    {
        $this->deleteAll(new Criteria('topic_time', 0), true, true);
        parent::cleanOrphan($this->db->prefix('newbb_forums'), 'forum_id');
        parent::cleanOrphan($this->db->prefix('newbb_posts'), 'topic_id');

        return true;
    }

    /**
     * clean expired objects from database
     *
     * @param  int $expire time limit for expiration
     * @return bool true on success
     */
    public function cleanExpires($expire = 0)
    {
        // irmtfan if 0 no cleanup look include/plugin.php
        if (!func_num_args()) {
            $newbbConfig = newbbLoadConfig();
            $expire      = isset($newbbConfig['pending_expire']) ? (int)$newbbConfig['pending_expire'] : 7;
            $expire      = $expire * 24 * 3600; // days to seconds
        }
        if (empty($expire)) {
            return false;
        }
        $crit_expire = new CriteriaCompo(new Criteria('approved', 0, '<='));
        $crit_expire->add(new Criteria('topic_time', time() - (int)$expire, '<'));

        return $this->deleteAll($crit_expire, true/*, true*/);
    }

    // START irmtfan - rewrite topic synchronization function. add pid sync and remove hard-code db access

    /**
     * @param  null $object
     * @param  bool $force
     * @return bool
     */
    public function synchronization($object = null, $force = true)
    {
        if (!is_object($object)) {
            $object = $this->get((int)$object);
        }
        if (!is_object($object) || !$object->getVar('topic_id')) {
            return false;
        }

        /** @var \NewbbPostHandler $postHandler */
        $postHandler = xoops_getModuleHandler('post', 'newbb');
        $criteria    = new CriteriaCompo();
        $criteria->add(new Criteria('topic_id', $object->getVar('topic_id')), 'AND');
        $criteria->add(new Criteria('approved', 1), 'AND');
        $post_ids = $postHandler->getIds($criteria);
        if (empty($post_ids)) {
            return false;
        }
        $last_post     = max($post_ids);
        $top_post      = min($post_ids);
        $topic_replies = count($post_ids) - 1;
        if ($object->getVar('topic_last_post_id') != $last_post) {
            $object->setVar('topic_last_post_id', $last_post);
        }
        if ($object->getVar('topic_replies') != $topic_replies) {
            $object->setVar('topic_replies', $topic_replies);
        }
        $b1 = $this->insert($object, $force);
        $criteria->add(new Criteria('post_id', $top_post, '<>'), 'AND');
        $criteria->add(new Criteria('pid', '(' . implode(', ', $post_ids) . ')', 'NOT IN'), 'AND');
        $b2       = $postHandler->updateAll('pid', $top_post, $criteria, $force);
        $criteria = new CriteriaCompo();
        $criteria->add(new Criteria('post_id', $top_post, '='), 'AND');
        $b3 = $postHandler->updateAll('pid', 0, $criteria, $force);

        return ($b1 && $b2 && $b3) ? true : false;
    }
    // END irmtfan - rewrite topic synchronization function. add pid sync and remove hard-code db access
    // START irmtfan getActivePolls
    /**
     * get all active poll modules in the current xoops installtion.
     * @access public
     * @return array $pollDirs = array($dirname1=>$dirname1, $dirname2=>$dirname2, ...) dirnames of all active poll modules
     */
    public function getActivePolls()
    {
        $pollDirs = [];
        $allDirs  = xoops_getActiveModules();
        foreach ($allDirs as $dirname) {
            // pollresults.php file is exist in all xoopspoll versions and umfrage versions
            if (file_exists($GLOBALS['xoops']->path('modules/' . $dirname . '/pollresults.php'))) {
                $pollDirs[$dirname] = $dirname;
            }
        }

        return $pollDirs;
    }
    // END irmtfan getActivePolls

    // START irmtfan findPollModule
    /**
     * find poll module that is in used in the current newbb installtion.
     * @access public
     * @param  array $pollDirs dirnames of all active poll modules
     * @return bool|string $dir_def | true | false
     *                         $dir_def: dirname of poll module that is in used in the current newbb installtion.
     *                         true: no poll module is installed | newbb has no topic with poll | newbb has no topic
     *                         false: errors (see below xoops_errors)
     */
    public function findPollModule(array $pollDirs = [])
    {
        $dir_def = '';
        if (empty($pollDirs)) {
            $pollDirs = $this->getActivePolls();
        }
        if (empty($pollDirs)) {
            return true;
        }
        // if only one active poll module still we need to check!!!
        //if(count($pollDirs) === 1) return end($pollDirs);
        $topicPollObjs = $this->getAll(new Criteria('topic_haspoll', 1), ['topic_id', 'poll_id']);
        if (empty($topicPollObjs)) {
            return true;
        } // no poll or no topic!!!
        foreach ($topicPollObjs as $tObj) {
            $poll_idInMod = 0;
            foreach ($pollDirs as $dirname) {
                $pollObj = $tObj->getPoll($tObj->getVar('poll_id'), $dirname);
                if (is_object($pollObj) && ($pollObj->getVar('poll_id') == $tObj->getVar('poll_id'))) {
                    ++$poll_idInMod;
                    $dir_def = $dirname;
                }
            }
            // Only one poll module should has this poll_id
            // if 0 there is an error
            if (0 == $poll_idInMod) {
                xoops_error("Error: Cannot find poll module for poll_id='{$tObj->getVar('poll_id')}'");

                return false;
            }
            // if 1 => $dir_def is correct
            if (1 == $poll_idInMod) {
                return $dir_def;
            }
            // if more than 1 continue
        }
        // if there is some topics but no module or more than one module have polls
        xoops_error("Error: Cannot find poll module that is in used in newbb!!! <br\><br\>You should select the correct poll module yourself in newbb > preferences > poll module setting.");

        return false;
    }
    // END irmtfan findPollModule
}
