<?php
/*-------------------------------------------------------+
| PHP-Fusion Content Management System
| Copyright (C) PHP-Fusion Inc
| https://www.php-fusion.co.uk/
+--------------------------------------------------------+
| Filename: Viewthread.php
| Author: Chan (Frederick MC Chan)
+--------------------------------------------------------+
| This program is released as free software under the
| Affero GPL license. You can redistribute it and/or
| modify it under the terms of this license which you
| can read by viewing the included agpl.txt or online
| at www.gnu.org/licenses/agpl.html. Removal of this
| copyright header is strictly prohibited without
| written permission from the original author(s).
+--------------------------------------------------------*/

namespace PHPFusion\Forums\Post;

use PHPFusion\Forums\ForumServer;

class NewThread extends ForumServer {

    public $info = array();

    /**
     * New thread
     */
    public function set_newThreadInfo() {

        $userdata = fusion_get_userdata();

        $locale = fusion_get_locale("", FORUM_LOCALE);
        $locale += fusion_get_locale("", FORUM_TAGS_LOCALE);

        $forum_settings = ForumServer::get_forum_settings();

        // @todo: Reduce lines and optimize further

        if (iMEMBER) {

            // New thread directly to a specified forum
            if (!empty($_GET['forum_id']) && ForumServer::verify_forum($_GET['forum_id'])) {

                add_to_title($locale['forum_0000']);
                add_to_meta("description", $locale['forum_0000']);
                add_breadcrumb(array("link" => FORUM."index.php", "title" => $locale['forum_0000']));
                add_to_title($locale['global_201'].$locale['forum_0057']);

                $forum_data = dbarray(dbquery("SELECT f.*, f2.forum_name AS forum_cat_name
				FROM ".DB_FORUMS." f
				LEFT JOIN ".DB_FORUMS." f2 ON f.forum_cat=f2.forum_id
				WHERE f.forum_id='".intval($_GET['forum_id'])."'
				AND ".groupaccess('f.forum_access')."
				"));

                if ($forum_data['forum_type'] == 1) { redirect(INFUSIONS."forum/index.php"); }

                // Use the new permission settings
                self::setPermission($forum_data);

                $forum_data['lock_edit'] = $forum_settings['forum_edit_lock'];

                if (self::getPermission("can_post") && self::getPermission("can_access")) {

                    add_breadcrumb(array(
                                       'link' => INFUSIONS.'forum/index.php?viewforum&amp;forum_id='.$forum_data['forum_id'].'&amp;parent_id='.$forum_data['forum_cat'],
                                       'title' => $forum_data['forum_name']
                                   ));

                    add_breadcrumb(array(
                                       'link' => INFUSIONS.'forum/index.php?viewforum&amp;forum_id='.$forum_data['forum_id'].'&amp;parent_id='.$forum_data['forum_cat'],
                                       'title' => $locale['forum_0057']
                                   ));

                    /**
                     * Generate a poll form
                     */
                    $poll_form = "";
                    if (self::getPermission("can_create_poll")) {
                        // initial data to push downwards
                        $pollData = array(
                            'thread_id' => 0,
                            'forum_poll_title' => !empty($_POST['forum_poll_title']) ? form_sanitizer($_POST['forum_poll_title'],
                                                                                                      '',
                                                                                                      'forum_poll_title') : '',
                            'forum_poll_start' => time(), // time poll started
                            'forum_poll_length' => 2, // how many poll options we have
                            'forum_poll_votes' => 0, // how many vote this poll has
                        );
                        // counter of lengths
                        $option_data[1] = "";
                        $option_data[2] = "";
                        // Do a validation if checked add_poll
                        if (isset($_POST['add_poll'])) {
                            $pollData = array(
                                'thread_id' => 0,
                                'forum_poll_title' => isset($_POST['forum_poll_title']) ? form_sanitizer($_POST['forum_poll_title'],
                                                                                                         '',
                                                                                                         'forum_poll_title') : '',
                                'forum_poll_start' => time(), // time poll started
                                'forum_poll_length' => count($option_data), // how many poll options we have
                                'forum_poll_votes' => 0, // how many vote this poll has
                            );
                            // calculate poll lengths
                            if (!empty($_POST['poll_options']) && is_array($_POST['poll_options'])) {
                                foreach ($_POST['poll_options'] as $i => $value) {
                                    $option_data[$i] = form_sanitizer($value, '', "poll_options[$i]");
                                }
                            }
                        }

                        if (isset($_POST['add_poll_option']) && isset($_POST['poll_options'])) {
                            // reindex the whole array with blank values.
                            foreach ($_POST['poll_options'] as $i => $value) {
                                $option_data[$i] = form_sanitizer($value, '', "poll_options[$i]");
                            }

                            if (\defender::safe()) {
                                $option_data = array_values(array_filter($option_data));
                                array_unshift($option_data, NULL);
                                unset($option_data[0]);
                                $pollData['forum_poll_length'] = count($option_data);
                            }
                            array_push($option_data, '');
                        }
                        $poll_field = '';
                        $poll_field['poll_field'] = form_text('forum_poll_title', $locale['forum_0604'],
                                                              $pollData['forum_poll_title'], array(
                                                                  'max_length' => 255,
                                                                  'placeholder' => $locale['forum_0604a'],
                                                                  'inline' => TRUE,
                                                                  'required' => TRUE
                                                              ));
                        for ($i = 1; $i <= count($option_data); $i++) {
                            $poll_field['poll_field'] .= form_text("poll_options[$i]", sprintf($locale['forum_0606'], $i),
                                                                   $option_data[$i], array(
                                                                       'max_length' => 255,
                                                                       'placeholder' => $locale['forum_0605'],
                                                                       'inline' => TRUE,
                                                                       'required' => $i <= 2 ? TRUE : FALSE
                                                                   ));
                        }
                        $poll_field['poll_field'] .= "<div class='col-xs-12 col-sm-offset-3'>\n";
                        $poll_field['poll_field'] .= form_button('add_poll_option', $locale['forum_0608'],
                                                                 $locale['forum_0608'], array('class' => 'btn-primary btn-sm'));
                        $poll_field['poll_field'] .= "</div>\n";
                        $info = array(
                            'title' => $locale['forum_0366'],
                            'description' => $locale['forum_0630'],
                            'field' => $poll_field
                        );

                        ob_start();
                        echo form_checkbox("add_poll", $locale['forum_0366'], isset($_POST['add_poll']) ? TRUE : FALSE, array('reverse_label'=>TRUE));
                        echo "<div id='poll_form' class='poll-form' style='display:none;'>\n";
                        echo "<div class='well clearfix'>\n";
                        echo "<!--pre_form-->\n";
                        echo $info['field']['poll_field'];
                        echo "</div>\n";
                        echo "</div>\n";
                        $poll_form = ob_get_contents();
                        ob_end_clean();

                    }

                    $thread_data = array(
                        'forum_id' => $forum_data['forum_id'],
                        'thread_id' => 0,
                        'thread_subject' => isset($_POST['thread_subject']) ? form_sanitizer($_POST['thread_subject'], '',
                                                                                             'thread_subject') : '',
                        'thread_tags' => isset($_POST['thread_tags']) ? form_sanitizer($_POST['thread_tags'], '', 'thread_tags') : '',
                        'thread_author' => $userdata['user_id'],
                        'thread_views' => 0,
                        'thread_lastpost' => time(),
                        'thread_lastpostid' => 0, // need to run update
                        'thread_lastuser' => $userdata['user_id'],
                        'thread_postcount' => 1, // already insert 1 postcount.
                        'thread_poll' => 0,
                        'thread_sticky' => isset($_POST['thread_sticky']) ? 1 : 0,
                        'thread_locked' => isset($_POST['thread_sticky']) ? 1 : 0,
                        'thread_hidden' => 0,
                    );

                    $post_data = array(
                        'forum_id' => $forum_data['forum_id'],
                        'forum_cat' => $forum_data['forum_cat'],
                        'thread_id' => 0,
                        'post_id' => 0,
                        'post_message' => isset($_POST['post_message']) ? form_sanitizer($_POST['post_message'], '',
                                                                                         'post_message') : '',
                        'post_showsig' => isset($_POST['post_showsig']) ? 1 : 0,
                        'post_smileys' => !isset($_POST['post_smileys']) || isset($_POST['post_message']) && preg_match("#(\[code\](.*?)\[/code\]|\[geshi=(.*?)\](.*?)\[/geshi\]|\[php\](.*?)\[/php\])#si",
                                                                                                                        $_POST['post_message']) ? 0 : 1,
                        'post_author' => $userdata['user_id'],
                        'post_datestamp' => time(),
                        'post_ip' => USER_IP,
                        'post_ip_type' => USER_IP_TYPE,
                        'post_edituser' => 0,
                        'post_edittime' => 0,
                        'post_editreason' => '',
                        'post_hidden' => 0,
                        'notify_me' => isset($_POST['notify_me']) ? 1 : 0,
                        'post_locked' => 0, //$forum_settings['forum_edit_lock'] || isset($_POST['post_locked']) ? 1 : 0,
                    );

                    // Execute post new thread
                    if (isset($_POST['post_newthread']) && \defender::safe()) {

                        require_once INCLUDES."flood_include.php";
                        // all data is sanitized here.
                        if (!flood_control("post_datestamp", DB_FORUM_POSTS,
                                           "post_author='".$userdata['user_id']."'")
                        ) { // have notice

                            if (\defender::safe()) {
                                // create a new thread.
                                dbquery_insert(DB_FORUM_THREADS, $thread_data, 'save', array(
                                    'primary_key' => 'thread_id',
                                    'keep_session' => TRUE
                                ));
                                $post_data['thread_id'] = dblastid();
                                $pollData['thread_id'] = dblastid();
                                dbquery_insert(DB_FORUM_POSTS, $post_data, 'save', array(
                                    'primary_key' => 'post_id',
                                    'keep_session' => TRUE
                                ));
                                $post_data['post_id'] = dblastid();

                                // Attach files if permitted
                                if (!empty($_FILES) && is_uploaded_file($_FILES['file_attachments']['tmp_name'][0]) && self::getPermission("can_upload_attach")) {
                                    $upload = form_sanitizer($_FILES['file_attachments'], '', 'file_attachments');
                                    if ($upload['error'] == 0) {
                                        foreach ($upload['target_file'] as $arr => $file_name) {
                                            $attach_data = array(
                                                'thread_id' => $post_data['thread_id'],
                                                'post_id' => $post_data['post_id'],
                                                'attach_name' => $file_name,
                                                'attach_mime' => $upload['type'][$arr],
                                                'attach_size' => $upload['source_size'][$arr],
                                                'attach_count' => '0', // downloaded times
                                            );
                                            dbquery_insert(DB_FORUM_ATTACHMENTS, $attach_data, "save", array('keep_session' => TRUE));
                                        }
                                    }
                                }

                                dbquery("UPDATE ".DB_USERS." SET user_posts=user_posts+1 WHERE user_id='".intval($post_data['post_author'])."'");
                                // Update stats in forum and threads
                                // find all parents and update them
                                $list_of_forums = get_all_parent(dbquery_tree(DB_FORUMS, 'forum_id', 'forum_cat'),
                                                                 $post_data['forum_id']);
                                if (is_array($list_of_forums)) {
                                    foreach ($list_of_forums as $forum_id) {

                                        $forum_update_sql = "
                                        UPDATE ".DB_FORUMS." SET forum_lastpost='".intval($post_data['post_datestamp'])."',
                                        forum_postcount=forum_postcount+1,
                                        forum_threadcount=forum_threadcount+1,
                                        forum_lastpostid='".intval($post_data['post_id'])."',
                                        forum_lastuser='".intval($post_data['post_author'])."' WHERE forum_id='".intval($forum_id)."'
                                        ";

                                        dbquery($forum_update_sql);
                                    }
                                }

                                // update current forum
                                dbquery("UPDATE ".DB_FORUMS." SET forum_lastpost='".$post_data['post_datestamp']."', forum_postcount=forum_postcount+1, forum_threadcount=forum_threadcount+1, forum_lastpostid='".$post_data['post_id']."', forum_lastuser='".$post_data['post_author']."' WHERE forum_id='".$post_data['forum_id']."'");

                                // update current thread
                                dbquery("UPDATE ".DB_FORUM_THREADS." SET thread_lastpost='".$post_data['post_datestamp']."', thread_lastpostid='".$post_data['post_id']."', thread_lastuser='".$post_data['post_author']."' WHERE thread_id='".$post_data['thread_id']."'");

                                // set notify
                                if ($forum_settings['thread_notify'] && isset($_POST['notify_me']) && $post_data['thread_id']) {
                                    if (!dbcount("(thread_id)", DB_FORUM_THREAD_NOTIFY,
                                                 "thread_id='".$post_data['thread_id']."' AND notify_user='".$post_data['post_author']."'")
                                    ) {
                                        dbquery("INSERT INTO ".DB_FORUM_THREAD_NOTIFY." (thread_id, notify_datestamp, notify_user, notify_status) VALUES('".$post_data['thread_id']."', '".$post_data['post_datestamp']."', '".$post_data['post_author']."', '1')");
                                    }
                                }

                                // Add poll if exist
                                if (!empty($option_data) && isset($_POST['add_poll'])) {
                                    dbquery_insert(DB_FORUM_POLLS, $pollData, 'save');
                                    $poll_option_data['thread_id'] = $pollData['thread_id'];
                                    $i = 1;
                                    foreach ($option_data as $option_text) {
                                        if ($option_text) {
                                            $poll_option_data['forum_poll_option_id'] = $i;
                                            $poll_option_data['forum_poll_option_text'] = $option_text;
                                            $poll_option_data['forum_poll_option_votes'] = 0;
                                            dbquery_insert(DB_FORUM_POLL_OPTIONS, $poll_option_data, 'save');
                                            $i++;
                                        }
                                    }
                                    dbquery("UPDATE ".DB_FORUM_THREADS." SET thread_poll='1' WHERE thread_id='".$pollData['thread_id']."'");
                                }
                            }
                            if (\defender::safe()) {
                                redirect(INFUSIONS."forum/postify.php?post=new&error=0&amp;forum_id=".intval($post_data['forum_id'])."&amp;parent_id=".intval($post_data['forum_cat'])."&amp;thread_id=".intval($post_data['thread_id'].""));
                            }
                        }
                    }

                    $this->info = array(
                        'title' => $locale['forum_0057'],
                        'description' => '',
                        'openform' => openform('input_form', 'post', FORUM."newthread.php?forum_id=".$post_data['forum_id'],
                                               array(
                                                   'enctype' => self::getPermission("can_upload_attach")
                                               )
                        ),
                        // use new permission to toggle enctype
                        'closeform' => closeform(),
                        'forum_id_field' => '',
                        'thread_id_field' => '',
                        "forum_field" => "",
                        'subject_field' => form_text('thread_subject', $locale['forum_0600'], $thread_data['thread_subject'],
                                                     array(
                                                         'required' => 1,
                                                         'placeholder' => $locale['forum_2001'],
                                                         'error_text' => '',
                                                         'class' => 'm-t-20 m-b-20'
                                                     )),
                        'tags_field' => form_select('thread_tags[]', $locale['forum_tag_0100'], $thread_data['thread_tags'],
                                                    array(
                                                        'options' => $this->tag()->get_TagOpts(TRUE),
                                                        'width' => '100%',
                                                        'multiple' => TRUE,
                                                        'delimiter' => '.',
                                                        'max_select' => 3, // to do settings on this
                                                    )),
                        'message_field' => form_textarea('post_message', $locale['forum_0601'], $post_data['post_message'],
                                                         array(
                                                             'required' => 1,
                                                             'error_text' => '',
                                                             'autosize' => 1,
                                                             'no_resize' => 1,
                                                             'preview' => 1,
                                                             'form_name' => 'input_form',
                                                             'bbcode' => 1
                                                         )),
                        'attachment_field' => self::getPermission("can_upload_attach") ?
                            form_fileinput('file_attachments[]',
                                           $locale['forum_0557'],
                                           "", array(
                                               'input_id' => 'file_attachments',
                                               'upload_path' => INFUSIONS.'forum/attachments/',
                                               'type' => 'object',
                                               'preview_off' => TRUE,
                                               "multiple" => TRUE,
                                               "inline" => FALSE,
                                               'max_count' => $forum_settings['forum_attachmax_count'],
                                               'valid_ext' => $forum_settings['forum_attachtypes'],
                                               "class" => "m-b-0",
                                           )
                            )." <div class='m-b-20'>\n<small>
                            ".sprintf($locale['forum_0559'], parsebytesize($forum_settings['forum_attachmax']), str_replace('|', ', ', $forum_settings['forum_attachtypes']), $forum_settings['forum_attachmax_count'])."</small>\n</div>\n" : "",

                        'poll_form' => $poll_form,

                        'smileys_field' => form_checkbox('post_smileys', $locale['forum_0622'], $post_data['post_smileys'],
                                                         array('class' => 'm-b-0', 'reverse_label'=>TRUE)),

                        'signature_field' => (array_key_exists("user_sig", $userdata) && $userdata['user_sig']) ?
                            form_checkbox('post_showsig', $locale['forum_0623'], $post_data['post_showsig'], array('class' => 'm-b-0', 'reverse_label'=>TRUE)) : '',

                        'sticky_field' => (iMOD || iSUPERADMIN) ? form_checkbox('thread_sticky', $locale['forum_0620'],
                                                                                $thread_data['thread_sticky'],
                                                                                array('class' => 'm-b-0', 'reverse_label'=>TRUE)) : '',

                        'lock_field' => (iMOD || iSUPERADMIN) ? form_checkbox('thread_locked', $locale['forum_0621'],
                                                                              $thread_data['thread_locked'],
                                                                              array('class' => 'm-b-0', 'reverse_label'=>TRUE)) : '',

                        'edit_reason_field' => '',
                        'delete_field' => '',
                        'hide_edit_field' => '',
                        'post_locked_field' => '',

                        'notify_field' => $forum_settings['thread_notify'] ? form_checkbox('notify_me', $locale['forum_0626'],
                                                                                           $post_data['notify_me'],
                                                                                           array('class' => 'm-b-0', 'reverse_label'=>TRUE)) : '',

                        'post_buttons' => form_button('post_newthread', $locale['forum_0057'], $locale['forum_0057'],
                                                      array('class' => 'btn-primary btn-sm')).form_button('cancel',
                                                                                                          $locale['cancel'],
                                                                                                          $locale['cancel'],
                                                                                                          array('class' => 'btn-default btn-sm m-l-10')),
                        'last_posts_reply' => '',
                    );
                    // add a jquery to toggle the poll form
                    add_to_jquery("
                        if ($('#add_poll').is(':checked')) {
                            $('#poll_form').show();
                        } else {
                            $('#poll_form').hide();
                        }
                        $('#add_poll').bind('click', function() {
                            if ($(this).is(':checked')) {
                                $('#poll_form').slideDown();
                            } else {
                                $('#poll_form').slideUp();
                            }
                        });
                    ");

                } else {
                    redirect(FORUM."index.php");
                }

            } else {

                /*
                 * Quick New Forum Posting.
                 * Does not require to run permissions.
                 * Does not contain forum poll.
                 * Does not contain attachment
                 */

                if (!dbcount("(forum_id)", DB_FORUMS, "forum_type !='1'")) {
                    redirect(INFUSIONS."forum/index.php");
                }
                if (!dbcount("(forum_id)", DB_FORUMS, "forum_language ='".LANGUAGE."'")) {
                    redirect(INFUSIONS."forum/index.php");
                }

                add_breadcrumb(array("link" => FORUM."newthread.php?forum_id=0", "title" => $locale['forum_0057']));

                $thread_data = array(
                    'forum_id' => isset($_POST['forum_id']) ? form_sanitizer($_POST['forum_id'], 0, "forum_id") : 0,
                    'thread_id' => 0,
                    'thread_subject' => isset($_POST['thread_subject']) ? form_sanitizer($_POST['thread_subject'], '',
                                                                                         'thread_subject') : '',
                    'thread_tags' => isset($_POST['thread_tags']) ? form_sanitizer($_POST['thread_tags'], '', 'thread_tags') : '',
                    'thread_author' => $userdata['user_id'],
                    'thread_views' => 0,
                    'thread_lastpost' => time(),
                    'thread_lastpostid' => 0, // need to run update
                    'thread_lastuser' => $userdata['user_id'],
                    'thread_postcount' => 1, // already insert 1 postcount.
                    'thread_poll' => 0,
                    'thread_sticky' => isset($_POST['thread_sticky']) ? TRUE : FALSE,
                    'thread_locked' => isset($_POST['thread_sticky']) ? TRUE : FALSE,
                    'thread_hidden' => 0,
                );

                $post_data = array(
                    'forum_id' => isset($_POST['forum_id']) ? form_sanitizer($_POST['forum_id'], 0, "forum_id") : 0,
                    "forum_cat" => 0, // for redirect
                    'thread_id' => 0, // required lastid
                    'post_id' => 0, // auto insertion
                    'post_message' => isset($_POST['post_message']) ? form_sanitizer($_POST['post_message'], '',
                                                                                     'post_message') : '',
                    'post_showsig' => isset($_POST['post_showsig']) ? TRUE : FALSE,
                    'post_smileys' => !isset($_POST['post_smileys']) || isset($_POST['post_message']) && preg_match("#(\[code\](.*?)\[/code\]|\[geshi=(.*?)\](.*?)\[/geshi\]|\[php\](.*?)\[/php\])#si",
                                                                                                                    $_POST['post_message']) ? FALSE : TRUE,
                    'post_author' => $userdata['user_id'],
                    'post_datestamp' => time(),
                    'post_ip' => USER_IP,
                    'post_ip_type' => USER_IP_TYPE,
                    'post_edituser' => 0,
                    'post_edittime' => 0,
                    'post_editreason' => '',
                    'post_hidden' => 0,
                    'notify_me' => isset($_POST['notify_me']) ? TRUE : FALSE,
                    'post_locked' => 0,
                );

                // go for a new thread posting.
                // check data
                // and validate
                // do not run attach, and do not run poll.
                if (isset($_POST['post_newthread']) && \defender::safe()) {
                    require_once INCLUDES."flood_include.php";
                    // all data is sanitized here.
                    if (!flood_control("post_datestamp", DB_FORUM_POSTS,
                                       "post_author='".$userdata['user_id']."'")
                    ) { // have notice

                        if (ForumServer::verify_forum($thread_data['forum_id'])) {

                            $forum_data = dbarray(dbquery("SELECT f.*, f2.forum_name AS forum_cat_name
                            FROM ".DB_FORUMS." f
                            LEFT JOIN ".DB_FORUMS." f2 ON f.forum_cat=f2.forum_id
                            WHERE f.forum_id='".intval($thread_data['forum_id'])."'
                            AND ".groupaccess('f.forum_access')."
                            "));

                            if ($forum_data['forum_type'] == 1) {
                                redirect(INFUSIONS."forum/index.php");
                            }

                            // Use the new permission settings
                            self::setPermission($forum_data);

                            $forum_data['lock_edit'] = $forum_settings['forum_edit_lock'];

                            if (self::getPermission("can_post") && self::getPermission("can_access")) {

                                $post_data['forum_cat'] = $forum_data['forum_cat'];
                                // create a new thread.
                                dbquery_insert(DB_FORUM_THREADS, $thread_data, 'save', array(
                                    'primary_key' => 'thread_id',
                                    'keep_session' => TRUE
                                ));
                                $post_data['thread_id'] = dblastid();

                                dbquery_insert(DB_FORUM_POSTS, $post_data, 'save', array(
                                    'primary_key' => 'post_id',
                                    'keep_session' => TRUE
                                ));

                                $post_data['post_id'] = dblastid();

                                dbquery("UPDATE ".DB_USERS." SET user_posts=user_posts+1 WHERE user_id='".$post_data['post_author']."'");

                                // Update stats in forum and threads
                                // find all parents and update them
                                $list_of_forums = get_all_parent(dbquery_tree(DB_FORUMS, 'forum_id', 'forum_cat'),
                                                                 $post_data['forum_id']);
                                foreach ($list_of_forums as $fid) {
                                    dbquery("UPDATE ".DB_FORUMS." SET forum_lastpost='".time()."', forum_postcount=forum_postcount+1, forum_threadcount=forum_threadcount+1, forum_lastpostid='".$post_data['post_id']."', forum_lastuser='".$post_data['post_author']."' WHERE forum_id='".$fid."'");
                                }
                                // update current forum
                                dbquery("UPDATE ".DB_FORUMS." SET forum_lastpost=''".time()."'', forum_postcount=forum_postcount+1, forum_threadcount=forum_threadcount+1, forum_lastpostid='".$post_data['post_id']."', forum_lastuser='".$post_data['post_author']."' WHERE forum_id='".$post_data['forum_id']."'");
                                // update current thread
                                dbquery("UPDATE ".DB_FORUM_THREADS." SET thread_lastpost=''".time()."'', thread_lastpostid='".$post_data['post_id']."', thread_lastuser='".$post_data['post_author']."' WHERE thread_id='".$post_data['thread_id']."'");
                                // set notify
                                if ($forum_settings['thread_notify'] && isset($_POST['notify_me']) && $post_data['thread_id']) {
                                    if (!dbcount("(thread_id)", DB_FORUM_THREAD_NOTIFY,
                                                 "thread_id='".$post_data['thread_id']."' AND notify_user='".$post_data['post_author']."'")
                                    ) {
                                        dbquery("INSERT INTO ".DB_FORUM_THREAD_NOTIFY." (thread_id, notify_datestamp, notify_user, notify_status) VALUES('".$post_data['thread_id']."', '".time()."', '".$post_data['post_author']."', 1)");
                                    }
                                }

                                if (\defender::safe()) {
                                    redirect(INFUSIONS."forum/postify.php?post=new&error=0&amp;forum_id=".intval($post_data['forum_id'])."&amp;parent_id=".intval($post_data['forum_cat'])."&amp;thread_id=".intval($post_data['thread_id'].""));
                                }

                            } else {
                                addNotice("danger", $locale['forum_0186']);
                            }
                        } else {
                            addNotice("danger", $locale['forum_0187']);
                            redirect(INFUSIONS."forum/index.php");
                        }
                    }
                }

                //Disable all parents
                $disabled_opts = array();
                $disable_query = "
                SELECT forum_id FROM ".DB_FORUMS." WHERE forum_type='1'
                ".(multilang_table("FO") ? "AND forum_language='".LANGUAGE."'" : "")."
                ";
                $disable_query = dbquery(" $disable_query ");
                if (dbrows($disable_query) > 0) {
                    while ($d_forum = dbarray($disable_query)) {
                        $disabled_opts = $d_forum['forum_id'];
                    }
                }

                $this->info = array(
                    'title' => $locale['forum_0057'],
                    'description' => '',
                    'openform' => openform('input_form', 'post', FORUM."newthread.php", array('enctype' => FALSE)),
                    'closeform' => closeform(),
                    'forum_id_field' => '',
                    'thread_id_field' => '',
                    // need to disable all parents
                    'forum_field' => form_select_tree("forum_id", $locale['forum_0395'], $thread_data['forum_id'],
                                                      array(
                                                          "required" => TRUE,
                                                          "width" => "320px",
                                                          "no_root" => TRUE,
                                                          "disable_opts" => $disabled_opts,
                                                          "query" => (multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."'" : ""),
                                                      ),
                                                      DB_FORUMS, "forum_name", "forum_id", "forum_cat"),
                    'subject_field' => form_text('thread_subject', $locale['forum_0600'], $thread_data['thread_subject'], array(
                        'required' => 1,
                        'placeholder' => $locale['forum_2001'],
                        'error_text' => '',
                        'class' => 'm-t-20 m-b-20'
                    )),
                    'tags_field' => form_select('thread_tags[]', $locale['forum_tag_0100'], $thread_data['thread_tags'],
                                                array(
                                                    'options' => $this->tag()->get_TagOpts(),
                                                    'width' => '100%',
                                                    'multiple' => TRUE,
                                                    'delimiter' => '.',
                                                    'max_select' => 3, // to do settings on this
                                                )),
                    'message_field' => form_textarea('post_message', $locale['forum_0601'], $post_data['post_message'], array(
                        'required' => 1,
                        'error_text' => '',
                        'autosize' => 1,
                        'no_resize' => 1,
                        'preview' => 1,
                        'form_name' => 'input_form',
                        'bbcode' => 1
                    )),
                    'attachment_field' => "",
                    'poll_form' => "",
                    'smileys_field' => form_checkbox('post_smileys', $locale['forum_0622'], $post_data['post_smileys'],
                                                     array('class' => 'm-b-0', 'reverse_label'=>TRUE)),
                    'signature_field' => (array_key_exists("user_sig",
                                                           $userdata) && $userdata['user_sig']) ? form_checkbox('post_showsig',
                                                                                                                $locale['forum_0623'],
                                                                                                                $post_data['post_showsig'],
                                                                                                                array('class' => 'm-b-0', 'reverse_label'=>TRUE)) : '',
                    'sticky_field' => (iSUPERADMIN) ? form_checkbox('thread_sticky', $locale['forum_0620'],
                                                                    $thread_data['thread_sticky'],
                                                                    array('class' => 'm-b-0', 'reverse_label'=>TRUE)) : '',
                    'lock_field' => (iSUPERADMIN) ? form_checkbox('thread_locked', $locale['forum_0621'],
                                                                  $thread_data['thread_locked'],
                                                                  array('class' => 'm-b-0', 'reverse_label'=>TRUE)) : '',
                    'edit_reason_field' => '',
                    'delete_field' => '',
                    'hide_edit_field' => '',
                    'post_locked_field' => '',
                    'notify_field' => $forum_settings['thread_notify'] ? form_checkbox('notify_me', $locale['forum_0626'],
                                                                                       $post_data['notify_me'],
                                                                                       array('class' => 'm-b-0', 'reverse_label'=>TRUE)) : '',
                    'post_buttons' => form_button('post_newthread', $locale['forum_0057'], $locale['forum_0057'],
                                                  array('class' => 'btn-primary btn-sm')).form_button('cancel',
                                                                                                      $locale['cancel'],
                                                                                                      $locale['cancel'],
                                                                                                      array('class' => 'btn-default btn-sm m-l-10')),
                    'last_posts_reply' => '',
                );
            }
        } else {
            redirect(INFUSIONS.'forum/index.php');
        }
    }

    /**
     * @return array
     */
    public function get_newThreadInfo() {
        return $this->info;
    }

    /**
     * Set user permission based on current forum configuration
     * @param $forum_data
     */
    private static $permissions = array();

    private function setPermission($forum_data) {
        // Generate iMOD Constant
        $this->moderator()->define_forum_mods($forum_data);

        // Access the forum
        self::$permissions['permissions']['can_access'] = (iMOD || checkgroup($forum_data['forum_access'])) ? TRUE : FALSE;
        // Create new thread -- whether user has permission to create a thread
        self::$permissions['permissions']['can_post'] = (iMOD || (checkgroup($forum_data['forum_post']) && $forum_data['forum_lock'] == FALSE)) ? TRUE : FALSE;
        // Poll creation -- thread has not exist, therefore cannot be locked.
        self::$permissions['permissions']['can_create_poll'] = $forum_data['forum_allow_poll'] == TRUE && (iMOD || (checkgroup($forum_data['forum_poll']) && $forum_data['forum_lock'] == FALSE)) ? TRUE : FALSE;
        self::$permissions['permissions']['can_upload_attach'] = $forum_data['forum_allow_attach'] == TRUE && (iMOD || checkgroup($forum_data['forum_attach'])) ? TRUE : FALSE;
        self::$permissions['permissions']['can_download_attach'] = iMOD || ($forum_data['forum_allow_attach'] == TRUE && checkgroup($forum_data['forum_attach_download'])) ? TRUE : FALSE;
    }

    private static function getPermission($key) {
        if (!empty(self::$permissions['permissions'])) {
            if (isset(self::$permissions['permissions'][$key])) {
                return self::$permissions['permissions'][$key];
            }
            return self::$permissions['permissions'];
        }
        return NULL;
    }


}