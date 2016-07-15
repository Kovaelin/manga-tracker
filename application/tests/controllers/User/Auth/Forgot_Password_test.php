<?php

class Forgot_Password_test extends TestCase {
	public function test_uri_forgot_password() {
		$this->request('GET', 'user/forgot_password');
		$this->assertResponseCode(200);
	}
	public function test_uri_reset_password() {
		$this->request('GET', 'user/reset_password/foobarcode');
		$this->assertRedirect('user/forgot_password');
	}
	public function test_uri_reset_password_no_code() {
		$this->request('GET', 'user/reset_password');
		$this->assertResponseCode(404);
	}

	public function test_index_logged_in() {
		//user is already logged in, redirect to dashboard


		$this->request->setCallablePreConstructor(
			function () {
				$ion_auth = $this->getMock_ion_auth_logged_in();
				load_class_instance('ion_auth', $ion_auth);
			}
		);

		$this->request('GET', 'user/forgot_password');
		$this->assertRedirect('/');
	}

	public function test_index_not_logged_in() {
		//user isn't logged in, so show forgot_password form
		$output = $this->request('GET', 'user/forgot_password');
		$this->assertContains('<title>Manga Tracker - Forgot Password</title>', $output);
	}

	public function test_forgot_password_validation_pass() {
		//user isn't logged in, form is valid, user is shown success page regardless of if email is used
		$this->request->setCallable(
			function ($CI) {
				$validation = $this->getDouble(
					'CI_Form_validation',
					['set_rules' => NULL, 'run' => TRUE]
				);

				$this->verifyInvokedMultipleTimes($validation, 'set_rules', 1);
				$this->verifyInvokedOnce($validation, 'run');
				//$this->verifyNeverInvoked($validation, 'reset_validation');

				$CI->form_validation = $validation;
			}
		);
		$this->request->addCallable(
			function ($CI) {
				$auth = $this->getDouble(
					'Ion_auth_model',
					['forgotten_password' => TRUE, 'row' => (object) ['email' => 'foo@bar.com'], 'logged_in' => FALSE]
				);
				$auth->tables = $CI->config->item('tables', 'ion_auth'); //for whatever reason, the test doesn't load this normally :\

				$this->verifyInvokedOnce($auth, 'forgotten_password');

				$CI->ion_auth = $auth;
			}
		);

		$output = $this->request('POST', 'user/forgot_password');
		$this->assertContains('An email has been sent with a link to reset your password.', $output);
	}

	public function test_forgot_password_validation_fail() {
		//user isn't logged in, form is invalid, this is usually the case when first viewing the page
		$this->request->setCallable(
			function ($CI) {
				$validation = $this->getDouble(
					'CI_Form_validation',
					['set_rules' => NULL, 'run' => FALSE]
				);

				$this->verifyInvokedMultipleTimes($validation, 'set_rules', 1);
				$this->verifyInvokedOnce($validation, 'run');
				//$this->verifyNeverInvoked($validation, 'reset_validation');

				$CI->form_validation = $validation;
			}
		);

		$output = $this->request('POST', 'user/forgot_password');
		$this->assertContains('Enter your email address and we\'ll send you a recovery link.', $output);
	}

	public function test_reset_password_user_pass_validation_pass_change_fail() {
		//reset code is valid, form is invalid (or new)
		$this->request->addCallable(
			function ($CI) {
				$auth = $this->getDouble(
					'Ion_auth_model',
					['forgotten_password_check' => TRUE, 'reset_password' => FALSE]
				);

				$this->verifyInvokedOnce($auth, 'forgotten_password_check');

				$CI->ion_auth = $auth;
			}
		);
		$this->request->addCallable(
			function ($CI) {
				$validation = $this->getDouble(
					'CI_Form_validation',
					['set_rules' => NULL, 'run' => TRUE]
				);

				$this->verifyInvokedMultipleTimes($validation, 'set_rules', 2);
				$this->verifyInvokedOnce($validation, 'run');
				//$this->verifyNeverInvoked($validation, 'reset_validation');

				$CI->form_validation = $validation;
			}
		);

		$this->request('GET', 'user/reset_password/foobarcode');
		$this->assertRedirect('user/reset_password/foobarcode');
	}

	public function test_reset_password_user_pass_validation_pass_change_pass() {
		//reset code is valid, form is valid, password change was successful, redirect to login
		$this->request->addCallable(
			function ($CI) {
				$auth = $this->getDouble(
					'Ion_auth_model',
					['forgotten_password_check' => TRUE, 'reset_password' => TRUE]
				);

				$this->verifyInvokedOnce($auth, 'forgotten_password_check');

				$CI->ion_auth = $auth;
			}
		);
		$this->request->addCallable(
			function ($CI) {
				$validation = $this->getDouble(
					'CI_Form_validation',
					['set_rules' => NULL, 'run' => TRUE]
				);

				$this->verifyInvokedMultipleTimes($validation, 'set_rules', 2);
				$this->verifyInvokedOnce($validation, 'run');
				//$this->verifyNeverInvoked($validation, 'reset_validation');

				$CI->form_validation = $validation;
			}
		);

		$this->request('GET', 'user/reset_password/foobarcode');
		$this->assertRedirect('user/login');
	}

	public function test_reset_password_user_pass_validation_fail() {
		//reset code is valid, form is valid, password change was unsuccessful, send back to reset_password page
		$this->request->setCallable(
			function ($CI) {
				$auth = $this->getDouble(
					'Ion_auth_model',
					['forgotten_password_check' => TRUE, 'logged_in' => FALSE]
				);

				$this->verifyInvokedOnce($auth, 'forgotten_password_check');

				$CI->ion_auth = $auth;
			}
		);

		$output = $this->request('GET', 'user/reset_password/foobarcode');
		$this->assertContains('Please enter your new password.', $output);
	}

	public function test_reset_password_user_fail() {
		//reset code was provided but was invalid, redirect the user
		$this->request('GET', 'user/reset_password/foobarcode');
		$this->assertRedirect('user/forgot_password');
	}

	public function test_reset_password_no_code() {
		//no reset code was provided, 404 the user.
		//this is done via specific routing

		$this->request('GET', 'user/reset_password');
		$this->assertResponseCode(404);
	}
}
