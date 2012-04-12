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

    public function onAddStatement(Erfurt_Event $event)
    {
        $this->_log("histories onAddStatement");
    }

    public function onAddMultipleStatements(Erfurt_Event $event)
    {
        $this->_log("histories onAddMultipleStatements");
        /*$urlBase = OntoWiki::getInstance()->getUrlBase();
        $subEvent = new Erfurt_Event('onInternalFeedDidChange');
        foreach($event->statements as $resource => $content){
            require_once 'HistoryController.php';
            $subEvent->feedUrl = HistoryController::getFeedUrlStatic($urlBase, $resource, $event->graphUri);
            $subEvent->trigger();
        }*/
    }

    public function onDeleteMatchingStatements(Erfurt_Event $event)
    {
        $this->_log("histories onDeleteMatchingStatements");
    }

    public function onDeleteMultipleStatements(Erfurt_Event $event)
    {
        $this->_log("histories onDeleteMultipleStatements");
    }

    /*
     * used on export event to add a statement to the export
     */
    public function beforeExportResource($event)
    {
        $owApp = OntoWiki::getInstance();

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
        $objectUri   = $owApp->config->urlBase . 'history/feed/?r=' . $subjectUri;

        $newModel->addRelation(
            $subjectUri, $propertyUri, $objectUri
        );

        return $newModel;
    }

    private function _log($msg)
    {
        $logger = OntoWiki::getInstance()->getCustomLogger($this->_privateConfig->logname);
        $logger->debug($msg);
    }
}
