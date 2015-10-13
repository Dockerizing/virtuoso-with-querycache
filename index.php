<?php

require 'vendor/autoload.php';

use Saft\Addition\Erfurt\QueryCache\QueryCache;
use Saft\Addition\Virtuoso\Store\Virtuoso;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Sparql\Query\QueryFactoryImpl;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\ResultFactoryImpl;

if (false == isset($_REQUEST['query']) || empty($_REQUEST['query'])) {
    echo json_encode('Parameter \'query\' is not given or empty.');
    return;
}

$defaultGraphUri = isset($_REQUEST['default-graph-uri']) ? $_REQUEST['default-graph-uri'] : null;

try {
    /**
     * Setup Virtuoso
     */
    $virtuoso = new Virtuoso(
        new NodeFactoryImpl(),
        new StatementFactoryImpl(),
        new QueryFactoryImpl(),
        new ResultFactoryImpl(),
        new StatementIteratorFactoryImpl(),
        array(
            'dsn' => 'VOS',
            'username' => 'dba',
            'password' => 'tercesrepus'
        )
    );

    /**
     * Setup query cache
     */
    $queryCache = new QueryCache(
        new QueryFactoryImpl(),
        array(
            'cache' => array(
                'backend' => array(
                    'file' => array(
                        'cache_dir' => '',
                    ),
                    'type' => 'file'
                ),
                'frontend' => array(
                    'cache_id_prefix' => 'saft_',
                    'enable' => true,
                    'lifetime' => 0
                ),
                'query' => array(
                    'enable' => 1,
                    'type' => 'database'
                )
            ),
            'store' => array(
                'backend' => 'virtuoso',
                'virtuoso' => array(
                    'dsn' => 'VOS',
                    'username' => 'dba',
                    'password' => 'tercesrepus',
                )
            )
        )
    );

    // add virtuoso as chain successor, which means, that each query will be first handled by the QueryCache and if it is
    // not able to answer, it asks the Virtuoso store instance.
    $queryCache->setChainSuccessor($virtuoso);

    $queryResult = $queryCache->query(
        urldecode($_REQUEST['query']),
        array('default_graph_uri' => $defaultGraphUri)
    );
} catch (\Exception $e) {
    echo 'Error:'. $e->getMessage();
}
// no query cache enabled
// $queryResult = $virtuoso->query(urldecode($_REQUEST['query']));

// ask
if ('askQuery' === QueryUtils::getQueryType(urldecode($_REQUEST['query']))) {
    $result = array(
        'head' => array('link' => array()), 'boolean' => $queryResult->getValue()
    );

// select
} elseif ('selectQuery' === QueryUtils::getQueryType(urldecode($_REQUEST['query']))) {
    $result = array(
        'head' => array(
            'link' => array(),
            'vars' => $queryResult->getVariables()
        ),
        'results' => array(
            'distinct' => 'TODO',
            'ordered' => 'TODO',
            'bindings' => array()
        )
    );

    foreach ($queryResult as $key => $entry) {
        $resultEntry = array();

        foreach ($queryResult->getVariables() as $var) {
            // if key $var has no valid value, ignore it and go to the next entry
            if (false === isset($entry[$var])) {
                continue;

            // uri
            } elseif ($entry[$var]->isNamed()) {
                $resultEntry[$var] = array(
                    'type' => 'uri',
                    'value' => $entry[$var]->getUri()
                );

            // literal
            } elseif ($entry[$var]->isLiteral()) {
                $resultEntry[$var] = array(
                    'type' => 'literal',
                    'value' => $entry[$var]->getValue()
                );
            }
        }

        $result['results']['bindings'][] = $resultEntry;
    }
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Content-Type: application/json');
echo json_encode($result);
