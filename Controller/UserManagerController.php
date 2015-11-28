<?php
/**
 * UserManager Controller
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Shohei Nakajima <nakajimashouhei@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('UserManagerAppController', 'UserManager.Controller');

/**
 * UserManager Controller
 *
 * @author Shohei Nakajima <nakajimashouhei@gmail.com>
 * @package NetCommons\UserManager\Controller
 */
class UserManagerController extends UserManagerAppController {

/**
 * use model
 *
 * @var array
 */
	public $uses = array(
		'Groups.Group',
		'Rooms.Space',
		'Rooms.Room',
		'Users.User',
	);

/**
 * use component
 *
 * @var array
 */
	public $components = array(
		'ControlPanel.ControlPanelLayout',
		'Files.FileUpload',
		'M17n.SwitchLanguage',
		'UserAttributes.UserAttributeLayout',
		'Users.UserSearch',
	);

/**
 * use helpers
 *
 * @var array
 */
	public $helpers = array(
		'UserAttributes.UserAttributeLayout',
	);

/**
 * indexアクション
 *
 * @return void
 */
	public function index() {
		//CakeLog::debug(print_r($this->request, true));
		//CakeLog::debug(print_r($this->request->query, true));
		//CakeLog::debug(print_r($_SERVER, true));
		//var_dump($this->request->query);

		//ユーザ一覧データ取得
		$Space = $this->Space;
		$this->UserSearch->search(
			array('space_id' => $Space::PRIVATE_SPACE_ID),
			array('Room' => array('Room.page_id_top NOT' => null))
		);
		$this->set('displayFields', $this->User->getDispayFields());

		$this->helpers[] = 'Users.UserSearch';
	}

/**
 * searchアクション
 *
 * @param string $type 処理タイプ(conditions: 検索フォーム表示、result: 検索条件保持処理)
 * @return void
 */
	public function search($type = null) {
		//CakeLog::debug(print_r($this->request->query, true));
		if ($type === 'conditions') {
			$this->helpers[] = 'UserManager.UserSearchForm';
			$this->viewClass = 'View';
			$this->layout = 'NetCommons.modal';

			//自分自身のグループデータ取得(後で置き換え発生する？)
			$result = $this->Group->find('list', array(
				'recursive' => -1,
				'fields' => array('id', 'name'),
				'conditions' => array(
					'created_user' => Current::read('User.id'),
				),
				'order' => array('id'),
			));
			$this->set('groups', $result);

			//参加ルームデータ取得
			$result = $this->Room->find('all', $this->Room->getReadableRoomsCondtions(array(
				'Room.space_id !=' => Space::PRIVATE_SPACE_ID
			)));
			$rooms = Hash::combine($result, '{n}.Room.id', '{n}.RoomsLanguage.{n}[language_id=' . Current::read('Language.id') . '].name');
			$this->set('rooms', $rooms);

		} elseif ($type === 'result') {

		} else {
			$this->throwBadRequest();
		}
	}

/**
 * addアクション
 *
 * @return void
 */
	public function add() {
		$this->view = 'edit';
		$this->helpers[] = 'Users.UserEditForm';

		$this->viewVars['userAttributes'] = Hash::remove($this->viewVars['userAttributes'],
				'{n}.{n}.{n}.UserAttributeChoice.{n}[key=' . UserRole::USER_ROLE_KEY_SYSTEM_ADMINISTRATOR . ']');

		if ($this->request->isPost()) {
			$Space = $this->Space;

			//不要パラメータ除去
			unset($this->request->data['save'], $this->request->data['active_lang_id']);

			//登録処理
			$this->User->userAttributeData = Hash::combine($this->viewVars['userAttributes'],
				'{n}.{n}.{n}.UserAttribute.id', '{n}.{n}.{n}'
			);
			$user = $this->User->saveUser($this->request->data);
			if ($user) {
				//正常の場合
				$this->redirect('/user_manager/users_roles_rooms/edit/' . $user['User']['id'] . '/' . $Space::ROOM_SPACE_ID);
				return;
			}
			$this->NetCommons->handleValidationError($this->User->validationErrors);

		} else {
			//表示処理
			$this->User->languages = $this->viewVars['languages'];
			$this->request->data = $this->User->createUser();
		}

		$this->set('userName', '');
	}

/**
 * editアクション
 *
 * @return void
 */
	public function edit() {
		$this->helpers[] = 'Users.UserEditForm';

		if (Current::read('User.role_key') !== UserRole::USER_ROLE_KEY_SYSTEM_ADMINISTRATOR) {
			$this->viewVars['userAttributes'] = Hash::remove($this->viewVars['userAttributes'],
					'{n}.{n}.{n}.UserAttributeChoice.{n}[key=' . UserRole::USER_ROLE_KEY_SYSTEM_ADMINISTRATOR . ']');
		}

		if ($this->request->isPut()) {
			$userId = $this->data['User']['id'];
		} else {
			$userId = $this->params['pass'][0];
		}
		$user = $this->User->getUser($userId);

		//システム管理者は編集不可
		if (Current::read('User.role_key') !== UserRole::USER_ROLE_KEY_SYSTEM_ADMINISTRATOR &&
				(! $user || $user['User']['role_key'] === UserRole::USER_ROLE_KEY_SYSTEM_ADMINISTRATOR)) {
			$this->throwBadRequest();
			return;
		}

		if ($this->request->isPut()) {
			//不要パラメータ除去
			unset($this->request->data['save'], $this->request->data['active_lang_id']);

			//登録処理
			$this->User->userAttributeData = Hash::combine($this->viewVars['userAttributes'],
				'{n}.{n}.{n}.UserAttribute.id', '{n}.{n}.{n}'
			);
			if ($this->User->saveUser($this->request->data)) {
				//正常の場合
				$this->NetCommons->setFlashNotification(__d('net_commons', 'Successfully saved.'), array('class' => 'success'));
				$this->redirect('/user_manager/user_manager/index/');
				return;
			}
			$this->NetCommons->handleValidationError($this->User->validationErrors);

		} else {
			//表示処理
			$this->User->languages = $this->viewVars['languages'];
			$this->request->data = $user;
		}

		$this->set('userName', $this->request->data['User']['handlename']);
		$this->set('activeUserId', $userId);
	}

/**
 * deleteアクション
 *
 * @return void
 */
	public function delete() {
		$user = $this->User->getUser($this->data['User']['id']);

		//システム管理者は削除不可
		if (Current::read('User.role_key') !== UserRole::USER_ROLE_KEY_SYSTEM_ADMINISTRATOR &&
				(! $user || $user['User']['role_key'] === UserRole::USER_ROLE_KEY_SYSTEM_ADMINISTRATOR)) {
			$this->throwBadRequest();
			return;
		}

		if (! $this->request->isDelete()) {
			$this->throwBadRequest();
			return;
		}

		$this->User->deleteUser($user);
		$this->redirect(NetCommonsUrl::backToIndexUrl('default_setting_action'));
	}

/**
 * importアクション
 *
 * @return void
 */
	public function import() {
		if ($this->request->isPost()) {
			$file = $this->FileUpload->getTemporaryUploadFile('import_csv');
			if (! $this->User->importUsers($file)) {
				//バリデーションエラーの場合
				$this->NetCommons->handleValidationError($this->User->validationErrors);
				return;
			}

			$this->NetCommons->setFlashNotification(__d('net_commons', 'Successfully saved.'), array('class' => 'success'));
			$this->redirect('/user_manager/user_manager/index/');
		}

	}

/**
 * exportアクション
 *
 * @return void
 */
	public function export() {

	}
}
