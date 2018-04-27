<?php
/**
 * Class xoctEventGUI
 *
 * @author            Fabian Schmid <fs@studer-raimann.ch>
 *
 * @ilCtrl_IsCalledBy xoctEventGUI: ilObjOpenCastGUI
 */
class xoctEventGUI extends xoctGUI {

	const IDENTIFIER = 'eid';
	const CMD_SHOW_CONTENT = 'showContent';
	const CMD_CLEAR_CACHE = 'clearCache';
	const CMD_EDIT_OWNER = 'editOwner';
	const CMD_UPDATE_OWNER = 'updateOwner';
	const CMD_UPLOAD_CHUNKS = 'uploadChunks';
	const CMD_SET_ONLINE = 'setOnline';
	const CMD_SET_OFFLINE = 'setOffline';
	const CMD_CUT = 'cut';
	const CMD_REPORT_DATE = 'reportDate';
	const CMD_REPORT_QUALITY = 'reportQuality';
	/**
	 * @var \xoctOpenCast
	 */
	protected $xoctOpenCast;


	/**
	 * @param xoctOpenCast $xoctOpenCast
	 */
	public function __construct(xoctOpenCast $xoctOpenCast = null) {
		parent::__construct();
		if ($xoctOpenCast instanceof xoctOpenCast) {
			$this->xoctOpenCast = $xoctOpenCast;
		} else {
			$this->xoctOpenCast = new xoctOpenCast();
		}
		$this->tabs->setTabActive(ilObjOpenCastGUI::TAB_EVENTS);
		$this->tpl->addCss('./Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/templates/default/events.css');
		$this->tpl->addJavaScript('./Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/templates/default/events.js');
	}


	/**
	 * @param $cmd
	 */
	protected function performCommand($cmd) {
		switch ($cmd) {
			case self::CMD_STANDARD:
			case self::CMD_SHOW_CONTENT:
				$this->prepareContent();
				break;
			default:
		}
		parent::performCommand($cmd);
	}


	/**
	 *
	 */
	protected function prepareContent() {
		global $ilUser;

		// init waiter
		xoctWaiterGUI::initJS();
		xoctWaiterGUI::addLinkOverlay('#rep_robj_xoct_event_clear_cache');

		// add "add" button
		if (ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_ADD_EVENT)) {
			$b = ilLinkButton::getInstance();
			$b->setCaption('rep_robj_xoct_event_add_new');
			$b->setUrl($this->ctrl->getLinkTarget($this, self::CMD_ADD));
			$b->setPrimary(true);
			$this->toolbar->addButtonInstance($b);
		}

		// add "clear cache" button
		if (xoctConf::getConfig(xoctConf::F_ACTIVATE_CACHE)) {
			$b = ilLinkButton::getInstance();
			$b->setId('rep_robj_xoct_event_clear_cache');
			$b->setCaption('rep_robj_xoct_event_clear_cache');
			$b->setUrl($this->ctrl->getLinkTarget($this, self::CMD_CLEAR_CACHE));
			$this->toolbar->addButtonInstance($b);
		}

		// add "clear clips" button (devmode)
		if ($ilUser->getId() == 6 && ilObjOpenCast::DEV) {
			$b = ilLinkButton::getInstance();
			$b->setCaption('rep_robj_xoct_event_clear_clips_develop');
			$b->setUrl($this->ctrl->getLinkTarget($this, 'clearAllClips'));
			$this->toolbar->addButtonInstance($b);
		}

		// add "report date change" button
		if (ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_REPORT_DATE_CHANGE)) {
			$b = ilButton::getInstance();
			$b->setCaption('rep_robj_xoct_event_report_date_modification');
			$b->setOnClick("$('#xoct_report_date_modal').modal('show');");

			$this->toolbar->addButtonInstance($b);
		}
	}


	/**
	 * same cmd as standard command (index()), except it's synchronous
	 */
	protected function showContent() {
		$intro_text = '';
		if ($this->xoctOpenCast->getIntroductionText()) {
			$intro = new ilTemplate('./Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/templates/default/tpl.intro.html', '', true, true);
			$intro->setVariable('INTRO', nl2br($this->xoctOpenCast->getIntroductionText()));
			$intro_text = $intro->get();
		}

		$xoctEventTableGUI = new xoctEventTableGUI($this, self::CMD_STANDARD, $this->xoctOpenCast, true);
		$this->tpl->setContent($intro_text . $xoctEventTableGUI->getHTML() . $this->getModalsHTML());
	}


	/**
	 * asynchronous loading of tableGUI
	 */
	protected function index() {

		$intro_text = '';
		if ($this->xoctOpenCast->getIntroductionText()) {
			$intro = new ilTemplate('./Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/templates/default/tpl.intro.html', '', true, true);
			$intro->setVariable('INTRO', nl2br($this->xoctOpenCast->getIntroductionText()));
			$intro_text = $intro->get();
		}
		// DELETE AFTER USAGE
		//		$b = ilLinkButton::getInstance();
		//		$b->setCaption('rechte_neuladen_hack');
		//		$b->setUrl($this->ctrl->getLinkTarget($this, 'resetPermissions'));
		//		$this->toolbar->addButtonInstance($b);

		if (isset($_GET[xoctEventTableGUI::getGeneratedPrefix($this->xoctOpenCast) . '_xpt']) || !empty($_POST)) {
			$xoctEventTableGUI = new xoctEventTableGUI($this, self::CMD_STANDARD, $this->xoctOpenCast);
			$this->tpl->setContent($intro_text . $xoctEventTableGUI->getHTML() . $this->getModalsHTML());
			return;
		}

		$this->tpl->setContent($intro_text . '<div id="xoct_table_placeholder"></div>' . $this->getModalsHTML());
		$this->tpl->addJavascript("./Services/Table/js/ServiceTable.js");
		$this->loadAjaxCode();
	}


	/**
	 *
	 */
	protected function loadAjaxCode() {
		foreach ($_GET as $para => $value) {
			$this->ctrl->setParameter($this, $para, $value);
		}

		$ajax_link = $this->ctrl->getLinkTarget($this, 'asyncGetTableGUI', "", true);

		// hacky stuff to allow asynchronous rendering of tableGUI
		$table_id = xoctEventTableGUI::getGeneratedPrefix($this->xoctOpenCast);
		$user_id = $this->user->getId();
		$tab_prop = new ilTablePropertiesStorage();
		if ($tab_prop->getProperty($table_id, $user_id, 'filter')) {
			$activate_filter_commmand = "ilShowTableFilter('tfil_$table_id', './ilias.php?baseClass=ilTablePropertiesStorage&table_id=$table_id&cmd=showFilter&user_id=$user_id');";
		}

		$ajax = "$.ajax({
				    url: '{$ajax_link}',
				    dataType: 'html',
				    success: function(data){
				        xoctWaiter.hide();
				        $('div#xoct_table_placeholder').replaceWith($(data));
				        $activate_filter_commmand
				    }
				});";
		$this->tpl->addOnLoadCode('xoctWaiter.show();');
		$this->tpl->addOnLoadCode($ajax);
	}


	/**
	 *
	 */
	public function asyncGetTableGUI() {
		$xoctEventTableGUI = new xoctEventTableGUI($this, self::CMD_STANDARD, $this->xoctOpenCast);
		echo $xoctEventTableGUI->getHTML();
		exit();
	}


	/**
	 *
	 */
	protected function applyFilter() {
		$xoctEventTableGUI = new xoctEventTableGUI($this, self::CMD_STANDARD, $this->xoctOpenCast, false);
		$xoctEventTableGUI->resetOffset(true);
		$xoctEventTableGUI->writeFilterToSession();
		$this->ctrl->redirect($this, self::CMD_STANDARD);
	}


	/**
	 *
	 */
	protected function resetFilter() {
		//		xoctEventTableGUI::setDefaultRowValue($this->xoctOpenCast);
		$xoctEventTableGUI = new xoctEventTableGUI($this, self::CMD_STANDARD, $this->xoctOpenCast, false);
		$xoctEventTableGUI->resetOffset();
		$xoctEventTableGUI->resetFilter();
		$this->ctrl->redirect($this, self::CMD_STANDARD);
	}


	/**
	 *
	 */
	protected function add() {
		if ($this->xoctOpenCast->getDuplicatesOnSystem()) {
			ilUtil::sendInfo($this->pl->txt('series_has_duplicates_events'));
		}
		$xoctEventFormGUI = new xoctEventFormGUI($this, new xoctEvent(), $this->xoctOpenCast);
		$xoctEventFormGUI->fillForm();
		$this->tpl->setContent($xoctEventFormGUI->getHTML());
	}


	/**
	 *
	 */
	protected function create() {
		global $ilUser;
		$xoctUser = xoctUser::getInstance($ilUser);
		$xoctEventFormGUI = new xoctEventFormGUI($this, new xoctEvent(), $this->xoctOpenCast);

		$xoctAclStandardSets = new xoctAclStandardSets($xoctUser->getOwnerRoleName() ? array($xoctUser->getOwnerRoleName(), $xoctUser->getUserRoleName()) : array());
		$xoctEventFormGUI->getObject()->setAcl($xoctAclStandardSets->getAcls());

		if ($xoctEventFormGUI->saveObject()) {
			ilUtil::sendSuccess($this->txt('msg_created'), true);
			$this->ctrl->redirect($this, self::CMD_STANDARD);
		}
		$xoctEventFormGUI->setValuesByPost();
		$this->tpl->setContent($xoctEventFormGUI->getHTML());
	}


	/**
	 *
	 */
	protected function uploadChunks() {
		$xoctPlupload = new xoctPlupload();
		$xoctPlupload->handleUpload();
	}


	/**
	 *
	 */
	protected function edit() {
		global $ilUser;
		/**
		 * @var xoctEvent $xoctEvent
		 */
		$xoctEvent = xoctEvent::find($_GET[self::IDENTIFIER]);
		$xoctUser = xoctUser::getInstance($ilUser);

		// check access
		if (!ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_EDIT_EVENT, $xoctEvent, $xoctUser)) {
			ilUtil::sendFailure($this->txt('msg_no_access'), true);
			$this->cancel();
		}

		$xoctEventFormGUI = new xoctEventFormGUI($this, $xoctEvent, $this->xoctOpenCast);
		$xoctEventFormGUI->fillForm();
		$this->tpl->setContent($xoctEventFormGUI->getHTML());
	}


	/**
	 *
	 */
	public function cut() {
		global $ilUser;
		$xoctUser = xoctUser::getInstance($ilUser);
		$xoctEvent = xoctEvent::find($_GET[self::IDENTIFIER]);

		// check access
		if (!ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_CUT, $xoctEvent, $xoctUser)) {
			ilUtil::sendFailure($this->txt('msg_no_access'), true);
			$this->cancel();
		}

		// will be set true if the user role is added to producers, since in that case there must be a short sleep() before redirecting
		$sleep = false;

		// add user to ilias producers
		try {
			$ilias_producers = xoctGroup::find(xoctConf::getConfig(xoctConf::F_GROUP_PRODUCERS));
			$sleep = $ilias_producers->addMember($xoctUser);
		} catch (xoctException $e) {
			$sleep = false;
		}

		// add user to series producers
		/** @var xoctSeries $xoctSeries */
		$xoctSeries = xoctSeries::find($xoctEvent->getSeriesIdentifier());
		$sleep = $xoctSeries->addProducer($xoctUser);

		if ($sleep) {
			sleep(3);
		}

		// redirect
		$cutting_link = $xoctEvent->getCuttingLink();
		header('Location: ' . $cutting_link);
	}


	/**
	 *
	 */
	public function setOnline() {
		$xoctEvent = xoctEvent::find($_GET[self::IDENTIFIER]);
		$xoctEvent->getXoctEventAdditions()->setIsOnline(true);
		$xoctEvent->getXoctEventAdditions()->update();
		$this->cancel();
	}


	/**
	 *
	 */
	public function setOffline() {
		$xoctEvent = xoctEvent::find($_GET[self::IDENTIFIER]);
		$xoctEvent->getXoctEventAdditions()->setIsOnline(false);
		$xoctEvent->getXoctEventAdditions()->update();
		$this->cancel();
	}


	/**
	 *
	 */
	public function streamVideo() {
		global $ilUser;
		$xoctEvent = xoctEvent::find($_GET[self::IDENTIFIER]);
		// check access
		if (!ilObjOpenCastAccess::hasReadAccessOnEvent($xoctEvent,xoctUser::getInstance($ilUser), $this->xoctOpenCast)) {
			ilUtil::sendFailure($this->txt('msg_no_access'), true);
			$this->cancel();
		}

		$publication_metadata = $xoctEvent->getPublicationMetadataForUsage(xoctPublicationUsage::getUsage(xoctPublicationUsage::USAGE_API));

		foreach ($publication_metadata->getMedia() as $media) {
			$url = $media->getUrl();

			// DELETE AFTER TESTING !!!!
//			$url = str_replace("localhost",'10.0.2.2',$url);
			// DELETE AFTER TESTING !!!!

			// find first media publication with video content
			if (strpos($media->getMediatype(),'video') !== false) {
				if (xoctConf::getConfig(xoctConf::F_SIGN_PLAYER_LINKS)) {
					$url = xoctSecureLink::sign($url);
				}
				// set the necessary headers from the original url
				$origin_headers = get_headers($url);
				foreach ($origin_headers as $origin_header) {
					if (strpos($origin_header,'Content-Length') !== false || strpos($origin_header,'Accept-Ranges') !== false) {
						header($origin_header);
					}
				}
				header('Content-Type: ' . $media->getMediatype());
				readfile($url);
				exit;
			}
		}

	}


	/**
	 *
	 */
	protected function saveAndStay() {
		global $ilUser;
		/**
		 * @var xoctEvent $xoctEvent
		 */
		$xoctEvent = xoctEvent::find($_GET[self::IDENTIFIER]);
		$xoctUser = xoctUser::getInstance($ilUser);
		if (!ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_EDIT_EVENT, $xoctEvent, $xoctUser)) {
			ilUtil::sendFailure($this->txt('msg_no_access'), true);
			$this->cancel();
		}

		$xoctEventFormGUI = new xoctEventFormGUI($this, xoctEvent::find($_GET[self::IDENTIFIER]), $this->xoctOpenCast);
		$xoctEventFormGUI->setValuesByPost();

		if ($xoctEventFormGUI->saveObject()) {
			ilUtil::sendSuccess($this->txt('msg_success'), true);
			$this->ctrl->redirect($this, self::CMD_EDIT);
		}
		$this->tpl->setContent($xoctEventFormGUI->getHTML());
	}


	/**
	 *
	 */
	protected function update() {
		global $ilUser;
		/**
		 * @var xoctEvent $xoctEvent
		 */
		$xoctEvent = xoctEvent::find($_GET[self::IDENTIFIER]);
		$xoctUser = xoctUser::getInstance($ilUser);
		if (!ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_EDIT_EVENT, $xoctEvent, $xoctUser)) {
			ilUtil::sendFailure($this->txt('msg_no_access'), true);
			$this->cancel();
		}

		$xoctEventFormGUI = new xoctEventFormGUI($this, xoctEvent::find($_GET[self::IDENTIFIER]), $this->xoctOpenCast);
		$xoctEventFormGUI->setValuesByPost();

		if ($xoctEventFormGUI->saveObject()) {
			ilUtil::sendSuccess($this->txt('msg_success'), true);
			$this->ctrl->redirect($this, self::CMD_STANDARD);
		}
		$this->tpl->setContent($xoctEventFormGUI->getHTML());
	}


	/**
	 *
	 */
	protected function removeInvitations() {
		foreach (xoctInvitation::get() as $xoctInvitation) {
			$xoctInvitation->delete();
		}
		ilUtil::sendSuccess($this->txt('msg_success'), true);
		$this->ctrl->redirect($this, self::CMD_STANDARD);
	}


	/**
	 *
	 */
	protected function clearAllClips() {
		$filter = array( 'series' => $this->xoctOpenCast->getSeriesIdentifier() );
		$a_data = xoctEvent::getFiltered($filter, null, null);
		/**
		 * @var $xoctEvent      xoctEvent
		 * @var $xoctInvitation xoctInvitation
		 * @var $xoctGroup      xoctIVTGroup
		 */
		foreach ($a_data as $i => $d) {
			$xoctEvent = xoctEvent::find($d['identifier']);
			$xoctEvent->setTitle('Clip ' . $i);
			$xoctEvent->setDescription('Subtitle ' . $i);
			$xoctEvent->setPresenter('Presenter ' . $i);
			$xoctEvent->setLocation('Station ' . $i);
			$xoctEvent->setCreated(new DateTime());
			$xoctEvent->removeOwner();
			$xoctEvent->removeAllOwnerAcls();
			$xoctEvent->update();
			foreach (xoctInvitation::where(array( 'event_identifier' => $xoctEvent->getIdentifier() ))->get() as $xoctInvitation) {
				$xoctInvitation->delete();
			}
		}
		foreach (xoctIVTGroup::where(array( 'serie_id' => $this->xoctOpenCast->getObjId() ))->get() as $xoctGroup) {
			$xoctGroup->delete();
		}

		$this->cancel();
	}


	/**
	 *
	 */
	protected function resetPermissions() {
		$filter = array( 'series' => $this->xoctOpenCast->getSeriesIdentifier() );
		$a_data = xoctEvent::getFiltered($filter, null, null);
		/**
		 * @var $xoctEvent      xoctEvent
		 * @var $xoctInvitation xoctInvitation
		 * @var $xoctGroup      xoctIVTGroup
		 */
		$errors = 'Folgende Clips konnten nicht upgedatet werde: ';
		foreach ($a_data as $i => $d) {
			$xoctEvent = xoctEvent::find($d['identifier']);
			try {
				$xoctEvent->update();
			} catch (xoctException $e) {
				$errors .= $xoctEvent->getTitle() . '; ';
			}
		}
		$this->cancel();
	}


	/**
	 *
	 */
	protected function confirmDelete() {
		global $ilUser;
		/**
		 * @var xoctEvent $xoctEvent
		 */
		$xoctEvent = xoctEvent::find($_GET[self::IDENTIFIER]);
		$xoctUser = xoctUser::getInstance($ilUser);
		if (!ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_DELETE_EVENT, $xoctEvent, $xoctUser)) {
			ilUtil::sendFailure($this->txt('msg_no_access'), true);
			$this->cancel();
		}
		$ilConfirmationGUI = new ilConfirmationGUI();
		$ilConfirmationGUI->setFormAction($this->ctrl->getFormAction($this));
		$header_text = $this->xoctOpenCast->getDuplicatesOnSystem() ? $this->txt('delete_confirm_w_duplicates') : $this->txt('delete_confirm');
		$ilConfirmationGUI->setHeaderText($header_text);
		$ilConfirmationGUI->setCancel($this->txt('cancel'), self::CMD_CANCEL);
		$ilConfirmationGUI->setConfirm($this->txt('delete'), self::CMD_DELETE);
		$ilConfirmationGUI->addItem(self::IDENTIFIER, $xoctEvent->getIdentifier(), $xoctEvent->getTitle());
		$this->tpl->setContent($ilConfirmationGUI->getHTML());
	}


	/**
	 *
	 */
	protected function delete() {
		global $ilUser;
		$xoctEvent = xoctEvent::find($_POST[self::IDENTIFIER]);
		$xoctUser = xoctUser::getInstance($ilUser);
		if (!ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_DELETE_EVENT, $xoctEvent, $xoctUser)) {
			ilUtil::sendFailure($this->txt('msg_no_access'), true);
			$this->cancel();
		}
		$xoctEvent->delete();
		ilUtil::sendSuccess($this->txt('msg_deleted'), true);
		$this->cancel();
	}


	/**
	 *
	 */
	protected function view() {
		$xoctEvent = xoctEvent::find($_GET[self::IDENTIFIER]);
		echo '<pre>' . print_r($xoctEvent, 1) . '</pre>';
		exit;
		//		$xoctEventFormGUI = new xoctEventFormGUI($this, $xoctEvent, $this->xoctOpenCast, true);
		//		$xoctEventFormGUI->fillForm();
		//		$this->tpl->setContent($xoctEventFormGUI->getHTML());
	}


	/**
	 *
	 */
	protected function search() {
		/**
		 * @var $event xoctEvent
		 */
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->addCommandButton('import', 'Import');
		$self = new ilSelectInputGUI('import_identifier', 'import_identifier');

		$request = xoctRequest::root()->events()->parameter('limit', 1000);
		$data = json_decode($request->get());
		$ids = array();
		foreach ($data as $d) {
			$event = xoctEvent::find($d->identifier);
			$ids[$event->getIdentifier()] = $event->getTitle() . ' (...' . substr($event->getIdentifier(), - 4, 4) . ')';
		}
		array_multisort($ids);

		$self->setOptions($ids);
		$form->addItem($self);
		$this->tpl->setContent($form->getHTML());
	}


	/**
	 *
	 */
	protected function import() {
		/**
		 * @var $event xoctEvent
		 */
		// $event = xoctEvent::find($_POST['import_identifier']);
		$event = xoctEvent::find($_POST['import_identifier']);
		$html = 'Series before set: ' . $event->getSeriesIdentifier() . '<br>';
		$event->setSeriesIdentifier($this->xoctOpenCast->getSeriesIdentifier());
		$html .= 'Series after set: ' . $event->getSeriesIdentifier() . '<br>';
		//		$event->updateSeries();
		$event->updateSeries();
		$html .= 'Series after update: ' . $event->getSeriesIdentifier() . '<br>';
		//		echo '<pre>' . print_r($event, 1) . '</pre>';
		$event = new xoctEvent($_POST['import_identifier']);
		$html .= 'Series after new read: ' . $event->getSeriesIdentifier() . '<br>';

		//		$html .= 'POST: ' . $_POST['import_identifier'];
		$this->tpl->setContent($html);
		//		$this->ctrl->redirect($this, self::CMD_STANDARD);
	}


	/**
	 *
	 */
	protected function listAll() {
		/**
		 * @var $event xoctEvent
		 */
		$request = xoctRequest::root()->events()->parameter('limit', 1000);
		$content = '';
		foreach (json_decode($request->get()) as $d) {
			$event = xoctEvent::find($d->identifier);
			$content .= '<pre>' . print_r($event->__toStdClass(), 1) . '</pre>';
		}
		$this->tpl->setContent($content);
	}


	/**
	 *
	 */
	protected function clearCache() {
		xoctCacheFactory::getInstance()->flush();
		$this->xoctOpenCast->getSeriesIdentifier();
		xoctEvent::getFiltered(array( 'series' => $this->xoctOpenCast->getSeriesIdentifier() ));
		$this->ctrl->redirect($this, self::CMD_SHOW_CONTENT);
	}


	/**
	 *
	 */
	protected function editOwner() {
		$xoctEventOwnerFormGUI = new xoctEventOwnerFormGUI($this, xoctEvent::find($_GET[self::IDENTIFIER]), $this->xoctOpenCast);
		$xoctEventOwnerFormGUI->fillForm();
		$this->tpl->setContent($xoctEventOwnerFormGUI->getHTML());
	}


	/**
	 *
	 */
	protected function updateOwner() {
		$xoctEventOwnerFormGUI = new xoctEventOwnerFormGUI($this, xoctEvent::find($_GET[self::IDENTIFIER]), $this->xoctOpenCast);
		$xoctEventOwnerFormGUI->setValuesByPost();
		if ($xoctEventOwnerFormGUI->saveObject()) {
			ilUtil::sendSuccess($this->txt('msg_success'), true);
			$this->ctrl->redirect($this, self::CMD_STANDARD);
		}
	}


	/**
	 * @return string
	 */
	protected function getModalsHTML() {
		$modal_date_html = $modal_quality_html = '';
		if (ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_REPORT_DATE_CHANGE)) {
			$modal_date = new xoctReportingModalGUI($this, xoctReportingModalGUI::REPORTING_TYPE_DATE);
			$modal_date_html = $modal_date->getHTML();
		}
		if (xoctConf::getConfig(xoctConf::F_REPORT_QUALITY)) {
			$modal_quality = new xoctReportingModalGUI($this, xoctReportingModalGUI::REPORTING_TYPE_QUALITY);
			$modal_quality_html = $modal_quality->getHTML();
		}


		return $modal_date_html . $modal_quality_html;
	}


	/**
	 *
	 */
	protected function reportDate() {
		if (ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_REPORT_DATE_CHANGE)) {
			$message = $_POST['message'];
			$link = ilLink::_getStaticLink($_GET['ref_id'], ilOpenCastPlugin::XOCT,
				true);
			$series_id = $this->xoctOpenCast->getSeriesIdentifier();
			$mail = new ilMimeMail();
			$mail->Subject('test');
			$mail->Body($message . '<br>' . $series_id . '<br>' . $link);
			$mail->To(xoctConf::getConfig(xoctConf::F_REPORT_DATE_EMAIL));
			$mail->From(ilSetting::_lookupValue('common', 'mail_external_sender_noreply'));
			$mail->Send();
		}
		ilUtil::sendSuccess($this->pl->txt('msg_date_report_sent'), true);
		$this->ctrl->redirect($this);
	}


	/**
	 *
	 */
	protected function reportQuality() {
		if (ilObjOpenCastAccess::checkAction(ilObjOpenCastAccess::ACTION_REPORT_DATE_CHANGE)) {
			$message = $_POST['message'];
			$event_id = $_POST['event_id'];
			$event = new xoctEvent($event_id);
			$event_title = $event->getTitle();
			$link = ilLink::_getStaticLink($_GET['ref_id'], ilOpenCastPlugin::XOCT,
				true);
			$series_id = $this->xoctOpenCast->getSeriesIdentifier();

			$mail = new ilMimeMail();
			$mail->Subject('test');
			$mail->Body($message . '<br>' . $event_id . '<br>' . $event_title . '<br>' . $series_id . '<br>' . $link);
			$mail->To(xoctConf::getConfig(xoctConf::F_REPORT_QUALITY_EMAIL));
			$mail->From(ilSetting::_lookupValue('common', 'mail_external_sender_noreply'));
			$mail->Send();
		}
		ilUtil::sendSuccess($this->pl->txt('msg_quality_report_sent'), true);
		$this->ctrl->redirect($this);
	}

	/**
	 * @param $key
	 *
	 * @return string
	 */
	public function txt($key) {
		return $this->pl->txt('event_' . $key);
	}
}