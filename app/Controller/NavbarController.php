<?php

class NavbarController extends AppController {

	public $components = array('Session');

	public function admin_index() {
		if($this->isConnected AND $this->Permissions->can('MANAGE_NAV')) {

			$this->set('title_for_layout',$this->Lang->get('NAVBAR__TITLE'));
			$this->layout = 'admin';
			$this->loadModel('Navbar');
			$navbars = $this->Navbar->find('all', array('order' => 'order'));

			$this->loadModel('Page');
			$pages = $this->Page->find('all', array('fields' => array('id', 'slug')));
			foreach ($pages as $key => $value) {
				$pages_listed[$value['Page']['id']] = $value['Page']['slug'];
			}

			foreach ($navbars as $key => $value) {

				if($value['Navbar']['url']['type'] == "plugin") {

					$plugin = $this->EyPlugin->findPluginByDBid($value['Navbar']['url']['id']);
					if(!empty($plugin)) {
						$navbars[$key]['Navbar']['url'] = Router::url('/'.strtolower($plugin->slug));
					} else {
						$navbars[$key]['Navbar']['url'] = false;
					}

				} elseif($value['Navbar']['url']['type'] == "page") {

					if(isset($pages_listed[$value['Navbar']['url']['id']])) {
						$navbars[$key]['Navbar']['url'] = Router::url('/p/'.$pages_listed[$value['Navbar']['url']['id']]);
					} else {
						$navbars[$key]['Navbar']['url'] = '#';
					}

				} elseif($value['Navbar']['url']['type'] == "custom") {

					$navbars[$key]['Navbar']['url'] = $value['Navbar']['url']['url'];

				} else {
					$navbars[$key]['Navbar']['url'] = '#';
				}

			}

			$this->set(compact('navbars'));
		} else {
			$this->redirect('/');
		}
	}

	public function admin_save_ajax() {
		$this->autoRender = false;
		if($this->isConnected AND $this->Permissions->can('MANAGE_NAV')) {

			if($this->request->is('post')) {
				if(!empty($this->request->data)) {
					$data = $this->request->data['nav'];
					$data = explode('&', $data);
					$i = 1;
					foreach ($data as $key => $value) {
						$data2[] = explode('=', $value);
						$data3 = substr($data2[0][0], 0, -2);
						$data1[$data3] = $i;
						unset($data3);
						unset($data2);
						$i++;
					}
					$data = $data1;
					$this->loadModel('Navbar');
					foreach ($data as $key => $value) {
						$find = $this->Navbar->find('first', array('conditions' => array('name' => $key)));
						if(!empty($find)) {
							$id = $find['Navbar']['id'];
							$this->Navbar->read(null, $id);
							$this->Navbar->set(array(
								'order' => $value,
								'url' => json_encode($find['Navbar']['url'])
							));
							$this->Navbar->save();
						} else {
							$error = 1;
						}
					}
					if(empty($error)) {
						$this->History->set('EDIT_NAVBAR', 'navbar');
						echo $this->Lang->get('NAVBAR__SAVE_SUCCESS').'|true';
					} else {
						echo $this->Lang->get('ERROR__INTERNAL_ERROR').'|false';
					}
				} else {
					echo $this->Lang->get('ERROR__FILL_ALL_FIELDS').'|false';
				}
			} else {
				echo $this->Lang->get('ERROR__BAD_REQUEST').'|false';
			}
		} else {
			$this->redirect('/');
		}
	}

	public function admin_delete($id = false) {
		$this->autoRender = false;
		if($this->isConnected AND $this->Permissions->can('MANAGE_NAV')) {
			if($id != false) {

				$this->loadModel('Navbar');
				if($this->Navbar->delete($id)) {
					$this->History->set('DELETE_NAV', 'navbar');
					$this->Session->setFlash($this->Lang->get('NAVBAR__DELETE_SUCCESS'), 'default.success');
					$this->redirect(array('controller' => 'navbar', 'action' => 'index', 'admin' => true));
				} else {
					$this->redirect(array('controller' => 'navbar', 'action' => 'index', 'admin' => true));
				}
			} else {
				$this->redirect(array('controller' => 'navbar', 'action' => 'index', 'admin' => true));
			}
		} else {
			$this->redirect('/');
		}
	}

	public function admin_add() {
		if($this->isConnected AND $this->Permissions->can('MANAGE_NAV')) {
			$this->layout = 'admin';

			$this->set('title_for_layout', $this->Lang->get('NAVBAR__ADD_LINK'));
			$url_plugins = $this->EyPlugin->getPluginsActive();
			foreach ($url_plugins as $key => $value) {
				if($value->nav) {
					$DBid = $value->DBid;
					$url_plugins2[$DBid] = $value->name;
				}
			}
			if(!empty($url_plugins2)) {
				$url_plugins = $url_plugins2;
			} else {
				$url_plugins = array();
			}
			$this->loadModel('Page');
			$url_pages = $this->Page->find('all');
			foreach ($url_pages as $key => $value) {
				$url_pages2[$value['Page']['id']] = $value['Page']['title'];
			}
			$url_pages = @$url_pages2;
			$this->set(compact('url_plugins'));
			$this->set(compact('url_pages'));
		} else {
			$this->redirect('/');
		}
	}

	public function admin_add_ajax() {
		$this->autoRender = false;
		if($this->isConnected AND $this->Permissions->can('MANAGE_NAV')) {

			if($this->request->is('post')) {

				if(!empty($this->request->data['name']) AND !empty($this->request->data['type'])) {
					$this->loadModel('Navbar');
					if(!empty($this->request->data['url']) AND $this->request->data['url'] != "undefined") {

						$order = $this->Navbar->find('first', array('order' => array('order' => 'DESC')));
						if(!empty($order)) {
							$order = $order['Navbar']['order'];
							$order = intval($order) + 1;
						} else {
							$order = 1;
						}

						$open_new_tab = ($this->request->data['open_new_tab'] == 'true') ? 1 : 0;

						$this->Navbar->read(null, null);

						$data = array(
							'order' => $order,
							'name' => $this->request->data['name'],
							'type' => 1,
							'open_new_tab' => $open_new_tab
						);

						if($this->request->data['type'] == "dropdown") {
							$data['type'] = 2;
							$data['url'] = json_encode(array('type' => 'submenu'));
							$data['submenu'] = json_encode($this->request->data['url']);
						} else {
							// URL
							$data['url'] = $this->request->data['url'];
						}

						$this->Navbar->set($data);
						$this->Navbar->save();

						$this->History->set('ADD_NAV', 'navbar');

						echo json_encode(array('statut' => true, 'msg' => $this->Lang->get('NAVBAR__ADD_SUCCESS')));
						$this->Session->setFlash($this->Lang->get('NAVBAR__ADD_SUCCESS'), 'default.success');

					} else {
						echo json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS')));
					}
				} else {
					echo json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS')));
				}
			} else {
				echo json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__BAD_REQUEST')));
			}
		} else {
			throw new ForbiddenException();
		}
	}

	public function admin_edit($id = false) {
		if($this->isConnected AND $this->Permissions->can('MANAGE_NAV')) {
			if($id) {

				$find = $this->Navbar->find('first', array('conditions' => array('id' => $id)));
				if(!empty($find)) {

					$nav = $find['Navbar'];

					$this->layout = 'admin';
					$this->set('title_for_layout', $this->Lang->get('NAVBAR__EDIT_TITLE'));

					$url_plugins = $this->EyPlugin->getPluginsActive();
					foreach ($url_plugins as $key => $value) {
						if($value->nav) {
							$DBid = $value->DBid;
							$url_plugins2[$DBid] = $value->name;
						}
					}
					if(!empty($url_plugins2)) {
						$url_plugins = $url_plugins2;
					} else {
						$url_plugins = array();
					}

					$this->loadModel('Page');
					$url_pages = $this->Page->find('all');
					foreach ($url_pages as $key => $value) {
						$url_pages2[$value['Page']['id']] = $value['Page']['title'];
					}
					$url_pages = @$url_pages2;


					$this->set(compact('url_pages', 'url_plugins', 'nav'));

				} else {
					throw new NotFoundException();
				}

			} else {
				throw new NotFoundException();
			}
		} else {
			$this->redirect('/');
		}
	}

	public function admin_edit_ajax($id) {
		$this->autoRender = false;
		if($this->isConnected AND $this->Permissions->can('MANAGE_NAV')) {

			if($this->request->is('post')) {
				if(!empty($this->request->data['name']) AND !empty($this->request->data['type'])) {
					$this->loadModel('Navbar');
					if(!empty($this->request->data['url']) AND $this->request->data['url'] != "undefined") {

						$open_new_tab = ($this->request->data['open_new_tab'] == 'true') ? 1 : 0;

						$this->Navbar->read(null, $id);

						$data = array(
							'name' => $this->request->data['name'],
							'type' => 1,
							'open_new_tab' => $open_new_tab
						);

						if($this->request->data['type'] == "dropdown") {
							$data['type'] = 2;
							$data['url'] = json_encode(array('type' => 'submenu'));
							$data['submenu'] = json_encode($this->request->data['url']);
						} else {
							// URL
							$data['url'] = $this->request->data['url'];
						}

						$this->Navbar->set($data);
						$this->Navbar->save();

						$this->History->set('ADD_NAV', 'navbar');

						echo json_encode(array('statut' => true, 'msg' => $this->Lang->get('NAVBAR__EDIT_SUCCESS')));
						$this->Session->setFlash($this->Lang->get('NAVBAR__EDIT_SUCCESS'), 'default.success');

					} else {
						echo json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS')));
					}
				} else {
					echo json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__FILL_ALL_FIELDS')));
				}
			} else {
				echo json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__BAD_REQUEST')));
			}
		} else {
			throw new ForbiddenException();
		}
	}

}
