<?php

/**
 * Roundcube Archiving plugin - Backend
 *
 * Archiving plugin, that enables roundcube users to archive messages
 *
 *  - based on date (by years, by months)
 *  - based on sender
 *
 * Based on Archive plugin by Andre Rodier, Thomas Bruederli
 * Button and skin-settings taken from the Archivefolder plugin by Andre
 * Rodier, Thomas Bruederli and Roland 'rosali' Liebl
 *
 * @version 1.0b
 * @author Dennis Plöger <develop@dieploegers.de>
 * @url https://github.com/dploeger/de_dieploegers_archive
 */

class de_dieploegers_archive extends rcube_plugin {

    public $task = 'mail|settings';
    public $account = "";

    function init()
    {
        $rcmail = rcmail::get_instance();

        $this->account = $_SESSION['account_dn'];

        // Register folder query
        $this->register_action(
            'plugin.de_dieploegers_archive_queryFolder',
            array($this, "queryFolder")
        );

        // There is no "Archived flags"
        // $GLOBALS['IMAP_FLAGS']['ARCHIVED'] = 'Archive';
        if (
            $rcmail->task == 'mail' && (
                $rcmail->action == '' ||
                $rcmail->action == 'show'
            ) && (
                $archive_folder = $rcmail->config->get('archive_mbox')
            )
        ) {

            $skin_path = $this->local_skin_path();

            if (is_file($this->home . "/$skin_path/archive.css")) {

                $this->include_stylesheet("$skin_path/archive.css");

            }

            $this->include_script('de_dieploegers_archive.js');
            $this->add_texts('localization', true);

            if($rcmail->config->get('skin', 'classic') == 'larry'){
                $this->add_button(
                    array(
                        'command' => 'plugin.de_dieploegers_archive',
                        'type' => 'link',
                        'label' => 'buttonText',
                        'class' => 'button buttonPas archive disabled',
                        'classact' => 'button archive',
                        'width' => 32,
                        'height' => 32,
                        'title' => 'buttonTitle',
                        'domain' => $this->ID,
                    ),
                    'toolbar');
            }
            else{
                $this->add_button(
                    array(
                        'command' => 'plugin.de_dieploegers_archive',
                        'type' => 'link',
                        'label' => 'buttonText',
                        'content' => ' ',
                        'class' => 'button buttonPas archivefolder disabled',
                        'classact' => 'button archivefolder',
                        'classsel' => 'button archivefolderSel',
                        'title' => 'buttonTitle',
                        'domain' => $this->ID,
                    ),
                    'toolbar');
            }

            // register hook to localize the archive folder
            $this->add_hook(
                'render_mailboxlist',
                array(
                    $this,
                    'render_mailboxlist'
                )
            );

            // set env variable for client
            $rcmail->output->set_env('archive_folder', $archive_folder);

            // add archive folder to the list of default mailboxes
            if (
                (
                    $default_folders = $rcmail->config->get('default_folders')
                ) &&
                !in_array(
                    $archive_folder, $default_folders
                )
            ) {
                $default_folders[] = $archive_folder;
                $rcmail->config->set('default_folders', $default_folders);
            }

        }

        if ($rcmail->task == 'settings') {

            $dont_override = $rcmail->config->get('dont_override', array());

            if (!in_array('archive_mbox', $dont_override)) {
                $this->add_hook(
                    'preferences_sections_list',
                    array($this, 'prefs_section')
                );

                $this->add_hook(
                    'preferences_list',
                    array($this, 'prefs_table')
                );

                $this->add_hook(
                    'preferences_save',
                    array($this, 'save_prefs')
                );
            }
        }
    }

    function render_mailboxlist($p)
    {
        $rcmail = rcmail::get_instance();
        $archive_folder = $rcmail->config->get(
            'archive_mbox_' . $this->account
        );

        // set localized name for the configured archive folder
        if ($archive_folder) {
            if (isset($p['list'][$archive_folder])) {

                $p['list'][$archive_folder]['name'] =
                    $this->gettext('archiveFolder');

            } else {

                // search in subfolders
                $this->_mod_folder_name(
                    $p['list'],
                    $archive_folder,
                    $this->gettext('archiveFolder')
                );

            }
        }

        return $p;
    }

    function _mod_folder_name(&$list, $folder, $new_name)
    {
        foreach ($list as $idx => $item) {
            if ($item['id'] == $folder) {

                $list[$idx]['name'] = $new_name;
                return true;

            } else if (!empty($item['folders'])) {

                if ($this->_mod_folder_name(
                    $list[$idx]['folders'],
                    $folder,
                    $new_name)
                ) {

                    return true;

                }
            }
        }

        return false;

    }

    function prefs_section($args) {

        $this->add_texts('localization');

        $args["list"]["archive"] = array(
            "id" => "archive",
            "section" => rcube_label("archive", "de_dieploegers_archive")
        );

        return $args;
    }

    function prefs_table($args)
    {
        global $CURR_SECTION;

        if ($args['section'] == 'archive') {

            $this->add_texts('localization');

            $rcmail = rcmail::get_instance();

            // load folders list when needed
            if ($CURR_SECTION) {

                $select = rcmail_mailbox_select(
                    array(
                        'noselection' => '---',
                        'realnames' => true,
                        'maxlength' => 30,
                        'exceptions' => array('INBOX'),
                        'folder_filter' => 'mail',
                        'folder_rights' => 'w'
                    )
                );

            } else {

                $select = new html_select();

            }

            $archiveType = new html_select();

            $archiveType->add(
                $this->gettext('archiveTypeYear'),
                "year"
            );

            $archiveType->add(
                $this->gettext('archiveTypeMonth'),
                "month"
            );

            $archiveType->add(
                $this->gettext('archiveTypeSender'),
                "sender"
            );

            $user = $this->account;

            $args['blocks']['archive'] = array(
                'name' => Q(rcube_label('archive', "de_dieploegers_archive")),
                'options' => array()
            );

            $args['blocks']['archive']['options']['archive_mbox'] = array(
                'title' => $this->gettext('archiveFolder'),
                'content' => $select->show(
                    $rcmail->config->get('archive_mbox_' . $user),
                    array('name' => "_archive_mbox")
                )
            );

            $args['blocks']['archive']['options']['archive_type'] = array(
                'title' => $this->gettext('archiveType'),
                'content' => $archiveType->show(
                    $rcmail->config->get('archive_type_' . $user),
                    array('name' => '_archive_type')
                )
            );

        }

        return $args;
    }

    function queryFolder() {

        $mails = json_decode(trim(get_input_value('mails', RCUBE_INPUT_GPC)));

        $rcmail = rcmail::get_instance();

        $user = $this->account;

        $archiveFolder = $rcmail->config->get('archive_mbox_' . $user);

        $archiveType = $rcmail->config->get('archive_type_' . $user);

        $storage = $rcmail->get_storage();

        $needReload = false;
        $needUpdate = false;

        $delimiter = $storage->get_hierarchy_delimiter();

        $return = array();

        foreach ($mails as $mid) {

            $mail = $rcmail->storage->get_message($mid);

            if ($archiveType == "sender") {

                $from = $mail->get("from");

                if (preg_match("/<(.*)>/", $from, $matches)) {

                    $folder = $matches[1];

                } else {

                    $folder = $this->gettext("unkownSender");

                }

                $replacement = "_";

                if ($delimiter == $replacement) {

                    $replacement = ".";

                }

                $folder = str_replace(
                    $delimiter,
                    $replacement,
                    $folder
                );

            } elseif ($archiveType == "month") {

                $folder = date(
                    "Y" .
                    $delimiter .
                    "Y-m",
                    $mail->timestamp
                );

            } else {

                $folder = date("Y", $mail->timestamp);

            }

            $folder = $archiveFolder .
                $delimiter .
                $folder;

            if (!$storage->folder_exists($folder, false)) {

                $needReload = true;

                $storage->create_folder($folder, true);

            }

            if (!$storage->move_message(array($mid), $folder)) {

                $return[] = $mid;

            } else {

                $needUpdate = true;

            }

        }

        // Return information

        $rcmail->output->command(
            'plugin.de_dieploegers_archive_handleQueryFolder',
            array(
                "errors" => $return,
                "needReload" => $needReload,
                "needUpdate" => $needUpdate
            )
        );
    }

    function save_prefs($args)
    {

        if ($args['section'] == 'archive') {

            $user = $this->account;

            $args['prefs']['archive_mbox_' . $user] = get_input_value(
                '_archive_mbox',
                RCUBE_INPUT_POST
            );

            $args['prefs']['archive_type_' . $user] = get_input_value(
                '_archive_type',
                RCUBE_INPUT_POST
            );

            return $args;

        }
    }

}