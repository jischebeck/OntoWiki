<?php
require_once 'OntoWiki/Plugin.php';

class HistoryPlugin extends OntoWiki_Plugin
{
    
    public function onAddStatement(Erfurt_Event $event){
        $this->_log("histories onAddStatement");        
    }
    
    public function onAddMultipleStatements(Erfurt_Event $event){
        $this->_log("histories onAddMultipleStatements");
        /*$urlBase = OntoWiki::getInstance()->getUrlBase();
        $subEvent = new Erfurt_Event('onInternalFeedDidChange');
        foreach($event->statements as $resource => $content){
            require_once 'HistoryController.php';
            $subEvent->feedUrl = HistoryController::getFeedUrlStatic($urlBase, $resource, $event->graphUri);
            $subEvent->trigger();
        }*/
    }
    
    public function onDeleteMatchingStatements(Erfurt_Event $event){
        $this->_log("histories onDeleteMatchingStatements");
    }
    
    public function onDeleteMultipleStatements(Erfurt_Event $event){
        $this->_log("histories onDeleteMultipleStatements");
    }
    
    private function _log($msg)
    {
        $logger = OntoWiki::getInstance()->getCustomLogger($this->_privateConfig->logname);
        $logger->debug($msg);        
    }
}
