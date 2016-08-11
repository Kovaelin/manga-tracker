<?php defined('BASEPATH') or exit('No direct script access allowed');

class Userscript extends AJAX_Controller {
	private $userID;

	public function __construct() {
		parent::__construct();

		$this->load->library('vendor/Limiter');
		$this->load->library('form_validation');

		//1000 requests per hour to either AJAX request.
		if($this->limiter->limit('tracker_userscript', 500)) {
			$this->output->set_status_header('429', 'Rate limit reached'); //rate limited reached
			exit();
		}

		//API Key is required for all AJAX requests
		//We're not using set_rules here since we can't run form_validation twice.
		if($this->form_validation->required($this->input->post('api-key')) && ctype_alnum($this->input->post('api-key'))) {
			$this->userID = $this->User->get_id_from_api_key($this->input->post('api-key'));
			if(!$this->userID) {
				$this->output->set_status_header('400', 'Invalid API Key');
				exit();
			}
		} else {
			$this->output->set_status_header('400', 'Missing/invalid parameters.');
			exit();
		}
	}

	/**
	 * This is the main update URL for the userscript.
	 *
	 * REQ_PARAMS: api-key, manga[site], manga[title], manga[chapter]
	 * METHOD:     POST
	 * URL:        /ajax/userscript/update
	 */
	public function update() {
		//NOTE: CORS is enabled via vhost <only> for this URL.
		$this->form_validation->set_rules('manga[site]',    'Manga [Site]',    'required');
		$this->form_validation->set_rules('manga[title]',   'Manga [Title]',   'required');
		$this->form_validation->set_rules('manga[chapter]', 'Manga [Chapter]', 'required');

		if($this->form_validation->run() === TRUE) {
			$manga = $this->input->post('manga');

			$success = $this->Tracker->updateTracker($this->userID, $manga['site'], $manga['title'], $manga['chapter']);
			//TODO: Do more stuff, error handling, proper output
			$this->output->set_content_type('text/plain', 'UTF-8');
			$this->output->set_output("1");
		} else {
			$this->output->set_status_header('400', 'Missing/invalid parameters.');
		}
	}
}