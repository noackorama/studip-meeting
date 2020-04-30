<?php

/*
 * Copyright (C) 2012 - Till Gl�ggler     <tgloeggl@uos.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */


/**
 * @author    tgloeggl@uos.de
 * @copyright (c) Authors
 */

require_once 'app/controllers/studip_controller.php';

use ElanEv\Driver\DriverFactory;
use ElanEv\Driver\JoinParameters;
use ElanEv\Model\CourseConfig;
use ElanEv\Model\Join;
use ElanEv\Model\Meeting;
use ElanEv\Model\MeetingCourse;
use ElanEv\Model\Driver;
use ElanEv\Model\Helper;

/**
 * @property \MeetingPlugin         $plugin
 * @property bool                   $configured
 * @property \Seminar_Perm          $perm
 * @property \Flexi_TemplateFactory $templateFactory
 * @property CourseConfig           $courseConfig
 * @property bool                   $confirmDeleteMeeting
 * @property bool                   $saved
 * @property string[]               $questionOptions
 * @property bool                   $canModifyCourse
 * @property array                  $errors
 * @property \Semester[]            $semesters
 * @property Meeting[]              $meetings
 * @property Meeting[]              $userMeetings
 * @property CourseConfig           $config
 * @property string                 $deleteAction
 */
class IndexController extends StudipController
{
    /**
     * @var ElanEv\Driver\DriverInterface
     */
    private $driver;

    public function __construct($dispatcher)
    {
        parent::__construct($dispatcher);

        $this->plugin = $GLOBALS['plugin'];
        $this->perm = $GLOBALS['perm'];
        $this->driver_config = Driver::getConfig();
        $this->driver_factory = new DriverFactory(Driver::getConfig());

        $this->configured = false;
        foreach ($this->driver_config as $driver => $config) {
            if ($config['enable']) {
                $this->configured = true;
            } else {
                unset($this->driver_config[$driver]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    function before_filter(&$action, &$args)
    {
        $this->validate_args($args, array('option', 'option'));

        parent::before_filter($action, $args);

        // set default layout
        $this->templateFactory = $GLOBALS['template_factory'];
        $layout = $this->templateFactory->open('layouts/base');
        $this->set_layout($layout);

        PageLayout::addScript($this->plugin->getAssetsUrl().'/js/jquery.tablesorter.min.js');
        PageLayout::addScript($this->plugin->getAssetsUrl().'/js/meetings.js');
        PageLayout::addStylesheet($this->plugin->getAssetsUrl().'/css/meetings.css');
        PageLayout::setHelpKeyword('Basis.Meetings');

        if ($action !== 'my' && Navigation::hasItem('course/'.MeetingPlugin::NAVIGATION_ITEM_NAME)) {
            Navigation::activateItem('course/'.MeetingPlugin::NAVIGATION_ITEM_NAME);
            /** @var Navigation $navItem */
            $navItem = Navigation::getItem('course/'.MeetingPlugin::NAVIGATION_ITEM_NAME);
            $navItem->setImage(MeetingPlugin::getIcon('chat', 'black'));
        } elseif ($action === 'my' && Navigation::hasItem('/meetings')) {
            Navigation::activateItem('/meetings');
        } elseif ($action === 'my' && Navigation::hasItem('/profile/meetings')) {
            Navigation::activateItem('/profile/meetings');
        }

       $this->courseConfig = CourseConfig::findByCourseId($this->getCourseId());

        libxml_use_internal_errors(true);
    }

    public function index_action()
    {
        PageLayout::setTitle(getHeaderLine($this->getCourseId()) .' - '. _('Meetings'));
        $this->getHelpbarContent('main');

        // get messages from rerouted actions
        $this->messages = $_SESSION['studip_meetings_messages'];
        $_SESSION['studip_meetings_messages'] = null;

        /** @var \Seminar_User $user */
        $user = $GLOBALS['user'];
        $course = new Course($this->getCourseId());
        $this->errors = $this->flash['errors'] ?: [];
        $this->deleteAction = PluginEngine::getURL($this->plugin, array(), 'index', true);
        $this->handleDeletion();

        if (Request::get('action') === 'link' && $this->userCanModifyCourse($this->getCourseId())) {
            $linkedMeetingId = Request::int('meeting_id');
            $meeting = new Meeting($linkedMeetingId);

            if (!$meeting->isNew() && $user->cfg->getUserId() === $meeting->user_id && !$meeting->isAssignedToCourse($course)) {
                $meeting->courses[] = new \Course($this->getCourseId());
                $meeting->store();
            }
        }

        $this->canModifyCourse = $this->userCanModifyCourse($this->getCourseId());

        if ($this->canModifyCourse) {
            $this->buildSidebar(
                array(array(
                    'label' => $this->courseConfig->title,
                    'url' => PluginEngine::getLink($this->plugin, array(), 'index'),
                )),
                array(array(
                    'label' => _('Informationen anzeigen'),
                    'url' => '#',
                    'icon' => MeetingPlugin::getIcon('info-circle', 'blue'),
                    'attributes' => array(
                        'class' => 'toggle-info show-info',
                        'data-show-text' => _('Informationen anzeigen'),
                        'data-hide-text' => _('Informationen ausblenden'),
                    ),
                )),
                array(array(
                    'label' => _('Anpassen'),
                    'url' => PluginEngine::getLink($this->plugin, array(), 'index/config'),
                    'icon' => MeetingPlugin::getIcon('admin', 'blue'),
                ))
            );
        } else {
            $this->buildSidebar(array(array(
                    'label' => $this->courseConfig->title,
                    'url' => PluginEngine::getLink($this->plugin, array(), 'index'),
            )));
        }

        if ($this->canModifyCourse) {
            $this->meetings = MeetingCourse::findByCourseId($this->getCourseId());
            $this->userMeetings = MeetingCourse::findLinkableByUser($user, $course);
        } else {
            $this->meetings = MeetingCourse::findActiveByCourseId($this->getCourseId());
            $this->userMeetings = array();
        }
    }

    public function create_action()
    {
        if ($this->userCanModifyCourse($this->getCourseId())) {
            if (!Request::get('name')) {
                $this->flash['errors'] = [_('Bitte geben Sie dem Meeting einen Namen.')];
            } else {
                $this->createMeeting(\Request::get('name'), Request::get('driver'));
            }
        }

        $this->redirect(PluginEngine::getURL($this->plugin, [], 'index'));
    }

    public function my_action($type = null)
    {
        global $user;

        PageLayout::setTitle(_('Meine Meetings'));
        $this->getHelpbarContent('my');
        $this->deleteAction = PluginEngine::getURL($this->plugin, array(), 'index/my', true);
        $this->handleDeletion();

        if ($type === 'name') {
            $this->type = 'name';
            $viewItem = array(
                'label' => _('Anzeige nach Semester'),
                'url' => PluginEngine::getLink($this->plugin, array(), 'index/my'),
                'active' => $type !== 'name',
            );
            $this->meetings = MeetingCourse::findByUser($user);
        } else {
            $viewItem = array(
                'label' => _('Anzeige nach Namen'),
                'url' => PluginEngine::getLink($this->plugin, array(), 'index/my/name'),
                'active' => $type === 'name',
            );
            $this->buildMeetingBlocks(MeetingCourse::findByUser($user));
        }

        $this->buildSidebar(
            array(),
            array(
                $viewItem,
                array(
                    'label' => _('Informationen anzeigen'),
                    'url' => '#',
                    'icon' => MeetingPlugin::getIcon('info-circle', 'blue'),
                    'attributes' => array(
                        'class' => 'toggle-info show-info',
                        'data-show-text' => _('Informationen anzeigen'),
                        'data-hide-text' => _('Informationen ausblenden'),
                    ),
                )
            )
        );
    }

    public function all_action($type = null)
    {
        if (!$GLOBALS['perm']->have_perm('root')) {
            throw new AccessDeniedException(_('Sie brauchen Administrationsrechte.'));
        }
        if (Navigation::hasItem('/admin/locations/meetings')) {
            Navigation::activateItem('/admin/locations');
        } elseif (Navigation::hasItem('/meetings')) {
            Navigation::activateItem('/meetings');
        }

        PageLayout::setTitle(_('Alle Meetings'));

        $this->deleteAction = PluginEngine::getURL($this->plugin, array(), 'index/all', true);
        $this->handleDeletion();

        if ($type === 'name') {
            $this->type = 'name';
            $viewItem = array(
                'label' => _('Anzeige nach Semester'),
                'url' => PluginEngine::getLink($this->plugin, array(), 'index/all'),
                'active' => $type !== 'name',
            );
            $this->meetings = MeetingCourse::findAll();
        } else {
            $viewItem = array(
                'label' => _('Anzeige nach Namen'),
                'url' => PluginEngine::getLink($this->plugin, array(), 'index/all/name'),
                'active' => $type === 'name',
            );
            $this->buildMeetingBlocks(MeetingCourse::findAll());
        }

        $this->buildSidebar(
            array(),
            array(
                $viewItem,
                array(
                    'label' => _('Informationen anzeigen'),
                    'url' => '#',
                    'icon' => MeetingPlugin::getIcon('info-circle', 'blue'),
                    'attributes' => array(
                        'class' => 'toggle-info show-info',
                        'data-show-text' => _('Informationen anzeigen'),
                        'data-hide-text' => _('Informationen ausblenden'),
                    ),
                )
            )
        );
    }

    public function enable_action($meetingId, $courseId)
    {
        $meeting = new MeetingCourse(array($meetingId, $courseId));

        if (!$meeting->isNew() && $this->userCanModifyCourse($meeting->course->id)) {
            $meeting->active = !$meeting->active;
            $meeting->store();
        }

        $this->redirect(PluginEngine::getURL($this->plugin, array(), Request::get('destination')));
    }

    public function edit_action($meetingId)
    {
        $meeting = new Meeting($meetingId);
        $name = utf8_decode(Request::get('name'));
        $recordingUrl = utf8_decode(Request::get('recording_url'));

        if (!$meeting->isNew() && $this->userCanModifyCourse($this->getCourseId()) && $name) {
            $meeting = new Meeting($meetingId);
            $meeting->name = $name;
            $meeting->recording_url = $recordingUrl;
            $meeting->store();
        }

        $this->redirect(PluginEngine::getURL($this->plugin, array(), 'index'));
    }

    public function moderator_permissions_action($meetingId)
    {
        $meeting = new Meeting($meetingId);

        if (!$meeting->isNew() && $this->userCanModifyCourse($this->getCourseId())) {
            $meeting->join_as_moderator = !$meeting->join_as_moderator;
            $meeting->store();
        }

        $this->redirect(PluginEngine::getURL($this->plugin, array(), Request::get('destination')));
    }

    public function delete_action($meetingId, $courseId)
    {
        $this->deleteMeeting($meetingId, $courseId);

        if (Request::get('destination') == 'index/my') {
            $destination = 'index/my';
        } else {
            $destination = 'index';
        }

        $this->redirect(PluginEngine::getURL($this->plugin, array(), $destination));
    }

    /**
     *  redirects to active BBB meeting.
     */
    public function joinMeeting_action($meetingId)
    {
        /*
        if(!$this->hasActiveMeeting()) {
            $this->redirect(PluginEngine::getURL($this->plugin, array(), 'index'));
            return;
        }
         *
         */

        /** @var Seminar_User $user */
        $user = $GLOBALS['user'];

        $meeting = Meeting::find($meetingId);
        if (!($meeting && $meeting->courses->find($this->getCourseId()))) {
            throw new Trails_Exception(400);
        }
        $driver = $this->driver_factory->getDriver($meeting->driver);
        // ugly hack for BBB
        if ($driver instanceof ElanEv\Driver\BigBlueButton) {
            // TODO: check if recreation is necessary
            $meetingParameters = $meeting->getMeetingParameters();
            $driver->createMeeting($meetingParameters);
        }
        $joinParameters = new JoinParameters();
        $joinParameters->setMeetingId($meetingId);
        $joinParameters->setIdentifier($meeting->identifier);
        $joinParameters->setRemoteId($meeting->remote_id);
        $joinParameters->setUsername(get_username($user->id));
        $joinParameters->setEmail($user->Email);
        $joinParameters->setFirstName($user->Vorname);
        $joinParameters->setLastName($user->Nachname);


        if ($this->userCanModifyCourse($this->getCourseId()) || $meeting->join_as_moderator) {
            $joinParameters->setPassword($meeting->moderator_password);
            $joinParameters->setHasModerationPermissions(true);
        } else {
            $joinParameters->setPassword($meeting->attendee_password);
            $joinParameters->setHasModerationPermissions(false);
        }

        $lastJoin = new Join();
        $lastJoin->meeting_id = $meetingId;
        $lastJoin->user_id = $user->cfg->getUserId();
        $lastJoin->last_join = time();
        $lastJoin->store();

        try {
            if ($join_url = $driver->getJoinMeetingUrl($joinParameters)) {
                $this->redirect($driver->getJoinMeetingUrl($joinParameters));
            } else {
                $_SESSION['studip_meetings_messages']['error'][] = 'Konnte dem Meeting nicht beitreten, Kommunikation mit dem Meeting-Server fehlgeschlagen.';
                $this->redirect(PluginEngine::getURL($this->plugin, array(), 'index'));
            }
        } catch (Exception $e) {
            $_SESSION['studip_meetings_messages']['error'][] = 'Konnte dem Meeting nicht beitreten, Kommunikation mit dem Meeting-Server fehlgeschlagen. ('. $e->getMessage() .')';
            $this->redirect(PluginEngine::getURL($this->plugin, array(), 'index'));
        }
    }

    public function config_action()
    {
        PageLayout::setTitle(getHeaderLine($this->getCourseId()) .' - '. _('Meetings'));
        $this->getHelpbarContent('config');
        $courseId = $this->getCourseId();

        if (!$this->userCanModifyCourse($courseId)) {
            $this->redirect(PluginEngine::getURL($this->plugin, array(), 'index'));
        }

        if (Request::method() === 'POST') {
            $this->courseConfig->title = Request::get('title');
            $this->courseConfig->introduction = Request::get('introduction');
            $this->courseConfig->store();
            $this->saved = true;

            $this->redirect(PluginEngine::getURL($this->plugin, array(), 'index/config'));
        }

        $this->buildSidebar(
            array(array(
                'label' => $this->courseConfig->title,
                'url' => PluginEngine::getLink($this->plugin, array(), 'index'),
            )),
            array(),
            array(array(
                'label' => _('Anpassen'),
                'url' => PluginEngine::getLink($this->plugin, array(), 'index/config'),
                'icon' => MeetingPlugin::getIcon('admin', 'blue'),
            ))
        );
    }

    public function saveConfig_action()
    {
        if ($GLOBALS['perm']->have_perm('root')) {
            foreach (Request::getArray('config') as $option => $data) {
                Config::get()->store($option, $data);
            }
        } else {
            throw new AccessDeniedException('You need to be root to perform this action!');
        }

        // TODO: FIXME -> set correct link main plugin class so there is no need for this hack
        $this->redirect(PluginEngine::getLink($this->plugin, array(), 'index'));
    }

    /* * * * * * * * * * * * * * * * * * * * * * * * * */
    /* * * * * H E L P E R   F U N C T I O N S * * * * */
    /* * * * * * * * * * * * * * * * * * * * * * * * * */

    private function getCourseId()
    {
        if (!Request::option('cid')) {
            if ($GLOBALS['SessionSeminar']) {
                URLHelper::bindLinkParam('cid', $GLOBALS['SessionSeminar']);
                return $GLOBALS['SessionSeminar'];
            }

            return false;
        }

        return Request::option('cid');
    }

    /**
     * @param string $name
     * @param string $driver_name
     *
     * @return bool
     */
    private function createMeeting($name, $driver_name)
    {
        /** @var \Seminar_User $user */
        global $user;

        $meeting = new Meeting();
        $meeting->courses[] = new Course($this->getCourseId());
        $meeting->user_id = $user->cfg->getUserId();
        $meeting->name = $name;
        $meeting->driver = $driver_name;
        $meeting->attendee_password = $this->generateAttendeePassword();
        $meeting->moderator_password = $this->generateModeratorPassword();
        $meeting->remote_id = md5(uniqid());
        $meeting->store();
        $meetingParameters = $meeting->getMeetingParameters();

        $driver = $this->driver_factory->getDriver($driver_name);

        try {
            if (!$driver->createMeeting($meetingParameters)) {
                return false;
            }
        } catch (Exception $e) {
            $_SESSION['studip_meetings_messages']['error'][] = $e->getMessage();
            return false;
        }

        $meeting->remote_id = $meetingParameters->getRemoteId();
        $meeting->store();

        return true;
    }

    private function userCanModifyCourse($courseId)
    {
        return $this->perm->have_studip_perm('tutor', $courseId);
    }

    private function generateModeratorPassword()
    {
        return Helper::createPassword();
    }

    private function generateAttendeePassword()
    {
        return Helper::createPassword();
    }

    private function buildSidebar($navigationItems = array(), $viewsItems = array(), $actionsItems = array())
    {
        $sidebar = Sidebar::Get();

        $sections = array(
            array(
                'label' => _('Navigation'),
                'class' => 'sidebar-meeting-navigation',
                'items' => $navigationItems,
            ),
            array(
                'label' => _('Ansichten'),
                'class' => 'sidebar-meeting-views',
                'items' => $viewsItems,
            ),
            array(
                'label' => _('Aktionen'),
                'class' => 'sidebar-meeting-actions',
                'items' => $actionsItems,
            ),
        );

        foreach ($sections as $section) {
            if (count($section['items']) > 0) {
                $navigation = new ActionsWidget();
                $navigation->addCSSClass($section['class']);
                $navigation->setTitle($section['label']);

                foreach ($section['items'] as $item) {
                    $link = $navigation->addLink(
                        $item['label'],
                        $item['url'],
                        isset($item['icon']) ? $item['icon'] : null,
                        isset($item['attributes']) && is_array($item['attributes']) ? $item['attributes'] : array()
                    );

                    if (isset($item['active']) && $item['active']) {
                        $link->setActive(true);
                    }
                }

                $sidebar->addWidget($navigation);
            }
        }
    }

    private function buildMeetingBlocks(array $meetingCourses)
    {
        $this->semesters = array();
        $this->meetings = array();

        foreach ($meetingCourses as $meetingCourse) {
            $semester = $meetingCourse->course->start_semester;

            if ($semester === null) {
                $now = new \DateTime();
                $semester = \Semester::findByTimestamp($now->getTimestamp());
            }

            if (!isset($this->semesters[$semester->id])) {
                $this->semesters[$semester->id] = $semester;
                $this->meetings[$semester->id] = array();
            }

            $this->meetings[$semester->id][] = $meetingCourse;
        }

        usort($this->semesters, function ($semester1, $semester2) {
            return $semester2->beginn - $semester1->beginn;
        });
    }

    private function handleDeletion()
    {
        if (Request::get('action') === 'multi-delete') {
            $this->handleMultiDeletion();
        } elseif (Request::get('delete') > 0 && Request::get('cid')) {
            $meeting = new Meeting(Request::get('delete'));

            if (!$meeting->isNew()) {
                $this->confirmDeleteMeeting = true;
                $this->questionOptions = array(
                    'question' => _('Wollen Sie wirklich das Meeting "').$meeting->name._('" l�schen?'),
                    'approvalLink' => PluginEngine::getLink($this->plugin, array('destination' => Request::get('destination')), 'index/delete/'.$meeting->id.'/'.Request::get('cid'), true),
                    'disapprovalLink' => PluginEngine::getLink($this->plugin, array(),  Request::get('destination')),
                );
            }
        }
    }

    private function handleMultiDeletion()
    {
        $deleteMeetings = array();
        foreach (Request::getArray('meeting_ids') as $deleteMeetingsId) {
            list($meetingId, $courseId) = explode('-', $deleteMeetingsId);
            $meetingCourse = new MeetingCourse(array($meetingId, $courseId));
            if (!$meetingCourse->isNew()) {
                $deleteMeetings[] = $meetingCourse;
            }
        }

        if (Request::submitted('confirm')) {
            foreach ($deleteMeetings as $meetingCourse) {
                $this->deleteMeeting($meetingCourse->meeting->id, $meetingCourse->course->id);
            }
        } elseif (!Request::submitted('cancel')) {
            $this->confirmDeleteMeeting = true;
            $this->questionOptions = array(
                'question' => _('Wollen Sie folgende Meetings wirklich l�schen?'),
                'approvalLink' => PluginEngine::getLink($this->plugin, array(), 'index/delete/'.$meeting->id.'/'.Request::get('cid')),
                'disapprovalLink' => PluginEngine::getLink($this->plugin, array(), Request::get('destination')),
                'deleteMeetings' => $deleteMeetings,
                'destination' => $this->deleteAction,
            );
        }
    }

    private function deleteMeeting($meetingId, $courseId)
    {
        $meetingCourse = new MeetingCourse(array($meetingId, $courseId));

        if (!$meetingCourse->isNew() && $this->userCanModifyCourse($meetingCourse->course->id)) {
            // don't associate the meeting and the course any more
            $meetingId = $meetingCourse->meeting->id;
            $meetingCourse->delete();

            $meeting = new Meeting($meetingId);

            // if the meeting isn't associated with at least one course at all,
            // it can be removed entirely
            if (count($meeting->courses) === 0) {
                // inform the driver to delete the meeting as well
                $driver = $this->driver_factory->getDriver($meeting->driver);
                try {
                    $driver->deleteMeeting($meeting->getMeetingParameters());
                } catch (Exception $e) {
                    $_SESSION['studip_meetings_messages']['error'][] = $e->getMessage();
                }

                $meeting->delete();
            }
        }
    }

    private function getHelpbarContent($id)
    {
        /** @var \Helpbar $helpBar */

        switch ($id) {

            case 'main':
                $helpText = _('Durchf�hrung und Verwaltung von Live-Online-Treffen, Webinaren und Videokonferenzen. '
                          . 'Mit Hilfe der Face-to-Face-Kommunikation k�nnen Entfernungen �berbr�ckt, externe Fachleute '
                          . 'einbezogen und Studierende in Projekten und Praktika begleitet werden.');
                $helpBar = Helpbar::get();
                $helpBar->addPlainText('', $helpText);
                break;

            case 'config':
                $helpText = _('Arbeitsbereich zum Anpassen der Gesamtansicht der Meetings einer Veranstaltung.');
                $helpBar = Helpbar::get();
                $helpBar->addPlainText('', $helpText);
                break;

            case 'my':
                $helpText = _('Gesamtansicht aller von Ihnen erstellten Meetings nach '
                          . 'Semestern oder nach Namen sortiert.');
                $helpBar = Helpbar::get();
                $helpBar->addPlainText('', $helpText);
                break;
        }
    }
}
