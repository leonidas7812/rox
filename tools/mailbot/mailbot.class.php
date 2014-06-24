<?php
/**
 * Mailbot is a php script used to automatically send emails to users
 *
 * Copyright (c) 2007 BeVolunteer
 *
 * This file is part of BW Rox.
 *
 * BW Rox is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * BW Rox is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/> or•
 * write to the Free Software Foundation, Inc., 59 Temple Place - Suite 330,•
 * Boston, MA  02111-1307, USA.
 *
 * @category Tools
 * @package  Mailbot
 * @author   Laurent Savaëte (franskmanen) <laurent.savaete@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License (GPL) v2
 * @link     http://www.bewelcome.org
 */

$i_am_the_mailbot = true;

// manually define the script base. mailbot MUST be run from the root directory (like php tools/mailbot/mailbot.class.php)
define('SCRIPT_BASE', dirname(__FILE__) . "/../../");

require_once SCRIPT_BASE . 'roxlauncher/roxloader.php';
require_once SCRIPT_BASE . 'roxlauncher/environmentexplorer.php';

/**
 * Mailbot base class
 *
 * @category  Tools
 * @package   Mailbot
 * @author    Laurent Savaëte (franskmanen) <laurent.savaete@gmail.com>
 * @copyright 2012 BeVolunteer Team
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License (GPL) v2
 * @version   $Id$
 * @link      http://www.bewelcome.org
 */
class Mailbot
{

    protected $count = array(
        'Sent' => 0,
        'Failed' => 0,
        'Freeze' => 0
    );

    /**
     * constructor...
     *
     * @return nothing
     */
    function __construct()
    {
        $this->baseuri = PVars::getObj('env')->baseuri;

        $this->IdTriggerer = 0;   //TODO: set this to bot id

        $this->words = new MOD_words();

        $this->messages_model = new MessagesModel;
        $this->members_model = new MembersModel;

        // setup DB access
        $db_vars = PVars::getObj('config_rdbms');
        if (!$db_vars) {
            throw new PException('DB config error!');
        }
        $this->dao = PDB::get($db_vars->dsn, $db_vars->user, $db_vars->password);
    }

    /**
     * an interface for all DB calls from mailbot
     *
     * @param string $queryString the SQL query to execute
     *
     * @return object the result from the DB call
     */
    protected function queryDB($queryString)
    {
        return $this->dao->query($queryString);
    }

    /**
     * a local replacement for LoadRow
     *
     * @param string $queryString the SQL query to execute
     *
     * @return object the first row returned by the query
     */
    protected function getSingleRow($queryString)
    {
        $qry = $this->queryDB($queryString);
        return $qry->fetch(PDB::FETCH_OBJ);
    }

    /**
     * log all mailbot(s) activities
     *
     * @param string $msg the text to log
     *
     * @return nothing
     */
    protected function log($msg)
    {
        //TODO: check how we are run, and log stuff accordingly
        echo($msg."\n");
    }

    /**
     * actually send out emails using a common BW template
     *
     * @param string $subject   the subject line for the message
     * @param string $from      the email address of the sender
     * @param string $to        the email address of the recipient
     * @param string $body      the plaintext body of the message
     * @param string $title     an optional title to show in the message (HTML H1 tag) (default: false)
     * @param string $body_html the html version of the body (default: false)
     * @param array  $attach    an array of attachments (default: array())
     * @param string $language  the language code used in the message (default: 'en')
     *
     * @return object the result from the MOD_mail::sendEmail call
     */
    protected function sendEmail($subject, $from, $to, $title, $body, $language, $html)
    {
        return MOD_mail::sendEmail($subject, $from, $to, $title, $body, $language, $html);
    }

    /**
     * Log results for the bot execution
     *
     * @return nothing
     */
    protected function reportStats()
    {
        // display statistics
        $this->log("Summary for ".get_class($this).":");
        foreach ($this->count as $status => $total) {
            $this->log($total. ' message(s) '.$status);
        }
    }

}
// -----------------------------------------------------------------------------
// broadcast messages for members (massmail)
// -----------------------------------------------------------------------------

/**
 * the mailbot that sends messages for newsletters, etc...
 *
 * @category  Tools
 * @package   Mailbot
 * @author    Laurent Savaëte (franskmanen) <laurent.savaete@gmail.com>
 * @copyright 2012 BeVolunteer Team
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License (GPL) v2
 * @version   $Id$
 * @link      http://www.bewelcome.org
 */
class MassMailbot extends Mailbot
{
    /**
     * get the list of messages to broadcast from the db
     *
     * @return object the mySQL query result object
     */
    private function _getMessageList()
    {
        $str = "
            SELECT
                broadcastmessages.*,
                Username,
                members.Status AS MemberStatus,
                broadcast.Name AS word,
                broadcast.Type as broadcast_type,
                broadcast.EmailFrom as EmailFrom
            FROM
                broadcast,
                broadcastmessages,
                members
            WHERE
                broadcast.id = broadcastmessages.IdBroadcast AND
                broadcastmessages.IdReceiver = members.id AND
                broadcastmessages.Status = 'ToSend' limit 100
                ";
        return $this->queryDB($str);
    }

    /**
     * update message status in the DB
     *
     * @param int    $id       the message id in the db
     * @param string $status   the status to set for the message
     * @param int    $receiver the id of the email recipient
     *
     * @return object the result from the DB call
     */
    private function _updateMessageStatus($id, $status, $receiver)
    {
        $this->count[$status]++;
        $str = "UPDATE broadcastmessages
            SET Status = '$status'
            WHERE IdBroadcast = $id AND IdReceiver = $receiver";
        return $this->queryDB($str);
    }

    /**
     * Actually run the bot
     *
     * @return nothing
     */
    public function run()
    {
        $qry = $this->_getMessageList();
        while ($msg = $qry->fetch(PDB::FETCH_OBJ)) {
            $Email = GetEmail($msg->IdReceiver);
            $language = GetDefaultLanguage($msg->IdReceiver);
            $subj = $this->words->getFormattedInLang("BroadCast_Title_".$msg->word, $language, $msg->Username);
            $text = $this->words->getFormattedInLang("BroadCast_Body_".$msg->word, $language, $msg->Username);

            if (empty($msg->EmailFrom)) {
                $sender_mail="newsletter@bewelcome.org" ;
                if ($msg->broadcast_type=="RemindToLog") {
                    $sender_mail="reminder@bewelcome.org" ;
                }
            } else {
                $sender_mail=$msg->EmailFrom ;
            }
            if (!$this->sendEmail($subj, $sender_mail, $Email, $subj, $text, $language)) {
                $this->_updateMessageStatus($msg->IdBroadcast, 'Failed', $msg->IdReceiver);
                $this->log("Cannot send broadcastmessages.id=#" . $msg->IdBroadcast . " to <b>".$msg->Username."</b> \$Email=[".$Email."] Type=[".$msg->broadcast_type."]", "mailbot");
            } else {
                if ($msg->broadcast_type == "RemindToLog") {
                    $this->queryDB("update members set NbRemindWithoutLogingIn=NbRemindWithoutLogingIn+1 where members.id=".$msg->IdReceiver);
                }
                $this->_updateMessageStatus($msg->IdBroadcast, 'Sent', $msg->IdReceiver);
            }
        }
        $this->reportStats();
    }
}

// -----------------------------------------------------------------------------
// Forums/groups notifications
// -----------------------------------------------------------------------------
/**
 * the mailbot that sends messages for forum/group posts
 *
 * @category  Tools
 * @package   Mailbot
 * @author    Laurent Savaëte (franskmanen) <laurent.savaete@gmail.com>
 * @copyright 2012 BeVolunteer Team
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License (GPL) v2
 * @version   $Id$
 * @link      http://www.bewelcome.org
 */
class ForumNotificationMailbot extends Mailbot
{

    /**
     * get the list of notifications (new posts) to send out
     *
     * @param int $grace_period the number of minutes before which to email out notifications for new posts
     *
     * @return object the mySQL query object
     */
    private function _getNotificationList($grace_period)
    {
        $str = "
            SELECT
                posts_notificationqueue.*,
                Username
            FROM
                posts_notificationqueue
            RIGHT JOIN members ON posts_notificationqueue.IdMember = members.id  AND
                (members.Status = 'Active' OR members.Status = 'ActiveHidden')
            WHERE
                posts_notificationqueue.Status = 'ToSend' AND
                (posts_notificationqueue.created < subtime(now(), sec_to_time($grace_period *60)) OR NOT
                (posts_notificationqueue.Type = 'newthread' OR
                posts_notificationqueue.Type = 'reply'));
                ";
        return $this->queryDB($str);
    }

    /**
     * get the group/forum post from the DB
     *
     * @param int $postId the unique id for the post
     *
     * @return object the data about the post
     */
    private function _getPost($postId)
    {
        return $this->getSingleRow("
            SELECT
                forums_posts.*,
                members.Username,
                members.id AS IdMember,
                forums_threads.title AS thread_title,
                forums_threads.IdTitle,
                forums_threads.threadid AS IdThread,
                forums_threads.IdGroup AS groupId,
                forums_posts.message,
                forums_posts.IdContent,
                geonames_cache.name AS cityname,
                geonames_cache2.name AS countryname
            FROM
                forums_posts,
                forums_threads,
                members,
                geonames_cache,
                geonames_cache as geonames_cache2
            WHERE
                forums_threads.threadid = forums_posts.threadid  AND
                forums_posts.IdWriter = members.id  AND
                forums_posts.postid = $postId AND
                geonames_cache.geonameid = members.IdCity  AND
                geonames_cache2.geonameid = geonames_cache.parentCountryId;"
        );
    }

    private function _getGroupName($groupId) {
        return $this->getSingleRow("
            SELECT
                Name
            FROM
                groups
            WHERE
                id = $groupId
        ");
    }

    /**
     * build a url to unsubscribe from forum notifications
     *
     * @param object $notification     the notification object returned by the SQL query
     * @param string $MemberIdLanguage the language code to use
     *
     * @return string the url to unsubscribe
     */
    private function _buildUnsubscribeLink($notification, $MemberIdLanguage)
    {
        $link="" ;
        if ($notification->IdSubscription!=0) { // Compute the unsubscribe link according to the table where the subscription was coming from
            $rSubscription = $this->getSingleRow(
                "SELECT
                  *
                FROM
                  $notification->TableSubscription
                WHERE
                  id = $notification->IdSubscription"
            );
            if ($notification->TableSubscription == "members_threads_subscribed") {
                $link = '<a href="'.$this->baseuri.'forums/subscriptions/unsubscribe/thread/'.$rSubscription->id.'/'.$rSubscription->UnSubscribeKey.'">'.$this->words->getFormattedInLang('ForumUnSubscribe', $MemberIdLanguage).'</a>';
            }
        } elseif ($notification->TableSubscription == 'membersgroups') {
            $link = "----<br/><br/>\n\n" . $this->words->getFormattedInLang('ForumUnSubscribeGroup', $MemberIdLanguage);
        }
        return $link;
    }

    /**
     * update the message status in the DB
     *
     * @param int    $id     the message identifier in the DB
     * @param string $status the status to set for the message
     *
     * @return nothing
     */
    private function _updateNotificationStatus($id, $status)
    {
        $str = "
            UPDATE
                posts_notificationqueue
            SET
                posts_notificationqueue.Status = '$status'
            WHERE
                posts_notificationqueue.id = $id
            ";
        $this->count[$status]++;
        $this->queryDB($str);
    }

    /**
     * return the formatted email content for $msg
     *
     * @param object $notification the message notification object as returned by mysql_fetch_object
     * @param object $post         the post returned by the SQL query
     * @param object $author       the member who wrote the post
     * @param string $language     the language code used for the message
     *
     * @return string the formatted email message body
     */
    private function _buildMessage($notification, $post, $author, $language)
    {
        $msg = array();
        switch ($notification->Type) {
        case 'newthread':
            $NotificationType = '';
            break ;
        case 'reply':
            $NotificationType = 'Re';
            break ;
        case 'moderatoraction':
        case 'deletepost':
        case 'deletethread':
        case 'useredit':
            $NotificationType = $this->words->getFormattedInLang("ForumMailbotEditedPost", $language);
            break ;
        case 'translation':
            break ;
        case 'buggy':
        default :
            $word->$text="Problem in forum notification Type=".$notification->Type."<br />" ;
            break ;
        }

        $msg['subject'] = $NotificationType.": ".$post->thread_title;
        if ($post->groupId) {
            $msg['subject'] .= ' [' . $this->_getGroupName($post->groupId)->Name . ']';
        }

        $text ='<table border="0" cellpadding="0" cellspacing="10" style="margin: 20px; background-color: #fff; font-family:Arial, Helvetica, sans-serif; font-size:12px; color: #333;">' ;
        $text.='<tr><th colspan="2"  align="left"><a href="'.$this->baseuri.'forums/s'.$post->IdThread.'">'.$post->thread_title.'</a></th></tr>' ;
        $text.='<tr><td colspan="2">from: <a href="'.$this->baseuri.'members/'.$author->Username.'">'.$author->Username.'</a> ('.$author->City.', '.$author->Country.')</td></tr>' ;
        $text.='<tr><td valign="top">';

        $PictureFilePath = $this->baseuri.'members/avatar/'.$author->Username ;
        $text .= '<img alt="picture of '.$author->Username.'" src="'.$PictureFilePath.'"/>';
        $text .= '</td><td>'.$post->message.'</td></tr>';

        $UnsubscribeLink = $this->_buildUnsubscribeLink($notification, $language);
        if ($UnsubscribeLink!="") {
            $text .= '<tr><td colspan="2">'.$UnsubscribeLink.'</td></tr>';
        } else {
            // This case should be for moderators only
            $text .= '<tr><td colspan="2"> IdPost #'.$notification->IdPost.' action='.$NotificationType.'</td></tr>';
        }
        $text .= '</table>';

        $msg['body'] = $text;

        return $msg;
    }

    /**
     * Actually run the bot
     *
     * @return nothing
     */
    public function run()
    {
        $grace_period = 0; // minutes, don't email notifications until after grace period to allow author to edit post
        $qry = $this->_getNotificationList($grace_period);
        while ($notification = $qry->fetch(PDB::FETCH_OBJ)) {

            $post = $this->_getPost($notification->IdPost);
            $author = $this->members_model->getMemberWithId($post->IdWriter);
            // Skip to next item in queue if there was no result from database
            if (!is_object($post)) {
                continue;
            }

            $recipient = $this->members_model->getMemberWithId($notification->IdMember);

            // Rewrite the title and the message to the corresponding default language for this member if any
            $post->thread_title = $this->words->fTrad($post->IdTitle);
            $post->message = $this->words->fTrad($post->IdContent);
            $post->message = str_replace('<p><br>\n</p>', '', $post->message);

            $MemberIdLanguage = $recipient->getLanguagePreference();
            $msg = $this->_buildMessage($notification, $post, $author, $MemberIdLanguage);

            if ($post->groupId) {
                $from = array('group@bewelcome.org' => '"BW ' . $recipient->Username . '"');
            } else {
                $from = array('forum@bewelcome.org' => '"BW ' . $recipient->Username . '"');
            }

            $to = $recipient->get_email();
            if (empty($to)) {
                continue;
            }
            if (!$this->sendEmail($msg['subject'], $from, $to, $msg['subject'], $msg['body'], $MemberIdLanguage)) {
                $this->_updateNotificationStatus($notification->id, 'Failed');
                $this->log("Could not send posts_notificationqueue=#" . $notification->id . " to <b>".$post->Username."</b> \$Email=[".$Email."]", "mailbot");
            } else {
                $this->_updateNotificationStatus($notification->id, 'Sent');
            }
        }
        $this->reportStats();

    }
} // class ForumNotificationMailbot


// -----------------------------------------------------------------------------
// Normal messages between members
// -----------------------------------------------------------------------------
/**
 * the mailbot that sends private messages between members
 *
 * @category  Tools
 * @package   Mailbot
 * @author    Laurent Savaëte (franskmanen) <laurent.savaete@gmail.com>
 * @copyright 2012 BeVolunteer Team
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License (GPL) v2
 * @version   $Id$
 * @link      http://www.bewelcome.org
 */
class MemberToMemberMailbot extends Mailbot
{
    /**
     * get the list of messages to be sent from the database
     *
     * @return object a mysql query object
     */
    private function _getMessageList()
    {
        return $this->messages_model->filteredMailbox(
            array(
                'messages.Status = "ToSend"',
                'messages.MessageType = "MemberToMember"'
                )
        );
    }

    /**
     * return the formatted email content for $msg
     *
     * @param object $message the msg object as returned by the SQL query
     * @param bool   $html    whether to format message in html (true) or plaintext (false)
     *
     * @return string the formatted email message body
     */
    private function _formatMessage($message)
    {
        $inboxUrl = $this->baseuri."messages";
        $messageUrl = $inboxUrl . '/' . $message->id;
        $purifier = MOD_htmlpure::get()->getPurifier();
        $direction_in = true;   // true means received message (false is sent)

        $contact_username = $this->Sender->Username;
        $contactProfileUrl = $this->baseuri.'members/'.$contact_username;
        $member = $this->Sender;

        $languages = $this->Sender->get_languages_spoken();
        $words = $this->words;
        $templateUsedInEmail = true;
        $baseuri = $this->baseuri;

        ob_start();
        include SCRIPT_BASE . 'tools/mailbot/templates/readMessage.php';
        $text = ob_get_contents();
        ob_end_clean();

        return $text;
    }

    /**
     * update the DB with new message statuses
     *
     * @param int    $msgId       the id of the message for which to update the DB
     * @param string $status      the status to set for the message
     * @param int    $IdTriggerer the user id of the user running the bot (default to 0)
     *
     * @return nothing
     */
    private function _updateMessageStatus($msgId, $status, $IdTriggerer = 0)
    {
        $status_values = Array('Sent', 'Failed', 'Freeze');
        if (!in_array($status, $status_values)) {
            die("ERROR! Mailbot is trying to set some incorrect Status for a message.");
        }

        $this->messages_model->markSent($msgId, $status, $IdTriggerer);

        $this->count[$status]++;
    }

    /**
     *
     */
    private function _calculateReplyAddress() {
        return PVars::getObj('syshcvol')->MessageSenderMail;
    }

    /**
     * Actually run the bot
     *
     * @return nothing
     */
    public function run()
    {

        $msg_list = $this->_getMessageList();

        foreach ($msg_list as $msg) {
            $FreezeMsgFor = Array('Active', 'ActiveHidden', 'NeedMore', 'Pending');
            $this->Sender = $this->members_model->getMemberWithId($msg->IdSender);
            $this->Receiver = $this->members_model->getMemberWithId($msg->IdReceiver);

            if (!in_array($this->Sender->Status, $FreezeMsgFor)) {
                // Don't send messages from e.g. banned members, unless it is a reply
                // TODO: replies are marked with IdParent != 0 in DB, check that earlier than in markMsgStatus if possible

                $this->_updateMessageStatus($msg->id, 'Freeze');
                $this->log("Message ".$msg->id." from ".$msg->senderUsername." is rejected (".$this->Sender->Status.")", "mailbot");
            } else {
                $from = array($this->_calculateReplyAddress() => '"BW ' . $msg->senderUsername . '"' );
                $to = $this->Receiver->get_email();
                if (empty($to)) {
                    $this->_updateMessageStatus($msg->id, 'Failed');
                    continue;
                }
                $MemberIdLanguage = $this->Receiver->getLanguagePreference();
                $memberPrefersHtml = true;
                if ($this->Receiver->getPreference('PreferenceHtmlMails') == 'No') {
                    $memberPrefersHtml = false;
                }
                $subject = $this->words->get("YouveGotAMail", $this->Sender->Username);
                $body = $this->_formatMessage($msg);

                // send email and update DB according to result
                if (!$this->sendEmail($subject, $from, $to, $subject, $body, $MemberIdLanguage, $memberPrefersHtml)) {
                    $this->_updateMessageStatus($msg->id, 'Failed');
                    $this->log("Cannot send messages.id=#" . $msg->id . " to <b>".$this->Receiver->Username."</b> \$Email=[".$to."]", "mailbot");
                } else {
                    $this->_updateMessageStatus($msg->id, 'Sent');
                }
            }
        }
        $this->reportStats();
    }

}   // class MemberToMemberMailbot
/**
 * main function instantiating and running the mailbots
 *
 * @return none
 */
function runMailbots()
{
    // load Rox environment
    $env_explorer = new EnvironmentExplorer;
    $env_explorer->initializeGlobalState();

    $m2mbot = new MemberToMemberMailbot();
    $m2mbot->run();

    $forum_bot = new ForumNotificationMailbot();
    $forum_bot->run();

    $massmailbot = new MassMailbot();
    $massmailbot->run();
}

runMailbots();

?>
