<?php declare(strict_types=1); defined('BASEPATH') OR exit('No direct script access allowed');

class DynastyScans extends Base_Site_Model {
	//FIXME: This has some major issues. SEE: https://github.com/DakuTree/manga-tracker/issues/58

	public $titleFormat   = '/^[a-z0-9_]+:--:(?:0|1)$/';
	public $chapterFormat = '/^[0-9a-z_]+$/';

	public $customType    = 2;

	public function getFullTitleURL(string $title_url) : string {
		$title_parts = explode(':--:', $title_url);
		$url_type = ($title_parts[1] == '0' ? 'series' : 'chapters');

		return 'https://dynasty-scans.com/'.$url_type.'/'.$title_parts[0];
	}

	public function getChapterData(string $title_url, string $chapter) : array {
		$title_parts = explode(':--:', $title_url);
		/* Known chapter url formats (# is numbers):
		       chapters_#A_#B - Ch#A-#B
		       ch_#A          - Ch#A
		       ch_#A_#B       - Ch#A.#B
		       <NOTHING>      - Oneshot (This is passed as "oneshot")
		*/

		$chapterData = [
			'url'    => 'https://dynasty-scans.com/chapters/' . $title_parts[0].'_'.$chapter,
			'number' => ''
		];

		if($chapter == 'oneshot') {
			$chapterData['number'] = 'oneshot';
		} else {
			$chapter = preg_replace("/^([a-zA-Z]+)/", '$1_', $chapter);
			$chapterSegments = explode('_', $chapter);
			switch($chapterSegments[0]) {
				case 'ch':
					$chapterData['number'] = 'c'.$chapterSegments[1].(isset($chapterSegments[2]) && !empty($chapterSegments[2]) ? '.'.$chapterSegments[2] : '');
					break;

				case 'chapters':
					//This is barely ever used, but I have seen it.
					$chapterData['number'] = 'c'.$chapterSegments[1].'-'.$chapterSegments[2];
					break;

				default:
					//TODO: FALLBACK, ALERT ADMIN?
					$chapterData['number'] = $chapter;
					break;
			}
		}
		return $chapterData;
	}

	public function getTitleData(string $title_url, bool $firstGet = FALSE) : ?array {
		$titleData = [];

		$fullURL = $this->getFullTitleURL($title_url);
		$content = $this->get_content($fullURL);

		$title_parts = explode(':--:', $title_url);
		switch($title_parts[1]) {
			case '0':
				//Normal series.
				$data = $this->parseTitleDataDOM(
					$content,
					$title_url,
					"//h2[@class='tag-title']/b[1]",
					"(//dl[@class='chapter-list']/dd[a[contains(@href,'/chapters/')]])[last()]",
					"small",
					"a[@class='name']"
				);
				if($data) {
					$titleData['title'] = $data['nodes_title']->textContent;
					//In cases where the series is a doujin, try and prepend the copyright.
					preg_match('/\/doujins\/[^"]+">(.+?)(?=<\/a>)<\/a>/', $content['body'], $matchesD);
					if(!empty($matchedD) && substr($matchesD[1], 0, -7) !== 'Original') {
						$titleData['title'] = substr($matchesD[1], 0, -7).' - '.$titleData['title'];
					}

					$chapterURLSegments = explode('/', (string) $data['nodes_chapter']->getAttribute('href'));
					if (strpos($chapterURLSegments[2], $title_parts[0]) !== false) {
						$titleData['latest_chapter'] = substr($chapterURLSegments[2], strlen($title_parts[0]) + 1);
					} else {
						$titleData['latest_chapter'] = $chapterURLSegments[2];
					}

					$titleData['last_updated'] =  date("Y-m-d H:i:s", strtotime(str_replace("'", '', substr((string) $data['nodes_latest']->textContent, 9))));
				}
				break;

			case '1':
				//Oneshot.
				$data = $content['body'];

				preg_match('/<b>.*<\/b>/', $data, $matchesT);
				preg_match('/\/doujins\/[^"]+">(.+)?(?=<\/a>)<\/a>/', $data, $matchesD);
				$titleData['title'] = (!empty($matchesD) ? ($matchesD[1] !== 'Original' ? $matchesD[1].' - ' : '') : '') . substr($matchesT[0], 3, -4);

				$titleData['latest_chapter'] = 'oneshot'; //This will never change

				preg_match('/<i class="icon-calendar"><\/i> (.*)<\/span>/', $data, $matches);
				$titleData['last_updated']   = date("Y-m-d H:i:s", strtotime($matches[1]));

				//Oneshots are special, and really shouldn't need to be re-tracked
				$titleData['status'] = '2';
				break;

			default:
				//something went wrong
				break;
		}
		return (!empty($titleData) ? $titleData : NULL);
	}

	public function doCustomUpdate() {
		$titleDataList = [];

		$updateURL = "https://dynasty-scans.com/chapters/added";
		if(($content = $this->get_content($updateURL)) && $content['status_code'] == 200) {
			$data = $content['body'];

			$data = preg_replace('/^[\s\S]+<dl class="chapter-list">/', '<dl class="chapter-list">', $data);
			$data = preg_replace('/<\/dl>[\s\S]+$/', '</dl>', $data);

			$dom = new DOMDocument();
			libxml_use_internal_errors(TRUE);
			$dom->loadHTML($data);
			libxml_use_internal_errors(FALSE);

			$xpath      = new DOMXPath($dom);
			$nodes_rows = $xpath->query("//dl[@class='chapter-list']/dt/following::dd[count(preceding::dt) = 1]");
			if($nodes_rows->length > 0) {
				foreach($nodes_rows as $row) {
					$titleData = [];

					$nodes_title   = $xpath->query("a[1]", $row);
					$nodes_chapter = $xpath->query("a[1]", $row);
					$nodes_latest  = $xpath->query("small[last()]", $row);

					if($nodes_title->length === 1 && $nodes_chapter->length === 1 && $nodes_latest->length === 1) {
						$title = $nodes_title->item(0);

						preg_match('/^\/chapters\/(?<url>.*?)(?:_(?<chapter>c[^_]+?))?$/', $title->getAttribute('href'), $title_url_arr);
						$title_url = $title_url_arr['url'] . ':--:' . ((int) empty($title_url_arr['chapter']));

						if(!array_key_exists($title_url, $titleDataList)) {
							$titleData['title'] = trim($title->textContent);
							if(!empty($title_url_arr['chapter'])) {
								$titleData['title'] = substr($titleData['title'], 0, stripos($titleData['title'], ' '.$title_url_arr['chapter']));
							}

							$titleData['latest_chapter'] = $title_url_arr['chapter'] ?? 'oneshot';

							$dateString = trim(str_replace('\'', '', str_replace('released ', '', $nodes_latest->item(0)->textContent)));
							$titleData['last_updated'] = date("Y-m-d H:i:s", strtotime($dateString));

							$titleDataList[$title_url] = $titleData;
						}
					} else {
						log_message('error', "{$this->site}/Custom | Invalid amount of nodes (TITLE: {$nodes_title->length} | CHAPTER: {$nodes_chapter->length}) | LATEST: {$nodes_latest->length})");
					}
				}
			} else {
				log_message('error', "{$this->site} | Following list is empty?");
			}
		} else {
			log_message('error', "{$this->site} - Custom updating failed.");
		}

		return $titleDataList;
	}
}
