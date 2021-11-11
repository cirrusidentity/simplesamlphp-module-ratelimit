<?php

/**
 * Show a warning to an user about the SP requesting SSO a short time after
 * doing it previously.
 *
 * @package SimpleSAMLphp
 */

if (!array_key_exists('StateId', $_REQUEST)) {
    throw new \SimpleSAML\Error\BadRequest('Missing required StateId query parameter.');
}
$id = $_REQUEST['StateId'];

/** @var array $state */
$state = \SimpleSAML\Auth\State::loadState($id, 'loginloopdetection:loop_detection');

$session = \SimpleSAML\Session::getSessionFromRequest();

if (array_key_exists('continue', $_REQUEST)) {
    // The user has pressed the continue/retry-button
    \SimpleSAML\Auth\ProcessingChain::resumeProcessing($state);
}

$globalConfig = \SimpleSAML\Configuration::getInstance();
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'loginloopdetection:loop_detection.tpl.php');
$translator = $t->getTranslator();
$t->data['target'] = \SimpleSAML\Module::getModuleURL('loginloopdetection/loop_detection.php');
$t->data['params'] = ['StateId' => $id];
$t->data['trackId'] = $session->getTrackID();
$t->data['header'] = $translator->t('{loginloopdetection:loop_detection:warning_header}');
$t->data['autofocus'] = 'contbutton';
$t->show();

