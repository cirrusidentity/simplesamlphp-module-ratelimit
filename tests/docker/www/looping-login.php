<?php

use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

require_once('_include.php');

$request = Request::createFromGlobals();
$loop = $request->query->getInt('loop', 0);

$as = new \SimpleSAML\Auth\Simple('loop-test');

$httpUtils = new \SimpleSAML\Utils\HTTP();
$returnTo = $httpUtils->getSelfURL();
if (!$as->isAuthenticated() || $loop > 0) {
    $loop--;
    if ($loop < 0) {
        $loop = 0;
    }
    $returnTo = $httpUtils->addURLParameters($returnTo, ['loop' => $loop]);
    $params = array(
        'ReturnTo' => $returnTo,
    );
    $as->login($params);
}

$attributes = $as->getAttributes();
$startLoopUrl = $httpUtils->addURLParameters($returnTo, ['loop' => 6]);

echo "<p><a href='$startLoopUrl'>Test Looping</a></p>";
echo "<pre>";
echo json_encode($attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "</pre>";
