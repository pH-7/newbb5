<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/
/**
 * NewBB module for xoops
 *
 * @copyright       XOOPS Project (https://xoops.org)
 * @license         GPL 2.0 or later
 * @package         newbb
 * @since           4.33
 * @min_xoops       2.5.8
 * @author          XOOPS Development Team - Email:<name@site.com> - Website:<https://xoops.org>
 */

// defined('XOOPS_ROOT_PATH') || exit('Restricted access.');

define('NEWBB_DIRNAME', basename(dirname(__DIR__)));
define('NEWBB_URL', XOOPS_URL . '/modules/' . NEWBB_DIRNAME);
define('NEWBB_PATH', XOOPS_ROOT_PATH . '/modules/' . NEWBB_DIRNAME);
define('NEWBB_IMAGES_URL', NEWBB_URL . '/assets/images');
define('NEWBB_ADMIN_URL', NEWBB_URL . '/admin');
define('NEWBB_ADMIN_PATH', NEWBB_PATH . '/admin/index.php');
define('NEWBB_ROOT_PATH', $GLOBALS['xoops']->path('modules/' . NEWBB_DIRNAME));
define('NEWBB_AUTHOR_LOGOIMG', NEWBB_URL . '/assets/images/logo_module.png');
define('NEWBB_UPLOAD_URL', XOOPS_UPLOAD_URL . '/' . NEWBB_DIRNAME); // WITHOUT Trailing slash
define('NEWBB_UPLOAD_PATH', XOOPS_UPLOAD_PATH . '/' . NEWBB_DIRNAME); // WITHOUT Trailing slash

// module information
$mod_copyright = "<a href='https://xoops.org' title='XOOPS Project' target='_blank'>
                     <img src='" . NEWBB_AUTHOR_LOGOIMG . "' alt='XOOPS Project' /></a>";

xoops_loadLanguage('common', NEWBB_DIRNAME);

//xoops_load('constants', NEWBB_DIRNAME);
xoops_load('utility', NEWBB_DIRNAME);
//xoops_load('XoopsRequest');
//xoops_load('XoopsFilterInput');

require_once NEWBB_ROOT_PATH . '/class/helper.php';

$debug     = false;
$helper = Newbb::getInstance($debug);

//This is needed or it will not work in blocks.
global $newbbIsAdmin;

// Load only if module is installed
if (is_object($helper->getModule())) {
    // Find if the user is admin of the module
    $publisherIsAdmin = NewbbUtility::userIsAdmin();
}

$db = \XoopsDatabaseFactory::getDatabase();

/** @var \NewbbCategoryHandler $categoryHandler */
$categoryHandler = $helper->getHandler('category');
/** @var \NewbbDigestHandler $digestHandler */
$digestHandler = $helper->getHandler('digest');
/** @var \NewbbForumHandler $forumHandler */
$forumHandler = $helper->getHandler('forum');
/** @var \NewbbIconHandler $iconHandler */
$iconHandler = $helper->getHandler('icon');
/** @var \NewbbKarmaHandler $karmaHandler */
$karmaHandler = $helper->getHandler('karma');
/** @var \NewbbModerateHandler $moderateHandler */
$moderateHandler = $helper->getHandler('moderate');
/** @var \NewbbOnlineHandler $onlineHandler */
$onlineHandler = $helper->getHandler('online');
/** @var \NewbbPermissionHandler $permHandler */
$permHandler = $helper->getHandler('permission');
/** @var \NewbbPostHandler $postHandler */
$postHandler = $helper->getHandler('post');
/** @var \NewbbRateHandler $rateHandler */
$rateHandler = $helper->getHandler('rate');
/** @var \NewbbReadHandler $readHandler */
//$readHandler = $helper->getHandler('read' . $type);
/** @var \NewbbReadForumHandler $readForumHandler */
$readForumHandler = $helper->getHandler('readforum');
/** @var \NewbbReadtopicHandler $readTopicHandler */
$readTopicHandler = $helper->getHandler('readtopic');
/** @var \NewbbReportHandler $reportHandler */
$reportHandler = $helper->getHandler('report');
/** @var \NewbbStatsHandler $statsHandler */
$statsHandler = $helper->getHandler('stats');
/** @var \NewbbTextHandler $textHandler */
$textHandler = $helper->getHandler('text');
/** @var \NewbbTopicHandler $topicHandler */
$topicHandler = $helper->getHandler('topic');
/** @var \NewbbTypeHandler $typeHandler */
$typeHandler = $helper->getHandler('type');
/** @var \NewbbUserstatsHandler $userstatsHandler */
$userstatsHandler = $helper->getHandler('userstats');
/** @var \NewbbXmlrssHandler $xmlrssHandler */
$xmlrssHandler = $helper->getHandler('xmlrss');
