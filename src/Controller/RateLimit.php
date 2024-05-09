<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Controller;

use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Error\BadRequest;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimit
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var Session */
    protected Session $session;


    /**
     * Controller constructor.
     *
     * It initializes the global configuration and session for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration $config The configuration to use.
     * @param \SimpleSAML\Session $session The current user session.
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

        /** @var string|null $stateId */
        $stateId = $request->query->get('StateId');
        if ($stateId === null) {
            throw new BadRequest('Missing required StateId query parameter.');
        }
        $state = State::loadState($stateId, 'ratelimit:loop_detection');
        $session = Session::getSessionFromRequest();

        if ($request->query->has('continue')) {
            // The user has pressed the continue/retry-button
            return new RunnableResponse([ProcessingChain::class, 'resumeProcessing'], [$state]);
        }

        $t = new Template($this->config, 'ratelimit:loop_detection.twig');
        $t->data['stateId'] = $stateId;
        $t->data['trackId'] = $session->getTrackID();
        /** @psalm-suppress MixedArrayAccess */
        $t->data['appName'] = $state['Destination']['entityid'];
        return $t;
    }
}
