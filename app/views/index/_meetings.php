<?php
/** @var string $deleteAction */
/** @var string $title */
/** @var bool $canModifyMeetings */
/** @var ElanEv\Model\MeetingCourse[] $meetings */
/** @var string $destination */
/** @var bool $showInstitute */
/** @var bool $showCourse */
/** @var bool $showUser */
/** @var bool $showCreateForm */

$colspan = 2;

if ($canModifyMeetings) {
    $colspan += 5;
}

if ($showCourse) {
    $colspan++;
}

if ($showUser) {
    $colspan++;
}
?>

<? if (!empty($meetings)) : ?>
<form action="<?=$deleteAction?>" method="post">
    <input type="hidden" name="action" value="multi-delete">

    <table class="default collapsable tablesorter conference-meetings<?=$canModifyMeetings ? ' admin': ''?>">
        <caption><?= htmlReady($title) ?></caption>
        <colgroup>
            <? if ($canModifyMeetings): ?>
                <col style="width: 20px;">
            <? endif ?>
            <col>
            <col style="width: 120px;">
            <? if ($showCourse): ?>
                <col style="width: 300px;">
            <? endif ?>
            <? if ($showUser): ?>
                <col style="width: 300px;">
            <? endif ?>

            <? if ($canModifyMeetings): ?>
                <col style="width: 100px;">
                <col style="width: 220px;">
                <col style="width: 80px;">
                <col style="width: 100px;">
            <? endif ?>
        </colgroup>
        <thead>
        <tr>
            <? if ($canModifyMeetings): ?>
                <th>&nbsp;</th>
            <? endif ?>
            <th class="sortable">Meeting</th>
            <th class="recording-url"><?=$_('Aufzeichnung')?></th>
            <? if ($showCourse): ?>
                <th class="sortable">
                    <? if ($showInstitute): ?>
                        <?=$_('Heimat-Einrichtung')?><br>
                    <? endif ?>
                    <?= $_('Veranstaltung') ?>
                </th>
            <? endif ?>
            <? if ($showUser): ?>
                <th class="sortable"><?= $_('Erstellt von') ?></th>
            <? endif ?>
            <? if ($canModifyMeetings): ?>
                <th class="sortable"><?= $_('Treiber') ?></th>
                <th class="sortable"><?=$_('Zuletzt betreten')?></th>
                <th class="active"><?= $_('Freigeben') ?></th>
                <th><?=$_('Aktion')?></th>
            <? endif; ?>
        </tr>
        </thead>

        <tbody>
        <? foreach ($meetings as $meetingCourse): ?>
            <? try {
                $driver = $driver_factory->getDriver($meetingCourse->meeting->driver);
            } catch (InvalidArgumentException $e) {
                // skip non-existent/deactivated drivers or otherwise bogus meeting-entries
                continue;
            }
            ?>

            <?
            $joinUrl = PluginEngine::getLink($plugin, array('cid' => $meetingCourse->course->id), 'index/joinMeeting/'.$meetingCourse->meeting->id);
            $moderatorPermissionsUrl = PluginEngine::getLink($plugin, array('destination' => $destination), 'index/moderator_permissions/'.$meetingCourse->meeting->id);
            $deleteUrl = PluginEngine::getLink($plugin, array('delete' => $meetingCourse->meeting->id, 'cid' => $meetingCourse->course->id, 'destination' => $destination), $destination);
            ?>

            <tr data-meeting-id="<?=$meetingCourse->meeting->id?>">
                <? if ($canModifyMeetings): ?>
                    <td>
                        <input class="check_all" type="checkbox" name="meeting_ids[]" value="<?=$meetingCourse->meeting->id?>-<?=$meetingCourse->course->id?>">
                    </td>
                <? endif ?>

                <td class="meeting-name">
                    <a href="<?=$joinUrl?>"
                        target="_blank"
                        title="<?=$canModifyMeetings ? $_('Dieser Meetingraum wird in ').count($meetingCourse->meeting->courses).$_(' LV verwendet.') : $_('Meeting betreten')?>">
                        <span><?=htmlReady($meetingCourse->meeting->name)?></span>
                        <? if (count($meetingCourse->meeting->courses) > 1): ?>
                            (<?=count($meetingCourse->meeting->courses)?> <?=$_('LV')?>)
                        <? endif ?>
                    </a>
                    <input type="text" name="name"><br>
                    <input type="text" name="recording_url" placeholder="<?=$_('URL zur Aufzeichnung')?>">

                    <?= Icon::create('accept', 'clickable', array('class' => 'accept-button', 'title' => $_('Änderungen speichern'))) ?>
                    <?= Icon::create('decline', 'clickable', array('class' => 'decline-button', 'title' => $_('Änderungen verwerfen'))) ?>

                    <img src="<?=$GLOBALS['ASSETS_URL']?>/images/ajax_indicator_small.gif" class="loading-indicator">
                </td>

                <td class="recording-url">
                    <? if (class_implements($driver, 'RecordingInterface') && !$meetingCourse->meeting->recording_url) : ?>
                        <? $recordings = $driver->getRecordings($meetingCourse->meeting->getMeetingParameters()) ?>
                        <? if (!empty($recordings)) foreach ($recordings as $recording) : ?>
                        <a href="<?= $recording->playback->format->url ?>" target="_blank" class="meeting-recording-url">
                            <? $title = sprintf($_('zur Aufzeichnung vom %s'), date('d.m.Y, H:i:s', (int)$recording->startTime / 1000)) ?>
                            <?= Icon::create('video', 'clickable', array('title' => $title)) ?>
                        </a>
                        <? endforeach ?>

                    <? else: ?>
                        <a href="<?= $meetingCourse->meeting->recording_url ?>" target="_blank" class="meeting-recording-url"
                                <?= !$meetingCourse->meeting->recording_url ? ' style="display:none;"' : ''?>
                        >
                            <?= Icon::create('video', 'clickable', array('title' => $_('zur Aufzeichnung'))) ?>
                        </a>
                    <? endif ?>
                </td>

                <? if ($showCourse): ?>
                    <td>
                        <? if ($showInstitute): ?>
                            <?=htmlReady($meetingCourse->course->home_institut->name)?><br>
                        <? endif ?>
                        <a href="<?=PluginEngine::getURL($plugin, array('cid' => $meetingCourse->course->id), 'index')?>">
                            <?=htmlReady($meetingCourse->course->name)?>
                        </a>
                    </td>
                <? endif ?>
                <? if ($showUser): ?>
                    <td>
                        <? $user = new User($meetingCourse->meeting->user_id) ?>
                        <?= htmlReady($user->vorname) ?> <?= htmlReady($user->nachname) ?> (<?= htmlReady($user->username) ?>)
                    </td>
                <? endif ?>
                <? if ($canModifyMeetings): ?>
                    <td><?= htmlReady($this->driver_config[$meetingCourse->meeting->driver]['display_name']) ?></td>
                    <td>
                        <? $recentJoins = array_reverse($meetingCourse->meeting->getAllJoins()) ?>
                        <? if (count($recentJoins) > 0): ?>
                            <?=date('d.m.Y', $recentJoins[0]->last_join)?> <?=$_('um')?> <?=date('H:i', $recentJoins[0]->last_join)?> <?=$_('Uhr')?>
                        <? else: ?>
                            <?=$_('Raum wurde noch nie betreten')?>
                        <? endif ?>
                    </td>
                    <td class="active"><input type="checkbox"<?=$meetingCourse->active ? ' checked="checked"' : ''?> data-meeting-enable-url="<?=PluginEngine::getLink($plugin, array('destination' => $destination), 'index/enable/'.$meetingCourse->meeting->id.'/'.$meetingCourse->course->id)?>" title="<?=$meetingCourse->active ? $_('Meeting für Teilnehmende unsichtbar schalten') : $_('Meeting für Teilnehmende sichtbar schalten')?>"></td>
                    <td>
                        <?= Icon::create('info-circle', 'clickable', array('class' => 'info')) ?>
                        <a href="#" title="<?=$_('Meeting bearbeiten')?>" class="edit-meeting" data-meeting-edit-url="<?=PluginEngine::getLink($plugin, array(), 'index/edit/'.$meetingCourse->meeting->id)?>">
                            <?= Icon::create('edit') ?>
                        </a>
                        <? if ($meetingCourse->meeting->join_as_moderator): ?>
                            <a href="<?= $moderatorPermissionsUrl ?>" title="<?=$_('Teilnehmende haben VeranstalterInnen-Rechte')?>">
                                <?= Icon::create('admin') ?>
                            </a>
                        <? else: ?>
                            <a href="<?= $moderatorPermissionsUrl ?>" title="<?=$_('Teilnehmende haben eingeschränkte Rechte')?>">
                                <?= Icon::create('admin+decline') ?>
                            </a>
                        <? endif; ?>

                        <a href="<?= $deleteUrl ?>" title="<?= count($meetingCourse->meeting->courses) > 1 ? $_('Zuordnung löschen') : $_('Meeting löschen') ?>">
                            <? if (count($meetingCourse->meeting->courses) > 1): ?>
                                <?= Icon::create('remove') ?>
                            <? else: ?>
                                <?= Icon::create('trash') ?>
                            <? endif ?>
                        </a>
                    </td>
                <? endif; ?>
                </tr>

                <? if ($canModifyMeetings): ?>
                <tr class="info">
                    <td colspan="8">
                        <ul>
                            <? if ($meetingCourse->meeting->join_as_moderator): ?>
                                <li><?=$_('Teilnehmende haben VeranstalterInnen-Rechte (wie Anlegende/r).')?></li>
                            <? else: ?>
                                <li><?=$_('Teilnehmende haben eingeschränkte Teilnehmenden-Rechte.')?></li>
                            <? endif; ?>

                            <? if (count($meetingCourse->meeting->getRecentJoins()) === 1): ?>
                                <li><?=$_('Eine Person hat das Meeting in den letzten 24 Stunden betreten')?>.</li>
                            <? else: ?>
                                <li><?=count($meetingCourse->meeting->getRecentJoins()).' '.$_('Personen haben das Meeting in den letzten 24 Stunden betreten')?>.</li>
                            <? endif; ?>

                            <? if (count($meetingCourse->meeting->getAllJoins()) === 1): ?>
                                <li><?=$_('Eine Person hat das Meeting insgesamt betreten')?>.</li>
                            <? else: ?>
                                <li><?=count($meetingCourse->meeting->getAllJoins()).' '.$_('Personen haben das Meeting insgesamt betreten')?>.</li>
                            <? endif; ?>
                        </ul>
                    </td>
                </tr>
                <? endif ?>
        <? endforeach; ?>
        </tbody>

        <? if ($canModifyMeetings): ?>
            <tfoot>
            <tr>
                <td colspan="<?= $colspan ?>">
                    <input class="middle" type="checkbox" name="check_all" title="<?= $_('Alle Meetings auswählen') ?>">
                    <input type="hidden" name="cid" value="<?= Context::getId() ?>">
                    <?= Studip\Button::create($_('Löschen'), array('title' => $_('Alle ausgewählten Meetings löschen'))) ?>
                </td>
            </tr>
            </tfoot>
        <? endif ?>
    </table>
</form>
<? endif ?>

<? if ($showCreateForm): ?>
    <?= $this->render_partial('index/_create_meeting') ?>
<? endif; ?>
