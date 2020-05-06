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

<form action="<?=$deleteAction?>" method="post">
    <input type="hidden" name="action" value="multi-delete">

    <table class="default collapsable tablesorter conference-meetings<?=$canModifyMeetings ? ' admin': ''?>">
        <caption><?=$title?></caption>
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
            <?php if ($canModifyMeetings): ?>
                <th>&nbsp;</th>
            <?php endif ?>
            <th class="sortable">Meeting</th>
            <th class="recording-url"><?=_('Aufzeichnung')?></th>
            <?php if ($showCourse): ?>
                <th class="sortable">
                    <?php if ($showInstitute): ?>
                        <?=_('Heimat-Einrichtung')?><br>
                    <?php endif ?>
                    <?= _('Veranstaltung') ?>
                </th>
            <?php endif ?>
            <?php if ($showUser): ?>
                <th class="sortable"><?= _('Erstellt von') ?></th>
            <?php endif ?>
            <?php if ($canModifyMeetings): ?>
                <th class="sortable"><?= _('Treiber') ?></th>
                <th class="sortable"><?=_('Zuletzt betreten')?></th>
                <th class="active"><?= _('Freigeben') ?></th>
                <th><?=_('Aktion')?></th>
            <?php endif; ?>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($meetings as $meetingCourse): ?>
            <? try {
                $driver = $driver_factory->getDriver($meetingCourse->meeting->driver, $meetingCourse->meeting->server_index);
            } catch (InvalidArgumentException $e) {
                // skip non-existent/deactivated drivers or otherwise bogus meeting-entries
                continue;
            }
            ?>
            <?php
            $joinUrl = PluginEngine::getLink($plugin, array('cid' => $meetingCourse->course->id), 'index/joinMeeting/'.$meetingCourse->meeting->id);
            $moderatorPermissionsUrl = PluginEngine::getLink($plugin, array('destination' => $destination), 'index/moderator_permissions/'.$meetingCourse->meeting->id);
            $deleteUrl = PluginEngine::getLink($plugin, array('delete' => $meetingCourse->meeting->id, 'cid' => $meetingCourse->course->id, 'destination' => $destination), $destination);
            ?>
            <tr data-meeting-id="<?=$meetingCourse->meeting->id?>">
                <?php if ($canModifyMeetings): ?>
                    <td>
                        <input class="check_all" type="checkbox" name="meeting_ids[]" value="<?=$meetingCourse->meeting->id?>-<?=$meetingCourse->course->id?>">
                    </td>
                <?php endif ?>
                <td class="meeting-name">
                    <a href="<?=$joinUrl?>"
                        target="_blank"
                        title="<?=$canModifyMeetings ? _('Dieser Meetingraum wird in ').count($meetingCourse->meeting->courses)._(' LV verwendet.') : _('Meeting betreten')?>">
                        <span><?=htmlReady($meetingCourse->meeting->name)?></span>
                        <?php if (count($meetingCourse->meeting->courses) > 1): ?>
                            (<?=count($meetingCourse->meeting->courses)?> <?=_('LV')?>)
                        <?php endif ?>
                    </a>
                    <input type="text" name="name"><br>
                    <input type="text" name="recording_url" placeholder="<?=_('URL zur Aufzeichnung')?>">
                    <? if (StudipVersion::newerThan('3.3')) : ?>
                        <?= Icon::create('accept', 'clickable', array('class' => 'accept-button', 'title' => _('�nderungen speichern'))) ?>
                        <?= Icon::create('decline', 'clickable', array('class' => 'decline-button', 'title' => _('�nderungen verwerfen'))) ?>
                    <? else: ?>
                        <img src="<?=$GLOBALS['ASSETS_URL']?>/images/icons/16/blue/accept.png" class="accept-button" title="<?=_('�nderungen speichern')?>">
                        <img src="<?=$GLOBALS['ASSETS_URL']?>/images/icons/16/blue/decline.png" class="decline-button" title="<?=_('�nderungen verwerfen')?>">
                    <? endif ?>
                    <img src="<?=$GLOBALS['ASSETS_URL']?>/images/ajax_indicator_small.gif" class="loading-indicator">
                </td>
                <td class="recording-url">
                    <? if (class_implements($driver, 'RecordingInterface')) : ?>
                    <? $recordings = $driver->getRecordings($meetingCourse->meeting->getMeetingParameters()) ?>
                    <? if (!empty($recordings)) foreach ($recordings as $recording) : ?>
                        <a href="<?= $recording->playback->format->url ?>" target="_blank" class="meeting-recording-url">
                            <? $title = sprintf(_('zur Aufzeichnung vom %s'), date('d.m.Y, H:i:s', (int)$recording->startTime / 1000)) ?>
                            <? if (StudipVersion::newerThan('3.3')) : ?>
                                <?= Icon::create('video', 'clickable', array('title' => _('zur Aufzeichnung'))) ?>
                            <? else: ?>
                                <img src="<?=$GLOBALS['ASSETS_URL']?>/images/icons/16/blue/video.png" title="<?=_('zur Aufzeichnung')?>">
                            <? endif ?>
                        </a>
                    <? endforeach ?>

                    <a href="<?=$meetingCourse->meeting->recording_url?>" target="_blank" class="meeting-recording-url"<?=!$meetingCourse->meeting->recording_url ? ' style="display:none;"' : ''?>>
                        <? if (StudipVersion::newerThan('3.3')) : ?>
                            <?= Icon::create('video', 'clickable', array('title' => _('zur Aufzeichnung'))) ?>
                        <? else: ?>
                            <img src="<?=$GLOBALS['ASSETS_URL']?>/images/icons/16/blue/video.png" title="<?=_('zur Aufzeichnung')?>">
                        <? endif ?>
                    </a>
                </td>
                <?php if ($showCourse): ?>
                    <td>
                        <?php if ($showInstitute): ?>
                            <?=htmlReady($meetingCourse->course->home_institut->name)?><br>
                        <?php endif ?>
                        <a href="<?=PluginEngine::getURL($plugin, array('cid' => $meetingCourse->course->id), 'index')?>">
                            <?=htmlReady($meetingCourse->course->name)?>
                        </a>
                    </td>
                <?php endif ?>
                <?php if ($showUser): ?>
                    <td>
                        <?php $user = new User($meetingCourse->meeting->user_id) ?>
                        <?= $user->vorname ?> <?= $user->nachname ?> (<?= $user->username ?>)
                    </td>
                <?php endif ?>
                <?php if ($canModifyMeetings): ?>
                    <td><?= $this->driver_config[$meetingCourse->meeting->driver]['display_name'] ?></td>
                    <td>
                        <?php $recentJoins = array_reverse($meetingCourse->meeting->getAllJoins()) ?>
                        <?php if (count($recentJoins) > 0): ?>
                            <?=date('d.m.Y', $recentJoins[0]->last_join)?> <?=_('um')?> <?=date('H:i', $recentJoins[0]->last_join)?> <?=_('Uhr')?>
                        <?php else: ?>
                            <?=_('Raum wurde noch nie betreten')?>
                        <?php endif ?>
                    </td>
                    <td class="active"><input type="checkbox"<?=$meetingCourse->active ? ' checked="checked"' : ''?> data-meeting-enable-url="<?=PluginEngine::getLink($plugin, array('destination' => $destination), 'index/enable/'.$meetingCourse->meeting->id.'/'.$meetingCourse->course->id)?>" title="<?=$meetingCourse->active ? _('Meeting f�r Teilnehmende unsichtbar schalten') : _('Meeting f�r Teilnehmende sichtbar schalten')?>"></td>
                    <td>
                        <? if (StudipVersion::newerThan('3.3')) : ?>
                            <?= Icon::create('info-circle', 'clickable', array('class' => 'info')) ?>
                            <a href="#" title="<?=_('Meeting bearbeiten')?>" class="edit-meeting" data-meeting-edit-url="<?=PluginEngine::getLink($plugin, array(), 'index/edit/'.$meetingCourse->meeting->id)?>">
                                <?= Icon::create('edit') ?>
                            </a>
                            <? if ($meetingCourse->meeting->join_as_moderator): ?>
                                <a href="<?= $moderatorPermissionsUrl ?>" title="<?=_('Teilnehmende haben VeranstalterInnen-Rechte')?>">
                                    <?= Icon::create('admin') ?>
                                </a>
                            <? else: ?>
                                <a href="<?= $moderatorPermissionsUrl ?>" title="<?=_('Teilnehmende haben eingeschr�nkte Rechte')?>">
                                    <?= Icon::create('admin+decline') ?>
                                </a>
                            <? endif; ?>

                            <a href="<?= $deleteUrl ?>" title="<?= count($meetingCourse->meeting->courses) > 1 ? _('Zuordnung l�schen') : _('Meeting l�schen') ?>">
                                <? if (count($meetingCourse->meeting->courses) > 1): ?>
                                    <?= Icon::create('remove') ?>
                                <? else: ?>
                                    <?= Icon::create('trash') ?>
                                <? endif ?>
                            </a>
                        <? else : ?>
                            <img src="<?=$GLOBALS['ASSETS_URL']?>/images/icons/16/blue/info-circle.png" title="<?=_('Informationen anzeigen')?>" class="info">
                            <a href="#" title="<?=_('Meeting bearbeiten')?>" class="edit-meeting" data-meeting-edit-url="<?=PluginEngine::getLink($plugin, array(), 'index/edit/'.$meetingCourse->meeting->id)?>">
                                <img src="<?=$GLOBALS['ASSETS_URL']?>/images/icons/16/blue/edit.png">
                            </a>

                            <? if ($meetingCourse->meeting->join_as_moderator): ?>
                                <a href="<?= $moderatorPermissionsUrl ?>" title="<?=_('Teilnehmende haben VeranstalterInnen-Rechte')?>">
                                    <img src="<?=$GLOBALS['ASSETS_URL']?>/images/icons/16/blue/admin.png">
                                </a>
                            <? else: ?>
                                <a href="<?= $moderatorPermissionsUrl ?>" title="<?=_('Teilnehmende haben eingeschr�nkte Rechte')?>">
                                    <img src="<?=$plugin->getAssetsUrl()?>/images/admin-decline.png">
                                </a>
                            <? endif; ?>

                            <a href="<?= $deleteUrl ?>" title="<?= count($meetingCourse->meeting->courses) > 1 ? _('Zuordnung l�schen') : _('Meeting l�schen') ?>">
                                <? if (count($meetingCourse->meeting->courses) > 1): ?>
                                    <img src="<?=$GLOBALS['ASSETS_URL']?>/images/icons/16/blue/remove.png">
                                <? else: ?>
                                    <img src="<?=$GLOBALS['ASSETS_URL']?>/images/icons/16/blue/trash.png">
                                <? endif ?>
                            </a>
                        <? endif ?>
                    </td>
                <? endif; ?>
                </tr>

                <?php if ($canModifyMeetings): ?>
                <tr class="info">
                    <td colspan="8">
                        <ul>
                            <?php if ($meetingCourse->meeting->join_as_moderator): ?>
                                <li><?=_('Teilnehmende haben VeranstalterInnen-Rechte (wie Anlegende/r).')?></li>
                            <?php else: ?>
                                <li><?=_('Teilnehmende haben eingeschr�nkte Teilnehmenden-Rechte.')?></li>
                            <?php endif; ?>

                            <?php if (count($meetingCourse->meeting->getRecentJoins()) === 1): ?>
                                <li><?=_('Eine Person hat das Meeting in den letzten 24 Stunden betreten')?>.</li>
                            <?php else: ?>
                                <li><?=count($meetingCourse->meeting->getRecentJoins()).' '._('Personen haben das Meeting in den letzten 24 Stunden betreten')?>.</li>
                            <?php endif; ?>

                            <?php if (count($meetingCourse->meeting->getAllJoins()) === 1): ?>
                                <li><?=_('Eine Person hat das Meeting insgesamt betreten')?>.</li>
                            <?php else: ?>
                                <li><?=count($meetingCourse->meeting->getAllJoins()).' '._('Personen haben das Meeting insgesamt betreten')?>.</li>
                            <?php endif; ?>
                        </ul>
                    </td>
                </tr>
                <?php endif ?>
        <?php endforeach; ?>
        </tbody>

        <?php if ($canModifyMeetings): ?>
            <tfoot>
            <tr>
                <td colspan="<?= $colspan ?>">
                    <input class="middle" type="checkbox" name="check_all" title="<?= _('Alle Meetings ausw�hlen') ?>">
                    <?= Studip\Button::create(_('L�schen'), array('title' => _('Alle ausgew�hlten Meetings l�schen'))) ?>
                </td>
            </tr>
            </tfoot>
        <?php endif ?>
    </table>
</form>

<table class="default collapsable tablesorter conference-meetings">
<?php if ($showCreateForm): ?>
    <?= $this->render_partial('index/_create_meeting', array('colspan' => $colspan)) ?>
<?php endif; ?>
</table>
