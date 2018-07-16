<?php

namespace sazik\elastic;

class Finder {

    public $_class;
    public $params = [];
    public $sort = [];
    public $query = [];
    public $inner = false;
    public $range = [];

    public function addQuery($type = false, $inner = false) {
        return new BoolQuery($this, $type, $inner);
    }

    public function storeQuery($query) {
        $this->query[] = $query;
        return $this;
    }

    public function __construct($class) {
        $this->_class = $class;
        $this->setParam("_source", $class::baseSource());
    }

    public function addSort($field) {
        $this->sort[] = $field;
        return $this;
    }

    public function range_Field($field, $range) {
        $this->range[$field] = $range;
        return $this;
    }

    public function setParam($name, $value) {
        $this->params[$name] = $value;
        return $this;
    }

    public function dropParam($name) {
        if (isset($this->params[$name])) {
            unset($this->params[$name]);
        }
        return $this;
    }

    public function buildQuery() {
        $class = $this->_class;
        $this->setParam("index", \Yii::$app->elastic->index);
        $this->setParam("type", $class::type());
        $query = [];
        foreach ($this->params as $name => $value) {
            $query[$name] = $value;
        }
        $query["body"] = [];
        $query["body"]["sort"] = $this->sort;
        $query["body"]["query"] = [
            "bool" => [
                "filter" => [
                ]
            ]
        ];
        foreach ($this->range as $field => $range) {
            $query["body"]["query"]["bool"]["filter"][] = ["range" => [$field => $range]];
        }
        $search = [];
        foreach ($this->query as $_query) {
            if ($_query->search) {
                $search[] = $_query->getQuery();
            } else {
                $query["body"]["query"]["bool"]["filter"][] = $_query->getQuery();
            }
        }
        if (!empty($search)) {
            $query["body"]["query"]["bool"]["filter"][] = ["bool" => ["should" => $search]];
        }
        return $query;
    }

    public function all($from = 0, $size = 10000) {
        $models = [];
        if ($from + $size <= 20000) {
            $this->setParam("size", $size);
            $this->setParam("from", $from);

            $result = \Yii::$app->elastic->search($this->buildQuery(), $this->_class);
            if (isset($result['hits']['hits']) && !empty($result['hits']['hits'])) {
                foreach ($result['hits']['hits'] as $hit) {
                    $id = $hit["_id"];
                    if (!isset($hit["inner_hits"]) && isset($hit["_source"])) {
                        $model = new $this->_class;
                        $model->load($hit['_source']);
                        if (!$model->id) {
                            $model->id = $hit["_id"];
                        }
                        $models[] = $model;
                    } elseif (isset($hit["inner_hits"])) {
                        foreach ($hit["inner_hits"] as $type => $type_hits) {
                            foreach ($type_hits["hits"]["hits"] as $_hit) {
                                $nested_model = new ElasticNested;
                                $nested_model->load($_hit["_source"]);
                                $nested_model->_id_parent = $id;
                                $nested_model->_type_parent = $hit["_type"];
                                $models[] = $nested_model;
                            }
                        }
                    }
                }
            }
        }
        return $models;
    }

    public function total() {
        $models = 0;
        $oldSrc = null;
        if (isset($this->params["_source"])) {
            $oldSrc = $this->params["_source"];
        }
        $this->setParam("_source", false);
        $this->setParam("size", 1);
        $this->setParam("from", 0);
        $result = \Yii::$app->elastic->search($this->buildQuery(), $this->_class);
        if (isset($result['hits']['total'])) {
            $models = (int) $result['hits']['total'];
        }
        if ($oldSrc === null) {
            unset($this->params["_source"]);
        } else {
            $this->setParam("_source", $oldSrc);
        }
        return $models;
    }

    public function arrayAll($from = 0, $size = 10000) {
        $this->dropParam("_source");
        $models = [];
        if ($from + $size <= 20000) {
            $this->setParam("size", $size);
            $this->setParam("from", $from);
            $result = \Yii::$app->elastic->search($this->buildQuery(), $this->_class);
            if (isset($result['hits']['hits']) && !empty($result['hits']['hits'])) {
                foreach ($result['hits']['hits'] as $hit) {
                    $id = $hit["_id"];
                    if (isset($hit["_source"])) {
                        $models[] = $hit['_source'];
                    }
                }
            }
        }
        return $models;
    }
    
    public function idsAll() {
        $models = [];
        $oldSrc = null;
        if (isset($this->params["_source"])) {
            $oldSrc = $this->params["_source"];
        }
        $this->setParam("_source", false);
        $this->setParam("size", 10000);
        $this->setParam("from", 0);
        $result = \Yii::$app->elastic->search($this->buildQuery(), $this->_class);
        if (isset($result['hits']['hits']) && !empty($result['hits']['hits'])) {
            foreach ($result['hits']['hits'] as $hit) {
                $models[] = $hit["_id"];
            }
        }
        if ($oldSrc === null) {
            unset($this->params["_source"]);
        } else {
            $this->setParam("_source", $oldSrc);
        }
        return $models;
    }

    public function fieldFromAll($field) {
        $models = [];
        $oldSrc = null;
        if (isset($this->params["_source"])) {
            $oldSrc = $this->params["_source"];
        }
        $this->setParam("_source", [$field]);
        $this->setParam("size", 10000);
        $this->setParam("from", 0);
        $result = \Yii::$app->elastic->search($this->buildQuery(), $this->_class);
        if (isset($result['hits']['hits'])) {
            foreach ($result['hits']['hits'] as $hit) {
                if (!isset($hit["inner_hits"]) && isset($hit["_source"])) {
                    if (isset($hit["_source"][$field])) {
                        $models[$hit["_source"][$field]] = 1;
                    }
                } elseif (isset($hit["inner_hits"])) {
                    foreach ($hit["inner_hits"] as $type => $type_hits) {
                        foreach ($type_hits["hits"]["hits"] as $_hit) {
                            if (isset($_hit["_source"][$field])) {
                                $models[$_hit["_source"][$field]] = 1;
                            }
                        }
                    }
                }
            }
        }
        if ($oldSrc === null) {
            unset($this->params["_source"]);
        } else {
            $this->setParam("_source", $oldSrc);
        }
        return array_keys($models);
    }

    public function arrayOne() {
        $result = $this->arrayAll(0, 1);
        if (!empty($result)) {
            return $result[0];
        }
        return false;
    }

    public function one() {
        $result = $this->all(0, 1);
        if (!empty($result)) {
            return $result[0];
        }
        return false;
    }

    public function debug() {
        return $this->buildQuery();
    }

}
