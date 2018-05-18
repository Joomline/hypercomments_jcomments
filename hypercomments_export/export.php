<?php
/**
 * Constant that is checked in included files to prevent direct access.
 * define() is used in the installation folder rather than "const" to not error for PHP 5.2 and lower
 */
define('_JEXEC', 1);

/*
 * Settings
 */
$clearTable = 1;
$xmlName = 'hcexport.xml';



/*
 * CODE, Not Change!!!!!
 */
if ( file_exists( __DIR__ . '/../defines.php' ) ) {
	include_once __DIR__ . '/../defines.php';
}
if ( !defined( '_JDEFINES' ) ) {
	define( 'JPATH_BASE', __DIR__ . '/..' );
	require_once JPATH_BASE . '/includes/defines.php';
}
require_once JPATH_BASE . '/includes/framework.php';
// Instantiate the application.
$app = JFactory::getApplication('site');



// Attempt to load the xml file.
if (!$xml = simplexml_load_file($xmlName))
{
	$app->close('Error Load XML');
}

if (!$posts = $xml->xpath('/hc/post'))
{
	$app->close('Error Load XML');
}
if (!is_array($posts) || !count($posts))
{
	$app->close('Error Load XML');
}


$db = JFactory::getDbo();
$query = $db->getQuery(true);

if($clearTable){
	$db->truncateTable('#__jcomments');
}

$count = 0;

$missedComments = array();

function sortCommentsById($a, $b){
	if(!isset($a->id) || !isset($b->id)){
		return 1;
	}
	return (int)$a->id > (int)$b->id ? 1 : -1;
}

foreach ( $posts as $post ) {
	$xid = (string)$post->xid;
	if(empty((string)$post->xid)){
		echo 'Empty xid '.$post->xid;
		continue;
	}
	$aXid = explode('_', $xid);
	if(count($aXid) != 3){
		echo 'Error parsing xid '.$post->xid;
		continue;
	}
	$component = $aXid[0].'_'.$aXid[1];
	$object_id = (int)$aXid[2];

	if (!$data = $post->xpath('./comments/comment'))
	{
		echo'Error Load comments XML for artickle '.$xid.'<br>';
		continue;
	}
	if (!is_array($data) || !count($data))
	{
		echo'Error count comments XML for artickle '.$xid.'<br>';
		continue;
	}

	$aComments = array();
	uasort($data, 'sortCommentsById');
	foreach ( $data as $comment ) {
		$oldCommentId = (int)$comment->id;
		$oldCommentParentId = (int)$comment->parent_id;
		$oldCommentRootId = (int)$comment->root_id;


		$ob = new stdClass();
		if($oldCommentParentId == 0){
			$ob->parent = $oldCommentParentId;
			$ob->thread_id = 0;
			$ob->path = 0;
			$ob->level = 0;
		}
		else if(isset($aComments[$oldCommentParentId])){
			$ob->parent = $aComments[$oldCommentParentId]['insert_id'];

			$ob->thread_id = ($aComments[$oldCommentParentId]['parent_id'] == 0)
				? $aComments[$oldCommentParentId]['insert_id']
				: $aComments[$oldCommentParentId]['thread_id'];

			$ob->path = $aComments[$oldCommentParentId]['path'].','.$ob->parent;
			$ob->level = $aComments[$oldCommentParentId]['level']+1;
		}
		else{
			echo 'Error loading patent comment '.$oldCommentParentId.'<br>';
			$missedComments[$oldCommentId] = $comment;
			$missedComments[$oldCommentId]->component = $component;
			$missedComments[$oldCommentId]->object_id = $object_id;
			continue;
		}

		$date = new DateTime((string)$comment->time);

		$ob->object_id = $object_id;
		$ob->object_group = $component;
		$ob->object_params = '';
		$ob->lang = 'ru-RU';
		$ob->userid = 0;
		$ob->name = (string)$comment->nick;
		$ob->username = (string)$comment->nick;
		$ob->email = (string)$comment->email;
		$ob->comment = (string)$comment->text;
		$ob->ip = (string)$comment->ip;
		$ob->date = $date->format('Y-m-d H:i:s');
		$ob->isgood = (string)$comment->vote_up;
		$ob->ispoor = (string)$comment->vote_dn;
		$ob->published = 1;
		$ob->deleted = 0;
		$ob->subscribe = 0;
		$ob->source = '';
		$ob->source_id = 0;
		$ob->checked_out = 0;
		$ob->checked_out_time = '0000-00-00 00:00:00';
		$ob->editor = '';

		$db->insertObject('#__jcomments', $ob, 'id');


		if(!empty($ob->id)){
			$aComments[$oldCommentId] = array(
				'insert_id' => $ob->id,
				'parent_id' => $ob->parent,
				'thread_id' => $ob->thread_id,
				'path' => $ob->path,
				'level' => $ob->level,
			);
			$count++;
		}
		else{
			echo 'Error inserting object '.$oldCommentId.'<br>';
		}

	}
}

$app->close('Imported '.$count.' comments');