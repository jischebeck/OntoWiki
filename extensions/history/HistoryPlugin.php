<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * The history plugin is used on different events
 *
 * @category   OntoWiki
 * @package    OntoWiki_extensions_history
 * @author     Sebastian Tramp <mail@sebastian.tramp.name>
 * @author     Florian Agsten
 * @author     Christoph Riess
 */
class HistoryPlugin extends OntoWiki_Plugin
{
    /**
     * New versioning type codes.
     */
    const VERSIONING_PUBSUB_ACTION_TYPE = 1110;
    
    /*
     * This method is triggered when the properties site for a resource is called.
     * It adds an entry for the sync-feed subscription.
     */
    public function onPropertiesAction($event){
        $translate = OntoWiki::getInstance()->translate;
        $owApp = OntoWiki::getInstance();
        
        $menu = OntoWiki_Menu_Registry::getInstance()->getMenu('resource');
        $menu->appendEntry(OntoWiki_Menu::SEPARATOR);
        
        /*$query = 'ASK FROM <'.$this->_privateConfig->sysOntoUri.'>
                      WHERE {
                        ?s <'.$this->_privateConfig->feedPredicate.'> <'.$feed->link('self').'>.
                        ?s <'.$this->_privateConfig->modelPredicate.'> ?m.
                      }';
        $queryObject = Erfurt_Sparql_SimpleQuery::initWithString($query);            
        $store = Erfurt_App::getInstance()->getStore();
        $result = $store->sparqlQuery($queryObject, array(STORE_USE_AC => false));
        $this->_log("rs: ".print_r($store->sparqlQuery($queryObject, array(STORE_USE_AC => false)),true));*/
        
        #$menu->appendEntry($translate->_('Subscribe for Resource Feed'), array('url' => $owApp->getUrlBase().'history/subscribe?r='.urlencode($event->uri).'&m='.urlencode($event->graph)));
        $menu->appendEntry(
                    $translate->_('Subscribe for Resource Feed'),
                    array(
                        'id' => 'subscribe_for_syncfeed',
                        'class' => $event->uri.' '.$event->graph
                    )
                );
    }

    /*
     * This method should be triggered when a statement is added to the triplestore.
     */
    public function onAddStatement(Erfurt_Event $event)
    {
        $this->_log("enter histories onAddStatement");
    }

    /*
     * This method is triggered when an array of statements is added to the triplestore.
     * It triggers an event which indicates that the sync-feed for a resource was changed.
     */
    public function onAddMultipleStatements(Erfurt_Event $event)
    {
        $this->_log("enter histories onAddMultipleStatements");
        $this->_triggerInternalFeedChange($event->statements);        
    }

    /*
     * This method should be triggered when a statement is deleted from the triplestore.
     */
    public function onDeleteMatchingStatements(Erfurt_Event $event)
    {
        $this->_log("enter histories onDeleteMatchingStatements");
    }

    /*
     * This method is triggered when an array of statements is deleted from the triplestore.
     * It triggers an event which indicates that the sync-feed for a resource was changed.
     */
    public function onDeleteMultipleStatements(Erfurt_Event $event)
    {
        $this->_log("enter histories onDeleteMultipleStatements");
        $this->_triggerInternalFeedChange($event->statements);
    }
    
    /*
     * This method triggers an event which indicates that the sync-feed for a resource was changed.
     */
    private function _triggerInternalFeedChange($statements){        
        $urlBase = OntoWiki::getInstance()->getUrlBase();
        $subEvent = new Erfurt_Event('onInternalFeedDidChange');
        foreach($statements as $resource => $content){
            require_once 'HistoryController.php';
            $subEvent->feedUrl = HistoryController::getFeedUrlStatic($urlBase, $resource, $this->_privateConfig->resourceParamName);
            $subEvent->trigger();
        }
        
    }
    
    /*
     * This method is triggered when a sync-feed is changed where a local subscription is available.
     */
    public function onExternalFeedDidChange(Erfurt_Event $event){
        $this->_log('processing payload: ');        
        try{
            // creates a feed object from the feed string
            $this->_privateConfig->__set('timeout', 50);
            $feed = new Zend_Feed_Atom(null, $event->feedData);
            
            // loads the resource 
            $tmp = explode($this->_privateConfig->resourceParamName.'=', $feed->link('self'));
            $resourceUri = urldecode($tmp[1]);
            
            // 1. Retrieve HTTP-Header and check if the x-syncfeed url is the same as the url from the feed
            $headers = get_headers($resourceUri, 1);
            if (!is_array($headers)) {
                return;
            }
            if (isset($headers['X-Syncfeed'])) {
                $this->_log('feed is syncfeed (historyfeed)');    
                $tasks = array();
                foreach ($feed->entries as $entry) {
                    $content = json_decode($entry->content->getDOM()->nodeValue, true);                    
                    $added = $content['added'];
                    $deleted = $content['deleted'];                    
                    if(isset($added) && sizeof($added) > 0)
                        foreach($added as $add) {
                            $task = array(
                                'type'    => 0,
                                'content' => $add,
                                'id'      => $content['id']
                            );
                            $tasks[] = $task;
                        }
                    if(isset($deleted) && sizeof($deleted) > 0)
                        foreach($deleted as $delete) {
                            $task = array(
                                'type'    => 1,
                                'content' => $delete,
                                'id'      => $content['id']
                            );
                            $tasks[] = $task;
                        }
                }
                $tasks = array_reverse($tasks);
                $erfurt = Erfurt_App::getInstance();
                $store = $erfurt->getStore();                
                $versioning = $erfurt->getVersioning();
                
                $actionSpec = array(
                    'type'        => self::VERSIONING_PUBSUB_ACTION_TYPE,
                    'modeluri'    => $event->model,
                    'resourceuri' => $resourceUri
                );
                // Start action, add statements, finish action.
                $versioning->setUserUri($event->subOwner);                
                $versioning->startAction($actionSpec);
                
                
                foreach($tasks as $task) {
                    if($task['type'])
                        $store->deleteMultipleStatements($event->model, $task['content']);
                    else
                        $store->addMultipleStatements ($event->model, $task['content']);
                }
                $versioning->endAction();
            }
            else
                return;
        }
        catch (Exception $e){
            $this->_log($e->getMessage());
            $this->_log("in feed: ");
            $this->_log(print_r($event->feedData,true));
        }
    }

    /*
     * used to add a special http response header
     */
    public function onBeforeLinkedDataRedirect($event)
    {
        if ($event->response === null) {
            return;
        }

        $url      = $this->_getSyncFeed((string) $event->resource);
        $response = $event->response;
        $response->setHeader('X-Syncfeed', $url, true);
    }

    /*
     * used on export event to add a statement to the export
     */
    public function beforeExportResource($event)
    {
        // this is the event value if there are other plugins before
        $prevModel = $event->getValue();
        // throw away non memory model values OR use the given one if valid
        if (is_object($prevModel) && get_class($prevModel) == 'Erfurt_Rdf_MemoryModel') {
            $newModel = $prevModel;
        } else {
            $newModel = new Erfurt_Rdf_MemoryModel();
        }

        // prepare the statement URIs
        $subjectUri  = (string) $event->resource;
        $propertyUri = $this->_privateConfig->syncfeedProperty;
        $objectUri   = $this->_getSyncFeed((string) $subjectUri);

        $newModel->addRelation(
            $subjectUri, $propertyUri, $objectUri
        );

        return $newModel;
    }

    /*
     * get a feed URL for a resource URI
     */
    private function _getSyncFeed($resource)
    {
        $owApp = OntoWiki::getInstance();
        return $owApp->config->urlBase . 'history/feed?'.$this->_privateConfig->resourceParamName.'=' . urlencode($resource);
    }

    private function _log($msg)
    {
        $logger = OntoWiki::getInstance()->getCustomLogger($this->_privateConfig->logname);
        $logger->debug($msg);
    }
}
