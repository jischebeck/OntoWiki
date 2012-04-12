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
        $propertyUri = 'http://purl.org/net/dssn/syncFeed';

        $r = $event->resource;
        $additional = array();

        $owApp = OntoWiki::getInstance();
        $url = $owApp->config->urlBase . 'history/feed/?r=' . (string) $r;

        $additional[$r] = array();
        $additional[$r][$propertyUri] = array();
        $additional[$r][$propertyUri][] = array(
            'value' => $url,
            'type' => 'uri'
        );

        return $additional;
    }

    private function _log($msg)
    {
        $logger = OntoWiki::getInstance()->getCustomLogger($this->_privateConfig->logname);
        $logger->debug($msg);
    }
}
