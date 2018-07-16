<?php

namespace sazik\elastic;

use Yii;

class Connection extends \yii\base\Component {

    const EVENT_QUERY = 'query';

    private $connection;
    public $index;
    public $emailFrom = false;
    public $emailTo = false;
    public $debugIPs = ['127.0.0.1', '192.168.0.*'];
    public $enabledSites = [];
    public $auth = false;
    private $log = [
        "list" => [],
        "time" => 0.0,
        "count" => 0
    ];

    public function init() {
        parent::init();
        $connection = \Elasticsearch\ClientBuilder::create();
        if ($this->auth) {
            $connection = $connection->setHosts([
                $this->auth
            ]);
        }
        $this->connection = $connection->build();
    }

    public function search($query, $caller = false) {
        try {
            $start = microtime(true);
            $result = $this->connection->search($query);
            $time = microtime(true) - $start;
            $this->log["list"][] = [
                "time" => round($time, 4) * 100,
                "query" => $query,
                "caller" => $caller,
                "trace" => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
            ];
            $this->log["time"] += round($time, 4) * 100;
            $this->log["count"] ++;
            return $result;
        } catch (\Exception $exc) {
            if ($this->emailFrom && $this->emailTo) {
                $body = [
                    'exception' => $exc->getMessage(),
                    'query' => $query
                ];

                $mailer = \Yii::$app->mailere; // Get component instance
                $message = $mailer->compose(); // Create new message instance
                $message->setFrom('shop@optica100.ru')// Set from
                        ->setSubject('optica100.ru - elastic')//Set subject
                        ->setBody(json_encode($body))//Set text
                        ->setTo('error@rumex.ru'); //Set recipient(s)
                $result = $mailer->send();
            }
            return [];
        }
    }

    public function indices() {
        return $this->connection->indices();
    }

    public function connection() {
        return $this->connection;
    }

    public function getLog() {
        return $this->log;
    }

    public function logEnabled() {
        $ip = Yii::$app->getRequest()->getUserIP();
        foreach ($this->debugIPs as $filter) {
            if ($filter === '*' || $filter === $ip || (($pos = strpos($filter, '*')) !== false && !strncmp($ip, $filter, $pos))) {
                return true;
            }
        }
        return false;
    }

}
