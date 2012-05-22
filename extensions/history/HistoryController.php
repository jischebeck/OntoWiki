<?php

/**
 * History component controller.
 * 
 * @category   OntoWiki
 * @package    OntoWiki_extensions_components_history
 * @author     Christoph Rieß <c.riess.dev@googlemail.com>
 * @copyright  Copyright (c) 2009, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version    $Id: HistoryController.php 4090 2009-08-19 22:10:54Z christian.wuerker $
 */

class HistoryController extends OntoWiki_Controller_Component
{

    public function feedAction()
    {                
        $store = $this->_erfurt->getStore();

        if($this->getRequest()->getParam($this->_privateConfig->resourceParamName) !== null)
            $resource    = $this->getRequest()->getParam($this->_privateConfig->resourceParamName);
        else
            $resource    = $this->_owApp->selectedResource;

        if($this->getRequest()->getParam("m") !== null)
            $model       = $this->getRequest()->getParam("m");
        else
            $model       = $store->getFirstReadableGraphForUri($resource);

        $limit       = 20;
        $rUriEncoded = urlencode($resource);
        $translate   = $this->_owApp->translate;

        $ac          = $this->_erfurt->getAc();
        $params      = $this->_request->getParams();

        if (!$model || !$resource) {
            var_dump('r or m missing');exit;
        }

        $versioning = $this->_erfurt->getVersioning();
        $versioning->setLimit($limit);
        if (!$versioning->isVersioningEnabled()) {
            var_dump('versioning disabled');exit;
        }

        $title = OntoWiki_Utils::contractNamespace($resource);
        $feedTitle = sprintf($translate->_('Versions for %1$s'), $title);

        $this->_log('creating feed for '.$resource.' from '.$model);

        $historyArray = $this->_getHistoryArray($resource, $versioning, 1, $model);

        $idArray = array();
        $userArray = $this->_erfurt->getUsers();
        $titleHelper = new OntoWiki_Model_TitleHelper();
        // Load IDs for rollback and Username Labels for view
        foreach ($historyArray as $key => $entry) {
            $idArray[] = (int) $entry['id'];
            // if(!$singleResource){
            //                 $historyArray[$key]['url'] = $this->_config->urlBase . "view?r=" . urlencode($entry['resource']);
            //                 $titleHelper->addResource($entry['resource']);
            //             }
            if ($entry['useruri'] == $this->_erfurt->getConfig()->ac->user->anonymousUser) {
                $userArray[$entry['useruri']] = 'Anonymous';
            } elseif ($entry['useruri'] == $this->_erfurt->getConfig()->ac->user->superAdmin) {
                $userArray[$entry['useruri']] = 'SuperAdmin';
            } elseif (
                is_array($userArray[$entry['useruri']]) &&
                array_key_exists('userName',$userArray[$entry['useruri']])
            ) {
                $userArray[$entry['useruri']] = $userArray[$entry['useruri']]['userName'];
            }
        }

        $linkUrl = $this->_config->urlBase . "history/list?r=$rUriEncoded&m=".urlencode($model);
        if($this->getRequest()->getParam("m") !== null)
            $feedUrl = $this->_config->urlBase . "history/feed?".$this->_privateConfig->resourceParamName."=$rUriEncoded&m=".urlencode($model);  
        else
            $feedUrl = $this->_config->urlBase . "history/feed?".$this->_privateConfig->resourceParamName."=$rUriEncoded";

        $feed = new Zend_Feed_Writer_Feed();
        $feed->setTitle($feedTitle);
        $feed->setLink($linkUrl);
        $feed->setFeedLink($feedUrl, 'atom');
        //$feed->addHub("http://localhost/casd/pubsub/hubbub");
        $feed->addAuthor(array(
            'name' => 'OntoWiki',
            'uri'  => $feedUrl
        ));
        $feed->setDateModified(time());

        foreach ($historyArray as $historyItem) {
            $title = $translate->_('HISTORY_ACTIONTYPE_'.$historyItem['action_type']);

            $entry = $feed->createEntry();
            $entry->setTitle($title);
            $entry->setLink($this->_config->urlBase . 'view?r='.$rUriEncoded."&id=".$historyItem['id']);
            $entry->addAuthor(array(
                'name' => $userArray[$historyItem['useruri']],
                'uri'  => $historyItem['useruri']
            ));
            $entry->setDateModified($historyItem['tstamp']);
            $entry->setDateCreated($historyItem['tstamp']);
            $entry->setDescription($title);
            
			$content = "";
			$result = $this->getActionTriple($historyItem['id'], false, $resource, $model);
			$content .= json_encode($result);	

            $entry->setContent( htmlentities($content) );
            
            $this->_log('history item: '.print_r($historyItem,true));
            $this->_log('result triple: '.print_r($result,true));
            $this->_log('entry content: '.print_r($entry->getContent(),true));    

            $feed->addEntry($entry);
        }
        $event = new Erfurt_Event('onCreateInternalFeed');
        $event->feed = $feed;
        $event->trigger();
        $feed = $event->feed;

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
		$this->getResponse()->setHeader("Content-Type", "application/atom+xml");

        $out = $feed->export('atom');

#$this->_log('feed: '.print_r($out,true));
		$pattern = '/updated>\n(.+?)link rel="alternate"/';
		$replace = "updated>\n$1link";
		$out = preg_replace($pattern, $replace, $out);

        echo $out;
		
        return;
		// Do we need this stuff below?
		// ----------------------------

        $this->view->userArray = $userArray;
        $this->view->idArray = $idArray;
        $this->view->historyArray = $historyArray;
        $this->view->singleResource = $singleResource;
        $this->view->titleHelper = $titleHelper;

        if (empty($historyArray))  {
            $this->_owApp->appendMessage(
                new OntoWiki_Message(
                    'No matches.' ,
                    OntoWiki_Message::INFO
                )
            );
        }

        if ($this->_erfurt->getAc()->isActionAllowed('Rollback')) {
            $this->view->rollbackAllowed = true;
            // adding submit button for rollback-action
            $toolbar = $this->_owApp->toolbar;
            $toolbar->appendButton(
                OntoWiki_Toolbar::SUBMIT,
                array('name' => $translate->_('Rollback changes'), 'id' => 'history-rollback')
            );
            $this->view->placeholder('main.window.toolbar')->set($toolbar);
        } else {
            $this->view->rollbackAllowed = false;
        }

        // paging

        $statusBar = $this->view->placeholder('main.window.statusbar');
        OntoWiki_Pager::setOptions(array('page_param'=>'page')); // the normal page_param p collides with the generic-list param p
        $statusBar->append(OntoWiki_Pager::get($count, $limit));

        // setting view variables

        $url = new OntoWiki_Url(array('controller' => 'history', 'action' => 'rollback'));

        $this->view->placeholder('main.window.title')->set($windowTitle);

        $this->view->formActionUrl = (string) $url;
        $this->view->formMethod    = 'post';
        // $this->view->formName      = 'instancelist';
        $this->view->formName      = 'history-rollback';
        $this->view->formEncoding  = 'multipart/form-data';
    }

    private function _getHistoryArray($resource, $versioning, $page, $model = null, $islist = false)
    {
        $translate   = $this->_owApp->translate;
        if(!$model)
            $model       = $this->_owApp->selectedModel;
        else
            $model = new Erfurt_Owl_Model($model, $model);
        // setting default title
        if(is_object($resource)) {
            $title = $resource->getTitle() ? $resource->getTitle()
                     : OntoWiki_Utils::contractNamespace($resource->getIri());
        }
        else
            $title = $resource;
        // setting if graph, class or instances        
        if ((string) $resource === (string) $model) {
            $windowTitle = sprintf($translate->_('Versions for %1$s'), $model->__toString());
            
            $historyArray = $versioning->getHistoryForGraph(
                (string) $model,
                $page
            );
        } elseif ($islist) {
            $windowTitle = $translate->_('Versions for elements of the list');

            $listHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('List');
            $listName = "instances";
            $listHelper->listExists($listName);
            if($listHelper->listExists($listName)) {
                $list = $listHelper->getList($listName);
            } else {
                $this->_owApp->appendMessage(
                    new OntoWiki_Message('something went wrong with the list of instances', OntoWiki_Message::ERROR)
                );
            }

            $query = clone $list->getResourceQuery();
            $query->setLimit(0);
            $query->setOffset(0);
            //echo htmlentities($query);

            $results = $model->sparqlQuery($query);
            $resourceVar = $list->getResourceVar()->getName();

            $resources = array();
            foreach ($results as $result) {
                $resources[] = $result[$resourceVar];
            }
            //var_dump($resources);

            $historyArray = $versioning->getHistoryForResourceList(
                $resources,
                (string) $model,
                $page
            );
        } else {
            $windowTitle = sprintf($translate->_('Versions for %1$s'), $title);

            $historyArray = $versioning->getHistoryForResource(
                (string) $resource,
                (string) $model,
                $page
            );
        }
        $this->view->placeholder('main.window.title')->set($windowTitle);
        return $historyArray;
    }

    public function getFeedUrl($resource, $model = null)
    {
        return self::getFeedUrlStatic(
                $this->_config->urlBase, 
                $resource, 
                $this->_privateConfig->resourceParamName, $model
        );
    }

    public static function getFeedUrlStatic($urlbase, $resource, $resourceParamName, $model = null)
    {
        if($model === null)
            return $urlbase . 'history/feed?'.$resourceParamName.'='.  urlencode($resource);
        else
            return $urlbase . 'history/feed?'.$resourceParamName.'='.  urlencode($resource).'&m='.  urlencode($model);
    }

    protected function _discoverFeedURL($resourceUri)
    {
        // 1. Retrieve HTTP-Header and check for X-Pingback
        $headers = get_headers($resourceUri, 1);
        $this->_log('headers: '.print_r($headers,true));
        if (!is_array($headers)) {
            return null;
        }
        if (isset($headers['X-Syncfeed'])) {
            if (is_array($headers['X-Syncfeed'])) {
                $this->_log($headers['X-Syncfeed'][0]);
                return $headers['X-Syncfeed'][0];
            }

            $this->_log($headers['X-Syncfeed']);
            return $headers['X-Syncfeed'];
        }

        // 2. Check for (X)HTML Link element, if target has content type text/html
        // TODO Fetch only the first X bytes...???
        $client = Erfurt_App::getInstance()->getHttpClient(
            $resourceUri, array(
                'maxredirects' => 0,
                'timeout' => 3
            )
        );

        $response = $client->request();
        if ($response->getStatus() === 200) {
            $htmlDoc = new DOMDocument();
            $result = @$htmlDoc->loadHtml($response->getBody());
            $relElements = $htmlDoc->getElementsByTagName('link');

            foreach ($relElements as $relElem) {
                $rel = $relElem->getAttribute('rel');
                if (strtolower($rel) === 'self') {
                    return $relElem->getAttribute('href');
                }
            }
        }

        // 3. Check RDF/XML
        require_once 'Zend/Http/Client.php';
        $client = Erfurt_App::getInstance()->getHttpClient(
            $resourceUri, array(
                'maxredirects' => 10,
                'timeout' => 3
            )
        );
        $client->setHeaders('Accept', 'application/rdf+xml');

        $response = $client->request();
        if ($response->getStatus() === 200) {
            $rdfString = $response->getBody();

            $parser = Erfurt_Syntax_RdfParser::rdfParserWithFormat('rdfxml');
            try {
                $result = $parser->parse($rdfString, Erfurt_Syntax_RdfParser::LOCATOR_DATASTRING);
            } catch (Exception $e) {
                $this->_log($e->getMessage().' When parsing '.$rdfString);
                return null;
            }

            if (isset($result[$resourceUri])) {
                $pArray = $result[$resourceUri];

                foreach ($pArray as $p => $oArray) {
                    if ($p === 'http://purl.org/net/dssn/syncFeed') {
                        return $oArray[0]['value'];
                    }
                }
            }
        }

        return null;
    }

    protected function _getSubscribeUrlFromFeed($feedUrl)
    {
        $client = Erfurt_App::getInstance()->getHttpClient(
            $feedUrl, array(
                'maxredirects' => 5,
                'timeout' => 10
            )
        );
        $response = $client->request();
        if ($response->getStatus() === 200) {
            $htmlDoc = new DOMDocument();
            $result = @$htmlDoc->loadHtml($response->getBody());
            $relElements = $htmlDoc->getElementsByTagName('link');

            foreach ($relElements as $relElem) {
                $rel = $relElem->getAttribute('rel');
                if (strtolower($rel) === 'hub') {
                    return $relElem->getAttribute('href');
                }
            }
        }

        return null;
    }

    public function subscribeAction()
    {
        // Disable rendering
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout()->disableLayout();
        
        if (!$this->_request->isGet()) {
            return $this->_exception(400, 'Only GET allowed for remote subscription');
        }

        // uses the resource uri from the request and searchs for according topic url
        $get = $this->_request->getQuery();
        
        $feedUrl = $this->_discoverFeedURL($get['r']);

        $client = Erfurt_App::getInstance()->getHttpClient(OntoWiki::getInstance()->getUrlBase().$this->_privateConfig->subscribeUrl, array(
                    'maxredirects' => 0,
                    'timeout' => 30
                ));
        $client->setMethod('GET');
        $client->setParameterGet('topic', $feedUrl);
        $client->setParameterGet('r', $get['r']);
        $client->setParameterGet('m', $get['m']);
        $client->setParameterGet('user', $this->_owApp->getUser()->getUri());
        $response = $client->request();
        
        if($response->getBody())
            return $this->_sendResponse(
                    true,
                    'Sucessfully subscribed.',
                    OntoWiki_Message::SUCCESS
                );   
        else
            return $this->_sendResponse(
                    true,
                    'Subscription failed.',
                    OntoWiki_Message::ERROR
                );  
    }
    
    private function _sendResponse($returnValue, $message = null, $messageType = OntoWiki_Message::SUCCESS)
    {
        if (null !== $message) {
            $translate = $this->_owApp->translate;

            $message = $translate->_($message);
            $this->_owApp->appendMessage(
                new OntoWiki_Message($message, $messageType)
            );
        }

        $this->_response->setHeader('Content-Type', 'application/json', true);
        $this->_response->setBody(json_encode($returnValue));
        $this->_response->sendResponse();
        exit;
    }

    /**
     *  Listing history for selected Resource
     */
    public function listAction()
    {
        $model       = $this->_owApp->selectedModel;
        $translate   = $this->_owApp->translate;
        $store       = $this->_erfurt->getStore();
        $resource    = $this->_owApp->selectedResource;
        $ac          = $this->_erfurt->getAc();
        $params      = $this->_request->getParams();
        $limit       = 20;

        $rUriEncoded = urlencode((string)$resource);
        $mUriEncoded = urlencode((string)$model);
        $feedUrl = $this->_config->urlBase . "history/feed?r=$rUriEncoded&m=".$mUriEncoded;

        $this->view->headLink()->setAlternate($feedUrl, 'application/atom+xml', 'History Feed');

        // redirecting to home if no model/resource is selected
        if (empty($model) || (empty($this->_owApp->selectedResource)
            && empty($params['r']) && $this->_owApp->lastRoute !== 'instances')) {
            $this->_abort('No model/resource selected.', OntoWiki_Message::ERROR);
        }

        // getting page (from and for paging)
        if (!empty($params['page']) && (int) $params['page'] > 0) {
            $page = (int) $params['page'];
        } else {
            $page = 1;
        }

        // enabling versioning
        $versioning = $this->_erfurt->getVersioning();
        $versioning->setLimit($limit);

        if (!$versioning->isVersioningEnabled()) {
            $this->_abort('Versioning/History is currently disabled', null, false);
        }

        $singleResource = true;
        // setting if class or instances
        if ($this->_owApp->lastRoute === 'instances')
            $singleResource = false;
        $historyArray = $this->_getHistoryArray($resource, $versioning, $page, null, !$singleResource);

        if (sizeof($historyArray) == ( $limit + 1 ) ) {
            $count = $page * $limit + 1;
            unset($historyArray[$limit]);
        } else {
            $count = ($page - 1) * $limit + sizeof($historyArray);
        }

        $idArray = array();
        $userArray = $this->_erfurt->getUsers();
        $titleHelper = new OntoWiki_Model_TitleHelper();
        // Load IDs for rollback and Username Labels for view
        foreach ($historyArray as $key => $entry) {
            $idArray[] = (int) $entry['id'];
            if(!$singleResource) {
                $historyArray[$key]['url'] = $this->_config->urlBase . "view?r=" . urlencode($entry['resource']);
                $titleHelper->addResource($entry['resource']);
            }
            if ($entry['useruri'] == $this->_erfurt->getConfig()->ac->user->anonymousUser) {
                $userArray[$entry['useruri']] = 'Anonymous';
            } elseif ($entry['useruri'] == $this->_erfurt->getConfig()->ac->user->superAdmin) {
                $userArray[$entry['useruri']] = 'SuperAdmin';
            } elseif (
                is_array($userArray[$entry['useruri']]) &&
                array_key_exists('userName', $userArray[$entry['useruri']])
            ) {
                $userArray[$entry['useruri']] = $userArray[$entry['useruri']]['userName'];
            }
        }

        $this->view->userArray = $userArray;
        $this->view->idArray = $idArray;
        $this->view->historyArray = $historyArray;
        $this->view->singleResource = $singleResource;
        $this->view->titleHelper = $titleHelper;

        if (empty($historyArray)) {
            $this->_owApp->appendMessage(
                new OntoWiki_Message(
                    'No history for the selected resource(s).',
                    OntoWiki_Message::INFO
                )
            );
        }

       if($singleResource)
           $sr = '1';
       else
           $sr = '0';
        $toolbar = $this->_owApp->toolbar;
        $toolbar->appendButton(
            OntoWiki_Toolbar::EXPORT,
            array('name' => $translate->_('Generate feed'), 'id' => 'feed',
                  'url' => $this->getFeedUrl($this->_owApp->selectedResource))
        );

        if ($this->_erfurt->getAc()->isActionAllowed('Rollback')) {
            $this->view->rollbackAllowed = true;
            // adding submit button for rollback-action
            $toolbar->appendButton(
                OntoWiki_Toolbar::SUBMIT,
                array('name' => $translate->_('Rollback changes'), 'id' => 'history-rollback')
            );
        } else {
            $this->view->rollbackAllowed = false;
        }
        $this->view->placeholder('main.window.toolbar')->set($toolbar);

        // paging

        $statusBar = $this->view->placeholder('main.window.statusbar');
        // the normal page_param p collides with the generic-list param p
        OntoWiki_Pager::setOptions(array('page_param'=>'page'));
        $statusBar->append(OntoWiki_Pager::get($count, $limit));

        // setting view variables

        $url = new OntoWiki_Url(array('controller' => 'history', 'action' => 'rollback'));

        $this->view->formActionUrl = (string) $url;
        $this->view->formMethod    = 'post';
        // $this->view->formName      = 'instancelist';
        $this->view->formName      = 'history-rollback';
        $this->view->formEncoding  = 'multipart/form-data';
    }

    /**
     *  Restoring actions that are specified within the POST parameter
     */
    public function rollbackAction()
    {
        $resource    = $this->_owApp->selectedResource;
        $graphuri    = (string) $this->_owApp->selectedModel;
        $translate   = $this->_owApp->translate;
        $params      = $this->_request->getParams();

        // abort on missing parameters
        if (!array_key_exists('actionid', $params) || empty($resource) || empty($graphuri)) {
            $this->_abort('missing parameters.', OntoWiki_Message::ERROR);
        }

        // set active tab to history
        Ontowiki_Navigation::setActive('history');

        // setting default title
        $title = $resource->getTitle() ? $resource->getTitle() : OntoWiki_Utils::contractNamespace($resource->getIri());
        $windowTitle = sprintf($translate->_('Versions for %1$s'), $title);
        $this->view->placeholder('main.window.title')->set($windowTitle);

        // setting more view variables
               $url = new OntoWiki_Url(array('controller' => 'view', 'action' => 'index' ), null);
        $this->view->backUrl = (string) $url;

        // set translate on view
        $this->view->translate = $this->_owApp->translate;

        // abort on insufficient rights
        if (!$this->_erfurt->getAc()->isActionAllowed('Rollback')) {
            $this->_abort('not allowed.', OntoWiki_Message::ERROR);
        }

        // enabling versioning
        $versioning = $this->_erfurt->getVersioning();

        if (!$versioning->isVersioningEnabled()) {
            $this->_abort('versioning / history is currently disabled.', null, false);
        }

        $successIDs = array();
        $errorIDs = array();
        $actionids = array();

        // starting rollback action
        $actionSpec = array(
            'modeluri'      => $graphuri ,
            'type'          => Erfurt_Versioning::STATEMENTS_ROLLBACK,
            'resourceuri'   => (string) $resource
        );

        $versioning->startAction($actionSpec);

        // Trying to rollback actions from POST parameters (style: serialized in actionid)
        foreach (unserialize($params['actionid']) as $id) {
                if ( $versioning->rollbackAction($id) ) {
                    $successIDs[] = $id;
                } else {
                    $errorIDs[] = $id;
                }
        }

        // ending rollback action
        $versioning->endAction();

        // adding messages for errors and success
        if (!empty($successIDs)) {
            $this->_owApp->appendMessage(
                new OntoWiki_Message(
                    'Rolled back action(s): ' . implode(', ', $successIDs),
                    OntoWiki_Message::SUCCESS
                )
            );
        }

        if (!empty($errorIDs)) {
            $this->_owApp->appendMessage(
                new OntoWiki_Message(
                    'Error on rollback of action(s): ' . implode(', ', $errorIDs),
                    OntoWiki_Message::ERROR
                )
            );
        }
    }

    /**
     * Service to generate small HTML for Action-Details-AJAX-Integration inside Ontowiki
     */
    public function detailsAction()
    {
        $params         = $this->_request->getParams();

        if (empty($params['id'])) {
            $this->_abort('missing parameters.');
        } else {
            $actionID = (int) $params['id'];
        }

        // disabling layout as it is used as a service
        $this->_helper->layout()->disableLayout();
        $this->view->isEmpty = true;

        $results = $this->getActionTriple($actionID);
	if ( $results != null ) {
            $this->view->isEmpty = false;
        }

        $this->view->translate      = $this->_owApp->translate;
        $this->view->actionID       = $actionID;
        $this->view->stAddArray     = $results['added'];
        $this->view->stDelArray     = $results['deleted'];
        $this->view->stOtherArray   = $results['other'];

    }

    private function toFlatArray($serializedString)
    {
        $statement = new Erfurt_Rdf_MemoryModel();        
        $walkArray = unserialize($serializedString);
        foreach ($walkArray as $subject => $a) {
            foreach ($a as $predicate => $b) {
                foreach ($b as $object) {                    
                    return array($subject, $predicate, $object['value']);
                }
            }
        }
    }
    
    private function toRdfXml($serializedString, $resource, $model)
    {
        $statement = new Erfurt_Rdf_MemoryModel();        
        $walkArray = unserialize($serializedString);
        $subject = key($walkArray);
        $predicate = key($walkArray[$subject]);
        $object = $walkArray[$subject][$predicate][0];
        $this->_log('s: '.print_r($subject,true));
        $this->_log('p: '.print_r($predicate,true));
        $this->_log('o: '.print_r($object,true));
        if($object['type'] === 'uri')
            $statement->addRelation($subject, $predicate, $object['value']);
        else
            $statement->addAttribute ($subject, $predicate, $object['value']);
        #$this->_log('addst: '.print_r($statement->getStatements(),true));
        return $statement->getStatements();
        #$rdfxml = $this->serialize($resource, $model, false, true, $addedStatements);        
        #$this->_log('rdfxml: '.print_r($rdfxml,true));
    }

    /*
     * param view   the viewing (history/list) expects another format then the 
     *              machine readable atom (history/feed)
     */
    private function getActionTriple($actionID, $view = true, $resource = null, $model = null)
    {
            // enabling versioning
        $versioning = $this->_erfurt->getVersioning();

        $detailsArray = $versioning->getDetailsForAction($actionID);

        $stAddArray     = array();
        $stDelArray     = array();
        $stOtherArray   = array();

        foreach ($detailsArray as $entry) {
            $type = (int) $entry['action_type'];
            if ( $type        === Erfurt_Versioning::STATEMENT_ADDED ) {
                if($view)
                    $stAddArray[]   = $this->toFlatArray($entry['statement_hash']);
                else
                    $stAddArray[]   = $this->toRdfXml($entry['statement_hash'], $resource, $model);
            } elseif ( $type  === Erfurt_Versioning::STATEMENT_REMOVED ) {
                if($view)
                    $stDelArray[]   = $this->toFlatArray($entry['statement_hash']);
                else
                    $stDelArray[]   = $this->toRdfXml($entry['statement_hash'], $resource, $model);
            } else {
                if($view)
                    $stOtherArray[] = $this->toFlatArray($entry['statement_hash']);
                else
                    $stOtherArray[]   = $this->toRdfXml($entry['statement_hash'], $resource, $model);
            }
        }

                return array(
                        'id' => $actionID,
                        'added' => $stAddArray,
                        'deleted' => $stDelArray,
                        'other' => $stOtherArray
                );
    }

    private function _log($msg)
    {
        $logger = OntoWiki::getInstance()->getCustomLogger($this->_privateConfig->logname);
        $logger->debug($msg);
    }

    /**
     * Shortcut for adding messages
     */
    private function _abort($msg, $type = null, $redirect = null)
    {
        if (empty($type)) {
            $type = OntoWiki_Message::INFO;
        }

        $this->_owApp->appendMessage(
            new OntoWiki_Message(
                $msg,
                $type
            )
        );

        if (empty($redirect)) {
            if ($redirect !== false) {
                $this->_redirect($this->_config->urlBase);
            }
        } else {
            $this->redirect((string)$redirect);
        }

        return true;
    }

    //TODO generate feed about resource
}
