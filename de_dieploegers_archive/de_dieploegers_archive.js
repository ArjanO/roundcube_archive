/**
 * Roundcube Archiving plugin - Frontend
 *
 * Archiving plugin, that enables roundcube users to archive messages
 *
 *  - based on date (by years, by months)
 *  - based on sender
 *
 * @version 1.0b
 * @author Dennis Plöger <develop@dieploegers.de>
 * @url https://github.com/dploeger/de_dieploegers_archive
 */

function de_dieploegers_archive_handleQueryFolder(response) {

    if (response["errors"].length > 0) {

        rcmail.show_popup_dialog(
            rcmail.gettext("errorMessage"),
            rcmail.gettext("errorMessageTitle")
        );

    } else {

        if (response["needReload"]) {

            rcmail.reload();

        } else if (response["needUpdate"]) {

            rcmail.check_for_recent(true);

        }

    }

}

function de_dieploegers_archive_handleButtonClick(prop)
{

    if (!rcmail.env.uid &&
        (
            !rcmail.message_list ||
            !rcmail.message_list.get_selection().length
        )
    ) {

        return;

    }

    // Only react if mail isn't already in the archive folder

    if (rcmail.env.mailbox.indexOf( rcmail.env.archive_folder) != 0) {

        // Fetch archive folder from backend

        rcmail.http_request(
            'plugin.de_dieploegers_archive_queryFolder',
            "mails=" + JSON.stringify(rcmail.message_list.get_selection())
        );

    }

}

// callback for app-onload event
if (window.rcmail) {

    rcmail.addEventListener('init', function(evt) {

        // register command (directly enable in message view mode)
        rcmail.register_command(
            'plugin.de_dieploegers_archive',
            de_dieploegers_archive_handleButtonClick,
            (
                rcmail.env.uid &&
                rcmail.env.mailbox != rcmail.env.archive_folder
            )
        );

        // add event-listener to message list
        if (rcmail.message_list) {

            rcmail.message_list.addEventListener(
                'select',
                function (list){
                    rcmail.enable_command(
                        'plugin.de_dieploegers_archive',
                        (
                            list.get_selection().length > 0 &&
                            rcmail.env.mailbox != rcmail.env.archive_folder
                        )
                    );
                }
            );

        }

        // set css style for archive folder

        var li;
        if (rcmail.env.archive_folder &&
            (
                li = rcmail.get_folder_li(rcmail.env.archive_folder, '', true)
            )
        ) {

            $(li).addClass('archive');

        }

    });

    rcmail.addEventListener(
        'plugin.de_dieploegers_archive_handleQueryFolder',
        de_dieploegers_archive_handleQueryFolder
    );

}