<?php

namespace SimpleSAML\Module\ratelimit\Controller;

use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimit
{

    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Session */
    protected Session $session;


    /**
     * Controller constructor.
     *
     * It initializes the global configuration and session for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration $config The configuration to use by the controllers.
     * @param \SimpleSAML\Session $session The session to use by the controllers.
     *
     * @throws \Exception
     */
    public function __construct(
        Configuration $config,
        Session $session
    ) {
        $this->config = $config;
        $this->session = $session;
    }

    /**
     * @param Request $request
     * @return Response|Template
     */
    public function loopDetection(Request $request): Response
    {

        if (!array_key_exists('StateId', $_REQUEST)) {
            throw new \SimpleSAML\Error\BadRequest('Missing required StateId query parameter.');
        }
        $id = $_REQUEST['StateId'];

        /** @var array $state */
        $state = \SimpleSAML\Auth\State::loadState($id, 'ratelimit:loop_detection');

        $session = \SimpleSAML\Session::getSessionFromRequest();

        if (array_key_exists('continue', $_REQUEST)) {
            // The user has pressed the continue/retry-button
            return new RunnableResponse([ProcessingChain::class, 'resumeProcessing'], [$state]);

        }

        $t = new \SimpleSAML\XHTML\Template($this->config, 'ratelimit:loop_detection.tpl.php');
        $translator = $t->getTranslator();
        $t->data['target'] = \SimpleSAML\Module::getModuleURL('ratelimit/loop_detection.php');
        $t->data['params'] = ['StateId' => $id];
        $t->data['trackId'] = $session->getTrackID();
        $t->data['header'] = $translator->t('{ratelimit:loop_detection:warning_header}');
        $t->data['autofocus'] = 'contbutton';

        return $t;
    }

}