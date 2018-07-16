<?php

namespace sazik\elastic;

class Operations {

    public $_class;
    public $params = [];
    public $body = [];

    public function __construct($class = false) {
        if ($class)
            $this->_class = $class;
        $this->setParam("index", \Yii::$app->elastic->index);
    }

    public function setParam($name, $value) {
        $this->params[$name] = $value;
        return $this;
    }

    public function addToBody($name, $value) {
        $this->body[$name] = $value;
        return $this;
    }

    public function buildQuery() {
        $query = [];
        foreach ($this->params as $name => $value) {
            $query[$name] = $value;
        }
        if (!empty($this->body)) {
            $query["body"] = [];
            foreach ($this->body as $name => $value) {
                $query["body"][$name] = $value;
            }
        }
        return $query;
    }

    public function save($model) {
        $class = $this->_class;
        $this->setParam("type", $class::type());
        if ($model->id()) {
            $this->setParam("id", $model->id());
        }
        foreach ($model->attributes as $name => $value) {
            $this->addToBody($name, $value);
        }
        return \Yii::$app->elastic->connection()->index($this->buildQuery());
    }

    public function delete($id) {
        $class = $this->_class;
        $this->setParam("type", $class::type());
        $this->setParam("id", $id);
        try {
            return \Yii::$app->elastic->connection()->delete($this->buildQuery());
        } catch (\Exception $exc) {
            return false;
        }
    }

    public function get($id) {
        $class = $this->_class;
        $this->setParam("type", $class::type());
        $this->setParam("id", $id);
        return \Yii::$app->elastic->connection()->get($this->buildQuery());
    }

    public function mapping() {
        $class = $this->_class;
        $this->setParam("type", $class::type());
        $this->addToBody($class::type(), $class::mapping()[$class::type()]);
        return \Yii::$app->elastic->indices()->putMapping($this->buildQuery());
    }

    public function indexSettings() {
        //todo
    }

    public function createIndex() {
        return \Yii::$app->elastic->indices()->create($this->buildQuery());
    }

    public function getStopWords() {
        $filename = "/web/elastic_stopwords.txt";
        if(!file_exists($filename)){
            file_put_contents($filename, "хрень,фигня,для");
        }
        return trim(file_get_contents($filename));
    }

    public function getWordForms() {
        $command = "select word, forms from _sphinxWordForms";
        $forms = \Yii::$app->db->createCommand($command)->queryAll();
        $result = [];
        foreach ($forms as $form){
            $_forms = [trim($form["word"])];
            foreach (explode(",", $form["forms"]) as $_form){
                $_forms[] = trim($_form);
            }
            $_forms = implode(",", $_forms). " => ".trim($form["word"]);
            $result[] = $_forms;
            echo "Wordforms to {$form["word"]} : {$_forms}\n";
        }
        return $result;
    }

    public function settings() {
        var_dump(\Yii::$app->elastic->indices()->close(["index" => \Yii::$app->elastic->index]));
        $this->addToBody("settings", [
            "index" => [
                "analysis" => [
                    "filter" => [
                        "my_stopwords" => [
                            "type" => "stop",
                            "stopwords" => $this->getStopWords()
                        ],
                        "search_synonym" => [
                            "ignore_case" => "true",
                            "type" => "synonym",
                            "synonyms" => $this->getWordForms()
                        ]
                    ],
                    "analyzer" => [
                        "russian" => [
                            "type" => "custom",
                            "tokenizer" => "standard",
                            "filter" => ["lowercase", "russian_morphology", "english_morphology", "search_synonym", "my_stopwords"]
                        ]
                    ]
                ]
            ]
        ]);
        var_dump(\Yii::$app->elastic->indices()->putSettings($this->buildQuery()));
        var_dump(\Yii::$app->elastic->indices()->open(["index" => \Yii::$app->elastic->index]));
    }

    public function dropIndex() {
        return \Yii::$app->elastic->indices()->delete($this->buildQuery());
    }

}
