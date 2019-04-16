<?php
if(!defined('ROOT')) die();

###### fonctions necessaires

/*
** fonction chargee de retourner la configuration par defaut du plugin (obligatoire)
** return : array
*/
function pageConfig(){
	return array(
		'priority' => 1,
		'activate' => 1,
	);
}
/*
** fonction chargee de retourner les infos du plugin (obligatoire)
** return : array
*/
function pageInfos(){
	return array(
		'name' => 'Page',
		'description' => 'Permet la création de pages statiques',
		'author' => 'Jonathan Coulet',
		'authorEmail' => 'j.coulet@gmail.com',
		'authorWebsite' => 'http://99ko.tuxfamily.org',
		'version' => '1.4',
	);
}
/*
** fonction chargee de retourner les hooks utilises par le plugin (obligatoire)
** return : array
*/
function pageHooks(){
	return array(
		'startFrontIncludePluginFile' => 'pageHook1',
	);	
}
/*
** fonction chargee de l'installation interne du plugin (facultatif)
*/
function pageInstall(){
	$page = new page();
	if(count($page->getItems()) < 1){
		$pageItem = new pageItem();
		$pageItem->setName('Page 1');
		$pageItem->setPosition(1);
		$pageItem->setIsHomepage(1);
		$pageItem->setContent('<p>Atque, ut Tullius ait, ut etiam ferae fame monitae plerumque ad eum locum ubi aliquando pastae sunt revertuntur, ita homines instar turbinis degressi montibus impeditis et arduis loca petivere mari confinia, per quae viis latebrosis sese convallibusque occultantes cum appeterent noctes luna etiam tum cornuta ideoque nondum solido splendore fulgente nauticos observabant quos cum in somnum sentirent effusos per ancoralia, quadrupedo gradu repentes seseque suspensis passibus iniectantes in scaphas eisdem sensim nihil opinantibus adsistebant et incendente aviditate saevitiam ne cedentium quidem ulli parcendo obtruncatis omnibus merces opimas velut viles nullis repugnantibus avertebant. haecque non diu sunt perpetrata.</p>');
		$pageItem->setIsHidden(0);
		$pageItem->setFile('');
		$page->save($pageItem);
	}
}

###### code du plugin

/*
** prechauffage
*/
define('PAGE_DATAPATH', ROOT.'data/plugin/page/');
$page = new page();

/*
** hooks
*/
function pageHook1(){
	global $coreConf, $runPlugin, $page;
	function pageMainNavigation($siteUrl, $defaultPlugin){
		global $page;
		$data = '';
		foreach($page->getItems() as $k=>$pageItem) if(!$pageItem->getIsHidden()){
			$target = ($defaultPlugin == 'page' && $pageItem->getIsHomepage()) ? $siteUrl : 'index.php?p=page&id='.$pageItem->getId();
			// fix bug (MaxguN)
			$data.= "\$data['mainNavigation'][".$pageItem->getPosition()."]['target'] = '".$target."';";
			$data.= "\$data['mainNavigation'][".$pageItem->getPosition()."]['label'] = '".$pageItem->getName()."';";
		}
		return $data;
	}
	return pageMainNavigation($coreConf['siteUrl'], $coreConf['defaultPlugin']);
}

/*
** librairie
*/
class page{
	private $items;
	
	public function __construct(){
		$data = array();
		if(is_dir(PAGE_DATAPATH)){
			$dataNotSorted = array();
			$items = utilScanDir(PAGE_DATAPATH, array('config.txt'));
			foreach($items['file'] as $k=>$file){
				$temp = json_decode(@file_get_contents(ROOT.'data/plugin/page/'.$file), true);
				$dataNotSorted[] = $temp;
			}
			$dataSorted = utilSort2DimArray($dataNotSorted, 'position', 'num');
			foreach($dataSorted as $pageItem){
				$data[] = new pageItem($pageItem);
			}
		}
		$this->items = $data;
	}
	
	public function getItems(){
		return $this->items;
	}
	
	public function create($id){
		foreach($this->items as $pageItem){
			if($pageItem->getId() == $id) return $pageItem;
		}
		return false;
	}
	public function createHomepage(){
		foreach($this->items as $pageItem){
			if($pageItem->getIshomepage()) return $pageItem;
		}
		return false;
	}
	public function save($obj){
		$id = intval($obj->getId());
		if($id < 1) $id = $this->makeId();
		$data = array(
			'id' => $id,
			'name' => $obj->getName(),
			'position' => $obj->getPosition(),
			'isHomepage' => $obj->getIsHomepage(),
			'content' => $obj->getContent(),
			'isHidden' => $obj->getIsHidden(),
			'file' => $obj->getFile(),
			'mainTitle' => $obj->getMainTitle(),
			'metaDescriptionTag' => $obj->getMetaDescriptionTag(),
		);
		if($obj->getIsHomepage() > 0) $this->initIshomepageVal();
		if(@file_put_contents(PAGE_DATAPATH.$id.'.txt', json_encode($data), 0666)){
			$this->repairPositions($obj);
			return true;
		}
		return false;
	}
	public function del($obj){
		if($obj->getIsHomepage() < 1 && $this->count() > 1){
			if(@unlink(PAGE_DATAPATH.$obj->getId().'.txt')) return true;
		}
		return false;
	}
	public function count(){
		return count($this->items);
	}
	public function listFiles(){
		$data = array();
		$items = utilScanDir(ROOT.'theme/'.getConfVal('core', 'theme').'/', array('header.php', 'footer.php', 'style.css'));
		foreach($items['file'] as $file){
			if(in_array(utilGetFileExtension($file), array('htm', 'html', 'txt', 'php'))) $data[] = $file;
		}
		return $data;
	}
	
	private function makeId(){
		$ids = array(0);
		foreach($this->items as $pageItem){
			$ids[] = $pageItem->getId();
		}
		return max($ids)+1;
	}

	private function initIshomepageVal(){
		foreach($this->items as $obj){
			$obj->setIsHomepage(0);
			$this->save($obj);
		}
	}
	
	private function repairPositions($currentObj){
		foreach($this->items as $obj) if($obj->getId() != $currentObj->getId()){
			$pos = $obj->getPosition();
			if($pos == $currentObj->getPosition()){
				$obj->setPosition($pos+1);
				$this->save($obj);
			}
		}
	}
}

class pageItem{
	private $id;
	private $name;
	private $position;
	private $isHomepage;
	private $content;
	private $isHidden;
	private $file;
	private $mainTitle;
	private $metaDescriptionTag;
	
	public function __construct($val = array()){
		if(count($val) > 0){
			$this->id = $val['id'];
			$this->name = $val['name'];
			$this->position = $val['position'];
			$this->isHomepage = $val['isHomepage'];
			$this->content = $val['content'];
			$this->isHidden = $val['isHidden'];
			$this->file = $val['file'];
			$this->mainTitle = $val['mainTitle'];
			$this->metaDescriptionTag = $val['metaDescriptionTag'];
		}
	}

	public function setName($val){
		$val = trim($val);
		if($val == '') $val = "Page sans nom";
		$this->name = $val;
	}
	public function setPosition($val){
		$this->position = intval($val);
	}
	public function setIsHomepage($val){
		$this->isHomepage = trim($val);
	}
	public function setContent($val){
		$this->content = trim($val);
	}
	public function setIsHidden($val){
		$this->isHidden = intval($val);
	}
	public function setFile($val){
		$this->file = trim($val);
	}
	public function setMainTitle($val){
		$this->mainTitle = trim($val);
	}
	public function setMetaDescriptionTag($val){
		$val = trim($val);
		if(mb_strlen($val) > 150) $val = mb_strcut($val, 0, 150).'...';
		$this->metaDescriptionTag = $val;
	}

	public function getId(){
		return $this->id;
	}
	public function getName(){
		return $this->name;
	}
	public function getPosition(){
		return $this->position;
	}
	public function getIsHomepage(){
		return $this->isHomepage;
	}
	public function getContent(){
		return $this->content;
	}
	public function getIsHidden(){
		return $this->isHidden;
	}
	public function getFile(){
		return $this->file;
	}
	public function getMainTitle(){
		return $this->mainTitle;
	}
	public function getMetaDescriptionTag(){
		return $this->metaDescriptionTag;
	}
}
?>
