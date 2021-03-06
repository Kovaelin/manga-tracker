<?php declare(strict_types=1); defined('BASEPATH') OR exit('No direct script access allowed');

class LHTranslation extends Base_Site_Model {
	public $titleFormat   = '/^[a-zA-Z0-9_\-.]+$/';
	public $chapterFormat = '/^[0-9\.]+$/';

	public function getFullTitleURL(string $title_url) : string {
		$title_url = str_replace('.','', $title_url);
		return "http://lhtranslation.net/{$title_url}";
	}

	public function getChapterData(string $title_url, string $chapter) : array {
		$title_url = str_replace('-chapter', '', $title_url);
		return [
			'url'    => "http://read.lhtranslation.com/read-{$title_url}-chapter-{$chapter}.html",
			'number' => "c{$chapter}"
		];
	}

	public function getTitleData(string $title_url, bool $firstGet = FALSE) : ?array {
		$titleData = [];

		$title_url = str_replace('.','', $title_url);
		$title_url = $this->_fixTitle($title_url);
		$fullURL = "http://lhtranslation.net/{$title_url}/feed/";
		$content = $this->get_content($fullURL);

		$data = $content['body'];
		$xml = simplexml_load_string($data) or die("Error: Cannot create object");
		if(((string) $xml->{'channel'}->title) !== 'Comments on: '){
			if(isset($xml->{'channel'}->item[0])) {
				if($title = (string) $xml->{'channel'}->item[0]->category) {
					$titleData['title'] = trim($title);

					$titleData['latest_chapter'] = str_replace('-', '.', preg_replace('/^.*?-(?:.*?)chapter-(.*?)\/$/', '$1', (string) $xml->{'channel'}->item[0]->link));
					$titleData['last_updated']   = date("Y-m-d H:i:s", strtotime((string) $xml->{'channel'}->item[0]->pubDate));
				} else {
					log_message('error', "Title is empty? - {$xml->{'channel'}->title} (LHTranslation): {$title_url}");
					return NULL;
				}
			}
		} else {
			log_message('error', "Series missing? (LHTranslation): {$title_url}");
			return NULL;
		}

		return (!empty($titleData) ? $titleData : NULL);
	}

	//FIXME: This is just a quick hack, we should really have a better way to handle this.
	//       Maybe when we finally add an "extra" data option for titles?
	private function _fixTitle(string $title_url) {
		//LHTranslation is a mess and doesn't work nice when we try to grab the chapter list.
		//This is just a quick fix for series with bad titles.

		$fixedTitles = [
			'genjitsu-shugi-yuusha-no-oukoku-saikenki' => 'genjitsushugisha-no-oukokukaizouki',
			'kuro-no-souzou-shoukanshi-tenseisha-no-hangyaku-' => 'kuro-no-souzou-shoukanshi-tenseisha-no-hangyaku'
		];
		if(array_key_exists($title_url, $fixedTitles)) {
			$title_url = $fixedTitles[$title_url];
		}

		return $title_url;
	}
}
