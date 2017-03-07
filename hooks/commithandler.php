<?php

use Bitrix\Main\Text\Encoding;
use Bitrix\Main\UserTable;

//define('GOGS_SECRET', 'secret key, указнный в gogs');

define('COMMIT_MESSAGE_TEMPLATE', '
Запушил в ветку <b>$branch$</b>
<a href="$commit_url$">$commit_hash$</a> $commit_message$');
define('TASK_REGEXP', '@(?:(?:task_?)|(?:#)|(?:Задача №))([0-9]+)@i');

define('NOT_CHECK_PERMISSIONS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

$handle = fopen('php://input', 'rb');
$requestPayload = stream_get_contents($handle);
$event = json_decode($requestPayload, true);
if (!is_array($event))
{
	throw new RuntimeException('Error decoding json request');
}
fclose($handle);

/**
 * Gogs передает SHA256 HMAC тела запроса в заголовке X-Gogs-Signature
 */
if (defined('GOGS_SECRET') && function_exists('hash_hmac'))
{
	if (hash_hmac('sha256', $requestPayload, GOGS_SECRET) != $_SERVER['X-Gogs-Signature'])
	{
		throw new RuntimeException('Secret key mismatch');
	}
}

$user = UserTable::getList(array(
	'select' => array('ID'),
	'filter' => array(
		'=EMAIL' => $event['pusher']['email'],
	),
))->fetch();

if (!is_array($user))
{
	throw new RuntimeException('User not found');
}

$GLOBALS['USER']->Authorize($user['ID']);

$taskId = false;
if (preg_match(TASK_REGEXP, $event['repository']['name'], $r))
{
	$taskId = $r[1];
}
elseif (preg_match(TASK_REGEXP, $event['repository']['description'], $r))
{
	$taskId = $r[1];
}
elseif (preg_match(TASK_REGEXP, $event['ref'], $r))
{
	$taskId = $r[1];
}

if (!\Bitrix\Main\Loader::includeModule('tasks'))
{
	throw new \Bitrix\Main\SystemException('Tasks module is not installed');
}
if (!\Bitrix\Main\Loader::includeModule('forum'))
{
	throw new \Bitrix\Main\SystemException('Forum module is not installed');
}

$branch = $event['ref'];
$branch = str_replace('refs/heads/', '', $branch);

foreach ($event['commits'] as $commit)
{
	$message = $commit['message'];
	$message = Encoding::convertEncoding($message, 'utf-8', SITE_CHARSET);
	if (preg_match(TASK_REGEXP, $message, $r))
	{
		$taskId = $r[1];
	}
	if (!$taskId)
	{
		continue;
	}

	$task = CTasks::GetList(array(), array('ID' => $taskId))->Fetch();
	if (!$task)
	{
		continue;
	}

	$fillTemplate = function($str, array $arguments)
	{
		return str_replace(array_keys($arguments), array_values($arguments), $str);
	};

	$commentFields = array(
		'POST_MESSAGE' => $fillTemplate(COMMIT_MESSAGE_TEMPLATE, array(
			'$commit_url$' => $commit['url'],
			'$commit_hash$' => substr($commit['id'], 0, 9),
			'$commit_message$' => $message,
			'$branch$' => $branch,
		)),
	);
	CTaskCommentItem::add(CTaskItem::getInstance($task['ID'], $user['ID']), $commentFields);
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
